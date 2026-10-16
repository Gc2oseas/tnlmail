<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Deploy/GitHub.php';
require dirname(__DIR__).'/app/Admin/Users.php';
require dirname(__DIR__).'/app/Admin/Recovery.php';
require dirname(__DIR__).'/app/Mail/InternalMail.php';
use TNL\Deploy\GitHub;
function expect(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);echo "PASS: $message\n";}
function rejected(Closure $fn,string $message): void {try{$fn();}catch(Throwable $e){echo "PASS: $message\n";return;}throw new RuntimeException('Not rejected: '.$message);}
$source=dirname(__DIR__);$root=sys_get_temp_dir().'/tnl-deploy-test-'.bin2hex(random_bytes(6));mkdir($root.'/storage',0700,true);
$manifest=json_decode(file_get_contents($source.'/tnl-update.json'),true,32,JSON_THROW_ON_ERROR);
foreach(array_keys($manifest['files']) as $name){if(!is_dir(dirname($root.'/'.$name)))mkdir(dirname($root.'/'.$name),0700,true);copy($source.'/'.$name,$root.'/'.$name);}
mkdir($root.'/app/Update',0700,true);copy($source.'/app/Update/Engine.php',$root.'/app/Update/Engine.php');
$db=new PDO('sqlite:'.$root.'/storage/system.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE admins(id INTEGER PRIMARY KEY,username TEXT,password_hash TEXT,created_at TEXT);CREATE TABLE settings(key TEXT PRIMARY KEY,value TEXT);CREATE TABLE events(id INTEGER PRIMARY KEY,action TEXT,actor TEXT,created_at TEXT)");
\TNL\Admin\Users::init($db);\TNL\Admin\Recovery::init($db);\TNL\Mail\InternalMail::init($db);
$db->exec("INSERT INTO settings VALUES('version','0.2.1-dev'),('mcp_token_hash','read-hash'),('deploy_token_hash','deploy-hash'),('deploy_enabled','1')");$db=null;
$head=str_repeat('a',40);$corrupt=false;$requests=[];
$transport=function(string $url)use($source,&$head,&$corrupt,&$requests): string {
 $requests[]=$url;if($url==='https://api.github.com/repos/Gc2oseas/tnlmail/commits/develop')return json_encode(['sha'=>$head]);
 $prefix='https://raw.githubusercontent.com/Gc2oseas/tnlmail/'.$head.'/';if(!str_starts_with($url,$prefix))throw new RuntimeException('Unexpected URL');$name=substr($url,strlen($prefix));if($corrupt&&$name==='bootstrap.php')return '<?php // tampered';return file_get_contents($source.'/'.$name);
};
$github=new GitHub($root,$transport);
rejected(fn()=>$github->preview('../main'),'reject malformed commit');
rejected(fn()=>$github->preview(str_repeat('b',40)),'reject commit outside current develop head');
$corrupt=true;rejected(fn()=>$github->preview($head),'reject downloaded bytes with mismatched hash');$corrupt=false;
$preview=$github->preview($head);expect($preview['commit']===$head&&count($preview['files'])===count($manifest['files']),'preview pinned commit and exact files');
expect($github->status()['version']==='0.2.1-dev','preview does not change installed version');
$head=str_repeat('b',40);rejected(fn()=>$github->deploy($preview['ticket'],str_repeat('a',40)),'reject develop head moving after preview');$head=str_repeat('a',40);
$expired=$github->preview($head);$f=$root.'/storage/deploy-pending/'.$expired['ticket'].'.json';$record=json_decode(file_get_contents($f),true);$record['created']=time()-1900;file_put_contents($f,json_encode($record));rejected(fn()=>$github->deploy($expired['ticket'],$head),'reject expired preview');
$tampered=$github->preview($head);file_put_contents($root.'/storage/deploy-pending/'.$tampered['ticket'].'.zip','tampered');rejected(fn()=>$github->deploy($tampered['ticket'],$head),'reject tampered staged package');
$applied=$github->deploy($preview['ticket'],$head);expect($applied['status']==='deployed'&&$github->status()['commit']===$head,'deploy records exact commit');
expect(!is_file($root.'/storage/deploy-maintenance'),'successful deployment clears maintenance');
$again=$github->deploy($preview['ticket'],$head);expect($again['status']==='already_deployed','retry is idempotent');
$db=new PDO('sqlite:'.$root.'/storage/system.sqlite');expect($db->query("SELECT value FROM settings WHERE key='deploy_token_hash'")->fetchColumn()==='deploy-hash','deployment preserves scoped token');$db=null;
$rollback=$github->rollback($applied['backup']);expect($rollback['status']==='restored'&&$github->status()['version']==='0.2.1-dev'&&$github->status()['commit']==='','rollback restores prior version and metadata');
expect(!is_file($root.'/storage/deploy-maintenance'),'rollback clears maintenance');
expect(count(array_filter($requests,fn($url)=>!str_starts_with($url,'https://api.github.com/repos/Gc2oseas/tnlmail/commits/develop')&&!str_starts_with($url,'https://raw.githubusercontent.com/Gc2oseas/tnlmail/')))===0,'network restricted to configured repository');
echo "ALL DEPLOYMENT TESTS PASSED (simulated GitHub transport)\n";
