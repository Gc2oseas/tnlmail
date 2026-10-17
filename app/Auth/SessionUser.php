<?php
declare(strict_types=1);
namespace TNL\Auth;
require_once dirname(__DIR__).'/Admin/Users.php';
require_once dirname(__DIR__).'/Admin/Recovery.php';
final class SessionUser {
 public static function require(\PDO $db): array {
  \tnl_session();\TNL\Admin\Users::init($db);\TNL\Admin\Recovery::init($db);
  if(isset($_SESSION['employee_id'])){
   $q=$db->prepare('SELECT * FROM users WHERE id=? AND active=1');$q->execute([(int)$_SESSION['employee_id']]);$u=$q->fetch(\PDO::FETCH_ASSOC);
   if($u&&(int)$u['credentials_version']===(int)($_SESSION['credentials_version']??0)){
    if($u['must_change_password']){header('Location: '.\tnl_url('recovery.php'));exit;}
    return ['role'=>'employee','id'=>(int)$u['id'],'principal'=>'employee:'.$u['id'],'name'=>$u['display_name'],'user'=>$u];
   }
  }elseif(isset($_SESSION['admin_id'])){
   $q=$db->prepare('SELECT id,username,password_hash FROM admins WHERE id=?');$q->execute([(int)$_SESSION['admin_id']]);$u=$q->fetch(\PDO::FETCH_ASSOC);
   if($u)return ['role'=>'admin','id'=>(int)$u['id'],'principal'=>'admin:'.$u['id'],'name'=>$u['username'],'user'=>$u];
  }
  http_response_code(403);exit('Inicia sesión nuevamente.');
 }
}
