<?php
declare(strict_types=1);
namespace TNL\Mail;
final class CorporateDiscovery {
 public function __construct(private ?\Closure $fetcher=null,private ?\Closure $dns=null,private ?\Closure $probe=null) {}
 public static function host(string $host): bool {return strlen($host)<=253&&(bool)preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,63}$/D',$host);}
 public static function publicIp(string $ip): bool {
  if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))return false;
  if(!str_contains($ip,':')){
   $n=ip2long($ip);foreach([['100.64.0.0',10],['192.0.0.0',24],['192.0.2.0',24],['198.18.0.0',15],['198.51.100.0',24],['203.0.113.0',24],['224.0.0.0',4]] as [$base,$bits]){if(($n>>(32-$bits))===(ip2long($base)>>(32-$bits)))return false;}
   return true;
  }
  $bytes=inet_pton($ip);if($bytes===false||(ord($bytes[0])&0xe0)!==0x20)return false;
  if(substr($bytes,0,2)==="\x20\x02")return false;
  if(substr($bytes,0,2)==="\x20\x01"&&ord($bytes[2])<2)return false;
  return true;
 }
 private function records(string $name,int $type): array {return ($this->dns?($this->dns)($name,$type):@dns_get_record($name,$type))?:[];}
 public function fetch(string $host,string $path): ?string {
  if(!self::host($host)||!str_starts_with($path,'/')||preg_match('/[\r\n\x00]/',$path))return null;
  if($this->fetcher)return ($this->fetcher)('https://'.$host.$path);
  $ips=[];foreach($this->records($host,DNS_A|DNS_AAAA) as $r){$ip=$r['ip']??$r['ipv6']??null;if($ip!==null){if(!self::publicIp($ip))return null;$ips[]=$ip;}}
  if(!$ips)return null;
  // Connect to the already validated IP, preventing a second DNS resolution.
  $ip=$ips[0];$authority=str_contains($ip,':')?'['.$ip.']':$ip;
  $ctx=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false,'peer_name'=>$host,'SNI_enabled'=>true],
   'http'=>['method'=>'GET','header'=>"Host: ".$host."\r\nAccept: application/xml, text/xml\r\nUser-Agent: TNL-Mail/0.6.1\r\nConnection: close\r\n",'timeout'=>2.5,'follow_location'=>0,'max_redirects'=>0,'ignore_errors'=>true,'protocol_version'=>1.1]]);
  $stream=@fopen('https://'.$authority.$path,'rb',false,$ctx);if(!$stream)return null;
  try{$meta=stream_get_meta_data($stream);$headers=$meta['wrapper_data']??[];
   if(!isset($headers[0])||!preg_match('~^HTTP/\S+ 200(?: |$)~',$headers[0]))return null;
   foreach($headers as $header)if(preg_match('/^Content-Length:\s*(\d+)/i',$header,$m)&&(int)$m[1]>65536)return null;
   $body=stream_get_contents($stream,65537);if($body===false||strlen($body)>65536||!feof($stream))return null;return $body;
  }finally{fclose($stream);}
 }
 public static function parse(string $xml,?string $domain=null): ?array {
  if(strlen($xml)>65536||str_contains($xml,"\x00")||preg_match('/<!\s*(?:DOCTYPE|ENTITY)/i',$xml)||!class_exists('DOMDocument'))return null;
  $doc=new \DOMDocument();$doc->resolveExternals=false;$doc->substituteEntities=false;$previous=libxml_use_internal_errors(true);
  try{if(!$doc->loadXML($xml,LIBXML_NONET|LIBXML_NOBLANKS)||$doc->doctype)return null;}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
  $xpath=new \DOMXPath($doc);$get=static function(\DOMElement $node,string $tag): string {
   foreach($node->childNodes as $child)if($child instanceof \DOMElement&&$child->localName===$tag)return trim($child->textContent);return '';
  };
  // Keep incoming and outgoing settings within a single provider.
  foreach($xpath->query('//*[local-name()="emailProvider"]') as $provider){
   if($domain!==null){$matches=false;foreach($provider->childNodes as $node)if($node instanceof \DOMElement&&$node->localName==='domain'&&strtolower(trim($node->textContent))===$domain)$matches=true;if(!$matches)continue;}
   $incoming=null;$outgoing=null;
   foreach($provider->childNodes as $node){if(!$node instanceof \DOMElement)continue;
    $type=$node->getAttribute('type');$host=strtolower(rtrim($get($node,'hostname'),'.'));$port=(int)$get($node,'port');$socket=strtoupper($get($node,'socketType'));
    $auth=strtolower($get($node,'authentication'));$username=$get($node,'username')?:'%EMAILADDRESS%';
    if(!self::host($host)||!in_array($auth,['password-cleartext','plain','password-encrypted'],true)||!self::username($username,'test@example.com'))continue;
    if($node->localName==='incomingServer'&&$type==='imap'&&$port===993&&$socket==='SSL')$incoming??=['imap_host'=>$host,'imap_username_template'=>$username];
    if($node->localName==='outgoingServer'&&$type==='smtp'&&(($port===465&&$socket==='SSL')||($port===587&&$socket==='STARTTLS')))$outgoing??=['smtp_host'=>$host,'smtp_port'=>$port,'smtp_username_template'=>$username];
   }
   if($incoming&&$outgoing)return $incoming+$outgoing+['auth'=>'password'];
  }return null;
 }
 public static function username(string $template,string $email): ?string {
  if(strlen($template)>254||preg_match('/[\r\n\x00]/',$template))return null;
  [$local,$domain]=explode('@',$email,2);
  $username=strtr($template,['%EMAILADDRESS%'=>$email,'%EMAILLOCALPART%'=>$local,'%EMAILDOMAIN%'=>$domain]);
  return $username!==''&&!str_contains($username,'%')&&strlen($username)<=254?$username:null;
 }
 private function srv(string $name,int $port): ?string {
  $records=$this->records($name,DNS_SRV);
  usort($records,fn($a,$b)=>(int)($a['pri']??65535)<=>(int)($b['pri']??65535));
  foreach($records as $r){$host=strtolower(rtrim((string)($r['target']??''),'.'));if((int)($r['port']??0)===$port&&self::host($host))return $host;}return null;
 }
 public function discover(string $domain,bool $templates=true): ?array {
  if(!self::host($domain))return null;$deadline=microtime(true)+8;
  $locations=[['autoconfig.'.$domain,'/mail/config-v1.1.xml','Autoconfig del dominio'],[$domain,'/.well-known/autoconfig/mail/config-v1.1.xml','Autoconfig del dominio'],['autoconfig.thunderbird.net','/v1.1/'.$domain,'Directorio de Thunderbird']];
  foreach($locations as [$host,$path,$source]){
   if(microtime(true)>$deadline)break;
   try{$xml=$this->fetch($host,$path);$profile=$xml!==null?self::parse($xml,$domain):null;if($profile)return $profile+['provider'=>'Correo corporativo','source'=>$source,'hint'=>'Servidores obtenidos de '.$source.'.'];}catch(\Throwable $e){}
  }
  $imap=$this->srv('_imaps._tcp.'.$domain,993);
  $smtp=$imap?$this->srv('_submissions._tcp.'.$domain,465):null;$port=465;
  if($imap&&!$smtp){$smtp=$this->srv('_submission._tcp.'.$domain,587);$port=587;}
  if($imap&&$smtp)return ['provider'=>'Correo corporativo','source'=>'DNS SRV','imap_host'=>$imap,'smtp_host'=>$smtp,'smtp_port'=>$port,'auth'=>'password','hint'=>'Servidores anunciados por el DNS del dominio.'];
  return $templates?$this->templates($domain):null;
 }
 public function templates(string $domain): ?array {
  if(($this->fetcher||$this->dns)&&!$this->probe)return null;
  require_once __DIR__.'/DomainTemplates.php';
  return (new DomainTemplates($this->probe))->discover($domain);
 }
}
