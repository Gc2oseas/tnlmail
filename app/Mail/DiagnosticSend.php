<?php
declare(strict_types=1);
namespace TNL\Mail;
require_once __DIR__.'/ExternalMail.php';
final class DiagnosticSend {
 const RECIPIENT='gc2oseas@gmail.com';
 public static function send(\PDO $db,string $storage,string $requestId,string $password=''): array {
  if(!preg_match('/^[a-f0-9]{32}$/D',$requestId))throw new \InvalidArgumentException('Identificador de prueba inválido.');
  $db->exec("CREATE TABLE IF NOT EXISTS mail_diagnostic_runs(request_id TEXT PRIMARY KEY,state TEXT NOT NULL,message_id TEXT NOT NULL DEFAULT '',created_at TEXT NOT NULL)");
  $q=$db->prepare('SELECT state,message_id,created_at FROM mail_diagnostic_runs WHERE request_id=?');$q->execute([$requestId]);if($old=$q->fetch(\PDO::FETCH_ASSOC))return $old+['recipient'=>self::RECIPIENT,'duplicate_prevented'=>true];
  $store=new ExternalAccount($db,$storage);$config=null;$owner=null;
  foreach($db->query('SELECT owner,config FROM external_accounts')->fetchAll(\PDO::FETCH_ASSOC) as $row){$meta=json_decode($row['config'],true);if(strtolower($meta['email']??'')==='claudetest@cache.lat'){$config=$store->load($row['owner']);$owner=$row['owner'];break;}}
  if(!$config){if($password===''||strlen($password)>512||preg_match('/[\r\n\x00]/',$password))throw new \RuntimeException('No hay una cuenta de prueba guardada y falta su contraseña.');$config=ExternalAccount::config(['email'=>'claudetest@cache.lat','imap_host'=>'premium902-2.web-hosting.com','smtp_host'=>'premium902-2.web-hosting.com'])+['password'=>$password,'smtp_password'=>$password];}
  $date=gmdate('c');$subject='TNL Mail · prueba real · '.substr($requestId,0,8);$body='<h2>Prueba de TNL Mail</h2><p>Mensaje de diagnóstico autorizado para tu cuenta. Referencia: '.substr($requestId,0,8).'</p><p>Texto UTF-8: información, envío, ñ, áéíóú.</p><table width="600"><tr><th>Área</th><th>Estado</th><th>Responsable</th></tr><tr><td width="25%"></td><td width="50%">En revisión</td><td width="25%">Equipo</td></tr><tr><td>Correo</td><td>Prueba SMTP</td><td>TNL Mail</td></tr></table><p>Imagen incrustada de prueba (128 × 72):</p><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAIAAAABICAIAAACx52pFAAAAmklEQVR4nO3RMQ0AMAzAsDIY3d1DPhg9YikAInnOu1ps1g/iAQDQDgCAdgAAtAMAoB0AAO0AAGgHAEA7AADaAQDQDgCAdgAAtAMAoB0AAO0AAGgHAEA7AADaAQDQDgCAdgAAtAMAoB0AAO0AAGgHAEA7AADaAQDQDgCAdgAAtAMAoB0AAO0AAGgHAEA7AADaAQDQDgCAdgAAtPt2G0Ysr36qgAAAAABJRU5ErkJggg==" alt="Imagen de prueba TNL Mail"><blockquote><p>Texto citado para comprobar su presentación.</p></blockquote>';
  $db->prepare('INSERT INTO mail_diagnostic_runs(request_id,state,created_at) VALUES(?,?,?)')->execute([$requestId,'attempting',$date]);
  try{$messageId=ExternalMail::send($config,self::RECIPIENT,$subject,$body,null,[],'html');$db->prepare('UPDATE mail_diagnostic_runs SET state=?,message_id=? WHERE request_id=?')->execute(['smtp_accepted',$messageId,$requestId]);$copy=false;if($owner!==null)try{$store->recordSent($owner,self::RECIPIENT,$subject,$body,$messageId,[],'html');$copy=true;}catch(\Throwable $e){}return ['state'=>'smtp_accepted','from'=>$config['email'],'recipient'=>self::RECIPIENT,'subject'=>$subject,'message_id'=>$messageId,'created_at'=>$date,'local_sent_copy'=>$copy,'smtp_host'=>$config['smtp_host'],'smtp_port'=>$config['smtp_port']??465,'tls_peer_verified'=>true,'delivery_confirmed'=>false];}
  catch(\Throwable $e){$db->prepare('UPDATE mail_diagnostic_runs SET state=? WHERE request_id=?')->execute(['unconfirmed',$requestId]);throw new \RuntimeException('Envío no confirmado. Esta prueba no se reintentará automáticamente.');}
 }
}
