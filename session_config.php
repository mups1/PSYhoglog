<?php
// настройка сессий для корректной работы и безопасного хранения вне web-root
$sessionPath = sys_get_temp_dir() . '/psihi_sessions';
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);

ini_set('session.save_path', $sessionPath);
ini_set('session.cookie_lifetime', 3600); // 1 час
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', '');
ini_set('session.cookie_secure', $isHttps ? '1' : '0');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => 3600,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// директория для сессий если её нет
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0700, true);
}

// запускаем сессию
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
