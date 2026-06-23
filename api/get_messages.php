<?php
require_once __DIR__ . '/../session_config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/api_helpers.php';

$userId = requireAuthenticatedUserId();
$chatId = (int)($_GET['chat_id'] ?? 0);
$lastMessageId = (int)($_GET['last_message_id'] ?? 0);

if ($chatId <= 0) {
    jsonError('Invalid chat ID');
}

try {
    $chat = requireChatMembership($pdo, $chatId, $userId);

    $stmt = $pdo->prepare("
        SELECT m.*, 
               COALESCE(NULLIF(u.display_name, ''), u.username, u.email) as sender_name,
               u.avatar_path as sender_avatar
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE m.chat_id = ? AND m.id > ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$chatId, $lastMessageId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $activeUsers = 0;
    if (chatActivityTableExists($pdo)) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS active_count
            FROM chat_activity
            WHERE chat_id = ?
              AND user_id != ?
              AND last_seen > DATE_SUB(NOW(), INTERVAL 30 SECOND)
        ");
        $stmt->execute([$chatId, $userId]);
        $activeUsers = (int)$stmt->fetchColumn();
    }

    jsonResponse([
        'success' => true,
        'messages' => $messages,
        'count' => count($messages),
        'active_users' => $activeUsers,
        'chat_status' => $chat['status'] ?? 'active',
    ]);
} catch (Exception $e) {
    error_log("get_messages.php error: " . $e->getMessage());
    jsonError('Server error', 500);
}
