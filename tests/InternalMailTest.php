<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Admin/Users.php';
require dirname(__DIR__).'/app/Mail/InternalMail.php';
use TNL\Admin\Users;
use TNL\Mail\InternalMail;
if(!extension_loaded('pdo_sqlite')){fwrite(STDERR,"SKIP: pdo_sqlite required\n");exit(2);}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('PRAGMA foreign_keys=ON');
Users::init($db);
Users::create($db,['username'=>'alice','display_name'=>'Alice','domain'=>'example.test','password'=>'test-password-123']);
Users::create($db,['username'=>'bob','display_name'=>'Bob','domain'=>'example.test','password'=>'test-password-456']);
Users::create($db,['username'=>'carol','display_name'=>'Carol','domain'=>'example.test','password'=>'test-password-789']);
InternalMail::send($db,1,'bob@example.test','Hola','Mensaje de prueba');
$inbox=InternalMail::list($db,2,'inbox');
assert(count($inbox)===1,'Bob should receive one message');
assert(count(InternalMail::list($db,1,'sent'))===1,'Alice should have sent message');
assert(InternalMail::read($db,3,1)===null,'Carol cannot read message');
$m=InternalMail::read($db,2,1);
assert($m!==null,'Bob can read message');
InternalMail::send($db,2,'alice@example.test','Re: Hola','Respuesta',1);
assert(count(InternalMail::list($db,1,'inbox'))===1,'Alice receives reply');
try{InternalMail::send($db,3,'alice@example.test','Falsa respuesta','No permitido',1);throw new RuntimeException('Unauthorized reply allowed');}
catch(InvalidArgumentException $expected){}
InternalMail::delete($db,2,1);
assert(count(InternalMail::list($db,2,'inbox'))===0,'Bob deleted own copy');
assert(count(InternalMail::list($db,1,'sent'))===1,'Alice still has sent copy');
echo "PASS: internal mail access, reply and deletion\n";

InternalMail::send($db,2,'alice@example.test','Re: Hola','<p><b>HTML reply</b></p>',2,'html');$rich=InternalMail::read($db,1,3);assert($rich['format']==='html');assert(count(InternalMail::conversation($db,1,3))===3);assert(count(InternalMail::conversation($db,2,3))===2);assert(InternalMail::conversation($db,3,3)===[]);echo "PASS: rich internal replies form a conversation scoped to visible participant messages\n";
