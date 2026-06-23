<?php
require_once __DIR__ . '/../session_config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/api_helpers.php';

$userId = requireAuthenticatedUserId();
requirePostMethod();

$input = readJsonBody();
$chatId = (int)($input['chat_id'] ?? 0);

if ($chatId <= 0) {
    jsonError('Invalid chat ID');
}

try {
    $stmt = $pdo->prepare("
        SELECT c.id, c.order_id, o.user_id as order_creator_id
        FROM chats c
        LEFT JOIN orders o ON c.order_id = o.id
        WHERE c.id = ? AND (c.user1_id = ? OR c.user2_id = ?)
    ");
    $stmt->execute([$chatId, $userId, $userId]);
    $chat = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$chat) {
        jsonError('Chat not found or access denied', 404);
    }
    
    if ($chat['order_creator_id'] != $userId) {
        jsonError('Only order creator can complete the chat', 403);
    }
    
    $stmt = $pdo->prepare("SELECT status FROM chats WHERE id = ?");
    $stmt->execute([$chatId]);
    $currentStatus = $stmt->fetchColumn();
    
    if ($currentStatus === 'completed') {
        jsonResponse(['success' => false, 'error' => 'Chat already completed']);
    }
    
    $stmt = $pdo->prepare("UPDATE chats SET status = 'completed' WHERE id = ?");
    $stmt->execute([$chatId]);
    
    if ($chat['order_id']) {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = ?");
        $stmt->execute([$chat['order_id']]);
    }
    
    jsonResponse(['success' => true, 'message' => 'Chat completed successfully']);
} catch (Exception $e) {
    error_log("complete_chat.php - Exception: " . $e->getMessage());
    jsonError('Server error', 500);
}
