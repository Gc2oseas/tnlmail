<?php
declare(strict_types=1);
namespace TNL\Mail;
require_once dirname(__DIR__).'/Admin/Settings.php';
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
 public static function detect(\PDO $db,string $email,?\Closure $resolver=null): ?array {
  if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)throw new \InvalidArgumentException('Dirección de correo inválida.');
  $domain=strtolower(substr(strrchr($email,'@'),1));
  $company=strtolower(\TNL\Admin\Settings::get($db,'company_domain'));
  if($domain===$company){
   $saved=\TNL\Admin\Settings::get($db,'company_mail_servers');
   if($saved!==''){$profile=json_decode($saved,true,16,JSON_THROW_ON_ERROR);if(($profile['domain']??'')===$domain)return $profile+['provider'=>'Servidor de la empresa','auth'=>'password','hint'=>'Configuración guardada por administración.'];}
  }
  $known=self::known($domain);if($known)return $known;
  // Only public DNS MX metadata is read. No passwords or requests go to an autodiscover URL.
  $records=$resolver?$resolver($domain):@dns_get_record($domain,DNS_MX);
  return self::known($domain,array_column($records?:[],'target'));
 }
 public static function configure(\PDO $db,array $input): array {
  if(($input['setup_mode']??'manual')!=='auto')return $input;
  $profile=self::detect($db,trim((string)($input['email']??'')));
  if(!$profile)throw new \InvalidArgumentException('No se reconoció el proveedor. Abre Configuración avanzada o pide a administración guardar los servidores de la empresa.');
  if($profile['auth']==='oauth')throw new \InvalidArgumentException($profile['hint']);
  return array_replace($input,['imap_host'=>$profile['imap_host'],'smtp_host'=>$profile['smtp_host'],'smtp_port'=>$profile['smtp_port'],'imap_username'=>trim($input['email']),'smtp_username'=>trim($input['email'])]);
 }
}
