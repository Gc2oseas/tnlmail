<?php
declare(strict_types=1);
namespace TNL\Mail;
require_once __DIR__.'/InternalMail.php';
require_once dirname(__DIR__).'/Admin/Settings.php';
require_once __DIR__.'/SpamPolicy.php';
final class Router {
 public static function init(\PDO $db): void {
  $db->exec('CREATE TABLE IF NOT EXISTS mail_routes(id INTEGER PRIMARY KEY,actor TEXT NOT NULL,recipient TEXT NOT NULL,route TEXT NOT NULL,status TEXT NOT NULL,created_at TEXT NOT NULL)');
 }
 public static function plan(\PDO $db,string $recipient): array {
  $to=strtolower(trim($recipient));
  if(!str_contains($to,'@')){$user=Directory::resolve($db,$to);return ['route'=>'internal','recipient'=>$user['username']];}
  if(strlen($to)>254||!filter_var($to,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n\x00]/',$to))throw new \InvalidArgumentException('Destinatario inválido.');
  $domain=strtolower(\TNL\Admin\Settings::get($db,'company_domain'));
  if($domain!==''&&substr(strrchr($to,'@'),1)===$domain){
   \TNL\Admin\Users::init($db);
   $q=$db->prepare('SELECT id,username FROM users WHERE internal_address=? COLLATE NOCASE AND active=1');$q->execute([$to]);$matches=[];foreach($q->fetchAll(\PDO::FETCH_ASSOC) as $r)$matches[(int)$r['id']]=$r['username'];
   // Assigned company mailbox aliases must route to their active app user, too.
   $tables=$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('mailboxes','external_accounts')")->fetchAll(\PDO::FETCH_COLUMN);
   if(count($tables)===2){
    $q=$db->prepare("SELECT u.id,u.username FROM mailboxes m JOIN users u ON u.id=m.user_id JOIN external_accounts a ON a.owner='mailbox:'||m.id WHERE u.active=1 AND json_extract(a.config,'$.email')=? COLLATE NOCASE");$q->execute([$to]);foreach($q->fetchAll(\PDO::FETCH_ASSOC) as $r)$matches[(int)$r['id']]=$r['username'];
   }
   if(count($matches)!==1)throw new \InvalidArgumentException(count($matches)>1?'Dirección interna ambigua: administración debe revisar las asignaciones.':'La dirección empresarial no tiene destinatario interno activo. No se enviará por SMTP.');
   return ['route'=>'internal','recipient'=>array_values($matches)[0]];
  }
  return ['route'=>'smtp','recipient'=>$to];
 }
 private static function log(\PDO $db,string $actor,string $to,string $route,string $status): void {
  self::init($db);$db->prepare('INSERT INTO mail_routes(actor,recipient,route,status,created_at) VALUES(?,?,?,?,?)')->execute([$actor,substr($to,0,254),$route,$status,gmdate('c')]);
 }
 public static function send(\PDO $db,string $actor,int $employeeId,string $to,string $subject,string $body,bool $canExternal,callable $smtp,?int $replyTo=null,string $format='plain'): array {
  self::init($db);
  try{$plan=self::plan($db,$to);}catch(\InvalidArgumentException $e){self::log($db,$actor,$to,'blocked','rejected');throw $e;}
  if($plan['route']==='internal'){
   if($employeeId<1){self::log($db,$actor,$to,'internal','rejected');throw new \InvalidArgumentException('Para enviar al equipo utiliza una identidad de usuario, no la cuenta de administración.');}
   $db->beginTransaction();
   try{InternalMail::send($db,$employeeId,$plan['recipient'],$subject,$body,$replyTo,$format);self::log($db,$actor,$plan['recipient'],'internal','delivered');$db->commit();}
   catch(\Throwable $e){$db->rollBack();throw $e;}
   return ['route'=>'internal','message_id'=>'tnl-local:'.bin2hex(random_bytes(16))];
  }
  if(!$canExternal){self::log($db,$actor,$to,'smtp','denied');throw new \InvalidArgumentException('El administrador debe habilitar tu permiso de envío externo.');}
  // Reserve a visible attempt before contacting SMTP. An interrupted attempt is never reported delivered.
  self::log($db,$actor,$plan['recipient'],'smtp','attempting');$routeId=(int)$db->lastInsertId();
  try{$messageId=$smtp($plan['recipient']);}catch(\Throwable $e){$db->prepare("UPDATE mail_routes SET status='unconfirmed' WHERE id=?")->execute([$routeId]);throw $e;}
  $db->prepare("UPDATE mail_routes SET status='accepted' WHERE id=?")->execute([$routeId]);return ['route'=>'smtp','message_id'=>$messageId];
 }
 public static function filterIncoming(\PDO $db,string $principal,array $inbox,callable $quarantine): array {
  $policy=new SpamPolicy($db);$visible=[];$hidden=0;$moved=0;$attempts=0;
  foreach($inbox['messages'] as $message){
   if(!$policy->blocked($principal,(string)($message['from']??''))){$visible[]=$message;continue;}
   $hidden++;$status='filtered';
   if($attempts<3&&(int)($inbox['validity']??0)>0){$attempts++;try{$quarantine((int)$message['uid'],(int)$inbox['validity']);$moved++;$status='quarantined';}catch(\Throwable $e){}}
   self::log($db,$principal,$policy->address($message['from']),'spam',$status);
  }
  $inbox['messages']=$visible;$inbox['spam_hidden']=$hidden;$inbox['spam_moved']=$moved;return $inbox;
 }
 public static function recent(\PDO $db): array {self::init($db);return $db->query('SELECT actor,recipient,route,status,created_at FROM mail_routes ORDER BY id DESC LIMIT 100')->fetchAll(\PDO::FETCH_ASSOC);}
}
