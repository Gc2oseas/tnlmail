<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/app/Admin/Users.php';
require_once dirname(__DIR__).'/app/Admin/Recovery.php';
require_once dirname(__DIR__).'/app/Mail/ServerProfiles.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
tnl_session();$db=tnl_db();\TNL\Admin\Users::init($db);\TNL\Admin\Recovery::init($db);$allowed=false;
if(isset($_SESSION['employee_id'])){
 $q=$db->prepare('SELECT credentials_version,must_change_password FROM users WHERE id=? AND active=1');$q->execute([(int)$_SESSION['employee_id']]);$u=$q->fetch(PDO::FETCH_ASSOC);
 $allowed=$u&&(int)$u['credentials_version']===(int)($_SESSION['credentials_version']??0)&&!$u['must_change_password'];
}elseif(isset($_SESSION['admin_id'])){$q=$db->prepare('SELECT id FROM admins WHERE id=?');$q->execute([(int)$_SESSION['admin_id']]);$allowed=(bool)$q->fetchColumn();}
if(!$allowed){http_response_code(403);echo '{"results":[]}';exit;}
try{echo json_encode(['profile'=>\TNL\Mail\ServerProfiles::detect($db,(string)($_GET['email']??''))],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);}catch(Throwable $e){http_response_code(422);echo '{"profile":null}';}
