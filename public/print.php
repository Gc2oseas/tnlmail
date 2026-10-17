<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/app/Auth/SessionUser.php';require_once dirname(__DIR__).'/app/Mail/ExternalMail.php';require_once dirname(__DIR__).'/app/Mail/AccountAccess.php';require_once dirname(__DIR__).'/app/Mail/InternalMail.php';require_once dirname(__DIR__).'/app/UI/PrintMessage.php';
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src data: https:; script-src 'self'; base-uri 'none'; frame-ancestors 'none'");
tnl_session();$db=tnl_db();$identity=\TNL\Auth\SessionUser::require($db);
try{
 if(($_GET['kind']??'')==='internal'){
  if($identity['role']!=='employee')throw new RuntimeException('Mensaje no disponible.');
  $m=\TNL\Mail\InternalMail::read($db,$identity['id'],(int)($_GET['id']??0));if(!$m)throw new RuntimeException('Mensaje no disponible.');
  $q=$db->prepare('SELECT internal_address FROM users WHERE id=?');$q->execute([$m['recipient_id']]);
  $message=['headers'=>['subject'=>$m['subject'],'from'=>$m['sender_name'].' <'.$m['sender_address'].'>','to'=>$q->fetchColumn()?:'','date'=>$m['created_at']],'body'=>$m['body']];
 }else{
  $store=new \TNL\Mail\ExternalAccount($db,tnl_path('storage'));$owner=\TNL\Mail\AccountAccess::owner($db,$store,$identity,$_GET);$account=$store->load($owner);if(!$account)throw new RuntimeException('Cuenta no disponible.');
  if(($_GET['source']??'')==='sent'){
   $m=$store->sentMessage($owner,(int)($_GET['id']??0));if(!$m)throw new RuntimeException('Mensaje no disponible.');
   $message=['headers'=>['subject'=>$m['subject'],'from'=>$account['email'],'to'=>$m['recipient'],'date'=>$m['created_at']],'body'=>$m['body']];
  }else{
   $folders=['inbox'=>'INBOX','archive'=>'TNL Archive','trash'=>'TNL Trash','spam'=>'TNL Spam','recovered'=>'TNL Recovered'];$folder=$folders[(string)($_GET['folder']??'inbox')]??null;if(!$folder)throw new RuntimeException('Carpeta inválida.');
   $imap=new \TNL\Mail\ImapClient($account);try{$imap->connect();$message=$imap->read((int)($_GET['uid']??0),$folder);}finally{$imap->close();}
  }
 }
 \TNL\UI\PrintMessage::render($message);
}catch(Throwable $e){http_response_code(404);echo 'No se pudo abrir este mensaje para imprimir. Comprueba el acceso y el tamaño del correo.';}
