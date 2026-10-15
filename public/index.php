<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");
if (!is_file(tnl_path('storage/system.sqlite'))) { header('Location: ../install.php'); exit; }
tnl_session();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tnl_check_csrf();
    if (($_POST['action'] ?? '') === 'logout') {
        $_SESSION = []; session_regenerate_id(true); header('Location: index.php'); exit;
    }
    if (($_POST['action'] ?? '') === 'login') {
        $stmt = tnl_db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([trim((string)($_POST['username'] ?? ''))]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($admin && password_verify((string)($_POST['password'] ?? ''), $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_name'] = $admin['username'];
            header('Location: index.php'); exit;
        }
        $error = 'Credenciales incorrectas.';
    }
}
$loggedIn = isset($_SESSION['admin_id']);
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#235d87"><link rel="stylesheet" href="assets/app.css"><title>TNL Mail</title></head><body><?php if (!$loggedIn): ?><main class="auth"><section class="panel"><span class="eyebrow">TNL MAIL</span><h1>Acceso administrativo</h1><?php if ($error): ?><p class="error"><?=tnl_escape($error)?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="login"><label>Usuario<input name="username" required autocomplete="username"></label><label>Contraseña<input name="password" type="password" required autocomplete="current-password"></label><button>Entrar</button></form></section></main><?php else: ?><div class="layout"><aside class="sidebar"><div class="brand">✉ TNL Mail</div><a class="nav active" href="index.php">Panel principal</a><span class="nav muted">Correo · Próximamente</span><span class="nav muted">Usuarios · Próximamente</span><span class="nav muted">Marca · Próximamente</span><span class="nav muted">Archivos · Próximamente</span></aside><main class="main"><div class="top"><div><span class="eyebrow">ADMINISTRACIÓN</span><h1>Bienvenido, <?=tnl_escape((string)$_SESSION['admin_name'])?></h1></div><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="logout"><button>Salir</button></form></div><p>Base inicial de TNL Mail. Tema blanco suave.</p><div class="cards"><article class="card"><h2>Correo empresarial</h2><p>Próxima fase: SMTP, IMAP y Reply-To.</p></article><article class="card"><h2>Usuarios y permisos</h2><p>Próxima fase: identidades internas y roles.</p></article><article class="card"><h2>Identidad corporativa</h2><p>Próxima fase: plantillas HTML y Jodit.</p></article></div></main></div><?php endif; ?></body></html>
