<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__).'/app/Admin/Users.php';
use TNL\Admin\Users;
require dirname(__DIR__).'/app/Admin/Recovery.php';
use TNL\Admin\Recovery;
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");
if(!is_file(tnl_path('storage/system.sqlite'))){header('Location: ../install.php');exit;}
tnl_session();$db=tnl_db();Users::init($db);Recovery::init($db);
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 tnl_check_csrf();
 if(($_POST['action']??'')==='logout'){
  unset($_SESSION['employee_id'],$_SESSION['employee_name'],$_SESSION['employee_role']);
  session_regenerate_id(true);header('Location: employee.php');exit;
 }
 if(($_POST['action']??'')==='login'){
  $username=strtolower(trim((string)($_POST['username']??'')));
  $s=$db->prepare('SELECT id,display_name,role,password_hash,credentials_version FROM users WHERE username=? AND active=1 LIMIT 1');
  $s->execute([$username]);$user=$s->fetch(PDO::FETCH_ASSOC);
  if($user&&!empty($user['password_hash'])&&password_verify((string)($_POST['password']??''),$user['password_hash'])){
   session_regenerate_id(true);
   $_SESSION['employee_id']=(int)$user['id'];$_SESSION['employee_name']=$user['display_name'];$_SESSION['employee_role']=$user['role'];$_SESSION['credentials_version']=(int)$user['credentials_version'];
   header('Location: employee.php');exit;
  }
  usleep(200000);$error='Usuario o contraseña incorrectos.';
 }
}
$loggedIn=isset($_SESSION['employee_id']);
if($loggedIn){
 $s=$db->prepare('SELECT id,display_name,role,credentials_version,must_change_password FROM users WHERE id=? AND active=1 LIMIT 1');
 $s->execute([$_SESSION['employee_id']]);$current=$s->fetch(PDO::FETCH_ASSOC);
 if ($current && (int)$current['credentials_version'] !== (int)($_SESSION['credentials_version'] ?? 0)) { $current = false; }
 if(!$current){unset($_SESSION['employee_id'],$_SESSION['employee_name'],$_SESSION['employee_role']);$loggedIn=false;}
}
if($loggedIn&&!empty($current['must_change_password'])){header('Location: recovery.php');exit;}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#235d87"><link rel="stylesheet" href="assets/app.css"><title>TNL Mail · Empleados</title></head><body>
<?php if(!$loggedIn):?><main class="auth"><section class="panel"><span class="eyebrow">TNL MAIL · EMPLEADOS</span><h1>Iniciar sesión</h1><p>Ingresa con el usuario y la contraseña asignados por tu empresa.</p><?php if($error):?><p class="error"><?=tnl_escape($error)?></p><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="login"><label>Usuario<input name="username" required autocomplete="username" autofocus></label><label>Contraseña<input type="password" name="password" required autocomplete="current-password"></label><button>Entrar a TNL Mail</button></form><p><a href="recovery.php">¿Olvidaste tu usuario o contraseña?</a></p><small>El acceso con huella digital estará disponible en una versión futura, mediante passkeys compatibles.</small></section></main>
<?php else:?><div class="layout"><aside class="sidebar"><div class="brand">✉ TNL Mail</div><span class="nav active">Mi espacio</span><a class="nav" href="recovery.php">Cambiar contraseña</a><a class="nav" href="mail.php">Correo interno</a><span class="nav muted">Chat · Próximamente</span><span class="nav muted">Archivos · Próximamente</span></aside><main class="main"><div class="top"><div><span class="eyebrow">ESPACIO PERSONAL</span><h1>Hola, <?=tnl_escape((string)$current['display_name'])?></h1></div><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="logout"><button>Salir</button></form></div><p>Tu cuenta está activa. Los módulos de correo y colaboración se incorporarán próximamente.</p><div class="cards"><article class="card"><h2>Correo</h2><p><a href="mail.php">Abrir bandeja interna</a></p></article><article class="card"><h2>Chat</h2><p>Próximamente</p></article><article class="card"><h2>Archivos</h2><p>Próximamente</p></article></div></main></div><?php endif;?></body></html>