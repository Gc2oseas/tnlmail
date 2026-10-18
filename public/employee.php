<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require_once dirname(__DIR__).'/app/UI/Workspace.php';
require dirname(__DIR__).'/app/Admin/Users.php';
use TNL\Admin\Users;
require_once dirname(__DIR__).'/app/Admin/Settings.php';
require dirname(__DIR__).'/app/Admin/Recovery.php';
use TNL\Admin\Recovery;
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");
if(!is_file(tnl_path('storage/system.sqlite'))){header('Location: ../install.php');exit;}
tnl_session();$db=tnl_db();Users::init($db);Recovery::init($db);
$domain=\TNL\Admin\Settings::get($db,'company_domain');$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 tnl_check_csrf();
 if(($_POST['action']??'')==='logout'){
  unset($_SESSION['employee_id'],$_SESSION['employee_name'],$_SESSION['employee_role']);
  session_regenerate_id(true);header('Location: employee.php');exit;
 }
 if(($_POST['action']??'')==='login'){
  $username=strtolower(trim((string)($_POST['username']??'')));
  $personal=($_POST['login_mode']??'')==='personal';
  if(!$personal&&!str_contains($username,'@')&&$domain!=='')$username.='@'.$domain;
  $s=$db->prepare('SELECT id,display_name,role,password_hash,credentials_version FROM users WHERE (username=? OR internal_address=?) AND active=1 LIMIT 1');
  $s->execute([$username,$username]);$user=$s->fetch(PDO::FETCH_ASSOC);
  $loginDomain=strtolower(substr(strrchr($username,'@')?:'',1));
  if($domain!==''&&($personal?($loginDomain===strtolower($domain)):($loginDomain!==strtolower($domain))))$user=false;
  if($user&&!empty($user['password_hash'])&&password_verify((string)($_POST['password']??''),$user['password_hash'])){
   unset($_SESSION['admin_id'],$_SESSION['admin_name']);session_regenerate_id(true);
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
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#235d87"><link rel="stylesheet" href="assets/app.css?v=0.12.4"><title>TNL Mail · Empleados</title><script src="assets/workspace.js?v=0.12.4" defer></script></head><body>
<?php if(!$loggedIn):?><main class="auth"><section class="panel"><?php \TNL\UI\Workspace::authBrand();?><span class="eyebrow">TU ESPACIO PERSONAL</span><h1>Hola, de nuevo.</h1><p>Tu correo y tus conversaciones, en un mismo lugar.</p><?php if($error):?><p class="error"><?=tnl_escape($error)?></p><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="login"><input type="hidden" name="login_mode" value="<?=($_GET['personal']??'')==='1'?'personal':'company'?>"><label><?=($_GET['personal']??'')==='1'?'Correo personal':'Usuario de empresa'?><input name="username" required autocomplete="username" autofocus placeholder="<?=tnl_escape(($_GET['personal']??'')==='1'?'nombre@gmail.com':'tu.usuario')?> "><?php if($domain&&($_GET['personal']??'')!=='1'):?><small>Tu usuario o tu dirección @<?=tnl_escape($domain)?></small><?php endif;?></label><label>Contraseña<input type="password" name="password" required autocomplete="current-password"></label><button>Entrar a TNL Mail</button></form><p><a href="recovery.php">¿Olvidaste tu usuario o contraseña?</a></p><p><a href="?personal=<?=($_GET['personal']??'')==='1'?'0':'1'?>"><?=($_GET['personal']??'')==='1'?'Volver al acceso de empresa':'Inicia con tu cuenta personal'?></a></p><p class="content-note">Usa tu contraseña de TNL Mail. La cuenta personal debe estar registrada por administración. Gmail y Outlook pueden requerir contraseña de aplicación u OAuth para conectar el buzón.</p><div class="auth-footer"><a href="admin/">Acceso de administración →</a></div></section></main>
<?php else:?><div class="layout"><?php \TNL\UI\Workspace::sidebar('employee','home',(string)$current['display_name']);?><main class="main"><div class="top"><div><span class="eyebrow">ESPACIO PERSONAL</span><h1>Hola, <?=tnl_escape((string)$current['display_name'])?></h1></div><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="logout"><button class="button-ghost">Cerrar sesión</button></form></div><p class="page-description">Tu espacio para mantenerte al día y trabajar con tu equipo.</p><div class="quick-links"><a class="quick-link" href="external.php"><span class="card-icon"><?=\TNL\UI\Workspace::icon('mail')?></span><div><strong>Correo externo</strong><small>Tu bandeja de entrada, respuestas y envíos</small></div><?=\TNL\UI\Workspace::icon('arrow')?></a><a class="quick-link" href="chat.php" data-chat-open><span class="card-icon"><?=\TNL\UI\Workspace::icon('users')?></span><div><strong>Chat del equipo</strong><small>Comunícate con las personas de tu empresa</small></div><?=\TNL\UI\Workspace::icon('arrow')?></a></div></main></div><?php endif;?></body></html>