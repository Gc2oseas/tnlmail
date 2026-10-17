<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Mail/DiagnosticSend.php';
use TNL\Mail\DiagnosticSend;
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
try{DiagnosticSend::send($db,sys_get_temp_dir(),'bad');throw new LogicException('Bad ID accepted');}catch(InvalidArgumentException $e){}
$db->exec("CREATE TABLE mail_diagnostic_runs(request_id TEXT PRIMARY KEY,state TEXT,message_id TEXT,created_at TEXT)");$id=str_repeat('a',32);$db->prepare('INSERT INTO mail_diagnostic_runs VALUES(?,?,?,?)')->execute([$id,'smtp_accepted','<fixed@example.test>',gmdate('c')]);$r=DiagnosticSend::send($db,sys_get_temp_dir(),$id);if(!$r['duplicate_prevented']||$r['message_id']!=='<fixed@example.test>'||$r['recipient']!=='gc2oseas@gmail.com')throw new RuntimeException('Idempotency failed');echo "PASS: accepted diagnostic is returned without reconnecting or resending\n";
$db->prepare('UPDATE mail_diagnostic_runs SET state=? WHERE request_id=?')->execute(['unconfirmed',$id]);$r=DiagnosticSend::send($db,sys_get_temp_dir(),$id);if($r['state']!=='unconfirmed'||!$r['duplicate_prevented'])throw new RuntimeException('Uncertain delivery retried');echo "PASS: uncertain diagnostic is never automatically retried\n";
