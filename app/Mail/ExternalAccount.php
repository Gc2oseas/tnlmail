<?php
declare(strict_types=1);
namespace TNL\Mail;

final class ExternalAccount {
 public function __construct(private \PDO $db,private string $storage) {
  $db->exec('CREATE TABLE IF NOT EXISTS external_accounts(owner TEXT PRIMARY KEY,config TEXT NOT NULL,secret TEXT NOT NULL)');
  $db->exec('CREATE TABLE IF NOT EXISTS external_sent(id INTEGER PRIMARY KEY,owner TEXT NOT NULL,recipient TEXT NOT NULL,subject TEXT NOT NULL,body TEXT NOT NULL,message_id TEXT NOT NULL,created_at TEXT NOT NULL)');
  $cols=$db->query('PRAGMA table_info(external_sent)')->fetchAll(\PDO::FETCH_COLUMN,1);foreach(['in_reply_to'=>"TEXT NOT NULL DEFAULT ''",'refs'=>"TEXT NOT NULL DEFAULT ''",'format'=>"TEXT NOT NULL DEFAULT 'plain'",'cc'=>"TEXT NOT NULL DEFAULT ''"] as $name=>$definition)if(!in_array($name,$cols,true))$db->exec('ALTER TABLE external_sent ADD COLUMN '.$name.' '.$definition);
 }
 private function key(): string {
  $file=$this->storage.'/external-mail.key';if(is_link($file))throw new \RuntimeException('Almacenamiento inválido.');
  $lock=fopen($this->storage.'/external-mail-key.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new \RuntimeException('No se pudo abrir el almacén de credenciales.');
  try {if(is_file($file)){$key=file_get_contents($file);if(strlen((string)$key)!==32)throw new \RuntimeException('Clave de credenciales inválida. Restaura la copia de seguridad.');return $key;}
   if((int)$this->db->query('SELECT count(*) FROM external_accounts')->fetchColumn()>0)throw new \RuntimeException('Falta storage/external-mail.key. Restaura la clave original junto con SQLite.');
   $key=random_bytes(32);$tmp=$file.'.'.bin2hex(random_bytes(8));$f=fopen($tmp,'xb');if(!$f)throw new \RuntimeException('No se pudo crear clave.');try{chmod($tmp,0600);if(fwrite($f,$key)!==32)throw new \RuntimeException('No se pudo guardar clave.');}finally{fclose($f);}if(!rename($tmp,$file))throw new \RuntimeException('No se pudo guardar clave.');return $key;
  }finally{flock($lock,LOCK_UN);fclose($lock);}
 }
 public static function config(array $input): array {
  $email=trim((string)($input['email']??''));if(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n\x00]/',$email))throw new \InvalidArgumentException('Dirección de correo inválida.');
  $out=['email'=>$email];foreach(['imap_host','smtp_host'] as $key){$host=strtolower(trim((string)($input[$key]??'')));if(strlen($host)>253||!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,63}$/D',$host))throw new \InvalidArgumentException('Servidor inválido: usa un nombre DNS público.');$out[$key]=$host;}
  foreach(['imap_username','smtp_username'] as $field){$v=trim((string)($input[$field]??$email));if($v==='')$v=$email;if(strlen($v)>254||preg_match('/[\r\n\x00]/',$v))throw new \InvalidArgumentException('Usuario de servidor inválido.');$out[$field]=$v;}
  $smtpPort=(int)($input['smtp_port']??465);if(!in_array($smtpPort,[465,587],true))throw new \InvalidArgumentException('SMTP admite SSL/TLS 465 o STARTTLS 587.');
  return $out+['imap_port'=>993,'smtp_port'=>$smtpPort];
 }
 public static function publicHost(string $host): void {
  if(!preg_match('/^[a-zA-Z0-9.-]+$/D',$host))throw new \InvalidArgumentException('Servidor inválido.');
  $addresses=dns_get_record($host,DNS_A|DNS_AAAA);$ips=[];foreach($addresses?:[] as $entry){if(isset($entry['ip']))$ips[]=$entry['ip'];if(isset($entry['ipv6']))$ips[]=$entry['ipv6'];}
  if(!$ips)throw new \RuntimeException('El servidor de correo no resuelve por DNS.');foreach($ips as $ip)if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))throw new \RuntimeException('El servidor debe resolver a una dirección pública.');
 }
 public function load(string $owner): ?array {
  $s=$this->db->prepare('SELECT config,secret FROM external_accounts WHERE owner=?');$s->execute([$owner]);$r=$s->fetch(\PDO::FETCH_ASSOC);if(!$r)return null;
  $packed=base64_decode($r['secret'],true);if($packed===false||strlen($packed)<29)throw new \RuntimeException('Credencial cifrada inválida.');
  $password=openssl_decrypt(substr($packed,28),'aes-256-gcm',$this->key(),OPENSSL_RAW_DATA,substr($packed,0,12),substr($packed,12,16),$owner);
  if($password===false)throw new \RuntimeException('No se pudo descifrar la credencial. Conserva storage/external-mail.key junto con SQLite.');
  $config=json_decode($r['config'],true,16,JSON_THROW_ON_ERROR);
  if(($config['secret_format']??1)===2){$secrets=json_decode($password,true,8,JSON_THROW_ON_ERROR);return $config+$secrets;}
  return $config+['password'=>$password,'smtp_password'=>$password];
 }
 public function save(string $owner,array $input,string $password): void {
  if(!preg_match('/^(admin|employee|mailbox|personal):[1-9][0-9]*$/D',$owner))throw new \InvalidArgumentException('Propietario inválido.');$config=self::config($input);
  if($password===''){$old=$this->load($owner);if(!$old||$old['email']!==$config['email'])throw new \InvalidArgumentException('Introduce la contraseña de esta cuenta.');$password=$old['password'];}
  if(strlen($password)>512||preg_match('/[\r\n\x00]/',$password))throw new \InvalidArgumentException('Contraseña inválida.');
  $old=$this->load($owner);$smtpPassword=(string)($input['smtp_password']??'');
  if($smtpPassword==='')$smtpPassword=($old&&$old['email']===$config['email'])?($old['smtp_password']??$password):$password;
  if(strlen($smtpPassword)>512||preg_match('/[\r\n\x00]/',$smtpPassword))throw new \InvalidArgumentException('Contraseña SMTP inválida.');
  $password=json_encode(['password'=>$password,'smtp_password'=>$smtpPassword],JSON_THROW_ON_ERROR);$config['secret_format']=2;
  $nonce=random_bytes(12);$tag='';$cipher=openssl_encrypt($password,'aes-256-gcm',$this->key(),OPENSSL_RAW_DATA,$nonce,$tag,$owner);
  if($cipher===false)throw new \RuntimeException('No se pudo cifrar la credencial.');
  $this->db->prepare('INSERT INTO external_accounts(owner,config,secret) VALUES(?,?,?) ON CONFLICT(owner) DO UPDATE SET config=excluded.config,secret=excluded.secret')->execute([$owner,json_encode($config,JSON_THROW_ON_ERROR),base64_encode($nonce.$tag.$cipher)]);
 }
 public function sentView(string $owner,int $id,string $email): ?array {$m=$this->sentMessage($owner,$id);if(!$m)return null;require_once __DIR__.'/MessageBody.php';return ['headers'=>['subject'=>$m['subject'],'from'=>$email,'to'=>$m['recipient'],'cc'=>$m['cc'],'date'=>$m['created_at'],'message-id'=>$m['message_id'],'in-reply-to'=>$m['in_reply_to'],'references'=>$m['refs']],'body'=>MessageBody::plain($m['body'],$m['format']),'html'=>MessageBody::html($m['body'],$m['format']),'inline_images'=>[]];}
 public function related(string $owner,array $headers): array {require_once __DIR__.'/ReplyComposer.php';$ids=ReplyComposer::thread($headers);preg_match_all('/<[^<>\s]+@[^<>\s]+>/',$ids['references'].' '.($headers['message-id']??''),$matches);$ids=array_unique($matches[0]);if(!$ids)return [];$q=$this->db->prepare('SELECT * FROM external_sent WHERE owner=? ORDER BY id DESC LIMIT 300');$q->execute([$owner]);return array_values(array_filter($q->fetchAll(\PDO::FETCH_ASSOC),function($m)use($ids){preg_match_all('/<[^<>\s]+@[^<>\s]+>/',$m['message_id'].' '.$m['in_reply_to'].' '.$m['refs'],$x);return (bool)array_intersect($ids,$x[0]);}));}
 public function sent(string $owner): array {$s=$this->db->prepare('SELECT id,recipient,subject,created_at FROM external_sent WHERE owner=? ORDER BY id DESC LIMIT 30');$s->execute([$owner]);return $s->fetchAll(\PDO::FETCH_ASSOC);}
 public function sentMessage(string $owner,int $id): ?array {$q=$this->db->prepare('SELECT * FROM external_sent WHERE id=? AND owner=?');$q->execute([$id,$owner]);return $q->fetch(\PDO::FETCH_ASSOC)?:null;}
 public function recordSent(string $owner,string $to,string $subject,string $body,string $messageId,array $thread=[],string $format='plain',string $cc=''): void {if(!in_array($format,['plain','html','markdown'],true))throw new \InvalidArgumentException('Formato inválido.');$this->db->prepare('INSERT INTO external_sent(owner,recipient,subject,body,message_id,created_at,in_reply_to,refs,format,cc) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$owner,$to,$subject,$body,$messageId,gmdate('c'),$thread['in_reply_to']??'',$thread['references']??'',$format,$cc]);}
}
