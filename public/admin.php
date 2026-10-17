<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require_once dirname(__DIR__).'/app/UI/Workspace.php';
require dirname(__DIR__).'/app/Admin/Settings.php';
require dirname(__DIR__).'/app/Admin/Users.php';
require_once dirname(__DIR__).'/app/Mail/Router.php';
use TNL\Admin\Settings;
use TNL\Admin\Users;
require_once dirname(__DIR__).'/app/Mail/Mailboxes.php';
require_once dirname(__DIR__).'/app/Mail/ExternalMail.php';
require_once dirname(__DIR__).'/app/Mail/RecoveryTrash.php';
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");
if(!is_file(tnl_path('storage/system.sqlite'))){header('Location: ../install.php');exit;}
tnl_session();
$error='';$notice='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 tnl_check_csrf();$action=(string)($_POST['action']??'');
 if($action==='logout'){$_SESSION=[];session_regenerate_id(true);header('Location: '.tnl_url('admin/'));exit;}
 if($action==='login'){
  $s=tnl_db()->prepare('SELECT id,username,password_hash FROM admins WHERE username=? LIMIT 1');
  $s->execute([trim((string)($_POST['username']??''))]);$a=$s->fetch(PDO::FETCH_ASSOC);
  if($a&&password_verify((string)($_POST['password']??''),$a['password_hash'])){
   unset($_SESSION['employee_id'],$_SESSION['employee_name'],$_SESSION['employee_role'],$_SESSION['credentials_version']);session_regenerate_id(true);$_SESSION['admin_id']=(int)$a['id'];$_SESSION['admin_name']=$a['username'];header('Location: '.tnl_url('admin/'));exit;
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
   }elseif($action==='company_servers'){
    $db=tnl_db();$config=\TNL\Mail\ExternalAccount::config(array_replace($_POST,['email'=>'server-profile@'.Settings::get($db,'company_domain')]));
    Settings::set($db,'company_mail_servers',json_encode(['domain'=>Settings::get($db,'company_domain'),'imap_host'=>$config['imap_host'],'smtp_host'=>$config['smtp_host'],'smtp_port'=>$config['smtp_port']],JSON_THROW_ON_ERROR));$notice='Servidores de la empresa guardados. Los usuarios solo necesitan su correo y contraseña del buzón.';
   }elseif($action==='recover_mail'){
    $db=tnl_db();$trash=new \TNL\Mail\RecoveryTrash($db);$copy=$trash->get((int)($_POST['recovery_id']??0));
    if($copy['restored_at'])throw new InvalidArgumentException('Esta copia ya fue restaurada.');
    $store=new \TNL\Mail\ExternalAccount($db,tnl_path('storage'));$account=$store->load($copy['owner']);
    if(!$account||$account['email']!==$copy['email'])throw new InvalidArgumentException('El buzón original ya no está configurado. Restaura su configuración antes de recuperar.');
    $imap=new \TNL\Mail\ImapClient($account);try{$imap->connect();$imap->restore($copy['raw']);$trash->restored((int)$copy['id']);}finally{$imap->close();}
    $notice='Restaurado en TNL Recovered. Si hubo un corte de conexión, comprueba el buzón antes de repetir.';
   }elseif($action==='user_update'){
    Users::update(tnl_db(),$_POST);$notice='Datos del usuario actualizados.';
   }elseif($action==='mailbox'){
    $db=tnl_db();$mailboxes=new \TNL\Mail\Mailboxes($db);$store=new \TNL\Mail\ExternalAccount($db,tnl_path('storage'));
    $mailboxes->assign((int)($_POST['mailbox_id']??0),(int)($_POST['user_id']??0),(string)($_POST['label']??''),$_POST,(string)($_POST['password']??''),$store);$notice='Buzón guardado y asignado.';
   }elseif($action==='external_permission'){
    $db=tnl_db();$stmt=$db->prepare('UPDATE users SET can_send_external=CASE can_send_external WHEN 1 THEN 0 ELSE 1 END WHERE id=?');$stmt->execute([(int)($_POST['user_id']??0)]);$notice='Permiso de envío externo actualizado.';
   }elseif($action==='user'){
    $db=tnl_db();$domain=Settings::get($db,'company_domain');
    if(!$domain)throw new InvalidArgumentException('Configura primero el dominio empresarial.');
    Users::create($db,['username'=>$_POST['username']??'','job_title'=>$_POST['job_title']??'','department'=>$_POST['department']??'','display_name'=>$_POST['display_name']??'','domain'=>$domain,'role'=>$_POST['role']??'employee','password'=>$_POST['password']??'','can_send_external'=>isset($_POST['can_send_external'])]);
    $notice='Usuario interno creado.';
   }
  }catch(Throwable $e){$error=$e instanceof InvalidArgumentException?$e->getMessage():'No se pudo guardar. Verifica que no exista un usuario duplicado.';}
 }
}
$loggedIn=isset($_SESSION['admin_id']);
$page=(string)($_GET['page']??'home');
if(!in_array($page,['home','settings','users','mail','trash','routes'],true))$page='home';
$db=$loggedIn?tnl_db():null;
$company=$db?Settings::get($db,'company_name','Mi empresa'):'';
$domain=$db?Settings::get($db,'company_domain'):'';
$users=$db&&$page==='users'?Users::all($db):[];
$editBox=null;$editAccount=[];
if($db&&$page==='users'&&(int)($_GET['edit_box']??0)>0){
 $mb=new \TNL\Mail\Mailboxes($db);
 foreach($mb->all() as $b)if((int)$b['id']===(int)$_GET['edit_box']){$editBox=$b;break;}
 if($editBox){try{$editAccount=(new \TNL\Mail\ExternalAccount($db,tnl_path('storage')))->load('mailbox:'.$editBox['id'])??[];}catch(Throwable $e){$error='No se pudo cargar la configuración del buzón.';}}
}
$teamCount=0;if($db&&$page==='home'){Users::init($db);$teamCount=(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();}
?><!doctype html><html lang="es"><head><base href="<?=tnl_escape(tnl_url(''))?>"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#235d87"><link rel="stylesheet" href="assets/app.css?v=0.6.0"><title>TNL Mail</title><script src="assets/workspace.js?v=0.6.0" defer></script></head><body>
<?php if(!$loggedIn): ?><main class="auth"><section class="panel"><?php \TNL\UI\Workspace::authBrand();?><span class="eyebrow">ADMINISTRACIÓN</span><h1>Administración de TNL Mail</h1><p class="auth-description">Inicia sesión para gestionar tu correo y tu equipo.</p><?php if($error):?><p class="error"><?=tnl_escape($error)?></p><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="login"><label>Usuario<input name="username" required autocomplete="username"></label><label>Contraseña<input type="password" name="password" required autocomplete="current-password"></label><button>Entrar al espacio</button></form><div class="auth-footer">¿Tienes una cuenta de empleado? <a href="index.php">Accede aquí →</a></div></section></main>
<?php else: ?><div class="layout"><?php \TNL\UI\Workspace::sidebar('admin',$page==='mail'?'external':$page,(string)($_SESSION['admin_name']??''));?><main class="main"><div class="top"><div><span class="eyebrow">ADMINISTRACIÓN · <?=tnl_escape($company)?></span><h1><?=tnl_escape(['home'=>'Panel principal','settings'=>'Configuración de empresa','users'=>'Usuarios internos','mail'=>'Correo empresarial','trash'=>'Papelera de recuperación','routes'=>'Supervisor de rutas'][$page])?></h1></div><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="logout"><button class="button-ghost">Cerrar sesión</button></form></div>
<?php if($error):?><p class="error"><?=tnl_escape($error)?></p><?php endif;?><?php if($notice):?><p class="notice" role="status"><?=tnl_escape($notice)?></p><?php endif;?>
<?php if($page==='home'):?><p class="page-description">Un lugar para tu correo, tu empresa y las personas con las que trabajas.</p><div class="cards"><article class="card"><span class="card-icon"><?=\TNL\UI\Workspace::icon('building')?></span><div class="stat-label">TU EMPRESA</div><div class="stat-value"><?=tnl_escape($company)?></div><small><?=tnl_escape($domain?:'Configura tu dominio empresarial')?></small></article><article class="card"><span class="card-icon"><?=\TNL\UI\Workspace::icon('users')?></span><div class="stat-label">PERSONAS EN TU EQUIPO</div><div class="stat-value"><?=$teamCount?></div><a href="admin/?page=users">Gestionar usuarios →</a></article><article class="card"><span class="card-icon"><?=\TNL\UI\Workspace::icon('mail')?></span><div class="stat-label">TU CORREO</div><div class="stat-value">Tu bandeja</div><small>Accede a tu buzón externo desde tu espacio.</small></article></div><section class="panel"><div class="section-header"><div><h2>¿Por dónde empezamos?</h2><p class="section-description">Acceso directo a lo que usas cada día.</p></div></div><div class="quick-links"><a class="quick-link" href="external.php?as=admin"><span class="card-icon"><?=\TNL\UI\Workspace::icon('mail')?></span><div><strong>Abrir mi correo</strong><small>Leer, responder y enviar mensajes</small></div><?=\TNL\UI\Workspace::icon('arrow')?></a><a class="quick-link" href="admin/?page=users"><span class="card-icon"><?=\TNL\UI\Workspace::icon('users')?></span><div><strong>Gestionar mi equipo</strong><small>Usuarios y permisos de correo</small></div><?=\TNL\UI\Workspace::icon('arrow')?></a></div></section>
<?php elseif($page==='settings'):?><section class="panel" style="margin-top:24px"><h2>Identidad empresarial</h2><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="settings"><label>Nombre de empresa<input name="company" required maxlength="120" value="<?=tnl_escape($company)?>"></label><label>Dominio empresarial<input name="domain" required placeholder="miempresa.com" value="<?=tnl_escape($domain)?>"></label><button>Guardar</button></form></section><section class="panel"><h2>Servidores de correo de la empresa</h2><p>Se reutilizarán al conectar una dirección de tu dominio. No compartimos contraseñas entre usuarios.</p><?php $servers=json_decode(Settings::get($db,'company_mail_servers','{}'),true);?><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="company_servers"><label>Servidor IMAP · TLS 993<input name="imap_host" required value="<?=tnl_escape($servers['imap_host']??'')?>"></label><label>Servidor SMTP<input name="smtp_host" required value="<?=tnl_escape($servers['smtp_host']??'')?>"></label><label>Seguridad SMTP<select name="smtp_port"><option value="465" <?=($servers['smtp_port']??465)===465?'selected':''?>>SSL/TLS 465</option><option value="587" <?=($servers['smtp_port']??465)===587?'selected':''?>>STARTTLS 587</option></select></label><button>Guardar servidores para mi empresa</button></form></section>
<?php elseif($page==='users'):?><section class="panel" style="margin-top:24px"><h2>Nuevo usuario</h2><p>Estas identidades no crean buzones en cPanel.</p><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="user"><label>Usuario<input name="username" required placeholder="empleado001"></label><label>Nombre<input name="display_name" required></label><label>Cargo<input name="job_title" maxlength="120" placeholder="Gerente general"></label><label>Departamento<input name="department" maxlength="120"></label><label>Contraseña inicial<input name="password" type="password" minlength="12" required autocomplete="new-password"></label><label>Rol<select name="role"><option value="employee">Empleado</option><option value="manager">Supervisor</option></select></label><label><input type="checkbox" name="can_send_external" value="1"> Permitir envío de correo externo</label><button>Crear usuario</button></form></section><div class="cards"><?php foreach($users as $u):?><article class="card"><h2><?=tnl_escape($u['display_name'])?></h2><p><?=tnl_escape($u['internal_address'])?></p><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="user_update"><input type="hidden" name="user_id" value="<?=(int)$u['id']?>"><label>Nombre<input name="display_name" required value="<?=tnl_escape($u['display_name'])?>"></label><p>Usuario: <strong><?=tnl_escape($u['username'])?></strong></p><label>Cargo<input name="job_title" maxlength="120" value="<?=tnl_escape($u['job_title'])?>"></label><label>Departamento<input name="department" maxlength="120" value="<?=tnl_escape($u['department'])?>"></label><label>Dirección interna<input type="email" name="internal_address" required value="<?=tnl_escape($u['internal_address'])?>"></label><button>Guardar usuario</button></form><small><?=tnl_escape($u['role'])?> · <?=((int)$u['can_send_external']===1?'Envío externo autorizado':'Envío externo bloqueado')?></small><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="external_permission"><input type="hidden" name="user_id" value="<?=(int)$u['id']?>"><button><?=!empty($u['can_send_external'])?'Bloquear envío externo':'Permitir envío externo'?></button></form></article><?php endforeach;?></div>

<section class="panel"><h2>Buzones asignados</h2><?php $mb=new \TNL\Mail\Mailboxes($db);foreach($mb->all() as $b):?><p><a href="admin/?page=users&amp;edit_box=<?=(int)$b['id']?>"><?=tnl_escape($b['label'].' · '.$b['display_name'])?> · Editar dirección, servidores o asignación</a></p><?php endforeach;?></section><section class="panel"><h2><?=$editBox?'Editar buzón':'Asignar buzón a un usuario'?></h2><p>El buzón debe existir en tu proveedor. Cada buzón conserva sus propios servidores y credenciales.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="mailbox">
<input type="hidden" name="mailbox_id" value="<?=(int)($editBox['id']??0)?>">
<label>Asignar a<select name="user_id" required><?php foreach($users as $u):?><option value="<?=(int)$u['id']?>" <?=(int)($editBox['user_id']??0)===(int)$u['id']?'selected':''?>><?=tnl_escape($u['display_name'])?></option><?php endforeach;?></select></label>
<label>Nombre del buzón<input name="label" required maxlength="100" value="<?=tnl_escape($editBox['label']??'')?>"></label><label>Dirección de envío<input type="email" name="email" value="<?=tnl_escape($editAccount['email']??'')?>" required></label>
<div class="form-grid"><label>Servidor IMAP · TLS 993<input name="imap_host" value="<?=tnl_escape($editAccount['imap_host']??'')?>" required placeholder="mail.empresa.com"></label><label>Servidor SMTP<input name="smtp_host" value="<?=tnl_escape($editAccount['smtp_host']??'')?>" required placeholder="mail.empresa.com"></label></div>
<div class="form-grid"><label>Usuario IMAP<input name="imap_username" value="<?=tnl_escape($editAccount['imap_username']??'')?>" placeholder="Por defecto: dirección de envío"></label><label>Usuario SMTP<input name="smtp_username" value="<?=tnl_escape($editAccount['smtp_username']??'')?>" placeholder="Por defecto: dirección de envío"></label></div>
<label>Seguridad SMTP<select name="smtp_port"><option value="465" <?=((int)($editAccount['smtp_port']??465)===465)?'selected':''?>>SSL/TLS · 465</option><option value="587" <?=((int)($editAccount['smtp_port']??465)===587)?'selected':''?>>STARTTLS · 587</option></select></label><label>Contraseña IMAP<input type="password" name="password" autocomplete="new-password" placeholder="Vacío conserva la anterior al editar"></label><label>Contraseña SMTP<input type="password" name="smtp_password" autocomplete="new-password" placeholder="Por defecto: misma contraseña de IMAP"></label>
<button>Guardar y asignar</button><?php if($editBox):?><a href="admin/?page=users">Asignar otro buzón</a><?php endif;?></form></section>
<?php elseif($page==='routes'):?><section class="panel"><h2>Últimas 100 decisiones de envío</h2><p>Los destinatarios internos se resuelven antes de conectar con SMTP. Las direcciones empresariales desconocidas o ambiguas se bloquean. Esta supervisión cubre los envíos desde TNL Mail; no sustituye al servidor de correo entrante.</p><?php $routeLabels=['internal'=>'Interno','smtp'=>'SMTP externo','blocked'=>'Bloqueado'];$statusLabels=['delivered'=>'Entregado internamente','accepted'=>'Aceptado por SMTP','attempting'=>'Intento iniciado','unconfirmed'=>'Resultado no confirmado','denied'=>'Sin permiso','rejected'=>'Rechazado'];foreach(\TNL\Mail\Router::recent($db) as $r):?><article class="card"><strong><?=tnl_escape($r['recipient'])?></strong><p><?=tnl_escape(($routeLabels[$r['route']]??$r['route']).' · '.($statusLabels[$r['status']]??$r['status']))?></p><small><?=tnl_escape($r['actor'].' · '.$r['created_at'])?></small></article><?php endforeach;?></section><?php elseif($page==='trash'):?><section class="panel"><h2>Correos recuperables</h2><p>Copias completas en SQLite, incluidos los adjuntos. Límite total: 250 MB; mensajes de hasta 1 MB. Si no se puede guardar la copia, el borrado se detiene. Sin eliminación automática.</p><?php $trash=new \TNL\Mail\RecoveryTrash($db);foreach($trash->all() as $copy):?><article class="card"><h3><?=tnl_escape($copy['subject'])?></h3><p><?=tnl_escape($copy['email'])?> · <?=tnl_escape($copy['created_at'])?> · <?=(int)$copy['bytes']?> bytes</p><?php if(!$copy['restored_at']):?><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="recover_mail"><input type="hidden" name="recovery_id" value="<?=(int)$copy['id']?>"><button>Recuperar al buzón original</button></form><?php else:?><p>Recuperado: <?=tnl_escape($copy['restored_at'])?></p><?php endif;?></article><?php endforeach;?></section><?php else:?><section class="card" style="margin-top:24px"><h2>Pasarela de correo</h2><p><a href="external.php?as=admin">Abrir correo externo y conectar un buzón</a>. Cada cuenta está aislada por usuario.</p></section><?php endif;?>
</main></div><?php endif;?></body></html>
