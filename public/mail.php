<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require dirname(__DIR__).'/app/Admin/Users.php';
require dirname(__DIR__).'/app/Admin/Recovery.php';
require dirname(__DIR__).'/app/Mail/InternalMail.php';
use TNL\Admin\Users;
use TNL\Admin\Recovery;
use TNL\Mail\InternalMail;
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");
if(!is_file(tnl_path('storage/system.sqlite'))){header('Location: ../install.php');exit;}
tnl_session();$db=tnl_db();Users::init($db);Recovery::init($db);
if(!isset($_SESSION['employee_id'])){header('Location: employee.php');exit;}
$id=(int)$_SESSION['employee_id'];
$s=$db->prepare('SELECT id,display_name,credentials_version,must_change_password FROM users WHERE id=? AND active=1');
$s->execute([$id]);$me=$s->fetch(PDO::FETCH_ASSOC);
if(!$me||(int)$me['credentials_version']!==(int)($_SESSION['credentials_version']??0)){unset($_SESSION['employee_id'],$_SESSION['credentials_version']);header('Location: employee.php');exit;}
if((int)$me['must_change_password']===1){header('Location: recovery.php');exit;}
$error='';$notice='';$folder=($_GET['folder']??'inbox')==='sent'?'sent':'inbox';
if($_SERVER['REQUEST_METHOD']==='POST'){
 tnl_check_csrf();
 try{
  if(($_POST['action']??'')==='send'){
   InternalMail::send($db,$id,(string)($_POST['recipient']??''),(string)($_POST['subject']??''),(string)($_POST['body']??''));
   $notice='Mensaje interno enviado.';
  }elseif(($_POST['action']??'')==='delete'){
   InternalMail::delete($db,$id,(int)($_POST['message_id']??0));$notice='Mensaje eliminado de tu bandeja.';
  }
 }catch(Throwable $e){$error=$e instanceof InvalidArgumentException?$e->getMessage():'No se pudo completar la operación.';}
}
$message=isset($_GET['id'])?InternalMail::read($db,$id,(int)$_GET['id']):null;
$messages=InternalMail::list($db,$id,$folder);
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="assets/app.css"><title>TNL Mail · Correo interno</title></head><body><div class="layout"><aside class="sidebar"><div class="brand">✉ TNL Mail</div><a class="nav" href="employee.php">Mi espacio</a><a class="nav <?=$folder==='inbox'?'active':''?>" href="mail.php">Entrada</a><a class="nav <?=$folder==='sent'?'active':''?>" href="mail.php?folder=sent">Enviados</a><a class="nav" href="recovery.php">Seguridad</a></aside><main class="main"><div class="top"><div><span class="eyebrow">CORREO INTERNO · <?=tnl_escape($me['display_name'])?></span><h1><?= $folder==='sent'?'Enviados':'Bandeja de entrada' ?></h1></div><a href="employee.php">Volver</a></div>
<?php if($error):?><p class="error"><?=tnl_escape($error)?></p><?php endif;?><?php if($notice):?><p><?=tnl_escape($notice)?></p><?php endif;?>
<section class="panel" style="margin:24px 0;width:min(100%,720px)"><h2>Redactar mensaje</h2><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="send"><label>Destinatario interno<input type="email" name="recipient" required placeholder="empleado001@miempresa.com"></label><label>Asunto<input name="subject" maxlength="200" required></label><label>Mensaje<textarea name="body" rows="6" maxlength="50000" required></textarea></label><button>Enviar mensaje interno</button></form></section>
<?php if($message):?><article class="card" style="margin:20px 0"><h2><?=tnl_escape($message['subject'])?></h2><p>De: <?=tnl_escape($message['sender_name'].' <'.$message['sender_address'].'>')?></p><p><?=tnl_escape($message['created_at'])?></p><div style="white-space:pre-wrap"><?=tnl_escape($message['body'])?></div><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="message_id" value="<?=(int)$message['id']?>"><button>Eliminar de mi bandeja</button></form></article><?php endif;?>
<h2>Mensajes</h2><?php if(!$messages):?><p>No hay mensajes en esta carpeta.</p><?php endif;?><div class="cards"><?php foreach($messages as $m):?><article class="card"><h2><a href="?folder=<?=tnl_escape($folder)?>&amp;id=<?=(int)$m['id']?>"><?=tnl_escape($m['subject'])?></a></h2><p><?=tnl_escape($m['peer_name'].' · '.$m['peer_address'])?></p><small><?=tnl_escape($m['created_at'])?><?=$m['read_at']===null&&$folder==='inbox'?' · Sin leer':''?></small></article><?php endforeach;?></div>
</main></div></body></html>