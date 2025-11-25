<?php

class EnhancedBiddingSystem {
    private $pdo;
    private $logger;
    private $lastLogHash = null;
    
    public function __construct($database, $logger = null) {
        $this->pdo = $database;
        $this->logger = $logger;
        $this->initializeSecureLogging();
    }
    
    /**
     * Initialize secure logging system
     */
    private function initializeSecureLogging() {
        // Get the last log hash for blockchain-style linking
        $stmt = $this->pdo->prepare("SELECT current_hash FROM secure_bidding_logs ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->lastLogHash = $result ? $result['current_hash'] : null;
    }
    
    /**
     * 1. BID INCREMENT LOGIC
     * Calculate required minimum bid based on increment rules
     */
    public function calculateMinimumBid($itemId) {
        $currentBid = $this->getCurrentHighestBid($itemId);
        $currentAmount = $currentBid ? $currentBid['bid_amount'] : 0;
        
        // Get increment settings
        $incrementType = $this->getSystemSetting('bid_increment_type', 'fixed');
        
        if ($incrementType === 'percentage') {
            $incrementPercentage = floatval($this->getSystemSetting('bid_increment_percentage', 5));
            $increment = ($currentAmount * $incrementPercentage) / 100;
        } else {
            $increment = floatval($this->getSystemSetting('bid_increment_fixed', 10));
        }
        
        return $currentAmount + $increment;
    }
    
    /**
     * Enhanced bid placement with increment validation
     */
    public function placeBid($itemId, $bidderId, $bidAmount, $downPaymentPercentage = 50.00) {
        try {
            $this->pdo->beginTransaction();
            
            // Get item details
            $item = $this->getItem($itemId);
            if (!$item) {
                throw new Exception('Item not found');
            }
            
            // Check reserved amount - bid must be at or above reserved amount
            if ($item['reserved_amount'] && $bidAmount < $item['reserved_amount']) {
                throw new Exception("Bid must be at least $" . number_format($item['reserved_amount'], 2) . " (seller's reserved amount)");
            }
            
            // Check seller's minimum down payment requirement
            $minDownPayment = $item['min_down_payment_percentage'] ?? 50.00;
            if ($downPaymentPercentage < $minDownPayment) {
                throw new Exception("Down payment must be at least {$minDownPayment}% as set by seller");
            }
            
            // Check if this is a buy now bid (100% down payment)
            $isBuyNow = ($downPaymentPercentage >= 100.00);
            
            // If buy now price is set and bid amount matches, trigger instant purchase
            if ($item['buy_now_price'] && $bidAmount >= $item['buy_now_price']) {
                return $this->processBuyNow($itemId, $bidderId, $item['buy_now_price']);
            }
            
            // Validate bid increment
            $minimumBid = $this->calculateMinimumBid($itemId);
            if ($bidAmount < $minimumBid) {
                throw new Exception("Bid must be at least $" . number_format($minimumBid, 2));
            }
            
            // Check auction timing and auto-extension
            $this->checkAuctionTiming($itemId, $item);
            
            // Validate down payment and check for warnings
            $downPaymentWarning = $this->validateDownPayment($downPaymentPercentage);
            
            // Get current highest bid
            $currentHighest = $this->getCurrentHighestBid($itemId);
            
            // Mark previous highest bid as outbid
            if ($currentHighest) {
                $this->updateBidStatus($currentHighest['bid_id'], 'outbid');
            }
            
            // Calculate increment applied
            $incrementApplied = $currentHighest ? ($bidAmount - $currentHighest['bid_amount']) : $bidAmount;
            $incrementType = $this->getSystemSetting('bid_increment_type', 'fixed');
            
            // Insert new bid
            $stmt = $this->pdo->prepare("
                INSERT INTO bids (
                    item_id, bidder_id, bid_amount, down_payment_percentage, 
                    increment_applied, increment_type, is_buy_now, 
                    triggered_extension, down_payment_warning, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            
            $triggeredExtension = $this->checkAndTriggerExtension($itemId, $item);
            
            $stmt->execute([
                $itemId, $bidderId, $bidAmount, $downPaymentPercentage,
                $incrementApplied, $incrementType, $isBuyNow,
                $triggeredExtension, $downPaymentWarning
            ]);
            
            $bidId = $this->pdo->lastInsertId();
            
            // Secure logging
            $this->secureLog('bid_created', $itemId, $bidderId, 
                $currentHighest ? $currentHighest['bid_amount'] : 0, $bidAmount, [
                'bid_id' => $bidId,
                'down_payment_percentage' => $downPaymentPercentage,
                'increment_applied' => $incrementApplied,
                'increment_type' => $incrementType,
                'is_buy_now' => $isBuyNow,
                'triggered_extension' => $triggeredExtension,
                'down_payment_warning' => $downPaymentWarning
            ]);
            
            // If 100% down payment, end auction immediately
            if ($isBuyNow) {
                $this->processInstantPurchase($itemId, $bidId, $bidderId);
            }
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'bid_id' => $bidId,
                'message' => $isBuyNow ? 'Instant purchase successful!' : 'Bid placed successfully',
                'triggered_extension' => $triggeredExtension,
                'down_payment_warning' => $downPaymentWarning
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            
            // Secure log rejected bid
            $this->secureLog('bid_rejected', $itemId, $bidderId, null, $bidAmount, [
                'reason' => $e->getMessage(),
                'down_payment_percentage' => $downPaymentPercentage
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 2. AUCTION END RULES - Check timing and trigger extensions
     */
    private function checkAuctionTiming($itemId, $item) {
        if (!$item['auction_end_time']) {
            return false; // No time limit set
        }
        
        $now = new DateTime();
        $endTime = new DateTime($item['auction_end_time']);
        
        // Check if auction has already ended
        if ($now > $endTime) {
            throw new Exception('Auction has already ended');
        }
        
        return true;
    }
    
    private function checkAndTriggerExtension($itemId, $item) {
        if (!$item['auto_extend_enabled'] || !$item['auction_end_time']) {
            return false;
        }
        
        $now = new DateTime();
        $endTime = new DateTime($item['auction_end_time']);
        $extensionMinutes = intval($this->getSystemSetting('auction_extension_minutes', 2));
        
        // Check if we're within extension window
        $timeDiff = $endTime->getTimestamp() - $now->getTimestamp();
        $extensionWindow = $extensionMinutes * 60; // Convert to seconds
        
        if ($timeDiff <= $extensionWindow && $timeDiff > 0) {
            // Extend auction
            $newEndTime = $now->add(new DateInterval("PT{$extensionMinutes}M"));
            
            $stmt = $this->pdo->prepare("
                UPDATE items 
                SET auction_end_time = ?, extension_count = extension_count + 1 
                WHERE id = ?
            ");
            $stmt->execute([$newEndTime->format('Y-m-d H:i:s'), $itemId]);
            
            // Log extension
            $this->secureLog('auction_extended', $itemId, null, 
                $endTime->getTimestamp(), $newEndTime->getTimestamp(), [
                'extension_minutes' => $extensionMinutes,
                'extension_count' => $item['extension_count'] + 1
            ]);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Process Buy Now functionality
     */
    private function processBuyNow($itemId, $bidderId, $buyNowPrice) {
        // End auction immediately
        $stmt = $this->pdo->prepare("UPDATE items SET status = 'sold' WHERE id = ?");
        $stmt->execute([$itemId]);
        
        // Create winning bid
        $stmt = $this->pdo->prepare("
            INSERT INTO bids (
                item_id, bidder_id, bid_amount, down_payment_percentage,
                is_buy_now, status
            ) VALUES (?, ?, ?, 100.00, TRUE, 'winning')
        ");
        $stmt->execute([$itemId, $bidderId, $buyNowPrice]);
        
        $bidId = $this->pdo->lastInsertId();
        
        // Secure log
        $this->secureLog('buy_now_triggered', $itemId, $bidderId, null, $buyNowPrice, [
            'bid_id' => $bidId
        ]);
        
        return [
            'success' => true,
            'bid_id' => $bidId,
            'message' => 'Buy Now purchase completed!',
            'instant_purchase' => true
        ];
    }
    
    /**
     * Process instant purchase (100% down payment)
     */
    private function processInstantPurchase($itemId, $bidId, $bidderId) {
        // Update bid to winning status
        $stmt = $this->pdo->prepare("UPDATE bids SET status = 'winning' WHERE id = ?");
        $stmt->execute([$bidId]);
        
        // End auction
        $stmt = $this->pdo->prepare("UPDATE items SET status = 'sold' WHERE id = ?");
        $stmt->execute([$itemId]);
        
        // Log instant purchase
        $this->secureLog('auction_ended', $itemId, $bidderId, null, null, [
            'reason' => 'instant_purchase_100_percent',
            'winning_bid_id' => $bidId
        ]);
    }
    
    /**
     * 3. DOWN PAYMENT VALIDATION with warning system
     */
    private function validateDownPayment($downPaymentPercentage) {
        $minDownPayment = floatval($this->getSystemSetting('min_down_payment', 1));
        $maxDownPayment = floatval($this->getSystemSetting('max_down_payment', 100));
        $warningThreshold = floatval($this->getSystemSetting('down_payment_warning_threshold', 10));
        
        if ($downPaymentPercentage < $minDownPayment || $downPaymentPercentage > $maxDownPayment) {
            throw new Exception("Down payment must be between {$minDownPayment}% and {$maxDownPayment}%");
        }
        
        // Return warning flag if below threshold
        return ($downPaymentPercentage < $warningThreshold);
    }
    
    /**
     * 4. SECURE LOGGING SYSTEM (Tamper-Proof)
     */
    public function secureLog($actionType, $itemId, $userId, $oldValue, $newValue, $additionalData = []) {
        try {
            // Generate UUID
            $uuid = $this->generateSecureUUID();
            
            // Prepare log data
            $logData = [
                'uuid' => $uuid,
                'timestamp' => date('Y-m-d H:i:s'),
                'user_id' => $userId,
                'user_role' => $this->getUserRole($userId),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'action_type' => $actionType,
                'item_id' => $itemId,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'additional_data' => json_encode($additionalData)
            ];
            
            // Generate current hash (SHA-256)
            $currentHash = $this->generateLogHash($logData, $this->lastLogHash);
            
            // Insert secure log
            $stmt = $this->pdo->prepare("
                INSERT INTO secure_bidding_logs (
                    uuid, previous_log_hash, current_hash, timestamp,
                    user_id, user_role, ip_address, user_agent,
                    action_type, item_id, old_value, new_value, additional_data
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $uuid, $this->lastLogHash, $currentHash, $logData['timestamp'],
                $userId, $logData['user_role'], $logData['ip_address'], $logData['user_agent'],
                $actionType, $itemId, $oldValue, $newValue, $logData['additional_data']
            ]);
            
            // Update last hash for next log
            $this->lastLogHash = $currentHash;
            
            return true;
            
        } catch (Exception $e) {
            error_log("Secure logging error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate secure UUID for logs
     */
    private function generateSecureUUID() {
        return hash('sha256', uniqid(mt_rand(), true) . microtime(true) . random_bytes(16));
    }
    
    /**
     * Generate tamper-proof hash for log entry
     */
    private function generateLogHash($logData, $previousHash) {
        $hashString = $logData['uuid'] . 
                     $logData['timestamp'] . 
                     $logData['user_id'] . 
                     $logData['action_type'] . 
                     $logData['old_value'] . 
                     $logData['new_value'] . 
                     $logData['additional_data'] . 
                     ($previousHash ?? '');
        
        return hash('sha256', $hashString);
    }
    
    /**
     * Verify log integrity (tamper detection)
     */
    public function verifyLogIntegrity($logId = null) {
        $sql = "SELECT * FROM secure_bidding_logs";
        $params = [];
        
        if ($logId) {
            $sql .= " WHERE id = ?";
            $params[] = $logId;
        }
        
        $sql .= " ORDER BY id ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $previousHash = null;
        $tamperedLogs = [];
        
        foreach ($logs as $log) {
            // Reconstruct hash
            $logData = [
                'uuid' => $log['uuid'],
                'timestamp' => $log['timestamp'],
                'user_id' => $log['user_id'],
                'action_type' => $log['action_type'],
                'old_value' => $log['old_value'],
                'new_value' => $log['new_value'],
                'additional_data' => $log['additional_data']
            ];
            
            $expectedHash = $this->generateLogHash($logData, $previousHash);
            
            if ($expectedHash !== $log['current_hash']) {
                $tamperedLogs[] = $log['id'];
                
                // Mark as tampered
                $updateStmt = $this->pdo->prepare("
                    UPDATE secure_bidding_logs 
                    SET verification_status = 'tampered' 
                    WHERE id = ?
                ");
                $updateStmt->execute([$log['id']]);
            }
            
            $previousHash = $log['current_hash'];
        }
        
        return [
            'verified' => empty($tamperedLogs),
            'tampered_logs' => $tamperedLogs,
            'total_logs_checked' => count($logs)
        ];
    }
    
    /**
     * Manual auction end by seller
     */
    public function endAuction($itemId, $sellerId) {
        try {
            $this->pdo->beginTransaction();
            
            // Verify ownership
            $item = $this->getItem($itemId);
            if (!$item || $item['seller_id'] != $sellerId) {
                throw new Exception('Unauthorized to end this auction');
            }
            
            $highestBid = $this->getCurrentHighestBid($itemId);
            
            // Check reserved amount rule
            if ($highestBid && $highestBid['bid_amount'] >= $item['reserved_amount']) {
                // Item sold
                $this->updateBidStatus($highestBid['bid_id'], 'winning');
                $stmt = $this->pdo->prepare("UPDATE items SET status = 'sold' WHERE id = ?");
                $stmt->execute([$itemId]);
                
                $result = [
                    'success' => true,
                    'sold' => true,
                    'winning_bid' => $highestBid,
                    'message' => 'Auction ended successfully. Item sold to highest bidder.'
                ];
            } else {
                // Item not sold
                $stmt = $this->pdo->prepare("UPDATE items SET status = 'ended' WHERE id = ?");
                $stmt->execute([$itemId]);
                
                $result = [
                    'success' => true,
                    'sold' => false,
                    'highest_bid' => $highestBid ? $highestBid['bid_amount'] : 0,
                    'reserved_amount' => $item['reserved_amount'],
                    'message' => 'Auction ended. Reserved amount not met - item not sold.'
                ];
            }
            
            // Secure log
            $this->secureLog('auction_ended', $itemId, $sellerId, null, null, [
                'end_type' => 'manual',
                'result' => $result
            ]);
            
            $this->pdo->commit();
            return $result;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Time-based auction expiry (cron job function)
     */
    public function processExpiredAuctions() {
        $stmt = $this->pdo->prepare("
            SELECT * FROM items 
            WHERE status = 'active_bidding' 
            AND auction_end_time IS NOT NULL 
            AND auction_end_time <= NOW()
        ");
        $stmt->execute();
        $expiredItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $processed = [];
        
        foreach ($expiredItems as $item) {
            $result = $this->endAuction($item['id'], $item['seller_id']);
            $processed[] = [
                'item_id' => $item['id'],
                'result' => $result
            ];
            
            // Log time-based expiry
            $this->secureLog('auction_ended', $item['id'], null, null, null, [
                'end_type' => 'time_expired',
                'original_end_time' => $item['auction_end_time'],
                'result' => $result
            ]);
        }
        
        return $processed;
    }
    
    // Helper methods
    private function getItem($itemId) {
        $stmt = $this->pdo->prepare("SELECT * FROM items WHERE id = ?");
        $stmt->execute([$itemId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getCurrentHighestBid($itemId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM current_highest_bids WHERE item_id = ?
        ");
        $stmt->execute([$itemId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function updateBidStatus($bidId, $status) {
        $stmt = $this->pdo->prepare("UPDATE bids SET status = ? WHERE id = ?");
        $stmt->execute([$status, $bidId]);
    }
    
    private function getSystemSetting($key, $default = null) {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $default;
    }
    
    private function getUserRole($userId) {
        if (!$userId) return 'system';
        
        $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['role'] : 'user';
    }
}
?>