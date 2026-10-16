<?php
declare(strict_types=1);
namespace TNL\Update;

final class Engine {
 public function __construct(private string $root) { $this->root=rtrim($root,'/\\'); }
 private function path(string $name): string { return $this->root.'/'.$name; }
 private function write(string $file,string $bytes): void {
  if(!is_dir(dirname($file))&&!mkdir(dirname($file),0700,true))throw new \RuntimeException('No se pudo crear directorio.');
  $tmp=$file.'.tnl-'.bin2hex(random_bytes(6));
  try { if(file_put_contents($tmp,$bytes,LOCK_EX)!==strlen($bytes)||!rename($tmp,$file))throw new \RuntimeException('No se pudo guardar un archivo.'); }
  finally { if(is_file($tmp))unlink($tmp); }
 }
 private function safe(string $name): bool {
  if(in_array($name,['public/updates.php','app/Update/Engine.php'],true))return false;
  return $name==='bootstrap.php'||(bool)preg_match('~^(?:app|public)/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_.-]+\.(?:php|css|js|json|webmanifest)$~D',$name);
 }
 private function noLinks(string $name): void {
  $current=$this->root;
  foreach(explode('/',$name) as $part){$current.='/'.$part;if(is_link($current))throw new \RuntimeException('No se permiten enlaces simbólicos.');}
 }
 public function inspect(string $zipPath,string $sha): array {
  if(!preg_match('/^[a-f0-9]{64}$/D',$sha)||!hash_equals($sha,hash_file('sha256',$zipPath)))throw new \RuntimeException('SHA-256 incorrecto.');
  if(filesize($zipPath)>20*1024*1024)throw new \RuntimeException('Límite del ZIP: 20 MB.');
  $z=new \ZipArchive();if($z->open($zipPath,\ZipArchive::CHECKCONS)!==true)throw new \RuntimeException('ZIP inválido.');
  try {
   if($z->numFiles>500)throw new \RuntimeException('Demasiadas entradas.');
   $files=[];$total=0;$seen=[];$manifest=null;
   for($i=0;$i<$z->numFiles;$i++){
    $s=$z->statIndex($i);if($s===false)throw new \RuntimeException('Entrada ilegible.');$name=$s['name'];
    $key=strtolower($name);if(isset($seen[$key]))throw new \RuntimeException('Ruta duplicada.');$seen[$key]=true;
    if(str_contains($name,'..')||str_contains($name,'\\')||str_starts_with($name,'/'))throw new \RuntimeException('Ruta inválida.');
    $opsys=0;$attr=0;$z->getExternalAttributesIndex($i,$opsys,$attr);if((($attr>>16)&0170000)===0120000)throw new \RuntimeException('Enlace en ZIP.');
    if(str_ends_with($name,'/'))continue;
    if($s['size']>2*1024*1024||($total+=$s['size'])>30*1024*1024)throw new \RuntimeException('Contenido excesivo.');
    $bytes=$z->getFromIndex($i);if($bytes===false||strlen($bytes)!==$s['size']||hash('crc32b',$bytes)!==sprintf('%08x',$s['crc']))throw new \RuntimeException('Integridad ZIP incorrecta.');
    if($name==='tnl-update.json'){$manifest=json_decode($bytes,true,32,JSON_THROW_ON_ERROR);continue;}
    if(!$this->safe($name))throw new \RuntimeException('Ruta no actualizable: '.$name);
    $this->noLinks($name);$files[$name]=['sha256'=>hash('sha256',$bytes),'bytes'=>$bytes];
   }
   if(!is_array($manifest)||($manifest['format']??null)!==1||($manifest['channel']??'')!=='develop'||!preg_match('/^[a-zA-Z0-9.+-]{1,60}$/D',$manifest['version']??'')||!is_array($manifest['files']??null)||!$files)throw new \RuntimeException('Manifiesto incompatible.');
   $expected=$manifest['files'];$actual=array_map(fn($f)=>$f['sha256'],$files);ksort($expected);ksort($actual);
   if($expected!==$actual)throw new \RuntimeException('Los archivos no coinciden con el manifiesto.');
   foreach(['bootstrap.php','public/index.php','public/employee.php','public/mail.php','public/recovery.php','app/Admin/Users.php','app/Admin/Settings.php','app/Admin/Recovery.php','app/Mail/InternalMail.php','public/mcp.php'] as $required)if(!isset($files[$required]))throw new \RuntimeException('Falta archivo obligatorio: '.$required);
   return ['manifest'=>$manifest,'files'=>$files];
  } finally {$z->close();}
 }
 private function connect(): \PDO {return new \PDO('sqlite:'.$this->path('storage/system.sqlite'),null,null,[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION]);}
 private function columns(\PDO $db,string $table): array {return array_column($db->query('PRAGMA table_info('.$table.')')->fetchAll(\PDO::FETCH_ASSOC),'name');}
 public function migrate(\PDO $db,string $version): void {
  $db->beginTransaction();
  try {
   $cols=$this->columns($db,'users');
   if(in_array('address',$cols,true)&&!in_array('internal_address',$cols,true)){
    $db->exec('ALTER TABLE users RENAME TO legacy_020_users');
    $db->exec("CREATE TABLE users(id INTEGER PRIMARY KEY,username TEXT UNIQUE NOT NULL,display_name TEXT NOT NULL,internal_address TEXT UNIQUE NOT NULL,role TEXT NOT NULL DEFAULT 'employee',active INTEGER NOT NULL DEFAULT 1,can_send_external INTEGER NOT NULL DEFAULT 0,password_hash TEXT,created_at TEXT NOT NULL,credentials_version INTEGER NOT NULL DEFAULT 1,must_change_password INTEGER NOT NULL DEFAULT 0)");
    $db->exec("INSERT INTO users SELECT id,username,display_name,address,'employee',active,0,password_hash,created_at,credentials_version,must_change FROM legacy_020_users");
    $db->exec('CREATE TABLE internal_messages(id INTEGER PRIMARY KEY,sender_id INTEGER NOT NULL,recipient_id INTEGER NOT NULL,subject TEXT NOT NULL,body TEXT NOT NULL,created_at TEXT NOT NULL,read_at TEXT,deleted_by_sender INTEGER NOT NULL DEFAULT 0,deleted_by_recipient INTEGER NOT NULL DEFAULT 0,reply_to_id INTEGER)');
    $db->exec('INSERT INTO internal_messages SELECT id,sender_id,recipient_id,subject,body,created_at,read_at,deleted_sender,deleted_recipient,reply_to_id FROM messages');
    $db->exec("CREATE TABLE recovery_requests(id INTEGER PRIMARY KEY,username_hint TEXT NOT NULL,details TEXT NOT NULL DEFAULT '',status TEXT NOT NULL DEFAULT 'pending',created_at TEXT NOT NULL,resolved_at TEXT)");
    $db->exec('INSERT INTO recovery_requests(id,username_hint,details,status,created_at) SELECT id,identity,detail,status,created_at FROM requests');
    $db->exec("INSERT OR IGNORE INTO settings(key,value) SELECT 'company_name',value FROM settings WHERE key='company'");
    $db->exec("INSERT OR IGNORE INTO settings(key,value) SELECT 'company_domain',value FROM settings WHERE key='domain'");
   } elseif(!in_array('internal_address',$cols,true))throw new \RuntimeException('Esquema de usuarios no reconocido.');
   if(!in_array('created_at',$this->columns($db,'admins'),true))$db->exec("ALTER TABLE admins ADD COLUMN created_at TEXT NOT NULL DEFAULT ''");
   $db->prepare("INSERT INTO settings(key,value) VALUES('version',?) ON CONFLICT(key) DO UPDATE SET value=excluded.value")->execute([$version]);
   if($db->query('PRAGMA integrity_check')->fetchColumn()!=='ok')throw new \RuntimeException('SQLite no superó la integridad.');
   $db->commit();
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }
 private function lock() {
  $file=$this->path('storage/update.lock');$lock=fopen($file,'c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new \RuntimeException('Otra actualización está en curso.');return $lock;
 }
 private function journal(string $dir,array $data): void {$this->write($dir.'/journal.json',json_encode($data,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT));}
 public function apply(string $zip,string $sha): string {
  $lock=$this->lock();$backup=null;$journal=null;
  try {
   $package=$this->inspect($zip,$sha);
   if(PHP_VERSION_ID<80300)throw new \RuntimeException('Se requiere PHP 8.3 o posterior.');
   foreach(array_keys($package['files']) as $name){$this->noLinks($name);$parent=dirname($this->path($name));while(!file_exists($parent))$parent=dirname($parent);if(!is_writable($parent)||(is_file($this->path($name))&&!is_writable($this->path($name))))throw new \RuntimeException('Permisos insuficientes: '.$name);}
   $id=gmdate('Ymd-His').'-'.bin2hex(random_bytes(6));$backup=$this->path('storage/update-backups/'.$id);
   if(!mkdir($backup,0700,true))throw new \RuntimeException('No se pudo crear respaldo.');
   $db=$this->connect();$db->exec('PRAGMA busy_timeout=5000');
   $db->exec('VACUUM INTO '.$db->quote($backup.'/system.sqlite'));$db=null;chmod($backup.'/system.sqlite',0600);
   $journal=['id'=>$id,'version'=>$package['manifest']['version'],'status'=>'preparing','files'=>[]];
   foreach($package['files'] as $name=>$f){$exists=is_file($this->path($name));$hash=null;if($exists){$bytes=file_get_contents($this->path($name));if($bytes===false)throw new \RuntimeException('No se pudo leer archivo original.');$this->write($backup.'/files/'.$name,$bytes);$hash=hash('sha256',$bytes);}$journal['files'][$name]=['existed'=>$exists,'before'=>$hash,'after'=>$f['sha256']];}
   $journal['db_sha256']=hash_file('sha256',$backup.'/system.sqlite');$journal['status']='applying';$this->journal($backup,$journal);
   $migrated=false;
   try {
    foreach($package['files'] as $name=>$f)$this->write($this->path($name),$f['bytes']);
    $db=$this->connect();$this->migrate($db,$journal['version']);$db=null;$migrated=true;
    foreach($journal['files'] as $name=>$f){if(hash_file('sha256',$this->path($name))!==$f['after'])throw new \RuntimeException('Verificación final fallida.');if(str_ends_with($name,'.php')&&function_exists('opcache_invalidate'))opcache_invalidate($this->path($name),true);}
    $journal['status']='applied';$this->journal($backup,$journal);return $id;
   }catch(\Throwable $e){$db=null;$this->recover($backup,$journal,$migrated);$journal['status']='auto_restored';$this->journal($backup,$journal);throw new \RuntimeException('Actualización fallida; se restauró el respaldo. '.$e->getMessage(),0,$e);}
  }finally{flock($lock,LOCK_UN);fclose($lock);}
 }
 private function recover(string $backup,array $journal,bool $restoreDb=true): void {
  if(!hash_equals($journal['db_sha256'],hash_file('sha256',$backup.'/system.sqlite')))throw new \RuntimeException('Respaldo SQLite dañado.');
  foreach($journal['files'] as $name=>$f){if(!$this->safe($name))throw new \RuntimeException('Respaldo con ruta inválida.');$this->noLinks($name);if($f['existed']&&hash_file('sha256',$backup.'/files/'.$name)!==$f['before'])throw new \RuntimeException('Respaldo de archivo dañado.');}
  if($restoreDb){$db=$this->connect();if(strtolower((string)$db->query('PRAGMA journal_mode')->fetchColumn())==='wal'){$checkpoint=$db->query('PRAGMA wal_checkpoint(TRUNCATE)')->fetch(\PDO::FETCH_NUM);if((int)$checkpoint[0]!==0)throw new \RuntimeException('SQLite ocupado.');}$db=null;}
  foreach($journal['files'] as $name=>$f){$target=$this->path($name);if($f['existed'])$this->write($target,file_get_contents($backup.'/files/'.$name));elseif(is_file($target)&&!unlink($target))throw new \RuntimeException('No se pudo retirar archivo nuevo.');if(str_ends_with($name,'.php')&&function_exists('opcache_invalidate'))opcache_invalidate($target,true);}
  if($restoreDb){$this->write($this->path('storage/system.sqlite'),file_get_contents($backup.'/system.sqlite'));chmod($this->path('storage/system.sqlite'),0600);}
 }
 public function history(): array {
  $out=[];foreach(glob($this->path('storage/update-backups/*/journal.json'))?:[] as $file){$j=json_decode(file_get_contents($file),true);if(is_array($j))$out[]=$j;}usort($out,fn($a,$b)=>strcmp($b['id'],$a['id']));return $out;
 }
 public function restore(string $id): void {
  if(!preg_match('/^[0-9]{8}-[0-9]{6}-[a-f0-9]{12}$/D',$id))throw new \RuntimeException('Respaldo inválido.');
  $lock=$this->lock();try{$history=$this->history();$j=$history[0]??null;if(!$j||$j['id']!==$id||!in_array($j['status'],['applied','applying'],true))throw new \RuntimeException('Solo se puede restaurar el último respaldo activo.');$dir=$this->path('storage/update-backups/'.$id);$this->recover($dir,$j);$j['status']='restored';$this->journal($dir,$j);}finally{flock($lock,LOCK_UN);fclose($lock);}
 }
}
