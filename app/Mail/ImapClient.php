<?php
declare(strict_types=1);
namespace TNL\Mail;

final class ImapClient {
 private $stream=null;private int $tag=0;
 public function __construct(private array $config,private ?\Closure $connector=null) {}
 public function connect(): void {
  if($this->connector)$this->stream=($this->connector)();
  else{ExternalAccount::publicHost($this->config['imap_host']);$ctx=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'peer_name'=>$this->config['imap_host'],'SNI_enabled'=>true]]);$this->stream=@stream_socket_client('tls://'.$this->config['imap_host'].':993',$errno,$error,10,STREAM_CLIENT_CONNECT,$ctx);}
  if(!$this->stream)throw new \RuntimeException('IMAP: no se pudo establecer TLS verificado en el puerto 993.');stream_set_timeout($this->stream,10);
  $greeting=$this->line();if(!str_starts_with($greeting,'* OK'))throw new \RuntimeException('IMAP: saludo no compatible.');
  $this->command('LOGIN '.$this->quote($this->config['imap_username']??$this->config['email']).' '.$this->quote($this->config['password']));
 }
 private function quote(string $v): string {if(preg_match('/[\r\n\x00]/',$v))throw new \InvalidArgumentException('Credencial IMAP inválida.');return '"'.str_replace(['\\','"'],['\\\\','\\"'],$v).'"';}
 private function line(): string {$line=fgets($this->stream,16385);if($line===false||!str_ends_with($line,"\n"))throw new \RuntimeException('IMAP: respuesta incompleta o tiempo agotado.');return $line;}
 private function command(string $command,?string $payload=null): array {
  if(!is_resource($this->stream))throw new \RuntimeException('IMAP desconectado.');$tag='T'.++$this->tag;$bytes=$tag.' '.$command."\r\n";if(fwrite($this->stream,$bytes)!==strlen($bytes))throw new \RuntimeException('IMAP: escritura fallida.');
  if($payload!==null){$continuation=$this->line();if(!str_starts_with($continuation,'+'))throw new \RuntimeException('IMAP: no aceptó la restauración.');$bytes=$payload."\r\n";$offset=0;while($offset<strlen($bytes)){$n=fwrite($this->stream,substr($bytes,$offset));if(!$n)throw new \RuntimeException('IMAP: restauración incompleta.');$offset+=$n;}}
  $out=[];$total=0;
  for($i=0;$i<500;$i++){$line=$this->line();$total+=strlen($line);if($total>2*1024*1024)throw new \RuntimeException('IMAP: respuesta demasiado grande.');
   if(str_starts_with($line,$tag.' ')){if(!preg_match('/^'.preg_quote($tag,'/').' OK\b/',$line))throw new \RuntimeException('IMAP: operación rechazada. Comprueba credenciales y permisos.');return $out;}
   $literal=null;if(preg_match('/\{([0-9]+)\}\r\n$/D',$line,$m)){$size=(int)$m[1];if($size>1048576||($total+=$size)>2*1048576)throw new \RuntimeException('El mensaje supera el límite de lectura de 1 MB.');$literal='';while(strlen($literal)<$size){$part=fread($this->stream,$size-strlen($literal));if($part===false||$part==='')throw new \RuntimeException('IMAP: mensaje incompleto.');$literal.=$part;}}
   $out[]=['line'=>$line,'literal'=>$literal];
  }throw new \RuntimeException('IMAP: respuesta demasiado extensa.');
 }
 public function inbox(int $page=1,string $folder='INBOX'): array {
  $response=$this->command('EXAMINE '.$this->folder($folder));$count=0;foreach($response as $r)if(preg_match('/^\* (\d+) EXISTS\r\n$/D',$r['line'],$m))$count=(int)$m[1];
  $page=max(1,min($page,max(1,(int)ceil($count/20))));$end=$count-($page-1)*20;if($end<1)return ['total'=>0,'page'=>1,'messages'=>[]];$start=max(1,$end-19);
  $rows=$this->command('FETCH '.$start.':'.$end.' (UID FLAGS BODY.PEEK[HEADER.FIELDS (SUBJECT FROM DATE TO)] RFC822.SIZE)');$messages=[];
  foreach($rows as $r){if($r['literal']===null||!preg_match('/\bUID (\d+)\b/',$r['line'],$m))continue;$headers=self::headers($r['literal']);$messages[]=['uid'=>(int)$m[1],'subject'=>$headers['subject']??'(sin asunto)','from'=>$headers['from']??'','date'=>$headers['date']??''];}
  return ['total'=>$count,'page'=>$page,'messages'=>array_reverse($messages),'validity'=>$this->validity($response)];
 }
 public function read(int $uid,string $folder='INBOX'): array {
  if($uid<1)throw new \InvalidArgumentException('Mensaje inválido.');$this->command('EXAMINE '.$this->folder($folder));$sizes=$this->command('UID FETCH '.$uid.' (RFC822.SIZE)');$found=false;foreach($sizes as $r)if(preg_match('/RFC822.SIZE (\d+)/',$r['line'],$m)){$found=true;if((int)$m[1]>1048576)throw new \RuntimeException('El mensaje supera el límite de lectura de 1 MB.');}if(!$found)throw new \RuntimeException('Mensaje no encontrado.');
  $rows=$this->command('UID FETCH '.$uid.' (UID BODY.PEEK[])');foreach($rows as $r)if($r['literal']!==null&&preg_match('/\bUID (\d+)\b/',$r['line'],$m)&&(int)$m[1]===$uid)return self::message($r['literal']);throw new \RuntimeException('Mensaje no encontrado.');
 }

 private function folder(string $folder): string {
  if(!in_array($folder,['INBOX','TNL Archive','TNL Trash','TNL Recovered'],true))throw new \InvalidArgumentException('Carpeta inválida.');
  return $folder==='INBOX'?'INBOX':$this->quote($folder);
 }
 private function validity(array $rows): int {
  foreach($rows as $r)if(preg_match('/\[UIDVALIDITY (\d+)\]/i',$r['line'],$m))return (int)$m[1];return 0;
 }
 private function selectWritable(string $folder,int $expected): void {
  $rows=$this->command('SELECT '.$this->folder($folder));
  if($expected<1||$this->validity($rows)!==$expected)throw new \RuntimeException('La carpeta cambió. Actualiza el listado antes de continuar.');
 }
 public function capabilities(): array {
  $rows=$this->command('CAPABILITY');$caps=[];foreach($rows as $r)if(str_starts_with($r['line'],'* CAPABILITY '))$caps=explode(' ',trim(substr($r['line'],13)));return array_map('strtoupper',$caps);
 }
 public function move(int $uid,string $from,string $to,int $validity): void {
  if($uid<1)throw new \InvalidArgumentException('Mensaje inválido.');
  if(!in_array('MOVE',$this->capabilities(),true))throw new \RuntimeException('El servidor no admite MOVE seguro. No se modificó el mensaje.');
  $this->selectWritable($from,$validity);
  try{$this->command('CREATE '.$this->folder($to));}catch(\RuntimeException $e){}
  $this->command('UID MOVE '.$uid.' '.$this->folder($to));
 }
 public function raw(int $uid,string $folder,int $validity): string {
  if($uid<1)throw new \InvalidArgumentException('Mensaje inválido.');
  $rows=$this->command('EXAMINE '.$this->folder($folder));
  if($validity<1||$this->validity($rows)!==$validity)throw new \RuntimeException('Actualiza la carpeta antes de borrar.');
  $rows=$this->command('UID FETCH '.$uid.' (UID BODY.PEEK[])');
  foreach($rows as $r)if($r['literal']!==null&&preg_match('/\bUID (\d+)\b/',$r['line'],$m)&&(int)$m[1]===$uid)return $r['literal'];
  throw new \RuntimeException('Mensaje no encontrado.');
 }
 public function remove(int $uid,string $folder,int $validity): void {
  if($uid<1||$folder!=='TNL Trash')throw new \InvalidArgumentException('Solo se puede borrar desde la papelera.');
  if(!in_array('UIDPLUS',$this->capabilities(),true))throw new \RuntimeException('El servidor no admite borrado individual seguro (UIDPLUS). Se conserva la copia de recuperación.');
  $this->selectWritable($folder,$validity);$this->command('UID STORE '.$uid.' +FLAGS.SILENT (\\Deleted)');$this->command('UID EXPUNGE '.$uid);
 }
 public function restore(string $raw): void {
  if(strlen($raw)>1048576)throw new \RuntimeException('El mensaje supera 1 MB.');
  try{$this->command('CREATE "TNL Recovered"');}catch(\RuntimeException $e){}
  $this->command('APPEND "TNL Recovered" {'.strlen($raw).'}',$raw);
 }
 public static function headers(string $raw): array {
  $raw=preg_replace('/\r?\n[ \t]+/',' ',$raw);$out=[];foreach(preg_split('/\r?\n/',$raw) as $line){if(!str_contains($line,':'))continue;[$k,$v]=explode(':',$line,2);$v=trim($v);if(function_exists('iconv_mime_decode')){$decoded=iconv_mime_decode($v,ICONV_MIME_DECODE_CONTINUE_ON_ERROR,'UTF-8');if($decoded!==false)$v=$decoded;}$out[strtolower($k)]=$v;}return $out;
 }
 public static function message(string $raw,int $depth=0): array {
  if($depth>5)return ['headers'=>[],'body'=>'Mensaje MIME demasiado anidado.','plain'=>false];$parts=preg_split('/\r?\n\r?\n/',$raw,2);$headers=self::headers($parts[0]);$body=$parts[1]??'';$type=$headers['content-type']??'text/plain';
  if(str_starts_with(strtolower($type),'multipart/')&&preg_match('/boundary\s*=\s*(?:"([^"]+)"|([^;\s]+))/i',$type,$m)){
   $boundary=$m[1]!==''?$m[1]:$m[2];$children=preg_split('/(?:\r?\n|^)--'.preg_quote($boundary,'/').'(?:--)?[ \t]*\r?\n/',$body,40);$fallback=null;
   foreach(array_slice($children,1) as $child){$parsed=self::message($child,$depth+1);if($parsed['plain'])return ['headers'=>$headers,'body'=>$parsed['body'],'plain'=>true];if($fallback===null&&$parsed['body']!=='')$fallback=$parsed['body'];}return ['headers'=>$headers,'body'=>$fallback??'Sin texto legible. Los adjuntos no se muestran.','plain'=>false];
  }
  if(str_contains(strtolower($headers['content-disposition']??''),'attachment'))return ['headers'=>$headers,'body'=>'','plain'=>false];
  $encoding=strtolower($headers['content-transfer-encoding']??'');if($encoding==='base64'){$decoded=base64_decode(preg_replace('/\s+/','',$body),true);$body=$decoded===false?'':$decoded;}elseif($encoding==='quoted-printable')$body=quoted_printable_decode($body);
  if(preg_match('/charset\s*=\s*"?([^;"\s]+)/i',$type,$m)&&function_exists('iconv')){$converted=@iconv($m[1],'UTF-8//IGNORE',$body);if($converted!==false)$body=$converted;}
  $plain=str_starts_with(strtolower($type),'text/plain');if(str_starts_with(strtolower($type),'text/html'))$body=html_entity_decode(strip_tags(preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is','',$body)),ENT_QUOTES|ENT_HTML5,'UTF-8');elseif(!$plain)$body='';
  return ['headers'=>$headers,'body'=>$body,'plain'=>$plain];
 }
 public function close(): void {if(is_resource($this->stream)){fclose($this->stream);$this->stream=null;}}
 public function __destruct(){$this->close();}
}
