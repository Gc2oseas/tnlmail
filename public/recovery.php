<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__).'/app/Admin/Users.php';
require dirname(__DIR__).'/app/Admin/Recovery.php';
use TNL\Admin\Users;
use TNL\Admin\Recovery;
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");
if(!is_file(tnl_path('storage/system.sqlite'))){header('Location: ../install.php');exit;}
tnl_session();$db=tnl_db();Users::init($db);Recovery::init($db);
$admin=isset($_SESSION['admin_id']);$employee=isset($_SESSION['employee_id']);$error='';$notice='';
if($employee){
 $check=$db->prepare('SELECT credentials_version FROM users WHERE id=? AND active=1');
 $check->execute([(int)$_SESSION['employee_id']]);$version=$check->fetchColumn();
 if($version===false||(int)$version!==(int)($_SESSION['credentials_version']??0)){
  unset($_SESSION['employee_id'],$_SESSION['credentials_version'],$_SESSION['employee_name'],$_SESSION['employee_role']);
  $employee=false;
 }
}
if($_SERVER['REQUEST_METHOD']==='POST'){
 tnl_check_csrf();$action=(string)($_POST['action']??'');
 try {
  if($action==='request'&&!$admin&&!$employee){
   Recovery::request($db,(string)($_POST['username']??''),(string)($_POST['details']??''));
   $notice='Solicitud registrada para revisión administrativa. Si necesitas atención urgente, contacta a tu empresa. El aviso por correo aún no está disponible.';
  }elseif($action==='reset'&&$admin){
   Recovery::reset($db,(int)($_POST['user_id']??0),(int)$_SESSION['admin_id'],(string)($_POST['new_password']??''));
   $notice='Contraseña actualizada. El usuario deberá cambiarla al iniciar sesión; las sesiones anteriores quedan invalidadas.';
  }elseif($action==='resolve'&&$admin){
   Recovery::resolve($db,(int)($_POST['request_id']??0));$notice='Solicitud marcada como resuelta.';
  }elseif($action==='change'&&$employee){
   if(($_POST['new_password']??'')!==($_POST['confirm_password']??''))throw new InvalidArgumentException('Las contraseñas nuevas no coinciden.');
   if(!Recovery::change($db,(int)$_SESSION['employee_id'],(string)($_POST['old_password']??''),(string)($_POST['new_password']??'')))throw new InvalidArgumentException('Contraseña actual incorrecta.');
   $s=$db->prepare('SELECT credentials_version FROM users WHERE id=?');$s->execute([$_SESSION['employee_id']]);$_SESSION['credentials_version']=(int)$s->fetchColumn();
   $notice='Contraseña cambiada correctamente.';
  }
 }catch(Throwable $e){$error=$e instanceof InvalidArgumentException?$e->getMessage():'No fue posible procesar la solicitud.';}
}
$users=$admin?Users::all($db):[];$requests=$admin?Recovery::pending($db):[];
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="assets/app.css"><title>TNL Mail · Credenciales</title></head><body><main class="auth"><section class="panel" style="width:min(100%,680px)"><span class="eyebrow">TNL MAIL · SEGURIDAD</span><h1><?= $admin?'Gestión de credenciales':($employee?'Cambiar contraseña':'Recuperar acceso') ?></h1>
<?php if($error):?><p class="error"><?=tnl_escape($error)?></p><?php endif;?><?php if($notice):?><p><?=tnl_escape($notice)?></p><?php endif;?>
<?php if($admin):?><p>El administrador puede reemplazar contraseñas, pero nunca consultar las anteriores.</p><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="reset"><label>Empleado<select name="user_id" required><?php foreach($users as $u):?><option value="<?=(int)$u['id']?>"><?=tnl_escape($u['display_name'].' ('.$u['username'].')')?></option><?php endforeach;?></select></label><label>Contraseña temporal nueva<input type="password" name="new_password" minlength="12" maxlength="256" required autocomplete="new-password"></label><button>Restablecer contraseña</button></form><h2>Solicitudes pendientes</h2><?php if(!$requests):?><p>No hay solicitudes pendientes.</p><?php endif;?><?php foreach($requests as $r):?><article class="card" style="margin:12px 0"><strong><?=tnl_escape($r['username_hint'])?></strong><p><?=tnl_escape($r['details'])?></p><small><?=tnl_escape($r['created_at'])?></small><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="resolve"><input type="hidden" name="request_id" value="<?=(int)$r['id']?>"><button>Marcar resuelta</button></form></article><?php endforeach;?>
<?php elseif($employee):?><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="change"><label>Contraseña actual<input type="password" name="old_password" required autocomplete="current-password"></label><label>Nueva contraseña<input type="password" name="new_password" minlength="12" maxlength="256" required autocomplete="new-password"></label><label>Repetir contraseña<input type="password" name="confirm_password" minlength="12" maxlength="256" required autocomplete="new-password"></label><button>Guardar nueva contraseña</button></form>
<?php else:?><p>Envía una solicitud al administrador de tu empresa. No necesitas una cuenta de correo externa.</p><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="request"><label>Usuario o nombre que recuerdes<input name="username" required maxlength="100" minlength="3"></label><label>Detalles para identificar tu solicitud<textarea name="details" maxlength="500" rows="3"></textarea></label><button>Solicitar recuperación</button></form><?php endif;?>
<a href="<?=$admin?'index.php': 'employee.php'?>">Volver</a><p><small>La notificación por SMTP estará disponible cuando se configure el servicio de correo. No se envían contraseñas por correo.</small></p></section></main></body></html>