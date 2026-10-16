<?php
declare(strict_types=1);
namespace TNL\Deploy;
require_once dirname(__DIR__).'/Update/Engine.php';
use TNL\Update\Engine;

final class GitHub {
 public const REPOSITORY='Gc2oseas/tnlmail';
 public const BRANCH='develop';
 public function __construct(private string $root,private ?\Closure $transport=null) {}
 private function db(): \PDO {return new \PDO('sqlite:'.$this->root.'/storage/system.sqlite',null,null,[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION]);}
 private function setting(string $key): string {$db=$this->db();$s=$db->prepare('SELECT value FROM settings WHERE key=?');$s->execute([$key]);return (string)($s->fetchColumn()?:'');}
 private function get(string $url,int $limit=2097152): string {
  if($this->transport){$bytes=($this->transport)($url);if(strlen($bytes)>$limit)throw new \RuntimeException('Respuesta demasiado grande.');return $bytes;}
  if(!extension_loaded('openssl')||!ini_get('allow_url_fopen'))throw new \RuntimeException('Se requiere OpenSSL y allow_url_fopen para consultar GitHub.');
  $context=stream_context_create(['http'=>['method'=>'GET','header'=>"User-Agent: TNL-Mail-Deploy\r\nAccept: application/vnd.github+json\r\n",'timeout'=>15,'follow_location'=>0,'ignore_errors'=>true],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
  $stream=@fopen($url,'rb',false,$context);if(!$stream)throw new \RuntimeException('No se pudo conectar con GitHub mediante HTTPS.');
  try{$meta=stream_get_meta_data($stream);$status=$meta['wrapper_data'][0]??'';if(!preg_match('~^HTTP/\S+ 200\b~',$status))throw new \RuntimeException('GitHub no devolvió HTTP 200; revisa disponibilidad y límites de API.');$bytes=stream_get_contents($stream,$limit+1);if($bytes===false||strlen($bytes)>$limit)throw new \RuntimeException('Descarga excesiva o ilegible.');return $bytes;}finally{fclose($stream);}
 }
 private function head(): string {
  $data=json_decode($this->get('https://api.github.com/repos/'.self::REPOSITORY.'/commits/'.self::BRANCH),true,64,JSON_THROW_ON_ERROR);
  $sha=$data['sha']??'';if(!preg_match('/^[a-f0-9]{40}$/D',$sha))throw new \RuntimeException('Commit de GitHub inválido.');return $sha;
 }
 private function commit(string $sha): void {if(!preg_match('/^[a-f0-9]{40}$/D',$sha))throw new \RuntimeException('Se requiere el SHA completo del commit.');if(!hash_equals($this->head(),$sha))throw new \RuntimeException('El commit no es la revisión actual de develop. Prepara una nueva vista previa.');}
 private function directory(): string {
  $dir=$this->root.'/storage/deploy-pending';if(is_link($dir))throw new \RuntimeException('Directorio temporal inválido.');if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new \RuntimeException('No se pudo preparar despliegue.');return $dir;
 }
 private function save(string $file,string $data): void {if(file_put_contents($file,$data,LOCK_EX)!==strlen($data))throw new \RuntimeException('No se pudo guardar despliegue.');chmod($file,0600);}
 public function preview(string $commit): array {
  $this->commit($commit);$raw='https://raw.githubusercontent.com/'.self::REPOSITORY.'/'.$commit.'/';
  $manifestBytes=$this->get($raw.'tnl-update.json',131072);$manifest=json_decode($manifestBytes,true,32,JSON_THROW_ON_ERROR);
  if(($manifest['format']??null)!==1||($manifest['channel']??'')!==self::BRANCH||!is_array($manifest['files']??null)||count($manifest['files'])>100)throw new \RuntimeException('El commit no contiene un manifiesto de despliegue compatible.');
  $dir=$this->directory();$ticket=bin2hex(random_bytes(16));$zipPath=$dir.'/'.$ticket.'.zip';$zip=new \ZipArchive();if($zip->open($zipPath,\ZipArchive::CREATE|\ZipArchive::EXCL)!==true)throw new \RuntimeException('No se pudo preparar ZIP.');
  $complete=false;
  try {
   $total=0;foreach($manifest['files'] as $name=>$hash){
    if(!is_string($name)||str_contains($name,'..')||!preg_match('~^(?:bootstrap\.php|(?:app|public)/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_.-]+\.(?:php|css|js|json|webmanifest))$~D',$name)||!is_string($hash)||!preg_match('/^[a-f0-9]{64}$/D',$hash))throw new \RuntimeException('Ruta o hash inválido en manifiesto.');
    $bytes=$this->get($raw.$name);$total+=strlen($bytes);if($total>30*1024*1024||!hash_equals($hash,hash('sha256',$bytes)))throw new \RuntimeException('Hash no coincide con GitHub o paquete excesivo.');
    if(!$zip->addFromString($name,$bytes))throw new \RuntimeException('No se pudo construir el paquete.');
   }
   if(!$zip->addFromString('tnl-update.json',$manifestBytes)||!$zip->close())throw new \RuntimeException('No se pudo cerrar el paquete.');$zip=null;
   $sha=hash_file('sha256',$zipPath);(new Engine($this->root))->inspect($zipPath,$sha);
   $record=['ticket'=>$ticket,'commit'=>$commit,'sha256'=>$sha,'version'=>$manifest['version'],'created'=>time()];
   $this->save($dir.'/'.$ticket.'.json',json_encode($record,JSON_THROW_ON_ERROR));chmod($zipPath,0600);$complete=true;
   return $record+['repository'=>self::REPOSITORY,'branch'=>self::BRANCH,'files'=>array_keys($manifest['files']),'expires_in'=>1800];
  }finally{if($zip instanceof \ZipArchive)$zip->close();if(!$complete&&is_file($zipPath))unlink($zipPath);}
 }
 private function lock() {$handle=fopen($this->root.'/storage/deploy.lock','c');if(!$handle||!flock($handle,LOCK_EX|LOCK_NB))throw new \RuntimeException('Hay otro despliegue activo.');return $handle;}
 private function maintenance(bool $on): void {
  $file=$this->root.'/storage/deploy-maintenance';if($on)$this->save($file,gmdate('c'));elseif(is_file($file)&&!unlink($file))throw new \RuntimeException('No se pudo retirar mantenimiento.');
 }
 public function deploy(string $ticket,string $commit): array {
  if(!preg_match('/^[a-f0-9]{32}$/D',$ticket))throw new \RuntimeException('Ticket inválido.');
  $lock=$this->lock();$maintenance=false;
  try {
   $dir=$this->directory();$file=$dir.'/'.$ticket.'.json';if(!is_file($file))throw new \RuntimeException('Vista previa inexistente.');$r=json_decode(file_get_contents($file),true,16,JSON_THROW_ON_ERROR);
   if(($r['commit']??'')!==$commit||time()-($r['created']??0)>1800)throw new \RuntimeException('Vista previa vencida o commit distinto.');
   $this->commit($commit);if($this->setting('deployed_commit')===$commit)return ['status'=>'already_deployed','commit'=>$commit];
   $history=(new Engine($this->root))->history();if(($history[0]['status']??'')==='applying')throw new \RuntimeException('Hay una actualización interrumpida. Restaura primero su respaldo.');
   $this->maintenance(true);$maintenance=true;
   $backup=(new Engine($this->root))->apply($dir.'/'.$ticket.'.zip',$r['sha256']);
   // El respaldo de Engine incluye los metadatos de la revisión anterior.
   $db=$this->db();$db->beginTransaction();try{$s=$db->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value');foreach(['deployed_commit'=>$commit,'deployed_branch'=>self::BRANCH,'deployed_at'=>gmdate('c')] as $key=>$value)$s->execute([$key,$value]);$db->commit();}catch(\Throwable $e){if($db->inTransaction())$db->rollBack();$s=null;$db=null;(new Engine($this->root))->restore($backup);throw $e;}$s=null;$db=null;
   return ['status'=>'deployed','commit'=>$commit,'version'=>$r['version'],'backup'=>$backup,'verification'=>'file hashes and SQLite integrity verified'];
  } finally {try{if($maintenance){$history=(new Engine($this->root))->history();if(($history[0]['status']??'')!=='applying')$this->maintenance(false);}}finally{flock($lock,LOCK_UN);fclose($lock);}}
 }
 public function rollback(string $backup): array {
  $lock=$this->lock();$maintenance=false;
  try {$this->maintenance(true);$maintenance=true;(new Engine($this->root))->restore($backup);return ['status'=>'restored','backup'=>$backup];}
  finally{try{if($maintenance){$history=(new Engine($this->root))->history();if(($history[0]['status']??'')!=='applying')$this->maintenance(false);}}finally{flock($lock,LOCK_UN);fclose($lock);}}
 }
 public function status(): array {
  $history=(new Engine($this->root))->history();return ['repository'=>self::REPOSITORY,'branch'=>self::BRANCH,'commit'=>$this->setting('deployed_commit'),'version'=>$this->setting('version'),'maintenance'=>is_file($this->root.'/storage/deploy-maintenance'),'last_backup'=>isset($history[0])?array_intersect_key($history[0],array_flip(['id','version','status'])):null];
 }
}
