<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Mail/ExternalMail.php';
use TNL\Mail\ImapClient;
final class FakeImapStream {
 public $context;public static array $commands=[];public static bool $oversize=false;private string $response="* OK test server\r\n";
 public function stream_open(string $path,string $mode,int $options,?string &$openedPath): bool{return true;}
 public function stream_set_option(int $option,int $arg1,?int $arg2): bool{return true;}
 public function stream_stat(): array{return [];}
 public function stream_eof(): bool{return false;}
 public function stream_read(int $length): string {$bytes=substr($this->response,0,min($length,17));$this->response=substr($this->response,strlen($bytes));return $bytes;}
 public function stream_write(string $bytes): int {
  self::$commands[]=$bytes;[$tag,$command]=explode(' ',rtrim($bytes,"\r\n"),2);
  $header="Subject: Header test\r\nFrom: sender@example.test\r\nDate: 2026-10-16\r\n\r\n";
  $body="Subject: Full test\r\nMessage-ID: <child@example.test>\r\nReferences: <root@example.test>\r\nContent-Type: text/plain\r\n\r\n".str_repeat('Payload ',600);
  if(str_starts_with($command,'LOGIN '))$this->response.=$tag." OK authenticated\r\n";
  elseif($command==='SELECT INBOX')$this->response.="* OK [UIDVALIDITY 99] valid\r\n".$tag." OK selected\r\n";
  elseif($command==='UID STORE 42 +FLAGS.SILENT (\\Seen)')$this->response.=$tag." OK seen\r\n";
  elseif($command==='EXAMINE INBOX')$this->response.="* 1 EXISTS\r\n* OK [UIDVALIDITY 99] valid\r\n* OK [UIDNEXT 43] next\r\n".$tag." OK [READ-ONLY]\r\n";
  elseif($command==='UID SEARCH UID 42:*')$this->response.="* SEARCH 1 42\r\n".$tag." OK searched\r\n";
  elseif($command==='STATUS INBOX (UNSEEN)')$this->response.="* STATUS INBOX (UNSEEN 3)\r\n".$tag." OK status\r\n";
  elseif(str_starts_with($command,'FETCH '))$this->response.="* 1 FETCH (UID 42 FLAGS () BODY[HEADER.FIELDS (SUBJECT FROM DATE TO)] {".strlen($header)."}\r\n".$header.")\r\n".$tag." OK fetched\r\n";
  elseif(str_starts_with($command,'UID SEARCH OR HEADER Message-ID '))$this->response.="* SEARCH 42\r\n".$tag." OK searched\r\n";
  elseif($command==='UID FETCH 42 (RFC822.SIZE)')$this->response.='* 1 FETCH (UID 42 RFC822.SIZE '.(self::$oversize?2000000:strlen($body)).")\r\n".$tag." OK size\r\n";
  elseif($command==='UID FETCH 42 (UID BODY.PEEK[])')$this->response.='* 1 FETCH (UID 42 BODY[] {'.strlen($body)."}\r\n".$body.")\r\n".$tag." OK full\r\n";
  else throw new RuntimeException('Unexpected IMAP command');
  return strlen($bytes);
 }
}
stream_wrapper_register('fakeimap',FakeImapStream::class);
$client=new ImapClient(['email'=>'test@example.test','password'=>'quote"slash\\','imap_host'=>'mail.example.test'],fn()=>fopen('fakeimap://test','r+'));
$client->connect();$inbox=$client->inbox();if(count($inbox['messages'])!==1||$inbox['messages'][0]['uid']!==42||$inbox['messages'][0]['subject']!=='Header test')throw new RuntimeException('Header literal parsing failed');echo "PASS: parse fragmented IMAP header literals and UID\n";
$message=$client->read(42);if($message['headers']['subject']!=='Full test'||strlen($message['body'])!==4800)throw new RuntimeException('Full literal parsing failed');echo "PASS: read fragmented full-message literal without marking seen\n";
if(!str_contains(FakeImapStream::$commands[0],'quote\\"slash\\\\'))throw new RuntimeException('Password quoting failed');echo "PASS: IMAP authentication quotes credentials correctly\n";
foreach(FakeImapStream::$commands as $command)if(str_contains($command,'BODY[')||str_contains($command,'SELECT '))throw new RuntimeException('Read operation is not read-only');echo "PASS: use EXAMINE and BODY.PEEK only\n";
$threadMessages=$client->conversation(['message-id'=>'<root@example.test>']);if(count($threadMessages)!==1)throw new RuntimeException('Thread search failed');echo "PASS: RFC conversation search returns related messages via read-only UID commands\n";
$cp=$client->checkpoint();if($cp!==['validity'=>99,'next'=>43])throw new RuntimeException('Checkpoint failed');$uids=$client->uidsSince(42);if($uids['uids']!==[42])throw new RuntimeException('UID cursor failed');if($client->unreadCount()!==3)throw new RuntimeException('Unread count failed');echo "PASS: shared reception cursor and unread counts use read-only IMAP commands\n";
FakeImapStream::$oversize=true;try{$client->read(42);throw new LogicException('Oversized message allowed');}catch(RuntimeException $e){if($e instanceof LogicException)throw $e;}echo "PASS: reject oversized message before full download\n";$client->close();

$seenImap=new ImapClient(['email'=>'test@example.test','password'=>'test'],fn()=>fopen('fakeimap://server','r+'));$seenImap->connect();$seenImap->markSeen([42,42,0]);$seenImap->close();if(!array_filter(FakeImapStream::$commands,fn($c)=>str_contains($c,'UID STORE 42 +FLAGS.SILENT (\\Seen)')))throw new RuntimeException('Reading thread must mark only requested UIDs seen');echo "PASS: explicit thread read marks validated UID list seen\n";
