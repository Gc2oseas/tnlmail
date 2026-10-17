<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Mail/ServerProfiles.php';
use TNL\Mail\CorporateDiscovery as D;
use TNL\Mail\ServerProfiles as P;
function yes(bool $v,string $label): void {if(!$v)throw new RuntimeException($label);echo "PASS: $label\n";}
$xml=<<<'XML'
<?xml version="1.0"?>
<clientConfig version="1.1"><emailProvider id="company.test"><domain>company.test</domain>
<incomingServer type="imap"><hostname>imap.hosting.test</hostname><port>993</port><socketType>SSL</socketType><authentication>password-cleartext</authentication><username>%EMAILLOCALPART%</username></incomingServer>
<outgoingServer type="smtp"><hostname>smtp.hosting.test</hostname><port>587</port><socketType>STARTTLS</socketType><authentication>password-cleartext</authentication><username>%EMAILADDRESS%</username></outgoingServer>
</emailProvider></clientConfig>
XML;
$p=D::parse($xml,'company.test');yes($p!==null&&$p['smtp_port']===587,'parse corporate Autoconfig with TLS IMAP and STARTTLS SMTP settings');
yes(D::parse($xml,'different.test')===null,'reject XML for another domain');
yes(D::parse(str_replace('<socketType>STARTTLS</socketType>','<socketType>plain</socketType>',$xml))===null,'reject unencrypted SMTP');
yes(D::parse(str_replace('<port>993</port>','<port>143</port>',$xml))===null,'unsupported IMAP mode falls back rather than downgrading TLS');
yes(D::parse('<!DOCTYPE x [<!ENTITY secret SYSTEM "file:///etc/passwd">]><clientConfig>&secret;</clientConfig>')===null,'reject DTD and external entity input');
yes(D::parse(str_repeat('x',65537))===null&&D::parse("<xml>\x00</xml>")===null,'reject oversized and null-byte XML');
yes(D::parse(str_replace('%EMAILLOCALPART%','%UNKNOWN%',$xml))===null,'unsupported username placeholders rejected');
yes(D::username('%EMAILLOCALPART%@%EMAILDOMAIN%','alice@company.test')==='alice@company.test','substitute supported username placeholders');
$urls=[];$discovery=new D(function($url)use(&$urls,$xml){$urls[]=$url;return $xml;},fn()=>[]);
$p=$discovery->discover('company.test');yes($p['source']==='Autoconfig del dominio'&&count($urls)===1,'prefer domain Autoconfig before other network sources');
yes($urls[0]==='https://autoconfig.company.test/mail/config-v1.1.xml','discovery request contains domain only and never account credentials');
$urls=[];$discovery=new D(function($url)use(&$urls,$xml){$urls[]=$url;return count($urls)===2?$xml:null;},fn()=>[]);
yes($discovery->discover('company.test')['source']==='Autoconfig del dominio'&&str_contains($urls[1],'/.well-known/autoconfig/'),'well-known Autoconfig fallback works');
$discovery=new D(fn($url)=>str_contains($url,'thunderbird.net')?$xml:null,fn()=>[]);
yes($discovery->discover('company.test')['source']==='Directorio de Thunderbird','ISPDB fallback parses corporate profile');
$dns=function($name,$type){
 if($name==='_imaps._tcp.company.test')return [['target'=>'imap.hosting.test.','port'=>993,'pri'=>10]];
 if($name==='_submission._tcp.company.test')return [['target'=>'smtp.hosting.test.','port'=>587,'pri'=>10]];
 return [];
};
$p=(new D(fn()=>null,$dns))->discover('company.test');
yes($p['source']==='DNS SRV'&&$p['smtp_port']===587&&$p['imap_host']==='imap.hosting.test','SRV selects supported IMAP and SMTP service ports');
yes((new D(fn()=>null,fn()=>[]))->discover('unknown.test')===null,'missing discovery returns manual fallback');
foreach(['127.0.0.1','10.0.0.1','169.254.169.254','100.64.0.1','198.18.0.1','224.0.0.1','::1','::ffff:127.0.0.1','fc00::1','fe80::1','2002:7f00:1::'] as $ip)yes(!D::publicIp($ip),'block non-public discovery address '.$ip);
yes(D::publicIp('1.1.1.1')&&D::publicIp('2606:4700:4700::1111'),'accept globally routable IPs');
$private=new D(null,fn()=>[['ip'=>'127.0.0.1']]);yes($private->fetch('autoconfig.company.test','/mail/config-v1.1.xml')===null,'private DNS response rejected before HTTPS connection');
$mixed=new D(null,fn()=>[['ip'=>'1.1.1.1'],['ip'=>'10.0.0.1']]);yes($mixed->fetch('autoconfig.company.test','/mail/config-v1.1.xml')===null,'mixed public and private DNS rejected');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);\TNL\Admin\Settings::set($db,'company_domain','company.test');
$d=new D(fn()=>$xml,fn()=>[]);$a=P::detect($db,'alice@company.test',fn()=>[],$d);$b=P::detect($db,'bob@company.test',fn()=>[],$d);
yes($a['imap_username']==='alice'&&$b['imap_username']==='bob'&&$b['smtp_username']==='bob@company.test','discovered profile keeps each employee username separate');
$db->prepare('INSERT INTO mail_discovery_cache(domain,profile,expires) VALUES(?,?,?)')->execute(['company.test',json_encode(D::parse($xml)),time()+3600]);
$c=P::configure($db,['email'=>'carol@company.test','setup_mode'=>'auto']);
yes($c['imap_username']==='carol'&&$c['smtp_username']==='carol@company.test','cached domain template never reuses another employee login');
\TNL\Admin\Settings::set($db,'company_mail_servers',json_encode(['domain'=>'company.test','imap_host'=>'override.company.test','smtp_host'=>'smtp.company.test','smtp_port'=>465]));
yes(P::detect($db,'alice@company.test',fn()=>throw new RuntimeException('Unexpected DNS'),new D(fn()=>throw new RuntimeException('Unexpected HTTPS')))['imap_host']==='override.company.test','administrator configuration takes precedence over discovery and cache');

$db2=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);\TNL\Admin\Settings::set($db2,'company_domain','company.test');
$db2->exec('CREATE TABLE external_accounts(owner TEXT PRIMARY KEY,config TEXT,secret TEXT)');
$db2->prepare('INSERT INTO external_accounts VALUES(?,?,?)')->execute(['admin:1',json_encode(['email'=>'admin@company.test','imap_host'=>'imap.hosting.test','smtp_host'=>'smtp.hosting.test','smtp_port'=>465,'smtp_username'=>'admin-only-login']),'never-read-secret']);
$reused=P::detect($db2,'employee@company.test',fn()=>throw new RuntimeException('Unexpected DNS'));
yes($reused['source']==='Configuración corporativa existente'&&!isset($reused['password'])&&!isset($reused['smtp_username']),'reuse existing corporate admin server metadata without username or password sharing');
$db2->prepare('INSERT INTO external_accounts VALUES(?,?,?)')->execute(['admin:2',json_encode(['email'=>'other@company.test','imap_host'=>'different.hosting.test','smtp_host'=>'different.hosting.test']),'another-secret']);
yes(P::detect($db2,'employee@company.test',fn()=>[])===null,'conflicting saved corporate servers are not silently guessed');
