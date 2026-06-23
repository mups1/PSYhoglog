<?php
require_once __DIR__ . '/../session_config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/api_helpers.php';

$userId = requireAuthenticatedUserId();
requirePostMethod();

$input = readJsonBody();
$chatId = (int)($input['chat_id'] ?? 0);
$messageContent = trim((string)($input['message'] ?? ''));

if ($chatId <= 0 || empty($messageContent)) {
    jsonError('Invalid parameters');
}

try {
    requireChatMembership($pdo, $chatId, $userId);

    // сохраняем сообщение и обновляем время чата
    $stmt = $pdo->prepare("INSERT INTO messages (chat_id, sender_id, content) VALUES (?, ?, ?)");
    $stmt->execute([$chatId, $userId, $messageContent]);
    $messageId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("UPDATE chats SET updated_at = NOW() WHERE id = ?");
    $stmt->execute([$chatId]);

    $sender = fetchMessageSender($pdo, $userId);

    jsonResponse([
        'success' => true,
        'message' => [
            'id' => $messageId,
            'content' => $messageContent,
            'sender_id' => $userId,
            'sender_name' => $sender['sender_name'],
            'sender_avatar' => $sender['sender_avatar'],
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
} catch (Exception $e) {
    error_log("send_message.php error: " . $e->getMessage());
    jsonError('Server error', 500);
}
