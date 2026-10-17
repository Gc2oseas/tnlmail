<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Admin/Users.php';require dirname(__DIR__).'/app/Mail/PersonalAccounts.php';require dirname(__DIR__).'/app/Mail/AccountAccess.php';require dirname(__DIR__).'/app/Mail/Router.php';
function yes(bool $v,string $label): void {if(!$v)throw new RuntimeException($label);echo "PASS: $label\n";}
function denied(callable $f,string $label): void {try{$f();}catch(InvalidArgumentException $e){echo "PASS: $label\n";return;}throw new RuntimeException($label);}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);\TNL\Admin\Users::init($db);\TNL\Admin\Settings::set($db,'company_domain','company.test');
foreach(['alice','bobby'] as $name)\TNL\Admin\Users::create($db,['username'=>$name,'display_name'=>$name,'domain'=>'company.test','password'=>'synthetic-password']);
$dir=sys_get_temp_dir().'/tnl-profile-'.bin2hex(random_bytes(5));mkdir($dir);
try{
 $store=new \TNL\Mail\ExternalAccount($db,$dir);$personal=new \TNL\Mail\PersonalAccounts($db,$store);$input=['email'=>'one@outside.test','imap_host'=>'mail.outside.test','smtp_host'=>'mail.outside.test'];
 $one=$personal->save(0,'employee:1','Personal',$input,'secret-one');$two=$personal->save(0,'employee:1','Project',array_replace($input,['email'=>'two@outside.test']),'secret-two');
 yes(count($personal->list('employee:1'))===2&&count($personal->list('employee:2'))===0,'multiple personal accounts remain isolated by identity');
 denied(fn()=>$personal->owner($one,'employee:2'),'cannot access another user personal account');
 denied(fn()=>$personal->remove($one,'employee:2'),'cannot unlink another user personal account');
 denied(fn()=>$personal->save(0,'employee:1','Business',array_replace($input,['email'=>'sales@company.test']),'secret'),'company address cannot be added as deletable personal account');
 $store->save('employee:1',array_replace($input,['email'=>'alice@company.test']),'business-secret');
 denied(fn()=>$personal->remove(0,'employee:1'),'corporate primary account cannot be unlinked');
 $store->recordSent('personal:'.$one,'recipient@outside.test','Sent','Body','test');
 yes($store->sentMessage('personal:'.$two,1)===null,'sent-message print lookup requires exact account owner');
 $personal->remove($one,'employee:1');yes(!$personal->allowed($one,'employee:1')&&$store->load('personal:'.$one)===null,'unlink removes connection and encrypted secret');
 $three=$personal->save(0,'employee:1','New',$input,'new-secret');yes($three>$two,'unlinked personal account IDs are not reused');
 $boxes=new \TNL\Mail\Mailboxes($db);$id=$boxes->assign(0,2,'Company alias',array_replace($input,['email'=>'business@outside.test']),'business-secret',$store);
 $spam=new \TNL\Mail\SpamPolicy($db);
 denied(fn()=>$spam->block('employee:1','Boss <boss@company.test>'),'company-domain mail cannot be marked spam');
 denied(fn()=>$spam->block('employee:1','business@outside.test'),'assigned company mailbox aliases cannot be marked spam');
 $spam->block('employee:1','Advertiser <ads@outside.test>');
 yes($spam->blocked('employee:1','ads@outside.test')&&!$spam->blocked('employee:2','ads@outside.test'),'spam rules are scoped to user');
 $moved=[];$filtered=\TNL\Mail\Router::filterIncoming($db,'employee:1',['validity'=>99,'messages'=>[['uid'=>7,'from'=>'ads@outside.test'],['uid'=>8,'from'=>'team@company.test']]],function($uid,$v)use(&$moved){$moved[]=[$uid,$v];});
 yes(count($filtered['messages'])===1&&$filtered['messages'][0]['uid']===8&&$moved===[[7,99]],'incoming router quarantines spam while preserving company messages');
 $spam->unblock('employee:1','ads@outside.test');yes(!$spam->blocked('employee:1','ads@outside.test'),'unblock restores future delivery');
 \TNL\Mail\InternalMail::send($db,1,'bobby','Archive','Body');\TNL\Mail\InternalMail::archive($db,2,1);
 yes(count(\TNL\Mail\InternalMail::list($db,2,'inbox'))===0&&count(\TNL\Mail\InternalMail::list($db,2,'archive'))===1,'internal archive moves recipient copy out of inbox');
 yes(count(\TNL\Mail\InternalMail::list($db,1,'sent'))===1,'recipient archiving does not archive sender copy');
}finally{foreach(glob($dir.'/*') as $f)unlink($f);rmdir($dir);}
