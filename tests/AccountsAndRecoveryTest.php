<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Admin/Users.php';
require dirname(__DIR__).'/app/Admin/Recovery.php';
require dirname(__DIR__).'/app/Admin/DeviceLock.php';
require dirname(__DIR__).'/app/Mail/Mailboxes.php';
require dirname(__DIR__).'/app/Mail/ImapClient.php';
require dirname(__DIR__).'/app/Mail/RecoveryTrash.php';
function check(bool $ok,string $label): void {if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
function rejects(callable $fn,string $label): void {try{$fn();}catch(Throwable $e){echo "PASS: $label\n";return;}throw new RuntimeException($label);}
$dir=sys_get_temp_dir().'/tnl-accounts-'.bin2hex(random_bytes(6));mkdir($dir,0700);
try {
 $db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 \TNL\Admin\Users::init($db);\TNL\Admin\Recovery::init($db);
 foreach(['alice','bobby'] as $username)\TNL\Admin\Users::create($db,['username'=>$username,'display_name'=>$username,'domain'=>'example.test','password'=>'test-password-only']);
 $accounts=new \TNL\Mail\ExternalAccount($db,$dir);$boxes=new \TNL\Mail\Mailboxes($db);
 $config=['email'=>'sales@example.test','imap_host'=>'mail.example.test','smtp_host'=>'smtp.example.test','smtp_username'=>'smtp-login','smtp_password'=>'smtp-secret'];
 $one=$boxes->assign(0,1,'Ventas',$config,'imap-secret',$accounts);$two=$boxes->assign(0,1,'Soporte',array_replace($config,['email'=>'support@example.test']),'other-secret',$accounts);
 check(count($boxes->listFor(1))===2&&count($boxes->listFor(2))===0,'multiple assigned accounts isolated from other users');
 $loaded=$accounts->load('mailbox:'.$one);check($loaded['password']==='imap-secret'&&$loaded['smtp_password']==='smtp-secret'&&$loaded['smtp_username']==='smtp-login','independent encrypted IMAP and SMTP credentials');
 $boxes->assign($one,2,'Ventas',$config,'',$accounts);check(!$boxes->allowed($one,1)&&$boxes->allowed($one,2),'reassignment immediately revokes old user access');
 rejects(fn()=>$boxes->assign(0,999,'Bad',$config,'secret',$accounts),'reject nonexistent assignee');
 check(count($boxes->all())===2,'invalid assignment does not leave orphan mailbox');
 \TNL\Admin\Users::update($db,['user_id'=>1,'display_name'=>'Alice Smith','internal_address'=>'alice.changed@example.test']);
 check($db->query('SELECT internal_address FROM users WHERE id=1')->fetchColumn()==='alice.changed@example.test','administrator can change user address');
 $devices=new \TNL\Admin\DeviceLock($db);$token=$devices->create('employee',1,1,'123456');
 check($devices->unlock($token,'123456')['user']['id']===1,'valid PIN unlocks correct identity');
 for($i=0;$i<5;$i++)rejects(fn()=>$devices->unlock($token,'999999'),'wrong PIN consumes attempt');
 rejects(fn()=>$devices->unlock($token,'123456'),'device blocked after five incorrect PIN attempts');
 $token=$devices->create('employee',1,1,'123456');$db->exec('UPDATE users SET credentials_version=2 WHERE id=1');
 rejects(fn()=>$devices->unlock($token,'123456'),'password reset revokes remembered device');
 $trash=new \TNL\Mail\RecoveryTrash($db);$raw="Subject: Recovery\r\nContent-Type: multipart/mixed; boundary=x\r\n\r\n--x\r\nContent-Disposition: attachment; filename=a.bin\r\n\r\n".chr(0)."binary\r\n--x--\r\n";
 $copy=$trash->preserve('mailbox:1','sales@example.test','TNL Trash',99,42,$raw);
 check($trash->get($copy)['raw']===$raw,'recovery preserves byte-identical MIME and attachments in SQLite');
 check($trash->preserve('mailbox:1','sales@example.test','TNL Trash',99,42,$raw)===$copy&&count($trash->all())===1,'repeated preservation is idempotent');
 rejects(fn()=>$trash->preserve('mailbox:1','sales@example.test','TNL Trash',99,43,str_repeat('x',1048577)),'oversize message blocks deletion before preservation');
 rejects(fn()=>$trash->preserve('mailbox:1','sales@example.test','TNL Trash',99,42,'Different message'),'reused UID with different content cannot delete unpreserved message');
 $trash->restored($copy);check($trash->get($copy)['restored_at']!==null,'restoration timestamp retained');
}finally{foreach(glob($dir.'/*') as $f)unlink($f);rmdir($dir);}
