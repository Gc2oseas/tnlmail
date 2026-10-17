<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Mail/DomainTemplates.php';
use TNL\Mail\DomainTemplates as Templates;
use TNL\Mail\MailServiceProbe as Probe;
function yes(bool $value,string $label): void {if(!$value)throw new RuntimeException($label);echo "PASS: $label\n";}
foreach(['first-company.test','second-company.test','other-provider.test'] as $domain){
 $calls=[];$p=(new Templates(function($protocol,$host,$port)use(&$calls,$domain){$calls[]=[$protocol,$host,$port];return $host==='mail.'.$domain&&in_array($port,[993,465],true);}))->discover($domain);
 yes($p['imap_host']==='mail.'.$domain&&$p['smtp_host']==='mail.'.$domain,'same template adapts to '.$domain);
 yes(!str_contains(json_encode($calls),'password')&&!isset($p['password']),'template discovery receives no account password');
}
$p=(new Templates(fn($type,$host,$port)=>($host==='imap.company.test'&&$port===993)||($host==='smtp.company.test'&&$port===587)))->discover('company.test');
yes($p['smtp_port']===587&&$p['imap_host']==='imap.company.test','split IMAP and SMTP template supports mandatory STARTTLS 587');
yes((new Templates(fn()=>false))->discover('unsupported.test')===null,'unverified server templates are never suggested');
final class ProbeStream {
 public $context;public static array $writes=[];public static string $mode='smtp';private string $read='';
 public function stream_open($p,$m,$o,&$opened): bool {$this->read=self::$mode==='imap'?"* OK IMAP ready\r\n":"220 SMTP ready\r\n";return true;}
 public function stream_read($n): string {$part=substr($this->read,0,$n);$this->read=substr($this->read,strlen($part));return $part;}
 public function stream_write($bytes): int {self::$writes[]=$bytes;if(str_starts_with($bytes,'EHLO'))$this->read.=self::$mode==='noauth'?"250 hello\r\n":(self::$mode==='nostarttls'?"250 AUTH PLAIN LOGIN\r\n":"250-hello\r\n250-STARTTLS\r\n250 AUTH PLAIN LOGIN\r\n");elseif($bytes==="STARTTLS\r\n")$this->read.="220 Start TLS\r\n";return strlen($bytes);}
 public function stream_eof(): bool {return false;}
 public function stream_set_option($o,$a,$b): bool {return true;}
 public function stream_stat(): array {return [];}
}
stream_wrapper_register('probe-fake',ProbeStream::class);
$contexts=[];$endpoints=[];$connect=function($endpoint,$ctx)use(&$contexts,&$endpoints){$contexts[]=stream_context_get_options($ctx);$endpoints[]=$endpoint;return fopen('probe-fake://test','r+');};$dns=fn()=>[['ip'=>'1.1.1.1']];
$p=new Probe($dns,$connect,fn()=>true);
yes($p->check('smtp','mail.company.test',465,microtime(true)+5),'TLS SMTP probe checks greeting and advertised authentication without login');
yes($contexts[0]['ssl']['verify_peer']&&$contexts[0]['ssl']['verify_peer_name']&&!$contexts[0]['ssl']['allow_self_signed']&&$contexts[0]['ssl']['peer_name']==='mail.company.test','TLS context keeps peer and hostname verification enabled');
yes($endpoints[0]==='tls://1.1.1.1:465','socket target pins validated public IP');
ProbeStream::$writes=[];yes($p->check('smtp','smtp.company.test',587,microtime(true)+5),'STARTTLS SMTP probe succeeds with simulated valid TLS negotiation');
yes(in_array("STARTTLS\r\n",ProbeStream::$writes,true)&&count(array_filter(ProbeStream::$writes,fn($s)=>str_starts_with($s,'EHLO')))===2,'EHLO repeated after STARTTLS as required');
yes(!array_filter(ProbeStream::$writes,fn($s)=>preg_match('/^(AUTH|MAIL FROM|RCPT TO|DATA|LOGIN)/i',$s)),'protocol probe never authenticates or sends a message');
ProbeStream::$mode='nostarttls';yes(!$p->check('smtp','smtp.company.test',587,microtime(true)+5),'missing STARTTLS rejected before authentication');
ProbeStream::$mode='smtp';$badTls=new Probe($dns,$connect,fn()=>false);yes(!$badTls->check('smtp','smtp.company.test',587,microtime(true)+5),'failed TLS negotiation rejects template');
ProbeStream::$mode='noauth';yes(!$p->check('smtp','mail.company.test',465,microtime(true)+5),'SMTP without advertised AUTH is not suggested for submission');
ProbeStream::$mode='imap';yes($p->check('imap','imap.company.test',993,microtime(true)+5),'IMAP probe reads greeting without LOGIN');
$opens=0;$private=new Probe(fn()=>[['ip'=>'127.0.0.1']],function()use(&$opens){$opens++;return false;});yes(!$private->check('imap','imap.company.test',993,microtime(true)+5)&&$opens===0,'private DNS target blocked before any connection');
yes(!$p->check('smtp','smtp.company.test',25,microtime(true)+5),'template probe cannot use arbitrary ports');
$discovery=new \TNL\Mail\CorporateDiscovery(fn()=>null,fn()=>[],fn($protocol,$host,$port)=>$host==='mail.company.test'&&in_array($port,[993,465],true));
yes($discovery->discover('company.test')['source']==='Plantilla del dominio comprobada','generic checked template is used only after published discovery methods fail');

require_once dirname(__DIR__).'/app/Mail/ServerProfiles.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$probeCalls=0;$discovery=new \TNL\Mail\CorporateDiscovery(fn()=>null,fn()=>[],function()use(&$probeCalls){$probeCalls++;return true;});
$profile=\TNL\Mail\ServerProfiles::detect($db,'user@corporate.test',fn()=>[['target'=>'corporate.mail.protection.outlook.com']],$discovery);
yes($profile['auth']==='oauth'&&$probeCalls===0,'recognized MX provider takes precedence over generic template guessing');
