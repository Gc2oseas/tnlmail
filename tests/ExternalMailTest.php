<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Mail/ExternalMail.php';
use TNL\Mail\ExternalAccount;use TNL\Mail\ExternalMail;use TNL\Mail\ImapClient;
function expectExternal(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);echo "PASS: $message\n";}
function rejectExternal(Closure $fn,string $message): void {try{$fn();}catch(Throwable $e){echo "PASS: $message\n";return;}throw new RuntimeException('Not rejected: '.$message);}
$storage=sys_get_temp_dir().'/tnl-external-'.bin2hex(random_bytes(8));mkdir($storage,0700,true);$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$store=new ExternalAccount($db,$storage);
$config=['email'=>'alice@example.test','imap_host'=>'mail.example.test','smtp_host'=>'mail.example.test'];
$store->save('employee:1',$config,'synthetic-test-password');$saved=$store->load('employee:1');
expectExternal($saved['password']==='synthetic-test-password'&&$saved['imap_port']===993&&$saved['smtp_port']===465,'encrypted credential round trip');
$raw=$db->query('SELECT secret||config FROM external_accounts')->fetchColumn();expectExternal(!str_contains($raw,'synthetic-test-password'),'SQLite does not contain plaintext password');
expectExternal($store->load('employee:2')===null&&$store->load('admin:1')===null,'accounts isolated by employee and admin identity');
$store->save('employee:1',$config,'');expectExternal($store->load('employee:1')['password']==='synthetic-test-password','blank password retains saved credential');
rejectExternal(fn()=>$store->save('employee:1',array_replace($config,['email'=>'bob@example.test']),''),'changing mailbox requires its password');
rejectExternal(fn()=>$store->save('employee:1',$config,"pass\r\ncommand"),'reject credential command injection');
rejectExternal(fn()=>ExternalAccount::config(array_replace($config,['imap_host'=>'127.0.0.1'])),'reject literal local IP target');
rejectExternal(fn()=>ExternalMail::send($saved,'bob@example.test',"subject\r\nBcc: victim@example.test",'test'),'reject SMTP header injection before connecting');
$db->exec("UPDATE external_accounts SET owner='employee:2'");rejectExternal(fn()=>$store->load('employee:2'),'ciphertext is bound to its owner');$db->exec("UPDATE external_accounts SET owner='employee:1'");
$store->recordSent('employee:1','bob@example.test','subject','body','test-id');expectExternal(count($store->sent('employee:1'))===1&&count($store->sent('employee:2'))===0,'sent copies isolated by owner');
$raw="Subject: =?UTF-8?B?UHJ1ZWJhIMOx?=\r\nFrom: Alice <alice@example.test>\r\nContent-Type: multipart/alternative; boundary=abc\r\n\r\n--abc\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n<script>bad()</script><p>HTML</p>\r\n--abc\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\nSG9sYQ==\r\n--abc--\r\n";
$m=ImapClient::message($raw);expectExternal(trim($m['body'])==='Hola'&&$m['plain'],'prefer decoded plain text over HTML alternative');
expectExternal($m['headers']['subject']==='Prueba ñ','decode MIME encoded subject');
$html=ImapClient::message("Content-Type: text/html\r\n\r\n<script>bad()</script><p>Hello<img src='https://tracker.test'></p>");expectExternal(!str_contains($html['body'],'bad()')&&!str_contains($html['body'],'<')&&$html['body']==='Hello','HTML converted to inert text without remote images');
$attachment=ImapClient::message("Content-Type: text/plain\r\nContent-Disposition: attachment\r\n\r\nsecret attachment");expectExternal($attachment['body']==='','attachments not displayed as message body');
rename($storage.'/external-mail.key',$storage.'/saved-key');rejectExternal(fn()=>$store->load('employee:1'),'missing encryption key fails without replacing it');expectExternal(!is_file($storage.'/external-mail.key'),'missing key not regenerated for existing credentials');
echo "ALL EXTERNAL MAIL TESTS PASSED\n";
