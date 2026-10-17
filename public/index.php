<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require_once dirname(__DIR__).'/app/UI/Workspace.php';
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
$teamCount=0;if($db&&$page==='home'){Users::init($db);$teamCount=(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#235d87"><link rel="stylesheet" href="assets/app.css?v=0.4.0"><title>TNL Mail</title><script src="assets/workspace.js?v=0.4.0" defer></script></head><body>
<?php if(!$loggedIn): ?><main class="auth"><section class="panel"><?php \TNL\UI\Workspace::authBrand();?><span class="eyebrow">BIENVENIDO A TU ESPACIO</span><h1>Todo listo para empezar.</h1><p class="auth-description">Inicia sesión para gestionar tu correo y tu equipo.</p><?php if($error):?><p class="error"><?=tnl_escape($error)?></p><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="login"><label>Usuario<input name="username" required autocomplete="username"></label><label>Contraseña<input type="password" name="password" required autocomplete="current-password"></label><button>Entrar al espacio</button></form><div class="auth-footer">¿Tienes una cuenta de empleado? <a href="employee.php">Accede aquí →</a></div></section></main>
<?php else: ?><div class="layout"><?php \TNL\UI\Workspace::sidebar('admin',$page==='mail'?'external':$page,(string)($_SESSION['admin_name']??''));?><main class="main"><div class="top"><div><span class="eyebrow">ADMINISTRACIÓN · <?=tnl_escape($company)?></span><h1><?=tnl_escape(['home'=>'Panel principal','settings'=>'Configuración de empresa','users'=>'Usuarios internos','mail'=>'Correo empresarial'][$page])?></h1></div><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="logout"><button class="button-ghost">Cerrar sesión</button></form></div>
<?php if($error):?><p class="error"><?=tnl_escape($error)?></p><?php endif;?><?php if($notice):?><p class="notice" role="status"><?=tnl_escape($notice)?></p><?php endif;?>
<?php if($page==='home'):?><p class="page-description">Un lugar para tu correo, tu empresa y las personas con las que trabajas.</p><div class="cards"><article class="card"><span class="card-icon"><?=\TNL\UI\Workspace::icon('building')?></span><div class="stat-label">TU EMPRESA</div><div class="stat-value"><?=tnl_escape($company)?></div><small><?=tnl_escape($domain?:'Configura tu dominio empresarial')?></small></article><article class="card"><span class="card-icon"><?=\TNL\UI\Workspace::icon('users')?></span><div class="stat-label">PERSONAS EN TU EQUIPO</div><div class="stat-value"><?=$teamCount?></div><a href="?page=users">Gestionar usuarios →</a></article><article class="card"><span class="card-icon"><?=\TNL\UI\Workspace::icon('mail')?></span><div class="stat-label">TU CORREO</div><div class="stat-value">Tu bandeja</div><small>Accede a tu buzón externo desde tu espacio.</small></article></div><section class="panel"><div class="section-header"><div><h2>¿Por dónde empezamos?</h2><p class="section-description">Acceso directo a lo que usas cada día.</p></div></div><div class="quick-links"><a class="quick-link" href="external.php?as=admin"><span class="card-icon"><?=\TNL\UI\Workspace::icon('mail')?></span><div><strong>Abrir mi correo</strong><small>Leer, responder y enviar mensajes</small></div><?=\TNL\UI\Workspace::icon('arrow')?></a><a class="quick-link" href="?page=users"><span class="card-icon"><?=\TNL\UI\Workspace::icon('users')?></span><div><strong>Gestionar mi equipo</strong><small>Usuarios y permisos de correo</small></div><?=\TNL\UI\Workspace::icon('arrow')?></a></div></section>
<?php elseif($page==='settings'):?><section class="panel" style="margin-top:24px"><h2>Identidad empresarial</h2><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="settings"><label>Nombre de empresa<input name="company" required maxlength="120" value="<?=tnl_escape($company)?>"></label><label>Dominio empresarial<input name="domain" required placeholder="miempresa.com" value="<?=tnl_escape($domain)?>"></label><button>Guardar</button></form></section>
<?php elseif($page==='users'):?><section class="panel" style="margin-top:24px"><h2>Nuevo usuario</h2><p>Estas identidades no crean buzones en cPanel.</p><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="user"><label>Usuario<input name="username" required placeholder="empleado001"></label><label>Nombre<input name="display_name" required></label><label>Contraseña inicial<input name="password" type="password" minlength="12" required autocomplete="new-password"></label><label>Rol<select name="role"><option value="employee">Empleado</option><option value="manager">Supervisor</option></select></label><label><input type="checkbox" name="can_send_external" value="1"> Permitir envío de correo externo</label><button>Crear usuario</button></form></section><div class="cards"><?php foreach($users as $u):?><article class="card"><h2><?=tnl_escape($u['display_name'])?></h2><p><?=tnl_escape($u['internal_address'])?></p><small><?=tnl_escape($u['role'])?> · <?=((int)$u['can_send_external']===1?'Envío externo autorizado':'Envío externo bloqueado')?></small><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="external_permission"><input type="hidden" name="user_id" value="<?=(int)$u['id']?>"><button><?=!empty($u['can_send_external'])?'Bloquear envío externo':'Permitir envío externo'?></button></form></article><?php endforeach;?></div>
<?php else:?><section class="card" style="margin-top:24px"><h2>Pasarela de correo</h2><p><a href="external.php?as=admin">Abrir correo externo y conectar un buzón</a>. Cada cuenta está aislada por usuario.</p></section><?php endif;?>
</main></div><?php endif;?></body></html>
