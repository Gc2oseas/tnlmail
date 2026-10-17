<?php
declare(strict_types=1);
namespace TNL\Mail;
require_once __DIR__.'/CorporateDiscovery.php';
final class DomainTemplates {
 public function __construct(private ?\Closure $checker=null) {}
 public function discover(string $domain): ?array {
  if(!CorporateDiscovery::host($domain))return null;
  $deadline=microtime(true)+6;$tested=[];
  $probe=function(string $protocol,string $host,int $port)use(&$tested,$deadline): bool {
   $key=$protocol.':'.$host.':'.$port;if(isset($tested[$key]))return $tested[$key];
   if(microtime(true)>=$deadline)return false;
   return $tested[$key]=$this->checker?(bool)($this->checker)($protocol,$host,$port):(new MailServiceProbe())->check($protocol,$host,$port,$deadline);
  };
  foreach([['mail.'.$domain,'mail.'.$domain],['imap.'.$domain,'smtp.'.$domain],[$domain,$domain]] as [$imap,$smtp]){
   if(!$probe('imap',$imap,993))continue;
   foreach([465,587] as $port)if($probe('smtp',$smtp,$port))return [
    'provider'=>'Correo corporativo','source'=>'Plantilla del dominio comprobada',
    'imap_host'=>$imap,'smtp_host'=>$smtp,'smtp_port'=>$port,'auth'=>'password',
    'hint'=>'Servidor y certificado comprobados sin contraseña. El acceso a tu buzón se comprobará al abrirlo.'
   ];
  }
  return null;
 }
}
final class MailServiceProbe {
 public function __construct(private ?\Closure $dns=null,private ?\Closure $connector=null,private ?\Closure $tls=null) {}
 private function reply($stream,int $code,float $deadline): ?array {
  $lines=[];for($n=0;$n<30&&microtime(true)<$deadline;$n++){
   $line=fgets($stream,2049);if($line===false||!str_ends_with($line,"\r\n")||!preg_match('/^'.$code.'([ -])/',$line,$m))return null;
   $lines[]=$line;if($m[1]===' ')return $lines;
  }return null;
 }
 public function check(string $protocol,string $host,int $port,float $deadline): bool {
  if(!CorporateDiscovery::host($host)||!in_array($protocol.':'.$port,['imap:993','smtp:465','smtp:587'],true)||microtime(true)>=$deadline)return false;
  $records=$this->dns?($this->dns)($host):@dns_get_record($host,DNS_A|DNS_AAAA);
  $ips=[];foreach($records?:[] as $r){$ip=$r['ip']??$r['ipv6']??null;if($ip!==null){if(!CorporateDiscovery::publicIp($ip))return false;$ips[]=$ip;}}
  if(!$ips)return false;$ip=$ips[0];$endpoint=(str_contains($ip,':')?'['.$ip.']':$ip).':'.$port;
  $direct=$port!==587;$ctx=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false,'peer_name'=>$host,'SNI_enabled'=>true]]);
  $stream=$this->connector?($this->connector)(($direct?'tls':'tcp').'://'.$endpoint,$ctx):@stream_socket_client(($direct?'tls':'tcp').'://'.$endpoint,$errno,$error,max(0.1,min(1.5,$deadline-microtime(true))),STREAM_CLIENT_CONNECT,$ctx);
  if(!is_resource($stream))return false;
  try{
   stream_set_timeout($stream,1,500000);
   if($protocol==='imap'){$greeting=fgets($stream,2049);return $greeting!==false&&str_ends_with($greeting,"\r\n")&&(bool)preg_match('/^\* OK\b/',$greeting);}
   if(!$this->reply($stream,220,$deadline))return false;
   if(fwrite($stream,"EHLO tnlmail.invalid\r\n")!==22)return false;
   $hello=$this->reply($stream,250,$deadline);if(!$hello)return false;
   if(!$direct){
    if(!preg_grep('/^250[ -]STARTTLS\s*$/i',$hello))return false;
    if(fwrite($stream,"STARTTLS\r\n")!==10||!$this->reply($stream,220,$deadline))return false;
    $secure=$this->tls?($this->tls)($stream):@stream_socket_enable_crypto($stream,true,STREAM_CRYPTO_METHOD_TLS_CLIENT);
    if($secure!==true)return false;
    if(fwrite($stream,"EHLO tnlmail.invalid\r\n")!==22)return false;
    $hello=$this->reply($stream,250,$deadline);if(!$hello)return false;
   }
   return (bool)preg_grep('/^250[ -]AUTH(?:[ =]|$)/i',$hello);
  }catch(\Throwable $e){return false;}finally{fclose($stream);}
 }
}
