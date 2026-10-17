<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require_once dirname(__DIR__).'/app/UI/Workspace.php';
require_once dirname(__DIR__).'/app/Admin/Settings.php';
use TNL\Admin\Settings;
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
tnl_session();if(!isset($_SESSION['admin_id'])){http_response_code(403);exit('Inicia sesión como administrador.');}
$db=tnl_db();$s=$db->prepare('SELECT id FROM admins WHERE id=?');$s->execute([$_SESSION['admin_id']]);if(!$s->fetchColumn()){http_response_code(403);exit('Administrador inválido.');}$s=null;
$token='';$error='';$notice='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 tnl_check_csrf();try {
  $s=$db->prepare('SELECT password_hash FROM admins WHERE id=?');$s->execute([$_SESSION['admin_id']]);$hash=$s->fetchColumn();$s=null;
  if(!password_verify((string)($_POST['password']??''),(string)$hash))throw new RuntimeException('Contraseña incorrecta.');
  if(($_POST['action']??'')==='enable'){$token=bin2hex(random_bytes(32));$db->beginTransaction();Settings::set($db,'deploy_token_hash',hash('sha256',$token));Settings::set($db,'deploy_enabled','1');$db->commit();$notice='Despliegues habilitados. Copia el token ahora: no se mostrará de nuevo.';}
  elseif(($_POST['action']??'')==='disable'){Settings::set($db,'deploy_enabled','0');Settings::set($db,'deploy_token_hash','');$notice='Despliegues deshabilitados.';}
  else throw new RuntimeException('Acción inválida.');
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();$error=$e->getMessage();}
}
$enabled=Settings::get($db,'deploy_enabled')==='1';
?><!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="assets/app.css?v=0.12.0"><script src="assets/workspace.js?v=0.12.0" defer></script><title>Despliegue GitHub · TNL Mail</title><body><div class="layout"><?php \TNL\UI\Workspace::sidebar('admin','deployment');?><main class="main"><section class="panel composer"><a href="admin/">← Administración</a><h1>Despliegue desde GitHub</h1><p>Repositorio: Gc2oseas/tnlmail · Rama: develop.</p><p>Estado: <?=$enabled?'Habilitado':'Deshabilitado'?>. El token MCP de diagnóstico sigue siendo de solo lectura.</p><p>El token nuevo permite instalar código y restaurar SQLite. Se descarga una revisión exacta de develop con hashes verificados, respaldo y mantenimiento temporal. No lo subas a GitHub.</p><?php if($error):?><p class="error"><?=tnl_escape($error)?></p><?php endif;?><?php if($notice):?><p><?=tnl_escape($notice)?></p><?php endif;?><?php if($token):?><label>Token de despliegue — copiar ahora<input readonly value="<?=tnl_escape($token)?>" autocomplete="off"></label><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=tnl_escape(tnl_csrf())?>"><label>Contraseña de administrador<input type="password" name="password" autocomplete="current-password" required></label><button name="action" value="enable"><?=$enabled?'Rotar token':'Habilitar y generar token'?></button><?php if($enabled):?><button name="action" value="disable">Deshabilitar despliegues</button><?php endif;?></form><p>Endpoint MCP: el mismo mcp.php de esta instalación. Después de activarlo utiliza el token de despliegue para github_preview y github_deploy. La restauración descarta los datos posteriores al respaldo.</p></section></main></div></body></html>
