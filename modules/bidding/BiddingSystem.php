<?php

class BiddingSystem {
    private $pdo;
    private $logger;
    
    public function __construct($database, $logger = null) {
        $this->pdo = $database;
        $this->logger = $logger;
    }
    
    /**
     * Place a new bid on an item
     */
    public function placeBid($itemId, $bidderId, $bidAmount, $downPaymentPercentage = 50.00) {
        try {
            $this->pdo->beginTransaction();
            
            // Validate the bid
            $this->validateBid($itemId, $bidAmount, $downPaymentPercentage, $bidderId);
            
            // Get current highest bid
            $currentHighest = $this->getCurrentHighestBid($itemId);
            
            // Mark previous highest bid as outbid
            if ($currentHighest) {
                $this->updateBidStatus($currentHighest['bid_id'], 'outbid');
            }
            
            // Insert new bid
            $stmt = $this->pdo->prepare("
                INSERT INTO bids (item_id, bidder_id, bid_amount, down_payment_percentage, status) 
                VALUES (?, ?, ?, ?, 'active')
            ");
            $stmt->execute([$itemId, $bidderId, $bidAmount, $downPaymentPercentage]);
            $bidId = $this->pdo->lastInsertId();
            
            // Log the action
            $this->logBiddingAction('bid_created', $itemId, $bidderId, 
                $currentHighest ? $currentHighest['bid_amount'] : 0, $bidAmount, [
                'bid_id' => $bidId,
                'down_payment_percentage' => $downPaymentPercentage
            ]);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'bid_id' => $bidId,
                'message' => 'Bid placed successfully'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            
            // Log rejected bid
            $this->logBiddingAction('bid_rejected', $itemId, $bidderId, null, $bidAmount, [
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
     * Update reserved amount for an item
     */
    public function updateReservedAmount($itemId, $sellerId, $newReservedAmount) {
        try {
            // Verify seller ownership
            $item = $this->getItem($itemId);
            if (!$item || $item['seller_id'] != $sellerId) {
                throw new Exception('Unauthorized to modify this item');
            }
            
            // Check if there are bids above the new reserved amount
            $highestBid = $this->getCurrentHighestBid($itemId);
            if ($highestBid && $newReservedAmount > $highestBid['bid_amount']) {
                throw new Exception('Cannot set reserved amount higher than existing bids');
            }
            
            $oldAmount = $item['reserved_amount'];
            
            // Update reserved amount
            $stmt = $this->pdo->prepare("UPDATE items SET reserved_amount = ? WHERE id = ?");
            $stmt->execute([$newReservedAmount, $itemId]);
            
            // Log the change
            $this->logBiddingAction('reserve_changed', $itemId, $sellerId, $oldAmount, $newReservedAmount);
            
            return [
                'success' => true,
                'message' => 'Reserved amount updated successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Calculate commission for a winning bid (used during final settlement)
     * @param float $bidAmount The winning bid amount
     * @return array Commission details
     */
    public function calculateCommission($bidAmount) {
        $commissionPercentage = floatval($this->getSystemSetting('commission_percentage', 10));
        $commissionAmount = ($bidAmount * $commissionPercentage) / 100;
        $sellerAmount = $bidAmount - $commissionAmount;
        
        return [
            'bid_amount' => $bidAmount,
            'commission_percentage' => $commissionPercentage,
            'commission_amount' => round($commissionAmount, 2),
            'seller_amount' => round($sellerAmount, 2)
        ];
    }
    
    /**
     * Get current commission percentage
     */
    public function getCommissionPercentage() {
        return floatval($this->getSystemSetting('commission_percentage', 10));
    }
    
    /**
     * Update system commission percentage (SuperAdmin only)
     * Dynamic: Can be updated anytime, applies to future transactions
     */
    public function updateCommissionPercentage($adminId, $newPercentage) {
        try {
            // Validate percentage
            if ($newPercentage < 0 || $newPercentage > 50) {
                throw new Exception('Commission percentage must be between 0% and 50%');
            }
            
            // Get current percentage
            $currentPercentage = $this->getSystemSetting('commission_percentage');
            
            // Update setting
            $stmt = $this->pdo->prepare("
                UPDATE system_settings 
                SET setting_value = ?, updated_by = ? 
                WHERE setting_key = 'commission_percentage'
            ");
            $stmt->execute([$newPercentage, $adminId]);
            
            // Log the change
            $this->logBiddingAction('commission_changed', null, null, $currentPercentage, $newPercentage, [
                'admin_id' => $adminId
            ]);
            
            return [
                'success' => true,
                'message' => 'Commission percentage updated successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get current highest bid for an item
     */
    public function getCurrentHighestBid($itemId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM current_highest_bids WHERE item_id = ?
        ");
        $stmt->execute([$itemId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get bid history for an item
     */
    public function getBidHistory($itemId, $limit = 50) {
        $stmt = $this->pdo->prepare("
            SELECT b.*, u.username, u.email 
            FROM bids b
            JOIN users u ON b.bidder_id = u.id
            WHERE b.item_id = ?
            ORDER BY b.bid_amount DESC, b.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$itemId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get user's bidding activity
     */
    public function getUserBids($userId, $status = null) {
        $sql = "
            SELECT b.*, i.title, i.status as item_status
            FROM bids b
            JOIN items i ON b.item_id = i.id
            WHERE b.bidder_id = ?
        ";
        $params = [$userId];
        
        if ($status) {
            $sql .= " AND b.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY b.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * End auction and determine winner
     * CRITICAL: Item is only sold if highest bid >= reserved amount
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
            
            // CRITICAL RULE: Item is only sold if highest bid >= reserved amount
            if ($highestBid && $highestBid['bid_amount'] >= $item['reserved_amount']) {
                // Reserved amount met - item is sold
                $this->updateBidStatus($highestBid['bid_id'], 'winning');
                
                // Update item status to sold
                $stmt = $this->pdo->prepare("UPDATE items SET status = 'sold' WHERE id = ?");
                $stmt->execute([$itemId]);
                
                // Log bid acceptance (item sold)
                $this->logBiddingAction('bid_accepted', $itemId, $highestBid['bidder_id'], 
                    null, $highestBid['bid_amount'], [
                    'winning_bid_id' => $highestBid['bid_id'],
                    'reserved_amount' => $item['reserved_amount'],
                    'highest_bid' => $highestBid['bid_amount'],
                    'sold' => true
                ]);
                
                $result = [
                    'success' => true,
                    'sold' => true,
                    'winning_bid' => $highestBid,
                    'reserved_amount' => $item['reserved_amount'],
                    'message' => 'Auction ended successfully. Item sold to highest bidder.'
                ];
            } else {
                // Reserved amount NOT met - item is NOT sold
                $stmt = $this->pdo->prepare("UPDATE items SET status = 'ended' WHERE id = ?");
                $stmt->execute([$itemId]);
                
                // Log that item was not sold due to reserve not met
                $this->logBiddingAction('bid_rejected', $itemId, null, 
                    $highestBid ? $highestBid['bid_amount'] : 0, 
                    $item['reserved_amount'], [
                    'reason' => 'Reserved amount not met',
                    'highest_bid' => $highestBid ? $highestBid['bid_amount'] : 0,
                    'reserved_amount' => $item['reserved_amount'],
                    'sold' => false
                ]);
                
                $result = [
                    'success' => true,
                    'sold' => false,
                    'highest_bid' => $highestBid ? $highestBid['bid_amount'] : 0,
                    'reserved_amount' => $item['reserved_amount'],
                    'message' => 'Auction ended. Reserved amount not met - item not sold.'
                ];
            }
            
            // Log auction end
            $this->logBiddingAction('auction_ended', $itemId, $sellerId, null, null, $result);
            
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
     * Validate bid according to business rules (UPDATED to use system settings)
     */
    private function validateBid($itemId, $bidAmount, $downPaymentPercentage, $bidderId) {
        // Get item details
        $item = $this->getItem($itemId);
        if (!$item) {
            throw new Exception('Item not found');
        }
        
        // Check if bidding is active
        if ($item['status'] !== 'active_bidding') {
            throw new Exception('Bidding is not active for this item');
        }
        
        // Check if auction has ended
        if ($item['auction_end_time'] && strtotime($item['auction_end_time']) < time()) {
            throw new Exception('Auction has ended');
        }
        
        // Check reserved amount
        if ($bidAmount < $item['reserved_amount']) {
            throw new Exception('Bid must be at least $' . number_format($item['reserved_amount'], 2));
        }
        
        // Check against current highest bid with SYSTEM SETTINGS increment
        $currentHighest = $this->getCurrentHighestBid($itemId);
        if ($currentHighest) {
            // Get increment settings from system_settings
            $incrementType = $this->getSystemSetting('bid_increment_type', 'fixed');
            
            if ($incrementType === 'percentage') {
                $incrementPercentage = floatval($this->getSystemSetting('bid_increment_percentage', 5));
                $requiredIncrement = ($currentHighest['bid_amount'] * $incrementPercentage) / 100;
            } else {
                $requiredIncrement = floatval($this->getSystemSetting('bid_increment_fixed', 1));
            }
            
            $minimumBid = $currentHighest['bid_amount'] + $requiredIncrement;
            
            if ($bidAmount < $minimumBid) {
                throw new Exception('Bid must be at least $' . number_format($minimumBid, 2) . 
                    ' (current bid: $' . number_format($currentHighest['bid_amount'], 2) . 
                    ' + increment: $' . number_format($requiredIncrement, 2) . ')');
            }
        }
        
        // Validate down payment percentage using system settings
        $minDownPayment = floatval($this->getSystemSetting('min_down_payment', 1));
        $maxDownPayment = floatval($this->getSystemSetting('max_down_payment', 100));
        
        if ($downPaymentPercentage < $minDownPayment || $downPaymentPercentage > $maxDownPayment) {
            throw new Exception("Down payment must be between {$minDownPayment}% and {$maxDownPayment}%");
        }
        
        // Check if user owns the item
        if ($item['seller_id'] == $bidderId) {
            throw new Exception('Cannot bid on your own item');
        }
        
        return true;
    }
    
    /**
     * Get item details
     */
    private function getItem($itemId) {
        $stmt = $this->pdo->prepare("SELECT * FROM items WHERE id = ?");
        $stmt->execute([$itemId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update bid status
     */
    private function updateBidStatus($bidId, $status) {
        $stmt = $this->pdo->prepare("UPDATE bids SET status = ? WHERE id = ?");
        $stmt->execute([$status, $bidId]);
    }
    
    /**
     * Get system setting value
     */
    private function getSystemSetting($key, $default = null) {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $default;
    }
    
    /**
     * Get bidding logs for SuperAdmin review
     * @param int $limit Number of logs to retrieve
     * @param string $actionType Filter by action type (optional)
     * @param int $itemId Filter by item ID (optional)
     */
    public function getBiddingLogs($limit = 100, $actionType = null, $itemId = null) {
        $sql = "
            SELECT bl.*, 
                   i.title as item_title,
                   u1.username as user_name,
                   u1.email as user_email,
                   u2.username as admin_name
            FROM bidding_logs bl
            LEFT JOIN items i ON bl.item_id = i.id
            LEFT JOIN users u1 ON bl.user_id = u1.id
            LEFT JOIN users u2 ON bl.admin_id = u2.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($actionType) {
            $sql .= " AND bl.action_type = ?";
            $params[] = $actionType;
        }
        
        if ($itemId) {
            $sql .= " AND bl.item_id = ?";
            $params[] = $itemId;
        }
        
        $sql .= " ORDER BY bl.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update bid amount (bid_updated action)
     */
    public function updateBid($bidId, $bidderId, $newBidAmount, $newDownPaymentPercentage = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Get current bid
            $stmt = $this->pdo->prepare("SELECT * FROM bids WHERE id = ? AND bidder_id = ?");
            $stmt->execute([$bidId, $bidderId]);
            $currentBid = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$currentBid) {
                throw new Exception('Bid not found or unauthorized');
            }
            
            // Validate new bid amount
            $item = $this->getItem($currentBid['item_id']);
            $downPaymentPercentage = $newDownPaymentPercentage ?? $currentBid['down_payment_percentage'];
            
            $this->validateBid($currentBid['item_id'], $newBidAmount, $downPaymentPercentage, $bidderId);
            
            // Update bid
            $updateSql = "UPDATE bids SET bid_amount = ?";
            $params = [$newBidAmount];
            
            if ($newDownPaymentPercentage !== null) {
                $updateSql .= ", down_payment_percentage = ?";
                $params[] = $newDownPaymentPercentage;
            }
            
            $updateSql .= " WHERE id = ?";
            $params[] = $bidId;
            
            $stmt = $this->pdo->prepare($updateSql);
            $stmt->execute($params);
            
            // Log bid update
            $this->logBiddingAction('bid_updated', $currentBid['item_id'], $bidderId,
                $currentBid['bid_amount'], $newBidAmount, [
                'bid_id' => $bidId,
                'old_down_payment_percentage' => $currentBid['down_payment_percentage'],
                'new_down_payment_percentage' => $downPaymentPercentage
            ]);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Bid updated successfully'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Log bidding actions for audit trail
     * All critical actions are logged securely
     */
    private function logBiddingAction($actionType, $itemId, $userId, $oldValue, $newValue, $additionalData = []) {
        try {
            // Handle admin_id for commission changes
            $adminId = null;
            if ($actionType === 'commission_changed' && isset($additionalData['admin_id'])) {
                $adminId = $additionalData['admin_id'];
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO bidding_logs 
                (action_type, item_id, user_id, admin_id, old_value, new_value, additional_data, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $actionType,
                $itemId,
                $userId,
                $adminId,
                $oldValue,
                $newValue,
                json_encode($additionalData),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            // Log error but don't break the flow
            error_log("Bidding log error: " . $e->getMessage());
        }
    }
}