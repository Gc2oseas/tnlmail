<?php
declare(strict_types=1);
// Independiente de bootstrap.php para recuperar ambas versiones.
require dirname(__DIR__).'/app/Update/Engine.php';
use TNL\Update\Engine;
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
$root=dirname(__DIR__);$dbPath=$root.'/storage/system.sqlite';
function up_e(mixed $v): string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
// Elige únicamente una sesión existente; el token CSRF se genera en esta página.
$sessionName=isset($_COOKIE['tnl_session'])?'tnl_session':'tnl_mail';
session_name($sessionName);session_set_cookie_params(['path'=>'/','httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax']);session_start();
if(!is_file($dbPath)||!isset($_SESSION['admin_id'])){http_response_code(403);exit('Inicia sesión como administrador en index.php y vuelve a Actualizaciones.');}
$db=new PDO('sqlite:'.$dbPath,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$s=$db->prepare('SELECT password_hash FROM admins WHERE id=?');$s->execute([$_SESSION['admin_id']]);$adminHash=$s->fetchColumn();$s->closeCursor();$s=null;$db=null;
if(!$adminHash){http_response_code(403);exit('Administrador inválido.');}
$_SESSION['update_csrf']??=bin2hex(random_bytes(32));$engine=new Engine($root);$error='';$notice='';
try {
 if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!hash_equals($_SESSION['update_csrf'],(string)($_POST['csrf']??'')))throw new RuntimeException('La solicitud caducó.');
  $action=(string)($_POST['action']??'');
  if($action==='preview'){
   if(!extension_loaded('zip'))throw new RuntimeException('Habilita la extensión ZIP de PHP.');
   $f=$_FILES['package']??null;if(!$f||$f['error']!==UPLOAD_ERR_OK||!is_uploaded_file($f['tmp_name']))throw new RuntimeException('No se recibió un ZIP válido.');
   $sha=strtolower(trim((string)($_POST['sha256']??'')));$package=$engine->inspect($f['tmp_name'],$sha);
   $stage=$root.'/storage/update-stage';if(!is_dir($stage)&&!mkdir($stage,0700,true))throw new RuntimeException('No se pudo preparar la actualización.');
   $path=$stage.'/'.bin2hex(random_bytes(16)).'.zip';if(!move_uploaded_file($f['tmp_name'],$path))throw new RuntimeException('No se pudo guardar el ZIP.');chmod($path,0600);
   if(isset($_SESSION['update_pending']['path']))@unlink($_SESSION['update_pending']['path']);
   $_SESSION['update_pending']=['path'=>$path,'sha'=>$sha,'version'=>$package['manifest']['version'],'files'=>array_keys($package['files']),'time'=>time()];
  }elseif($action==='apply'||$action==='restore'){
   if(empty($_POST['paused']))throw new RuntimeException('Confirma que nadie está usando el entorno de pruebas.');
   if(!password_verify((string)($_POST['password']??''),$adminHash))throw new RuntimeException('Contraseña de administrador incorrecta.');
   if($action==='apply'){$p=$_SESSION['update_pending']??null;if(!$p||time()-$p['time']>1800)throw new RuntimeException('Carga y verifica de nuevo el paquete.');$id=$engine->apply($p['path'],$p['sha']);unlink($p['path']);unset($_SESSION['update_pending']);$notice='Actualización aplicada. Respaldo: '.$id.'. Vuelve al panel e inicia sesión de nuevo.';}
   else{if(empty($_POST['discard']))throw new RuntimeException('Confirma la pérdida de los cambios posteriores al respaldo.');$engine->restore((string)($_POST['backup']??''));$notice='Archivos y SQLite restaurados. Vuelve al panel e inicia sesión de nuevo.';}
  }else throw new RuntimeException('Acción desconocida.');
 }
}catch(Throwable $e){$error=$e->getMessage();}
$pending=$_SESSION['update_pending']??null;$history=$engine->history();
?><!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Actualizaciones · TNL Mail</title>
<style>body{font:16px system-ui;background:#f4f7fa;color:#18344a;margin:0}main{max-width:850px;margin:40px auto;padding:24px}section{background:white;padding:24px;margin:20px 0;border:1px solid #d9e3eb;border-radius:12px}label{display:block;margin:16px 0}input:not([type=checkbox]){display:block;padding:10px;max-width:95%;width:440px}button{padding:12px 20px;background:#235d87;color:white;border:0;border-radius:6px}.error{color:#a32121}.success{color:#17613c}li{overflow-wrap:anywhere}</style>
<main><a href="index.php">← Administración</a><h1>Actualizaciones</h1><p>Canal de pruebas: develop. Solo paquetes preparados con manifiesto; un ZIP directo de GitHub no es compatible.</p>
<?php if($error):?><p class="error"><?=up_e($error)?></p><?php endif;?><?php if($notice):?><p class="success"><?=up_e($notice)?></p><?php endif;?>
<section><h2>1. Comprobar paquete</h2><p>Se comprueban SHA-256, integridad, rutas y contenido antes de modificar la aplicación. El hash no sustituye una firma digital: usa paquetes de confianza.</p><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=up_e($_SESSION['update_csrf'])?>"><input type="hidden" name="action" value="preview"><label>ZIP (máximo 20 MB)<input type="file" name="package" accept=".zip" required></label><label>SHA-256 proporcionado con el paquete<input name="sha256" pattern="[a-fA-F0-9]{64}" required></label><button>Comprobar sin instalar</button></form></section>
<?php if($pending):?><section><h2>2. Instalar <?=up_e($pending['version'])?></h2><p>Se reemplazarán <?=count($pending['files'])?> archivos. Se respaldarán los originales y SQLite. Se conservarán usuarios, contraseñas, mensajes y token MCP al migrar desde 0.2.0.</p><details><summary>Archivos</summary><ul><?php foreach($pending['files'] as $name):?><li><?=up_e($name)?></li><?php endforeach;?></ul></details><form method="post"><input type="hidden" name="csrf" value="<?=up_e($_SESSION['update_csrf'])?>"><input type="hidden" name="action" value="apply"><label><input type="checkbox" name="paused" required> Nadie más está usando el entorno de pruebas; he detenido las solicitudes externas.</label><label>Contraseña del administrador<input type="password" name="password" autocomplete="current-password" required></label><button>Respaldar e instalar</button></form></section><?php endif;?>
<section><h2>Historial y restauración</h2><p>La restauración recupera también SQLite: perderás los mensajes y cambios realizados después del respaldo. Conserva además una copia externa. Si una operación se interrumpe, vuelve a esta página para restaurar el respaldo marcado «applying».</p><?php foreach($history as $i=>$j):?><p><?=up_e($j['id'].' · '.$j['version'].' · '.$j['status'])?></p><?php if($i===0&&in_array($j['status'],['applied','applying'],true)):?><form method="post"><input type="hidden" name="csrf" value="<?=up_e($_SESSION['update_csrf'])?>"><input type="hidden" name="action" value="restore"><input type="hidden" name="backup" value="<?=up_e($j['id'])?>"><label><input type="checkbox" name="paused" required> He detenido el uso del entorno.</label><label><input type="checkbox" name="discard" required> Acepto descartar los datos posteriores al respaldo.</label><label>Contraseña del administrador<input type="password" name="password" required autocomplete="current-password"></label><button>Restaurar respaldo</button></form><?php endif;?><?php endforeach;?><?php if(!$history):?><p>Aún no hay actualizaciones.</p><?php endif;?></section></main></html>
