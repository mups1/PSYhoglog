<?php
$host = getenv('PSIHI_DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('PSIHI_DB_PORT') ?: 3306);
$dbname = getenv('PSIHI_DB_NAME') ?: '';
$username = getenv('PSIHI_DB_USER') ?: '';
$password = getenv('PSIHI_DB_PASSWORD') ?: '';

if ($dbname === '' || $username === '') {
    http_response_code(500);
    exit('Database configuration error.');
}

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log('Database connection error: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection error.');
}

// наличие таблицы и нужных колонок
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(191) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        username VARCHAR(100) NULL,
        display_name VARCHAR(100) NULL,
        avatar VARCHAR(255) NULL,
        avatar_path VARCHAR(255) NULL,
        description TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
);

// подсейв для таблицы если в ней не было полей тогда их добавим
$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_hash'");
$stmt->execute();
if (!$stmt->fetchColumn()) {
    $pdo->exec("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NOT NULL AFTER email");
}

// описание при отсутствии
$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'description'");
$stmt->execute();
if (!$stmt->fetchColumn()) {
    $pdo->exec("ALTER TABLE users ADD COLUMN description TEXT NULL AFTER password_hash");
}

// путь к аве при отсутствии
$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'avatar_path'");
$stmt->execute();
if (!$stmt->fetchColumn()) {
    $pdo->exec("ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL AFTER password_hash");
}

// username при отсутствии
$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'username'");
$stmt->execute();
if (!$stmt->fetchColumn()) {
    $pdo->exec("ALTER TABLE users ADD COLUMN username VARCHAR(100) NULL AFTER email");
}

// отображаемое имя
$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'display_name'");
$stmt->execute();
if (!$stmt->fetchColumn()) {
    $pdo->exec("ALTER TABLE users ADD COLUMN display_name VARCHAR(100) NULL AFTER email");
}

// ава при отсутствии
$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'avatar'");
$stmt->execute();
if (!$stmt->fetchColumn()) {
    $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER display_name");
}

// last_activity для отслеживания статуса онлайн при отсутствии
$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'last_activity'");
$stmt->execute();
if (!$stmt->fetchColumn()) {
    $pdo->exec("ALTER TABLE users ADD COLUMN last_activity TIMESTAMP NULL AFTER created_at");
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'email'");
$stmt->execute();
if ((int)$stmt->fetchColumn() === 0) {
    // уникальность email
    $pdo->exec("ALTER TABLE users ADD UNIQUE KEY email (email)");
}

// таблица orders для хранения ордеров
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        priority INT NOT NULL DEFAULT 0,
        accepted_by INT UNSIGNED NULL,
        accepted_at TIMESTAMP NULL,
        status ENUM('active', 'taken', 'completed') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (accepted_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
);

// аблица chats для хранения чатов
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS chats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user1_id INT UNSIGNED NOT NULL,
        user2_id INT UNSIGNED NOT NULL,
        order_id INT NULL,
        status ENUM('active', 'completed') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
        UNIQUE KEY unique_order_chat (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
);

// таблица для пользователей, которые взяли ордер
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS order_takers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_order_taker (order_id, user_id),
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
);

// таблица messages для хранения сообщений
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chat_id INT NOT NULL,
        sender_id INT UNSIGNED NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_read BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
);

// + поле is_read если его нет
try {
    $pdo->exec("ALTER TABLE messages ADD COLUMN is_read BOOLEAN DEFAULT FALSE");
} catch (PDOException $e) {
    // Поле уже существует, игнорируем ошибку
}

// accepted_by при отсутствии
$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'accepted_by'");
$stmt->execute();
if (!$stmt->fetchColumn()) {
    $pdo->exec("ALTER TABLE orders ADD COLUMN accepted_by INT NULL AFTER priority");
}

// accepted_at при отсутствии
$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'accepted_at'");
$stmt->execute();
if (!$stmt->fetchColumn()) {
    $pdo->exec("ALTER TABLE orders ADD COLUMN accepted_at TIMESTAMP NULL AFTER accepted_by");
}

// status в чатах при отсутствии
$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chats' AND COLUMN_NAME = 'status'");
$stmt->execute();
if (!$stmt->fetchColumn()) {
    $pdo->exec("ALTER TABLE chats ADD COLUMN status ENUM('active', 'completed') DEFAULT 'active' AFTER order_id");
}

// поле для отслеживания последней активности в чате
$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chats' AND COLUMN_NAME = 'user1_last_seen'");
$stmt->execute();
if (!$stmt->fetchColumn()) {
    $pdo->exec("ALTER TABLE chats ADD COLUMN user1_last_seen TIMESTAMP NULL AFTER created_at");
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chats' AND COLUMN_NAME = 'user2_last_seen'");
$stmt->execute();
if (!$stmt->fetchColumn()) {
    $pdo->exec("ALTER TABLE chats ADD COLUMN user2_last_seen TIMESTAMP NULL AFTER user1_last_seen");
}

// таблица для оценок пользователей
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS user_ratings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rater_user_id INT UNSIGNED NOT NULL,
        rated_user_id INT UNSIGNED NOT NULL,
        rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (rater_user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (rated_user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_rating (rater_user_id, rated_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
);

// таблица для отзывов пользователей
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS user_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reviewer_user_id INT UNSIGNED NOT NULL,
        reviewed_user_id INT UNSIGNED NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (reviewer_user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (reviewed_user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_review (reviewer_user_id, reviewed_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
);
?>
