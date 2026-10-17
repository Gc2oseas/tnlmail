<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require_once dirname(__DIR__).'/app/UI/Workspace.php';
require dirname(__DIR__).'/app/Admin/Users.php';
require dirname(__DIR__).'/app/Admin/Recovery.php';
require_once dirname(__DIR__).'/app/Mail/InternalMail.php';
require_once dirname(__DIR__).'/app/Mail/Router.php';
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
   \TNL\Mail\Router::send($db,'employee:'.$id,$id,(string)($_POST['recipient']??''),(string)($_POST['subject']??''),(string)($_POST['body']??''),false,fn()=>throw new RuntimeException('Esta pantalla solo admite correo interno.'),isset($_POST['reply_to_id'])?(int)$_POST['reply_to_id']:null);
   $notice='Mensaje interno enviado.';
  }elseif(($_POST['action']??'')==='delete'){
   InternalMail::delete($db,$id,(int)($_POST['message_id']??0));$notice='Mensaje eliminado de tu bandeja.';
  }
 }catch(Throwable $e){$error=$e instanceof InvalidArgumentException?$e->getMessage():'No se pudo completar la operación.';}
}
$message=isset($_GET['id'])?InternalMail::read($db,$id,(int)$_GET['id']):null;
$messages=InternalMail::list($db,$id,$folder);
$replyTo=($message&&(int)$message['sender_id']!==$id)?$message:null;
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="assets/app.css?v=0.6.0"><script src="assets/workspace.js?v=0.6.0" defer></script><title>Correo del equipo · TNL Mail</title></head><body><div class="layout"><?php \TNL\UI\Workspace::sidebar('employee','internal',(string)$me['display_name']);?><main class="main"><header class="page-header"><div><span class="eyebrow">TU EMPRESA · CORREO INTERNO</span><h1><?=$folder==='sent'?'Enviados al equipo':'Correo del equipo'?></h1><p class="page-description">Conversaciones con las personas de tu empresa.</p></div><div class="page-actions"><a class="button" href="?folder=<?=tnl_escape($folder)?>&amp;compose=1#internal-compose"><?=\TNL\UI\Workspace::icon('compose')?> Redactar</a></div></header><div class="mail-toolbar"><nav class="mail-tabs" aria-label="Carpetas internas"><a class="mail-tab <?=$folder==='inbox'?'active':''?>" href="mail.php">Entrada</a><a class="mail-tab <?=$folder==='sent'?'active':''?>" href="mail.php?folder=sent">Enviados</a></nav><span class="badge">Solo tu equipo</span></div>
<?php if($error):?><p class="error" role="alert"><?=tnl_escape($error)?></p><?php endif;?><?php if($notice):?><p class="notice" role="status"><?=tnl_escape($notice)?></p><?php endif;?>
<?php if($message):?><article class="panel message-panel"><header class="message-heading"><div><h2><?=tnl_escape($message['subject'])?></h2><div class="message-meta"><span>De: <?=tnl_escape($message['sender_name'].' <'.$message['sender_address'].'>')?></span><span><?=tnl_escape($message['created_at'])?></span></div></div><?php if($replyTo):?><a class="button button-secondary" href="#internal-compose">Responder</a><?php endif;?></header><pre class="message-text"><?=tnl_escape($message['body'])?></pre><footer class="message-footer"><a href="mail.php?folder=<?=tnl_escape($folder)?>">← Volver a la bandeja</a><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="message_id" value="<?=(int)$message['id']?>"><div><button class="button-danger">Eliminar de mi bandeja</button></div></form></footer></article><?php else:?><section class="panel mail-surface"><?php if($messages):?><div class="mail-list-heading"><span><?=$folder==='sent'?'Destinatario':'Remitente'?></span><span>Asunto</span><span>Fecha</span></div><div class="mail-list"><?php foreach($messages as $m):?><a class="mail-row <?=$m['read_at']===null&&$folder==='inbox'?'unread':''?>" href="?folder=<?=tnl_escape($folder)?>&amp;id=<?=(int)$m['id']?>"><span class="mail-sender" title="<?=tnl_escape($m['peer_address'])?>"><?=tnl_escape($m['peer_name'])?></span><span class="mail-subject"><?=tnl_escape($m['subject'])?><?=$m['read_at']===null&&$folder==='inbox'?' · Sin leer':''?></span><time class="mail-date"><?=tnl_escape($m['created_at'])?></time></a><?php endforeach;?></div><?php else:?><div class="empty-state"><span class="card-icon"><?=\TNL\UI\Workspace::icon('mail')?></span><h2>No hay mensajes en esta carpeta</h2><p>Escribe a alguien de tu equipo para comenzar una conversación.</p></div><?php endif;?></section><?php endif;?>
<details class="panel composer" id="internal-compose" <?=($replyTo||isset($_GET['compose']))?'open':''?>><summary class="compose-summary"><?=\TNL\UI\Workspace::icon('compose')?> <?=$replyTo?'Responder al mensaje':'Redactar un mensaje al equipo'?></summary><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><input type="hidden" name="action" value="send"><?php if($replyTo):?><input type="hidden" name="reply_to_id" value="<?=(int)$replyTo['id']?>"><?php endif;?><label>Para<input type="text" name="recipient" required placeholder="Escribe usuario, nombre o cargo" autocomplete="off" data-directory value="<?=tnl_escape($replyTo?(string)$replyTo['sender_address']:'')?>"></label><label>Asunto<input name="subject" maxlength="200" required value="<?=tnl_escape($replyTo?'Re: '.$replyTo['subject']:'')?>"></label><label>Mensaje<textarea name="body" rows="10" maxlength="50000" required placeholder="Escribe tu mensaje aquí…"></textarea></label><div class="form-actions"><small>Este mensaje solo se enviará dentro de tu empresa.</small><button><?=\TNL\UI\Workspace::icon('send')?> Enviar mensaje</button></div></form></details></main></div></body></html>
