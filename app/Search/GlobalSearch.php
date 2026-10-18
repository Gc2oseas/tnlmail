<?php
declare(strict_types=1);
namespace TNL\Search;
require_once dirname(__DIR__).'/Contacts/Book.php';
require_once dirname(__DIR__).'/Mail/InternalMail.php';
require_once dirname(__DIR__).'/Mail/Reception.php';
require_once dirname(__DIR__).'/Mail/AccountAccess.php';
require_once dirname(__DIR__).'/Mail/MessageBody.php';
final class GlobalSearch {
 public static function owners(\PDO $db,array $who): array {
  $store=new \TNL\Mail\ExternalAccount($db,'');$owners=[$who['principal']];foreach((new \TNL\Mail\PersonalAccounts($db,$store))->list($who['principal']) as $c)$owners[]='personal:'.$c['id'];if($who['role']==='employee')foreach((new \TNL\Mail\Mailboxes($db))->listFor($who['id']) as $c)$owners[]='mailbox:'.$c['id'];$out=[];$q=$db->prepare('SELECT config FROM external_accounts WHERE owner=?');foreach($owners as $owner){$q->execute([$owner]);$json=$q->fetchColumn();if($json){$config=json_decode($json,true);$out[$owner]=['owner'=>$owner,'email'=>$config['email']];}}return $out;
 }
 public static function terms(\PDO $db,array $who,string $query): array {
  $query=trim($query);if(strlen($query)<2||strlen($query)>160||preg_match('/[\x00-\x1f\x7f]/',$query))throw new \InvalidArgumentException('Escribe entre 2 y 160 caracteres.');$terms=[$query];foreach(\TNL\Contacts\Book::search($db,$who,$query,1,20)['rows'] as $c)if($c['can_read']&&$c['email'])$terms[]=$c['email'];return array_slice(array_values(array_unique($terms)),0,11);
 }
 private static function where(array $columns,array $terms,array &$params): string {
  $parts=[];foreach($terms as $term){$term='%'.str_replace(['!','%','_'],['!!','!%','!_'],$term).'%';foreach($columns as $column){$parts[]=$column." LIKE ? ESCAPE '!'";$params[]=$term;}}return '('.implode(' OR ',$parts).')';
 }
 private static function matches(string $text,array $terms): bool {foreach($terms as $term)if(stripos($text,$term)!==false)return true;return false;}
 public static function local(\PDO $db,array $who,string $query): array {
  $terms=self::terms($db,$who,$query);$result=['contacts'=>\TNL\Contacts\Book::search($db,$who,$query,1,20)['rows'],'internal'=>[],'reception'=>[],'sent'=>[],'terms'=>$terms];$owners=self::owners($db,$who);\TNL\Mail\InternalMail::init($db);\TNL\Mail\Reception::init($db);
  if($who['role']==='employee'){$params=[$who['id'],$who['id']];$condition=self::where(['m.subject','m.body','m.recipients_to','m.recipients_cc','u.display_name','u.internal_address','v.display_name','v.internal_address'],$terms,$params);$q=$db->prepare("SELECT m.*,u.display_name sender_name,v.display_name recipient_name FROM internal_messages m JOIN users u ON u.id=m.sender_id JOIN users v ON v.id=m.recipient_id WHERE ((m.sender_id=? AND m.deleted_by_sender=0) OR (m.recipient_id=? AND m.deleted_by_recipient=0)) AND $condition ORDER BY m.id DESC LIMIT 40");$q->execute($params);foreach($q->fetchAll(\PDO::FETCH_ASSOC) as $m)$result['internal'][]=['title'=>$m['subject'],'sender'=>$m['sender_name'].' → '.$m['recipient_name'],'snippet'=>self::snippet($m['body'],$m['format'],$terms),'href'=>'mail.php?id='.(int)$m['id']];}
  $params=$who['role']==='admin'?[]:[$who['id']];$condition=self::where(['subject','body','sender','headers'],$terms,$params);$q=$db->prepare('SELECT * FROM reception_messages WHERE '.($who['role']==='admin'?'1=1':'assigned_user=?').' AND '.$condition.' ORDER BY id DESC LIMIT 40');$q->execute($params);foreach($q->fetchAll(\PDO::FETCH_ASSOC) as $m)$result['reception'][]=['title'=>$m['subject'],'sender'=>$m['sender'],'snippet'=>self::snippet($m['html']?:$m['body'],$m['html']?'html':'plain',$terms),'href'=>'reception-mail.php?id='.(int)$m['id']];
  if($owners){$params=array_keys($owners);$slots=implode(',',array_fill(0,count($owners),'?'));$condition=self::where(['s.subject','s.body','s.recipient','s.cc',"json_extract(a.config,'$.email')"],$terms,$params);$q=$db->prepare("SELECT s.*,a.config FROM external_sent s JOIN external_accounts a ON a.owner=s.owner WHERE s.owner IN ($slots) AND $condition ORDER BY s.id DESC LIMIT 40");$q->execute($params);foreach($q->fetchAll(\PDO::FETCH_ASSOC) as $m)$result['sent'][]=['title'=>$m['subject'],'sender'=>json_decode($m['config'],true)['email'].' → '.$m['recipient'],'snippet'=>self::snippet($m['body'],$m['format'],$terms),'href'=>self::externalUrl($m['owner'],$who).'&view=sentread&id='.(int)$m['id']];}
  if($who['role']==='employee'){$params=[$who['id']];$condition=self::where(['s.subject','s.body','s.recipient','s.cc'],$terms,$params);$q=$db->prepare("SELECT DISTINCT s.* FROM external_sent s JOIN reception_links l ON l.mailbox=s.owner AND l.message_id=s.message_id JOIN reception_routes r ON r.token=l.token AND r.mailbox=l.mailbox WHERE r.user_id=? AND $condition ORDER BY s.id DESC LIMIT 30");$q->execute($params);foreach($q->fetchAll(\PDO::FETCH_ASSOC) as $m)$result['sent'][]=['title'=>$m['subject'],'sender'=>'Recepción pública → '.$m['recipient'],'snippet'=>self::snippet($m['body'],$m['format'],$terms),'href'=>'search.php?sent='.(int)$m['id']];}
  return $result;
 }
 public static function sharedSent(\PDO $db,array $who,int $id): ?array {
  if($who['role']!=='employee')return null;\TNL\Mail\Reception::init($db);$store=new \TNL\Mail\ExternalAccount($db,'');$q=$db->prepare('SELECT s.id,s.owner,a.config FROM external_sent s JOIN external_accounts a ON a.owner=s.owner JOIN reception_links l ON l.mailbox=s.owner AND l.message_id=s.message_id JOIN reception_routes r ON r.token=l.token AND r.mailbox=l.mailbox WHERE s.id=? AND r.user_id=? LIMIT 1');$q->execute([$id,$who['id']]);$m=$q->fetch(\PDO::FETCH_ASSOC);return $m?$store->sentView($m['owner'],(int)$m['id'],json_decode($m['config'],true)['email']):null;
 }
 public static function externalUrl(string $owner,array $who): string {
  $url='external.php?as='.$who['role'];if(str_starts_with($owner,'personal:'))return $url.'&personal='.(int)substr($owner,9);if(str_starts_with($owner,'mailbox:'))return $url.'&box='.(int)substr($owner,8);return $url.'&box=0';
 }
 public static function remote(\PDO $db,array $who,string $query,string $storage,string $selected='',?\Closure $factory=null): array {
  $terms=self::terms($db,$who,$query);$owners=self::owners($db,$who);if($selected!==''&&!isset($owners[$selected]))throw new \InvalidArgumentException('Buzón de búsqueda no disponible.');if($selected!=='')$owners=[$selected=>$owners[$selected]];$result=['rows'=>[],'errors'=>[],'partial'=>false,'searched'=>0,'accounts'=>count($owners)];$store=new \TNL\Mail\ExternalAccount($db,$storage);$deadline=microtime(true)+18;
  foreach($owners as $owner=>$meta){if(microtime(true)>$deadline){$result['partial']=true;break;}$imap=null;try{$account=$store->load($owner);if(!$account)continue;$imap=$factory?$factory($account):new \TNL\Mail\ImapClient($account);$imap->connect();$rows=$imap->search(array_slice($terms,0,6),'INBOX',10);$result['searched']++;foreach($rows as $m)$result['rows'][]=['title'=>$m['headers']['subject']??'(sin asunto)','sender'=>($m['headers']['from']??'').' · '.$meta['email'],'snippet'=>self::snippet(!empty($m['html'])?$m['html']:($m['body']??''),!empty($m['html'])?'html':'plain',$terms),'href'=>self::externalUrl($owner,$who).'&view=inbox&uid='.(int)$m['uid']];}catch(\Throwable $e){$result['errors'][]='No se pudo buscar en '.$meta['email'].'.';}finally{if($imap)$imap->close();}}
  return $result;
 }
 public static function snippet(string $body,string $format,array $terms): string {
  $plain=preg_replace('/\s+/u',' ',\TNL\Mail\MessageBody::plain($body,$format))??'';$position=0;foreach($terms as $term){$found=stripos($plain,$term);if($found!==false){$position=max(0,$found-50);break;}}$snippet=substr($plain,$position,220);if(function_exists('iconv'))$snippet=iconv('UTF-8','UTF-8//IGNORE',$snippet)?:'';return ($position?'…':'').$snippet.(strlen($plain)>$position+220?'…':'');
 }
}
