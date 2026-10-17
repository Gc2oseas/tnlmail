<?php
declare(strict_types=1);
namespace TNL\Mail;
require_once __DIR__.'/ExternalAccount.php';require_once __DIR__.'/ImapClient.php';
require_once __DIR__.'/ReplyComposer.php';
require_once dirname(__DIR__).'/ThirdParty/PHPMailer/Exception.php';require_once dirname(__DIR__).'/ThirdParty/PHPMailer/SMTP.php';require_once dirname(__DIR__).'/ThirdParty/PHPMailer/PHPMailer.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
final class ExternalMail {
 public static function smtp(array $config,?SMTP $transport=null): PHPMailer {
  if($transport===null)ExternalAccount::publicHost($config['smtp_host']);$mail=new PHPMailer(true);if($transport)$mail->setSMTPInstance($transport);$mail->isSMTP();$mail->Host=$config['smtp_host'];$mail->Port=(int)($config['smtp_port']??465);$mail->SMTPSecure=$mail->Port===587?PHPMailer::ENCRYPTION_STARTTLS:PHPMailer::ENCRYPTION_SMTPS;$mail->SMTPAuth=true;$mail->Username=$config['smtp_username']??$config['email'];$mail->Password=$config['smtp_password']??$config['password'];$mail->Timeout=10;$mail->getSMTPInstance()->Timelimit=15;$mail->CharSet='UTF-8';$mail->SMTPOptions=['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false]];$mail->setFrom($config['email']);return $mail;
 }
 public static function probe(array $config): array {
  $out=[];$imap=new ImapClient($config);try{$imap->connect();$out['imap']='Autenticación correcta (TLS, 993).';}catch(\Throwable $e){$out['imap']=$e->getMessage();}finally{$imap->close();}
  $mail=null;try{$mail=self::smtp($config);if(!$mail->smtpConnect())throw new \RuntimeException('Falló autenticación SMTP.');$out['smtp']='Autenticación correcta (TLS, '.($config['smtp_port']??465).').';}catch(\Throwable $e){$out['smtp']='SMTP: no se pudo autenticar con TLS en el servidor configurado. Comprueba servidor, cuenta y contraseña.';}finally{if($mail)$mail->smtpClose();}return $out;
 }
 public static function send(array $config,string $to,string $subject,string $body,?SMTP $transport=null,array $thread=[],string $format='plain'): string {
  if(!filter_var($to,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n\x00]/',$to.$subject)||strlen($subject)<1||strlen($subject)>200||strlen($body)<1||strlen($body)>50000)throw new \InvalidArgumentException('Revisa destinatario, asunto (1–200) y mensaje (1–50000 caracteres).');
  $mail=self::smtp($config,$transport);try{$mail->addAddress($to);$mail->Subject=$subject;require_once __DIR__.'/MessageBody.php';if(!in_array($format,['plain','html','markdown'],true))throw new \InvalidArgumentException('Formato inválido.');$html=MessageBody::html($body,$format);if($html!=='')$html=preg_replace_callback('~src="data:(image/(?:png|jpeg|gif|webp));base64,([A-Za-z0-9+/=]+)"~',function($m)use($mail){$bytes=base64_decode($m[2],true);$info=$bytes!==false?@getimagesizefromstring($bytes):false;if(!$info||strlen($bytes)>524288)return ''; $cid='tnl-'.hash('sha256',$bytes).'@inline';$mail->addStringEmbeddedImage($bytes,$cid,'image.'.explode('/',$m[1])[1],'base64',$info['mime']);return 'src="cid:'.$cid.'"';},$html);$mail->Body=$html!==''?$html:$body;$mail->isHTML($html!=='');if($html!=='')$mail->AltBody=MessageBody::plain($body,$format);
   $reply=ReplyComposer::messageId((string)($thread['in_reply_to']??''));if($reply!==''){$safe=ReplyComposer::thread(['message-id'=>$reply,'references'=>(string)($thread['references']??'')]);$mail->addCustomHeader('In-Reply-To',$safe['in_reply_to']);$mail->addCustomHeader('References',$safe['references']);}
   $mail->send();return $mail->getLastMessageID();}catch(\Throwable $e){throw new \RuntimeException('No se confirmó el envío SMTP. Revisa el servidor antes de volver a intentarlo para evitar duplicados.');}finally{$mail->smtpClose();}
 }
}
