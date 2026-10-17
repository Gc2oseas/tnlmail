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
 public static function send(array $config,string $to,string $subject,string $body,?SMTP $transport=null,array $thread=[],string $format='plain',array $extraTo=[],array $cc=[]): string {
  if(($to===''&&!$cc)||($to!==''&&!filter_var($to,FILTER_VALIDATE_EMAIL))||preg_match('/[\r\n\x00]/',$to.$subject)||strlen($subject)<1||strlen($subject)>200||strlen($body)<1||strlen($body)>50000)throw new \InvalidArgumentException('Revisa destinatario, asunto (1–200) y mensaje (1–50000 caracteres).');
  foreach(array_merge($extraTo,$cc) as $address)if(!is_string($address)||!filter_var($address,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n\x00]/',$address))throw new \InvalidArgumentException('Destinatario inválido.');
  $mail=self::smtp($config,$transport);try{if($to!=='')$mail->addAddress($to);foreach($extraTo as $address)$mail->addAddress($address);foreach($cc as $address)$mail->addCC($address);$mail->Subject=$subject;require_once __DIR__.'/MessageBody.php';if(!in_array($format,['plain','html','markdown'],true))throw new \InvalidArgumentException('Formato inválido.');$html=MessageBody::html($body,$format);if($html!=='')$html=preg_replace_callback('~src="data:(image/(?:png|jpeg|gif|webp));base64,([A-Za-z0-9+/=]+)"~',function($m)use($mail){$bytes=base64_decode($m[2],true);$info=$bytes!==false?@getimagesizefromstring($bytes):false;if(!$info||strlen($bytes)>524288)return ''; $cid='tnl-'.hash('sha256',$bytes).'@inline';$mail->addStringEmbeddedImage($bytes,$cid,'image.'.explode('/',$m[1])[1],'base64',$info['mime']);return 'src="cid:'.$cid.'"';},$html);if($html!==''&&preg_match('/^[a-f0-9]{32}\.[a-f0-9]{64}$/D',$thread['route_marker']??'')){$marker=$thread['route_marker'];$label=htmlspecialchars(substr($thread['route_identity']??'',0,300),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$html.='<!-- tnl-route='.$marker.' --><span style="display:none!important">sent by:'.$label.' [tnl-route='.$marker.']</span>';$mail->addCustomHeader('X-TNL-Route',$marker);}if(!empty($thread['auto_reply'])){$mail->addCustomHeader('Auto-Submitted','auto-replied');$mail->addCustomHeader('X-Auto-Response-Suppress','All');$mail->addCustomHeader('Precedence','bulk');}$mail->Body=$html!==''?$html:$body;$mail->isHTML($html!=='');if($html!=='')$mail->AltBody=MessageBody::plain($body,$format);
   $reply=ReplyComposer::messageId((string)($thread['in_reply_to']??''));if($reply!==''){$safe=ReplyComposer::thread(['message-id'=>$reply,'references'=>(string)($thread['references']??'')]);$mail->addCustomHeader('In-Reply-To',$safe['in_reply_to']);$mail->addCustomHeader('References',$safe['references']);}
   $mail->send();return $mail->getLastMessageID();}catch(\Throwable $e){throw new \RuntimeException('No se confirmó el envío SMTP. Revisa el servidor antes de volver a intentarlo para evitar duplicados.');}finally{$mail->smtpClose();}
 }
}
