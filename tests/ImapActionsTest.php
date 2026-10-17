<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Mail/ExternalMail.php';
use TNL\Mail\ImapClient;
final class ActionStream {
 public $context;public static array $commands=[];public static string $caps='IMAP4rev1 MOVE UIDPLUS';public static string $raw="Subject: Backup\r\n\r\nPreserve me";private string $response="* OK ready\r\n";private ?string $append=null;
 public function stream_open(string $p,string $m,int $o,?string &$opened): bool{return true;}
 public function stream_set_option(int $o,int $a,?int $b): bool{return true;}
 public function stream_stat(): array{return [];}
 public function stream_eof(): bool{return false;}
 public function stream_read(int $n): string {$v=substr($this->response,0,min($n,13));$this->response=substr($this->response,strlen($v));return $v;}
 public function stream_write(string $bytes): int {
  self::$commands[]=$bytes;
  if($this->append!==null){if($bytes!==self::$raw."\r\n")throw new RuntimeException('APPEND altered raw bytes');$this->response.=$this->append." OK appended\r\n";$this->append=null;return strlen($bytes);}
  [$tag,$cmd]=explode(' ',rtrim($bytes,"\r\n"),2);
  if($cmd==='CAPABILITY')$this->response.='* CAPABILITY '.self::$caps."\r\n";
  if(str_starts_with($cmd,'SELECT ')||str_starts_with($cmd,'EXAMINE '))$this->response.="* OK [UIDVALIDITY 99] valid\r\n* 1 EXISTS\r\n";
  if($cmd==='UID FETCH 42 (UID BODY.PEEK[])')$this->response.='* 1 FETCH (UID 42 BODY[] {'.strlen(self::$raw)."}\r\n".self::$raw.")\r\n";
  if(str_starts_with($cmd,'APPEND ')){$this->append=$tag;$this->response.="+ continue\r\n";}else $this->response.=$tag." OK done\r\n";
  return strlen($bytes);
 }
}
stream_wrapper_register('actionsimap',ActionStream::class);
function client(): ImapClient {$c=new ImapClient(['email'=>'a@example.test','password'=>'test','imap_host'=>'mail.example.test'],fn()=>fopen('actionsimap://test','r+'));$c->connect();return $c;}
function yes(bool $condition,string $label): void {if(!$condition)throw new RuntimeException($label);echo "PASS: $label\n";}
$c=client();$c->move(42,'INBOX','TNL Archive',99);yes((bool)array_filter(ActionStream::$commands,fn($s)=>str_contains($s,'UID MOVE 42 "TNL Archive"')),'archive uses individual UID MOVE');
ActionStream::$commands=[];try{$c->move(42,'INBOX','TNL Trash',98);throw new LogicException('Wrong validity accepted');}catch(RuntimeException $e){}yes(!array_filter(ActionStream::$commands,fn($s)=>str_contains($s,'UID MOVE')),'changed UIDVALIDITY prevents moving wrong message');
yes($c->raw(42,'TNL Trash',99)===ActionStream::$raw,'recovery download preserves raw MIME bytes');
ActionStream::$commands=[];$c->remove(42,'TNL Trash',99);
yes((bool)array_filter(ActionStream::$commands,fn($s)=>str_contains($s,'UID EXPUNGE 42'))&&!array_filter(ActionStream::$commands,fn($s)=>preg_match('/^T[0-9]+ EXPUNGE/', $s)),'deletion never performs global EXPUNGE');
$c->restore(ActionStream::$raw);yes((bool)array_filter(ActionStream::$commands,fn($s)=>str_contains($s,'APPEND "TNL Recovered"')),'restore uses APPEND with original MIME');
ActionStream::$caps='IMAP4rev1';ActionStream::$commands=[];try{$c->remove(42,'TNL Trash',99);throw new LogicException('Unsafe deletion accepted');}catch(RuntimeException $e){}
yes(!array_filter(ActionStream::$commands,fn($s)=>str_contains($s,'UID STORE')),'missing UIDPLUS blocks deletion before marking flags');
$c->close();
