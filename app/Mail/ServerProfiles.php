<?php
declare(strict_types=1);
namespace TNL\Mail;
require_once dirname(__DIR__).'/Admin/Settings.php';
require_once __DIR__.'/CorporateDiscovery.php';
final class ServerProfiles {
 public static function known(string $domain,array $mx=[]): ?array {
  $domain=strtolower($domain);$provider='';$zone='com';$organization=false;
  if(in_array($domain,['gmail.com','googlemail.com'],true))$provider='google';
  if(in_array($domain,['outlook.com','hotmail.com','live.com','msn.com','outlook.es','hotmail.es','live.co.uk','hotmail.co.uk','hotmail.fr','outlook.fr'],true))$provider='outlook';
  if(preg_match('/^(?:zoho|zohomail)\.(com|eu|in|com\.au|jp|ca|com\.cn)$/D',$domain,$m)){$provider='zoho';$zone=$m[1];}
  if($provider==='')foreach($mx as $host){
   $host=strtolower(rtrim((string)$host,'.'));
   if(preg_match('/(?:^|\.)google(?:mail)?\.com$/D',$host)){$provider='google';break;}
   if(str_ends_with($host,'.mail.protection.outlook.com')){$provider='microsoft365';break;}
   if(preg_match('/(?:^|\.)zoho\.(com|eu|in|com\.au|jp|ca|com\.cn)$/D',$host,$m)){$provider='zoho';$zone=$m[1];$organization=true;break;}
  }
  return match($provider){
   'google'=>['provider'=>'Gmail / Google Workspace','imap_host'=>'imap.gmail.com','smtp_host'=>'smtp.gmail.com','smtp_port'=>465,'auth'=>'app_password','hint'=>'Usa una contraseña de aplicación de Google si tu cuenta la permite. La contraseña habitual puede ser rechazada.'],
   'outlook','microsoft365'=>['provider'=>'Outlook / Microsoft 365','imap_host'=>'outlook.office365.com','smtp_host'=>$provider==='outlook'?'smtp-mail.outlook.com':'smtp.office365.com','smtp_port'=>587,'auth'=>'oauth','hint'=>'Microsoft exige autorización OAuth. Los servidores están detectados; la conexión con Microsoft todavía requiere integrar OAuth.'],
   'zoho'=>['provider'=>'Zoho Mail'.($organization?' · empresa':''),'imap_host'=>($organization?'imappro':'imap').'.zoho.'.$zone,'smtp_host'=>($organization?'smtppro':'smtp').'.zoho.'.$zone,'smtp_port'=>465,'auth'=>'password','hint'=>'Activa IMAP en Zoho. Si usas verificación en dos pasos, utiliza una contraseña de aplicación. El acceso depende de tu plan y región.'],
   default=>null
  };
 }
 public static function detect(\PDO $db,string $email,?\Closure $resolver=null,?CorporateDiscovery $discovery=null): ?array {
  if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)throw new \InvalidArgumentException('Dirección de correo inválida.');
  $domain=strtolower(substr(strrchr($email,'@'),1));
  $company=strtolower(\TNL\Admin\Settings::get($db,'company_domain'));
  if($domain===$company){
   $saved=\TNL\Admin\Settings::get($db,'company_mail_servers');
   if($saved!==''){$profile=json_decode($saved,true,16,JSON_THROW_ON_ERROR);if(($profile['domain']??'')===$domain)return $profile+['provider'=>'Servidor de la empresa','auth'=>'password','hint'=>'Configuración guardada por administración.'];}
  }
  if($domain===$company){
   $exists=$db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='external_accounts'")->fetchColumn();
   if($exists){
    $candidates=[];
    foreach($db->query("SELECT config FROM external_accounts WHERE owner GLOB 'admin:[0-9]*'")->fetchAll(\PDO::FETCH_COLUMN) as $json){
     try{$account=json_decode($json,true,16,JSON_THROW_ON_ERROR);}catch(\Throwable $e){continue;}
     $address=(string)($account['email']??'');
     if(!filter_var($address,FILTER_VALIDATE_EMAIL)||strtolower(substr(strrchr($address,'@'),1))!==$domain)continue;
     $imap=(string)($account['imap_host']??'');$smtp=(string)($account['smtp_host']??'');$port=(int)($account['smtp_port']??465);
     if(!CorporateDiscovery::host($imap)||!CorporateDiscovery::host($smtp)||!in_array($port,[465,587],true))continue;
     $p=['imap_host'=>$imap,'smtp_host'=>$smtp,'smtp_port'=>$port];$candidates[json_encode($p)]=$p;
    }
    if(count($candidates)===1)return array_values($candidates)[0]+['provider'=>'Correo corporativo','source'=>'Configuración corporativa existente','auth'=>'password','hint'=>'Servidores recuperados de una cuenta corporativa de administración. Cada usuario utiliza sus propias credenciales.'];
   }
  }
  $known=self::known($domain);if($known)return $known;
  $db->exec('CREATE TABLE IF NOT EXISTS mail_discovery_cache(domain TEXT PRIMARY KEY,profile TEXT,expires INTEGER NOT NULL)');
  $useCache=$resolver===null&&$discovery===null;
  if($useCache){
   $q=$db->prepare('SELECT profile FROM mail_discovery_cache WHERE domain=? AND expires>?');$q->execute([$domain,time()]);$cached=$q->fetch(\PDO::FETCH_ASSOC);
   if($cached){$profile=$cached['profile']!==null?json_decode($cached['profile'],true,16,JSON_THROW_ON_ERROR):null;return $profile?self::forEmail($profile,$email):null;}
  }
  $profile=null;
  if($resolver===null||$discovery!==null)$profile=($discovery??new CorporateDiscovery())->discover($domain,false);
  if($profile){if($useCache)self::cache($db,$domain,$profile);return self::forEmail($profile,$email);}
  // Provider MX fallback reads public metadata only; credentials are never used for discovery.
  $records=$resolver?$resolver($domain):@dns_get_record($domain,DNS_MX);
  $profile=self::known($domain,array_column($records?:[],'target'));
  if(!$profile&&($resolver===null||$discovery!==null))$profile=($discovery??new CorporateDiscovery())->templates($domain);
  if($useCache)self::cache($db,$domain,$profile);
  return $profile?self::forEmail($profile,$email):null;
 }
 private static function cache(\PDO $db,string $domain,?array $profile): void {
  $db->prepare('DELETE FROM mail_discovery_cache WHERE expires<?')->execute([time()]);
  if((int)$db->query('SELECT COUNT(*) FROM mail_discovery_cache')->fetchColumn()>=1000)$db->exec('DELETE FROM mail_discovery_cache WHERE domain IN (SELECT domain FROM mail_discovery_cache ORDER BY expires LIMIT 100)');
  $db->prepare('INSERT INTO mail_discovery_cache(domain,profile,expires) VALUES(?,?,?) ON CONFLICT(domain) DO UPDATE SET profile=excluded.profile,expires=excluded.expires')->execute([$domain,$profile?json_encode($profile,JSON_THROW_ON_ERROR):null,time()+($profile?3600:300)]);
 }
 private static function forEmail(array $profile,string $email): array {
  foreach(['imap','smtp'] as $protocol)if(isset($profile[$protocol.'_username_template'])){
   $username=CorporateDiscovery::username($profile[$protocol.'_username_template'],$email);
   if($username===null)throw new \InvalidArgumentException('El formato de usuario publicado no es compatible. Usa la configuración avanzada.');
   $profile[$protocol.'_username']=$username;
  }
  return $profile;
 }
 public static function configure(\PDO $db,array $input): array {
  if(($input['setup_mode']??'manual')!=='auto')return $input;
  $profile=self::detect($db,trim((string)($input['email']??'')));
  if(!$profile)throw new \InvalidArgumentException('No se reconoció el proveedor. Abre Configuración avanzada o pide a administración guardar los servidores de la empresa.');
  if($profile['auth']==='oauth')throw new \InvalidArgumentException($profile['hint']);
  return array_replace($input,['imap_host'=>$profile['imap_host'],'smtp_host'=>$profile['smtp_host'],'smtp_port'=>$profile['smtp_port'],'imap_username'=>$profile['imap_username']??trim($input['email']),'smtp_username'=>$profile['smtp_username']??trim($input['email'])]);
 }
}
