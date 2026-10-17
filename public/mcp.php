<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/app/Admin/Settings.php';
use TNL\Admin\Settings;
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
function reply(mixed $id,mixed $result=null,?array $error=null): never {echo json_encode(['jsonrpc'=>'2.0','id'=>$id]+($error?['error'=>$error]:['result'=>$result]),JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: POST');http_response_code(405);reply(null,null,['code'=>-32600,'message'=>'POST requerido']);}
$loop=in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true);
if((empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off')&&!$loop){http_response_code(403);reply(null,null,['code'=>-32001,'message'=>'HTTPS requerido']);}
if(!is_file(dirname(__DIR__).'/storage/system.sqlite')){http_response_code(503);reply(null,null,['code'=>-32000,'message'=>'No instalado']);}
$db=tnl_db();$auth=(string)($_SERVER['HTTP_AUTHORIZATION']??'');$hash='';
if(preg_match('/^Bearer ([a-f0-9]{64})$/D',$auth,$m))$hash=hash('sha256',$m[1]);
$read=$hash!==''&&hash_equals(Settings::get($db,'mcp_token_hash','invalid'),$hash);
$deploy=$hash!==''&&Settings::get($db,'deploy_enabled')==='1'&&hash_equals(Settings::get($db,'deploy_token_hash','invalid'),$hash);
if(!$read&&!$deploy){http_response_code(401);header('WWW-Authenticate: Bearer');reply(null,null,['code'=>-32001,'message'=>'No autorizado']);}
$raw=file_get_contents('php://input',false,null,0,65537);if($raw===false||strlen($raw)>65536){http_response_code(413);reply(null,null,['code'=>-32600,'message'=>'Solicitud demasiado grande']);}
try{$req=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){reply(null,null,['code'=>-32700,'message'=>'JSON inválido']);}
if(!is_array($req)||($req['jsonrpc']??'')!=='2.0'){reply(null,null,['code'=>-32600,'message'=>'JSON-RPC inválido']);}
$id=$req['id']??null;$method=$req['method']??'';
if($method==='initialize')reply($id,['protocolVersion'=>'2025-03-26','capabilities'=>['tools'=>['listChanged'=>false]],'serverInfo'=>['name'=>'tnlmail','version'=>Settings::get($db,'version','0.3.0-dev')]]);
if($method==='notifications/initialized'){http_response_code(202);exit;}
function tool(string $name,string $description,array $properties=[],array $required=[]): array{return ['name'=>$name,'description'=>$description,'inputSchema'=>['type'=>'object','properties'=>$properties?:new stdClass(),'required'=>$required,'additionalProperties'=>false]];}
$string=['type'=>'string'];$sha=['type'=>'string','pattern'=>'^[a-f0-9]{40}$'];
$tools=[tool('corporate_mail_settings','Detectar servidores públicos del dominio empresarial configurado; no usa contraseñas'),tool('system_status','Estado de PHP y versión, sin credenciales'),tool('deploy_status','Revisión instalada y último respaldo')];
if($read){$tools[]=tool('mail_counts','Recuentos agregados, sin contenidos');$tools[]=tool('recent_events','Últimos eventos administrativos');}
if($deploy){$tools[]=tool('github_preview','Preparar la revisión exacta actual de develop; sin instalar',['commit'=>$sha],['commit']);$tools[]=tool('github_deploy','Aplicar una vista previa verificada y respaldar la instalación',['commit'=>$sha,'ticket'=>['type'=>'string','pattern'=>'^[a-f0-9]{32}$']],['commit','ticket']);$tools[]=tool('github_rollback','Restaurar archivos y SQLite del último respaldo; descarta los cambios posteriores',['backup'=>$string,'confirm_data_loss'=>['type'=>'boolean']],['backup','confirm_data_loss']);}
if($deploy)$tools[]=tool('external_mail_probe','Probar autenticación IMAP/SMTP de la cuenta de pruebas; no guarda credenciales ni envía correos',['email'=>['type'=>'string','format'=>'email'],'password'=>['type'=>'string','maxLength'=>512],'server'=>['type'=>'string','enum'=>['cache.lat','premium902-2.web-hosting.com']]],['email','password']);
if($deploy)$tools[]=tool('mail_test_send','Enviar prueba fija a gc2oseas@gmail.com con autorización del titular',['request_id'=>['type'=>'string','pattern'=>'^[a-f0-9]{32}$'],'password'=>['type'=>'string','maxLength'=>512]],['request_id']);
if($method==='tools/list')reply($id,['tools'=>$tools]);
if($method==='tools/call'){
 $name=$req['params']['name']??'';$args=$req['params']['arguments']??[];
 if(!is_string($name)||!is_array($args)||!in_array($name,array_column($tools,'name'),true))reply($id,null,['code'=>-32602,'message'=>'Herramienta o argumentos no autorizados']);
 try {
  if($name==='corporate_mail_settings'){
   require_once dirname(__DIR__).'/app/Mail/ServerProfiles.php';$domain=Settings::get($db,'company_domain');
   if(!filter_var('discovery@'.$domain,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Configura primero el dominio de la empresa.');
   $profile=\TNL\Mail\ServerProfiles::detect($db,'discovery@'.$domain);
   if($profile)foreach(['imap_username','smtp_username','imap_username_template','smtp_username_template'] as $privateField)unset($profile[$privateField]);
   $out=['domain'=>$domain,'profile'=>$profile,'credentials_used'=>false,'xml_parser'=>class_exists('DOMDocument')];
  }
  elseif($name==='system_status')$out=['php'=>PHP_VERSION,'pdo_sqlite'=>extension_loaded('pdo_sqlite'),'zip'=>extension_loaded('zip'),'app_version'=>Settings::get($db,'version'),'timestamp'=>gmdate('c')];
  elseif($name==='mail_counts')$out=['users'=>(int)$db->query('SELECT count(*) FROM users')->fetchColumn(),'messages'=>(int)$db->query('SELECT count(*) FROM internal_messages')->fetchColumn(),'recovery_pending'=>(int)$db->query("SELECT count(*) FROM recovery_requests WHERE status='pending'")->fetchColumn()];
  elseif($name==='recent_events')$out=$db->query('SELECT action,actor,created_at FROM events ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
  elseif($name==='mail_test_send'){require_once dirname(__DIR__).'/app/Mail/DiagnosticSend.php';$out=\TNL\Mail\DiagnosticSend::send($db,tnl_path('storage'),(string)($args['request_id']??''),(string)($args['password']??''));}
  elseif($name==='external_mail_probe'){require_once dirname(__DIR__).'/app/Mail/ExternalMail.php';$server=(string)($args['server']??'premium902-2.web-hosting.com');if(!in_array($server,['cache.lat','premium902-2.web-hosting.com'],true))throw new RuntimeException('Servidor no permitido para la prueba.');$config=\TNL\Mail\ExternalAccount::config(['email'=>$args['email']??'','imap_host'=>$server,'smtp_host'=>$server]);$password=(string)($args['password']??'');if($password===''||strlen($password)>512||preg_match('/[\r\n\x00]/',$password))throw new RuntimeException('Credencial inválida.');$out=\TNL\Mail\ExternalMail::probe($config+['password'=>$password]);}
  else {
   require_once dirname(__DIR__).'/app/Deploy/GitHub.php';$db=null;$github=new \TNL\Deploy\GitHub(dirname(__DIR__));
   if($name==='deploy_status')$out=$github->status();
   elseif($name==='github_preview')$out=$github->preview((string)($args['commit']??''));
   elseif($name==='github_deploy')$out=$github->deploy((string)($args['ticket']??''),(string)($args['commit']??''));
   elseif($name==='github_rollback'){if(($args['confirm_data_loss']??false)!==true)throw new RuntimeException('Confirma explícitamente que se descartarán los datos posteriores al respaldo.');$out=$github->rollback((string)($args['backup']??''));}
   else throw new RuntimeException('Herramienta desconocida.');
  }
  reply($id,['content'=>[['type'=>'text','text'=>json_encode($out,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]]]);
 }catch(Throwable $e){reply($id,['isError'=>true,'content'=>[['type'=>'text','text'=>$e->getMessage()]]]);}
}
reply($id,null,['code'=>-32601,'message'=>'Método no implementado']);
