<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/app/Admin/DeviceLock.php';
require_once dirname(__DIR__).'/app/UI/Workspace.php';
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
tnl_session();$db=tnl_db();$devices=new \TNL\Admin\DeviceLock($db);$error='';
$token=(string)($_COOKIE['tnl_device']??'');$logged=isset($_SESSION['employee_id'])||isset($_SESSION['admin_id']);
if($token!==''&&time()-(int)($_SESSION['pin_last_activity']??time())>=900)$_SESSION['pin_locked']=true;
$locked=!empty($_SESSION['pin_locked'])||!$logged;
function device_cookie(string $token,int $expires): void {setcookie('tnl_device',$token,['expires'=>$expires,'path'=>tnl_url(''),'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Strict']);}
if($_SERVER['REQUEST_METHOD']==='POST'){
 tnl_check_csrf();
 try {
  $action=(string)($_POST['action']??'');
  if($action==='forget'){$devices->forget($token);device_cookie('',time()-3600);$_SESSION=[];session_regenerate_id(true);header('Location: index.php');exit;}
  if($action==='setup'&&$logged&&!$locked){
   $role=isset($_SESSION['employee_id'])?'employee':'admin';$id=(int)$_SESSION[$role.'_id'];
   $q=$db->prepare($role==='employee'?'SELECT password_hash,credentials_version FROM users WHERE id=? AND active=1':'SELECT password_hash FROM admins WHERE id=?');$q->execute([$id]);$user=$q->fetch(PDO::FETCH_ASSOC);
   if(!$user||!password_verify((string)($_POST['password']??''),$user['password_hash']))throw new RuntimeException('Contraseña incorrecta.');
   if($token!=='')$devices->forget($token);
   $token=$devices->create($role,$id,(int)($user['credentials_version']??0),(string)($_POST['pin']??''));device_cookie($token,time()+30*86400);$_SESSION['pin_locked']=true;session_regenerate_id(true);header('Location: lock.php');exit;
  }
  if($action==='lock'&&$logged&&$token!==''){$_SESSION['pin_locked']=true;header('Location: lock.php');exit;}
  if($action==='unlock'&&$token!==''){
   $result=$devices->unlock($token,(string)($_POST['pin']??''));$_SESSION=[];session_regenerate_id(true);$role=$result['role'];$user=$result['user'];
   $_SESSION[$role.'_id']=(int)$user['id'];$_SESSION[$role.'_name']=$user['display_name']??$user['username'];
   if($role==='employee'){$_SESSION['employee_role']=$user['role'];$_SESSION['credentials_version']=(int)$user['credentials_version'];}
   $_SESSION['pin_last_activity']=time();header('Location: '.($role==='admin'?'admin/':'index.php'));exit;
  }
  throw new RuntimeException('Acción no disponible.');
 }catch(Throwable $e){$error=$e->getMessage();}
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="assets/app.css?v=0.12.4"><title>Bloqueo del dispositivo · TNL Mail</title></head><body><main class="auth"><section class="panel"><?php \TNL\UI\Workspace::authBrand();?><h1><?=$locked?'Desbloquear tu espacio':'Proteger esta estación'?></h1><?php if($error):?><p class="error"><?=tnl_escape($error)?></p><?php endif;?>
<?php if($locked&&$token!==''):?><p>Introduce tu PIN de 6 dígitos. El dispositivo se olvida a los 30 días; cinco errores requieren volver a iniciar sesión.</p><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><label>PIN<input name="pin" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="off"></label><button name="action" value="unlock">Desbloquear</button></form>
<?php elseif($logged&&!$locked):?><p>El PIN bloqueará este dispositivo tras 15 minutos sin actividad en TNL Mail. Se requiere tu contraseña para activarlo.</p><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><label>Contraseña de TNL Mail<input type="password" name="password" required autocomplete="current-password"></label><label>PIN de 6 dígitos<input type="password" name="pin" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="new-password"></label><button name="action" value="setup">Guardar PIN y bloquear</button></form><?php if($token!==''):?><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><button name="action" value="lock">Bloquear ahora</button></form><?php endif;?>
<?php else:?><p>Inicia sesión antes de configurar el PIN.</p><a href="index.php">Ir al acceso</a><?php endif;?>
<?php if($token!==''):?><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><button class="button-ghost" name="action" value="forget">Olvidar dispositivo y cerrar sesión</button></form><?php endif;?></section></main></body></html>
