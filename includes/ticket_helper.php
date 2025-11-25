<?php
/**
 * Ticket Helper Functions
 * Core business logic for support ticket system
 */

/**
 * Create a new support ticket with initial message
 * 
 * @param int $userId User ID creating the ticket
 * @param string $subject Ticket subject
 * @param string $initialMessage First message in the ticket
 * @return int|false Ticket ID on success, false on failure
 */
function createTicket($userId, $subject, $initialMessage) {
    try {
        $pdo = db();
        $pdo->beginTransaction();
        
        // Insert ticket
        $stmt = $pdo->prepare("
            INSERT INTO tickets (user_id, subject, status, created_at, updated_at)
            VALUES (?, ?, 'open', NOW(), NOW())
        ");
        $stmt->execute([$userId, $subject]);
        $ticketId = $pdo->lastInsertId();
        
        // Insert initial message
        $stmt = $pdo->prepare("
            INSERT INTO ticket_messages (ticket_id, user_id, message, is_admin, created_at)
            VALUES (?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$ticketId, $userId, $initialMessage]);
        
        $pdo->commit();
        return $ticketId;
        
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error creating ticket: " . $e->getMessage());
        return false;
    }
}

/**
 * Get ticket by ID with user information
 * 
 * @param int $ticketId Ticket ID
 * @return array|null Ticket data with user info, or null if not found
 */
function getTicketById($ticketId) {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT 
                t.*,
                u.name as user_name,
                u.email as user_email,
                (SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = t.id) as message_count
            FROM tickets t
            JOIN users u ON t.user_id = u.id
            WHERE t.id = ?
        ");
        $stmt->execute([$ticketId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        
    } catch (PDOException $e) {
        error_log("Error fetching ticket: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all tickets for a specific user with pagination
 * 
 * @param int $userId User ID
 * @param string|null $status Filter by status ('open', 'closed', or null for all)
 * @param int $page Page number (1-indexed)
 * @param int $perPage Items per page
 * @return array Array with 'tickets' and 'total' keys
 */
function getUserTickets($userId, $status = null, $page = 1, $perPage = 10) {
    try {
        $pdo = db();
        $offset = ($page - 1) * $perPage;
        
        // Build query - SIMPLIFIED
        $whereClause = "WHERE user_id = ?";
        $params = [$userId];
        
        if ($status !== null) {
            $whereClause .= " AND status = ?";
            $params[] = $status;
        }
        
        // Get total count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets $whereClause");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        
        // Get tickets - SIMPLIFIED without subqueries
        $query = "SELECT * FROM tickets $whereClause ORDER BY updated_at DESC LIMIT ? OFFSET ?";
        
        $stmt = $pdo->prepare($query);
        
        // Add limit and offset to params
        $executeParams = $params;
        $executeParams[] = (int)$perPage;
        $executeParams[] = (int)$offset;
        
        $stmt->execute($executeParams);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add message count to each ticket
        foreach ($tickets as &$ticket) {
            $msgStmt = $pdo->prepare("SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = ?");
            $msgStmt->execute([$ticket['id']]);
            $ticket['message_count'] = (int)$msgStmt->fetchColumn();
            
            // Get last message
            $lastMsgStmt = $pdo->prepare("SELECT message, created_at FROM ticket_messages WHERE ticket_id = ? ORDER BY created_at DESC LIMIT 1");
            $lastMsgStmt->execute([$ticket['id']]);
            $lastMsg = $lastMsgStmt->fetch(PDO::FETCH_ASSOC);
            $ticket['last_message'] = $lastMsg['message'] ?? null;
            $ticket['last_message_time'] = $lastMsg['created_at'] ?? null;
        }
        
        return [
            'tickets' => $tickets,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
        
    } catch (PDOException $e) {
        error_log("Error fetching user tickets: " . $e->getMessage());
        return [
            'tickets' => [],
            'total' => 0,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => 0
        ];
    }
}

/**
 * Get all tickets (admin view) with pagination
 * 
 * @param string|null $status Filter by status ('open', 'closed', or null for all)
 * @param int $page Page number (1-indexed)
 * @param int $perPage Items per page
 * @return array Array with 'tickets' and 'total' keys
 */
function getAllTickets($status = null, $page = 1, $perPage = 20) {
    try {
        $pdo = db();
        $offset = ($page - 1) * $perPage;
        
        // Build query
        $whereClause = "";
        $params = [];
        
        if ($status !== null) {
            $whereClause = "WHERE t.status = ?";
            $params[] = $status;
        }
        
        // Get total count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets t $whereClause");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();
        
        // Get tickets with user info
        $stmt = $pdo->prepare("
            SELECT 
                t.*,
                u.name as user_name,
                u.email as user_email,
                (SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = t.id) as message_count,
                (SELECT message FROM ticket_messages WHERE ticket_id = t.id ORDER BY created_at DESC LIMIT 1) as last_message,
                (SELECT created_at FROM ticket_messages WHERE ticket_id = t.id ORDER BY created_at DESC LIMIT 1) as last_message_time
            FROM tickets t
            JOIN users u ON t.user_id = u.id
            $whereClause
            ORDER BY t.updated_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $perPage;
        $params[] = $offset;
        $stmt->execute($params);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'tickets' => $tickets,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
        
    } catch (PDOException $e) {
        error_log("Error fetching all tickets: " . $e->getMessage());
        return [
            'tickets' => [],
            'total' => 0,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => 0
        ];
    }
}

/**
 * Update ticket status
 * 
 * @param int $ticketId Ticket ID
 * @param string $status New status ('open' or 'closed')
 * @return bool True on success, false on failure
 */
function updateTicketStatus($ticketId, $status) {
    try {
        // Validate status
        if (!in_array($status, ['open', 'closed'])) {
            error_log("Invalid ticket status: $status");
            return false;
        }
        
        $pdo = db();
        $stmt = $pdo->prepare("
            UPDATE tickets 
            SET status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$status, $ticketId]);
        
    } catch (PDOException $e) {
        error_log("Error updating ticket status: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a ticket and all its messages
 * 
 * @param int $ticketId Ticket ID
 * @return bool True on success, false on failure
 */
function deleteTicket($ticketId) {
    try {
        $pdo = db();
        $pdo->beginTransaction();
        
        // Delete messages first
        $stmt = $pdo->prepare("DELETE FROM ticket_messages WHERE ticket_id = ?");
        $stmt->execute([$ticketId]);
        
        // Delete ticket
        $stmt = $pdo->prepare("DELETE FROM tickets WHERE id = ?");
        $stmt->execute([$ticketId]);
        
        $pdo->commit();
        return true;
        
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error deleting ticket: " . $e->getMessage());
        return false;
    }
}


/**
 * Add a message to a ticket
 * 
 * @param int $ticketId Ticket ID
 * @param int $userId User ID sending the message
 * @param string $message Message content
 * @param bool $isAdmin Whether the sender is an admin
 * @return int|false Message ID on success, false on failure
 */
function addTicketMessage($ticketId, $userId, $message, $isAdmin = false) {
    try {
        $pdo = db();
        $pdo->beginTransaction();
        
        // Insert message
        $stmt = $pdo->prepare("
            INSERT INTO ticket_messages (ticket_id, user_id, message, is_admin, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$ticketId, $userId, $message, $isAdmin ? 1 : 0]);
        $messageId = $pdo->lastInsertId();
        
        // Update ticket's updated_at timestamp
        $stmt = $pdo->prepare("UPDATE tickets SET updated_at = NOW() WHERE id = ?");
        $stmt->execute([$ticketId]);
        
        $pdo->commit();
        return $messageId;
        
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error adding ticket message: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all messages for a ticket
 * 
 * @param int $ticketId Ticket ID
 * @return array Array of messages with user information
 */
function getTicketMessages($ticketId) {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT 
                tm.*,
                u.name as user_name,
                u.email as user_email,
                u.role as user_role
            FROM ticket_messages tm
            JOIN users u ON tm.user_id = u.id
            WHERE tm.ticket_id = ?
            ORDER BY tm.created_at ASC
        ");
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error fetching ticket messages: " . $e->getMessage());
        return [];
    }
}

/**
 * Get the last message for a ticket
 * 
 * @param int $ticketId Ticket ID
 * @return array|null Last message data, or null if no messages
 */
function getLastMessage($ticketId) {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT 
                tm.*,
                u.name as user_name,
                u.email as user_email
            FROM ticket_messages tm
            JOIN users u ON tm.user_id = u.id
            WHERE tm.ticket_id = ?
            ORDER BY tm.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$ticketId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        
    } catch (PDOException $e) {
        error_log("Error fetching last message: " . $e->getMessage());
        return null;
    }
}


/**
 * Get ticket statistics
 * 
 * @param int|null $userId User ID (null for all users/admin view)
 * @return array Statistics including total, open, closed counts
 */
function getTicketStats($userId = null) {
    try {
        $pdo = db();
        
        if ($userId !== null) {
            // User-specific stats
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
                    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
                FROM tickets
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
        } else {
            // All tickets stats (admin view)
            $stmt = $pdo->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
                    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
                FROM tickets
            ");
        }
        
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total' => (int)($stats['total'] ?? 0),
            'open' => (int)($stats['open'] ?? 0),
            'closed' => (int)($stats['closed'] ?? 0)
        ];
        
    } catch (PDOException $e) {
        error_log("Error fetching ticket stats: " . $e->getMessage());
        return [
            'total' => 0,
            'open' => 0,
            'closed' => 0
        ];
    }
}

/**
 * Get count of open tickets
 * 
 * @param int|null $userId User ID (null for all users)
 * @return int Number of open tickets
 */
function getOpenTicketsCount($userId = null) {
    try {
        $pdo = db();
        
        if ($userId !== null) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM tickets 
                WHERE user_id = ? AND status = 'open'
            ");
            $stmt->execute([$userId]);
        } else {
            $stmt = $pdo->query("
                SELECT COUNT(*) 
                FROM tickets 
                WHERE status = 'open'
            ");
        }
        
        return (int)$stmt->fetchColumn();
        
    } catch (PDOException $e) {
        error_log("Error fetching open tickets count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Check if user can access a ticket
 * 
 * @param int $ticketId Ticket ID
 * @param int $userId User ID
 * @param string $userRole User role
 * @return bool True if user can access, false otherwise
 */
function canAccessTicket($ticketId, $userId, $userRole) {
    // SuperAdmin can access all tickets
    if ($userRole === 'superadmin') {
        return true;
    }
    
    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT user_id FROM tickets WHERE id = ?");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $ticket && $ticket['user_id'] == $userId;
        
    } catch (PDOException $e) {
        error_log("Error checking ticket access: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate ticket data
 * 
 * @param array $data Ticket data to validate
 * @return array Array of error messages (empty if valid)
 */
function validateTicketData($data) {
    $errors = [];
    
    $subject = trim($data['subject'] ?? '');
    $message = trim($data['message'] ?? '');
    
    if (strlen($subject) < 5 || strlen($subject) > 255) {
        $errors[] = 'Subject must be between 5 and 255 characters.';
    }
    
    if (strlen($message) < 10 || strlen($message) > 5000) {
        $errors[] = 'Message must be between 10 and 5000 characters.';
    }
    
    return $errors;
}

/**
 * Sanitize ticket input
 * 
 * @param array $input Raw input data
 * @return array Sanitized data
 */
function sanitizeTicketInput($input) {
    return [
        'subject' => trim(strip_tags($input['subject'] ?? '')),
        'message' => trim(htmlspecialchars($input['message'] ?? '', ENT_QUOTES, 'UTF-8'))
    ];
}
