// общ АРI проверки

<?php

function jsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function jsonError(string $message, int $statusCode = 400): void
{
    jsonResponse(['error' => $message], $statusCode);
}

function requireAuthenticatedUserId(): int
{
    if (!isset($_SESSION['user']['id'])) {
        jsonError('Unauthorized', 401);
    }

    return (int)$_SESSION['user']['id'];
}

function requirePostMethod(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Method not allowed', 405);
    }
}

function readJsonBody(): array
{
    $input = json_decode(file_get_contents('php://input'), true);

    return is_array($input) ? $input : [];
}

function requireChatMembership(PDO $pdo, int $chatId, int $userId, string $select = 'c.*'): array
{
    $stmt = $pdo->prepare("
        SELECT {$select}
        FROM chats c
        WHERE c.id = ? AND (c.user1_id = ? OR c.user2_id = ?)
        LIMIT 1
    ");
    $stmt->execute([$chatId, $userId, $userId]);

    $chat = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$chat) {
        jsonError('Access denied', 403);
    }

    return $chat;
}

function fetchMessageSender(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(NULLIF(display_name, ''), username, email) AS sender_name,
               avatar_path AS sender_avatar
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
        'sender_name' => 'Unknown',
        'sender_avatar' => null,
    ];
}

function chatActivityTableExists(PDO $pdo): bool
{
    static $exists = null;

    if ($exists !== null) {
        return $exists;
    }

    $stmt = $pdo->query("SHOW TABLES LIKE 'chat_activity'");
    $exists = $stmt->rowCount() > 0;

    return $exists;
}
