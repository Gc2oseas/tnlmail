<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
$installed = is_file(tnl_path('storage/system.sqlite'));
if ($installed) { http_response_code(403); exit('TNL Mail ya está instalado. El instalador está bloqueado.'); }
if (!extension_loaded('pdo_sqlite') || !extension_loaded('openssl')) { http_response_code(500); exit('Se requieren PDO SQLite y OpenSSL.'); }
tnl_session();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tnl_check_csrf();
    $user = trim((string)($_POST['username'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $user)) $error = 'Usuario: 3 a 50 caracteres, letras, números, puntos, guiones.';
    elseif (strlen($pass) < 12) $error = 'La contraseña debe tener al menos 12 caracteres.';
    else {
        $storage = tnl_path('storage');
        if (!is_dir($storage) && !mkdir($storage, 0700, true) && !is_dir($storage)) $error = 'No se pudo crear storage.';
        if (!$error) {
            $guard = $storage . '/.htaccess';
            if (file_put_contents($guard, "Require all denied\nDeny from all\n", LOCK_EX) === false) $error = 'No se pudo proteger storage.';
        }
        if (!$error) {
            $dbPath = $storage . '/system.sqlite';
            try {
                $db = new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $db->exec('CREATE TABLE IF NOT EXISTS admins (id INTEGER PRIMARY KEY, username TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL, created_at TEXT NOT NULL)');
                $stmt = $db->prepare('INSERT INTO admins (username, password_hash, created_at) VALUES (?, ?, ?)');
                $stmt->execute([$user, password_hash($pass, PASSWORD_DEFAULT), gmdate('c')]);
                @chmod($dbPath, 0600);
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int)$db->lastInsertId();
                $_SESSION['admin_name'] = $user;
                @unlink(__FILE__);
                header('Location: public/index.php'); exit;
            } catch (Throwable $e) {
                if (is_file($dbPath)) @unlink($dbPath);
                $error = 'No se pudo completar la instalación. Revisa los permisos.';
            }
        }
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalar TNL Mail</title><link rel="stylesheet" href="public/assets/app.css"></head><body><main class="auth"><section class="panel"><span class="eyebrow">TNL MAIL · INSTALACIÓN</span><h1>Crear administrador</h1><p>Configuración inicial del sistema. No uses este instalador sobre una instalación existente.</p><?php if ($error): ?><p class="error"><?=tnl_escape($error)?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><label>Usuario<input required name="username" minlength="3" maxlength="50" autocomplete="username"></label><label>Contraseña<input required name="password" type="password" minlength="12" autocomplete="new-password"></label><button>Instalar y entrar</button></form><small>La base de datos se guarda en storage/. Para producción, ubica el proyecto fuera del directorio público o verifica las reglas de acceso.</small></section></main></body></html>
