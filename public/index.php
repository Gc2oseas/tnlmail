<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__).'/app/Admin/Settings.php';
require dirname(__DIR__).'/app/Admin/Users.php';
use TNL\Admin\Settings;
use TNL\Admin\Users;
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");
if(!is_file(tnl_path('storage/system.sqlite'))){header('Location: ../install.php');exit;}
tnl_session();
$error='';$notice='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 tnl_check_csrf();$action=(string)($_POST['action']??'');
 if($action==='logout'){$_SESSION=[];session_regenerate_id(true);header('Location: index.php');exit;}
 if($action==='login'){
  $s=tnl_db()->prepare('SELECT id,username,password_hash FROM admins WHERE username=? LIMIT 1');
  $s->execute([trim((string)($_POST['username']??''))]);$a=$s->fetch(PDO::FETCH_ASSOC);
  if($a&&password_verify((string)($_POST['password']??''),$a['password_hash'])){
   session_regenerate_id(true);$_SESSION['admin_id']=(int)$a['id'];$_SESSION['admin_name']=$a['username'];header('Location: index.php');exit;
  }$error='Credenciales incorrectas.';
 }elseif(isset($_SESSION['admin_id'])){
  try{
   if($action==='settings'){
    $domain=strtolower(trim((string)($_POST['domain']??'')));
    if(!filter_var('test@'.$domain,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Dominio inválido.');
    $company=trim((string)($_POST['company']??''));
    if(strlen($company)<2||strlen($company)>120)throw new InvalidArgumentException('Nombre de empresa inválido.');
    $db=tnl_db();Settings::set($db,'company_name',$company);Settings::set($db,'company_domain',$domain);
    $notice='Configuración guardada.';
   }elseif($action==='external_permission'){
    $db=tnl_db();$stmt=$db->prepare('UPDATE users SET can_send_external=CASE can_send_external WHEN 1 THEN 0 ELSE 1 END WHERE id=?');$stmt->execute([(int)($_POST['user_id']??0)]);$notice='Permiso de envío externo actualizado.';
   }elseif($action==='user'){
    $db=tnl_db();$domain=Settings::get($db,'company_domain');
    if(!$domain)throw new InvalidArgumentException('Configura primero el dominio empresarial.');
    Users::create($db,['username'=>$_POST['username']??'','display_name'=>$_POST['display_name']??'','domain'=>$domain,'role'=>$_POST['role']??'employee','password'=>$_POST['password']??'','can_send_external'=>isset($_POST['can_send_external'])]);
    $notice='Usuario interno creado.';
   }
  }catch(Throwable $e){$error=$e instanceof InvalidArgumentException?$e->getMessage():'No se pudo guardar. Verifica que no exista un usuario duplicado.';}
 }
}
$loggedIn=isset($_SESSION['admin_id']);
$page=(string)($_GET['page']??'home');
if(!in_array($page,['home','settings','users','mail'],true))$page='home';
$db=$loggedIn?tnl_db():null;
$company=$db?Settings::get($db,'company_name','Mi empresa'):'';
$domain=$db?Settings::get($db,'company_domain'):'';
$users=$db&&$page==='users'?Users::all($db):[];
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#235d87"><link rel="stylesheet" href="assets/app.css"><title>TNL Mail</title></head><body>
<?php if(!$loggedIn): ?><main class="auth"><section class="panel"><span class="eyebrow">TNL MAIL</span><h1>Acceso administrativo</h1><?php if($error):?><p class="error"><?=tnl_escape($error)?></p><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="login"><label>Usuario<input name="username" required autocomplete="username"></label><label>Contraseña<input type="password" name="password" required autocomplete="current-password"></label><button>Entrar</button></form></section></main>
<?php else: ?><div class="layout"><aside class="sidebar"><div class="brand">✉ TNL Mail</div><a class="nav" href="updates.php">Actualizaciones</a><a class="nav" href="deployment.php">Despliegue GitHub</a><a class="nav" href="external.php?as=admin">Correo externo</a><a class="nav" href="employee.php">Empleados</a><a class="nav" href="recovery.php">Recuperaciones</a><a class="nav <?=$page==='home'?'active':''?>" href="index.php">Inicio</a><a class="nav <?=$page==='settings'?'active':''?>" href="?page=settings">Empresa</a><a class="nav <?=$page==='users'?'active':''?>" href="?page=users">Usuarios</a><a class="nav <?=$page==='mail'?'active':''?>" href="?page=mail">Correo</a></aside><main class="main"><div class="top"><div><span class="eyebrow">ADMINISTRACIÓN · <?=tnl_escape($company)?></span><h1><?=tnl_escape(['home'=>'Panel principal','settings'=>'Configuración de empresa','users'=>'Usuarios internos','mail'=>'Correo empresarial'][$page])?></h1></div><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="logout"><button>Salir</button></form></div>
<?php if($error):?><p class="error"><?=tnl_escape($error)?></p><?php endif;?><?php if($notice):?><p><?=tnl_escape($notice)?></p><?php endif;?>
<?php if($page==='home'):?><p>Panel de administración · Tema blanco suave</p><div class="cards"><article class="card"><h2>Empresa</h2><p><?=tnl_escape($domain?:'Configura el dominio')?></p></article><article class="card"><h2>Usuarios internos</h2><p>Identidades virtuales sin buzones IMAP.</p></article><article class="card"><h2>Correo externo</h2><p>Conexión individual por IMAP y SMTP con SSL/TLS.</p></article></div>
<?php elseif($page==='settings'):?><section class="panel" style="margin-top:24px"><h2>Identidad empresarial</h2><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="settings"><label>Nombre de empresa<input name="company" required maxlength="120" value="<?=tnl_escape($company)?>"></label><label>Dominio empresarial<input name="domain" required placeholder="miempresa.com" value="<?=tnl_escape($domain)?>"></label><button>Guardar</button></form></section>
<?php elseif($page==='users'):?><section class="panel" style="margin-top:24px"><h2>Nueva identidad interna</h2><p>Estas identidades no crean buzones en cPanel.</p><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="user"><label>Usuario<input name="username" required placeholder="empleado001"></label><label>Nombre<input name="display_name" required></label><label>Contraseña inicial<input name="password" type="password" minlength="12" required autocomplete="new-password"></label><label>Rol<select name="role"><option value="employee">Empleado</option><option value="manager">Supervisor</option></select></label><label><input type="checkbox" name="can_send_external" value="1"> Permiso futuro de envío externo</label><button>Crear identidad</button></form></section><div class="cards"><?php foreach($users as $u):?><article class="card"><h2><?=tnl_escape($u['display_name'])?></h2><p><?=tnl_escape($u['internal_address'])?></p><small><?=tnl_escape($u['role'])?> · <?=((int)$u['can_send_external']===1?'Envío externo autorizado':'Envío externo bloqueado')?></small><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="external_permission"><input type="hidden" name="user_id" value="<?=(int)$u['id']?>"><button><?=!empty($u['can_send_external'])?'Bloquear envío externo':'Permitir envío externo'?></button></form></article><?php endforeach;?></div>
<?php else:?><section class="card" style="margin-top:24px"><h2>Pasarela de correo</h2><p><a href="external.php?as=admin">Abrir correo externo y conectar un buzón</a>. Cada cuenta está aislada por usuario.</p></section><?php endif;?>
</main></div><?php endif;?></body></html>
