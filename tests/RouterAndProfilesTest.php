<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Mail/Router.php';
require dirname(__DIR__).'/app/Mail/Mailboxes.php';
require dirname(__DIR__).'/app/Mail/ServerProfiles.php';
function ok(bool $v,string $s): void {if(!$v)throw new RuntimeException($s);echo "PASS: $s\n";}
function fails(callable $f,string $s): void {try{$f();}catch(InvalidArgumentException $e){echo "PASS: $s\n";return;}throw new RuntimeException($s);}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
\TNL\Admin\Users::init($db);
foreach([['sender','Sender',''],['josegaldamez','José Galdámez','Gerente general'],['josegaldamez2','José Galdámez','Ventas']] as [$username,$name,$title])\TNL\Admin\Users::create($db,['username'=>$username,'display_name'=>$name,'job_title'=>$title,'department'=>'Central','domain'=>'example.test','password'=>'test-password-only']);
\TNL\Admin\Settings::set($db,'company_domain','example.test');
$results=\TNL\Mail\Directory::search($db,'josegal');
ok(count($results)===2&&str_contains($results[0]['label'],'Gerente general')&&str_contains($results[1]['label'],'Ventas'),'homonyms distinguished by username and job title');
ok(\TNL\Mail\Directory::search($db,'%')===[]&&count(\TNL\Mail\Directory::search($db,'jo%'))===0,'directory wildcards are literal and short queries do not enumerate');
\TNL\Mail\InternalMail::send($db,1,'josegaldamez','Test','Message');
ok(count(\TNL\Mail\InternalMail::list($db,2,'inbox'))===1,'internal mail accepts username without domain');
$smtpCalls=0;$smtp=function($to)use(&$smtpCalls){$smtpCalls++;return '<fake@example.test>';};
$r=\TNL\Mail\Router::send($db,'employee:1',1,'josegaldamez@example.test','Local','Do not send SMTP',false,$smtp);
ok($r['route']==='internal'&&$smtpCalls===0,'company recipient delivered locally without external permission or SMTP call');
fails(fn()=>\TNL\Mail\Router::send($db,'employee:1',1,'missing@example.test','Bad','Body',true,$smtp),'unknown company address blocked without SMTP fallback');
ok($smtpCalls===0,'blocked internal route never invokes SMTP');
fails(fn()=>\TNL\Mail\Router::send($db,'employee:1',1,'outside@elsewhere.test','External','Body',false,$smtp),'external permission remains enforced');
$r=\TNL\Mail\Router::send($db,'employee:1',1,'outside@elsewhere.test','External','Body',true,$smtp);
ok($r['route']==='smtp'&&$smtpCalls===1,'real external destination uses exactly one SMTP call');
$routes=\TNL\Mail\Router::recent($db);
ok(count($routes)===4&&$routes[0]['status']==='accepted','supervisor records internal, rejected, denied and SMTP-accepted decisions');
$dir=sys_get_temp_dir().'/tnl-router-'.bin2hex(random_bytes(5));mkdir($dir);
try{
 $store=new \TNL\Mail\ExternalAccount($db,$dir);$boxes=new \TNL\Mail\Mailboxes($db);
 $config=['email'=>'sales@example.test','imap_host'=>'mail.example.test','smtp_host'=>'mail.example.test'];
 $boxes->assign(0,2,'Sales',$config,'synthetic-password',$store);
 ok(\TNL\Mail\Router::plan($db,'sales@example.test')['recipient']==='josegaldamez','assigned mailbox aliases resolve internally');
 $boxes->assign(0,3,'Ambiguous',$config,'synthetic-password',$store);
 fails(fn()=>\TNL\Mail\Router::plan($db,'sales@example.test'),'ambiguous mailbox alias blocks rather than guessing recipient');
 $db->exec('UPDATE users SET active=0 WHERE id=2');
 fails(fn()=>\TNL\Mail\Router::plan($db,'josegaldamez@example.test'),'disabled user cannot receive company routing');
}finally{foreach(glob($dir.'/*') as $file)unlink($file);rmdir($dir);}
$g=\TNL\Mail\ServerProfiles::detect($db,'user@gmail.com',fn()=>throw new RuntimeException('Should not query DNS'));
ok($g['imap_host']==='imap.gmail.com'&&$g['smtp_host']==='smtp.gmail.com','Gmail exact domain profile needs no DNS guessing');
$m=\TNL\Mail\ServerProfiles::known('outlook.com');
ok($m['auth']==='oauth'&&$m['smtp_port']===587,'Outlook requires OAuth and STARTTLS profile');
$z=\TNL\Mail\ServerProfiles::detect($db,'user@custom.test',fn()=>[['target'=>'mx.zoho.eu']]);
ok($z['imap_host']==='imappro.zoho.eu'&&$z['smtp_host']==='smtppro.zoho.eu','custom Zoho MX preserves region and business servers');
ok(\TNL\Mail\ServerProfiles::known('evil.test',['fakegoogle.com'])===null,'MX suffix lookalikes are not trusted provider matches');
fails(fn()=>\TNL\Mail\ServerProfiles::configure($db,['email'=>'user@outlook.com','setup_mode'=>'auto']),'automatic Outlook setup explains missing OAuth rather than pretending password login');
\TNL\Admin\Settings::set($db,'company_mail_servers',json_encode(['domain'=>'example.test','imap_host'=>'mail.company.test','smtp_host'=>'smtp.company.test','smtp_port'=>587]));
$p=\TNL\Mail\ServerProfiles::configure($db,['email'=>'user@example.test','setup_mode'=>'auto']);
ok($p['imap_host']==='mail.company.test'&&$p['smtp_username']==='user@example.test','company server settings reused without sharing other-user credentials');

\TNL\Admin\Settings::set($db,'company_domain','newcompany.test');
ok(\TNL\Mail\ServerProfiles::detect($db,'user@newcompany.test',fn()=>[])===null,'changing company domain cannot reuse another-domain server profile');
