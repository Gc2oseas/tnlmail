<?php
declare(strict_types=1);
const TNL_ROOT = __DIR__;
function tnl_path(string $relative): string {
    if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/')) {
        throw new InvalidArgumentException('Invalid relative path');
    }
    return TNL_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}
function tnl_db(): PDO {
    $path = tnl_path('storage/system.sqlite');
    if (!is_file($path)) { throw new RuntimeException('TNL Mail is not installed.'); }
    $db = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA busy_timeout = 5000');
    return $db;
}
function tnl_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    if (!headers_sent()) {
        session_name('tnl_session');
        session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','httponly'=>true,'samesite'=>'Lax']);
    }
    session_start();
}
function tnl_csrf(): string {
    tnl_session();
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function tnl_check_csrf(): void {
    if (!hash_equals(tnl_csrf(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419); exit('Solicitud caducada. Recarga la página.');
    }
}
function tnl_escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
