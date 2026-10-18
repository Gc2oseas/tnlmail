<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/app/Admin/Users.php';
require_once dirname(__DIR__).'/app/Admin/Recovery.php';
require_once dirname(__DIR__).'/app/Mail/Directory.php';require_once dirname(__DIR__).'/app/Contacts/Book.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
tnl_session();$db=tnl_db();\TNL\Admin\Users::init($db);\TNL\Admin\Recovery::init($db);$allowed=false;
if(isset($_SESSION['employee_id'])){
 $q=$db->prepare('SELECT credentials_version,must_change_password FROM users WHERE id=? AND active=1');$q->execute([(int)$_SESSION['employee_id']]);$u=$q->fetch(PDO::FETCH_ASSOC);
 $allowed=$u&&(int)$u['credentials_version']===(int)($_SESSION['credentials_version']??0)&&!$u['must_change_password'];
}elseif(isset($_SESSION['admin_id'])){$q=$db->prepare('SELECT id FROM admins WHERE id=?');$q->execute([(int)$_SESSION['admin_id']]);$allowed=(bool)$q->fetchColumn();}
if(!$allowed){http_response_code(403);echo '{"results":[]}';exit;}
$term=(string)($_GET['q']??'');$results=\TNL\Mail\Directory::search($db,$term);if(strlen(trim($term))>=2&&strlen($term)<=80){$principal=isset($_SESSION['employee_id'])?'employee:'.(int)$_SESSION['employee_id']:'admin:'.(int)$_SESSION['admin_id'];$who=['principal'=>$principal];foreach(\TNL\Contacts\Book::search($db,$who,$term,1,10)['rows'] as $c)if($c['can_read'])$results[]=['username'=>$c['email'],'label'=>$c['first_name'].' '.$c['last_name'].' · '.$c['company'].' <'.$c['email'].'>'];}echo json_encode(['results'=>array_slice($results,0,25)],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
