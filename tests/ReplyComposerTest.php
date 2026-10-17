<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Mail/ExternalMail.php';
use TNL\Mail\ReplyComposer;
function replyExpect(bool $value,string $message): void {if(!$value)throw new RuntimeException($message);echo "PASS: $message\n";}
$message=['headers'=>['from'=>'Original <sender@example.test>','reply-to'=>'Support <support@example.test>','to'=>'reader@example.test','date'=>'2026-10-16','subject'=>'Consulta','message-id'=>'<original@example.test>','references'=>'<ancestor@example.test>'],'body'=>"Hola\nSegunda línea"];
$reply=ReplyComposer::prepare($message,'reply');replyExpect($reply['to']==='support@example.test','reply honors valid Reply-To');replyExpect($reply['subject']==='Re: Consulta'&&str_contains($reply['body'],"> Hola\n> Segunda línea"),'reply prefixes subject and quotes original text');replyExpect($reply['thread']===['in_reply_to'=>'<original@example.test>','references'=>'<ancestor@example.test> <original@example.test>'],'reply preserves RFC conversation headers');
$fallback=$message;$fallback['headers']['reply-to']="bad@example.test\r\nBcc: victim@example.test";replyExpect(ReplyComposer::prepare($fallback,'reply')['to']==='sender@example.test','malformed Reply-To falls back to From');
$already=$message;$already['headers']['subject']='Re: Consulta';replyExpect(ReplyComposer::prepare($already,'reply')['subject']==='Re: Consulta','avoid repeated Re prefix');
$forward=ReplyComposer::prepare($message,'forward');replyExpect($forward['to']===''&&$forward['thread']===[]&&$forward['subject']==='Fwd: Consulta'&&str_contains($forward['body'],'Mensaje reenviado'),'forward requires recipient and starts a new conversation');
$bad=$message;$bad['headers']['message-id']="<id@example.test>\r\nBcc: victim@example.test";replyExpect(ReplyComposer::prepare($bad,'reply')['thread']===[],'invalid Message-ID cannot inject headers');
$long=$message;$long['headers']['subject']=str_repeat('ñ',300);$long['headers']['from']=str_repeat('x',100000);$long['headers']['references']=implode(' ',array_map(fn($i)=>'<'.str_repeat('a',120).$i.'@example.test>',range(1,40)));$long['body']=str_repeat("ñ\n",30000);$result=ReplyComposer::prepare($long,'reply');replyExpect(strlen($result['subject'])<=200&&strlen($result['body'])<=50000&&preg_match('//u',$result['subject'])===1&&preg_match('//u',$result['body'])===1,'large UTF-8 originals stay within compose limits');replyExpect(strlen($result['thread']['references'])<=800&&str_ends_with($result['thread']['references'],'<original@example.test>'),'bound reference chain and retain direct parent');
$empty=$message;$empty['headers']['from']='not an address';unset($empty['headers']['reply-to']);replyExpect(ReplyComposer::prepare($empty,'reply')['to']==='','invalid sender requires manually entering a valid recipient');
replyExpect(strlen($result['body'])>10000,'UTF-8 truncation preserves the quoted original');
$quoted=$message;$quoted['headers']['reply-to']='"Support, Example" <support@example.test>';replyExpect(ReplyComposer::prepare($quoted,'reply')['to']==='support@example.test','parse quoted display name with comma');
$multiple=$message;$multiple['headers']['from']='first@example.test, second@example.test';unset($multiple['headers']['reply-to']);replyExpect(ReplyComposer::prepare($multiple,'reply')['to']==='','ambiguous address list requires manual recipient');
echo "ALL REPLY/FORWARD TESTS PASSED\n";
