<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';require_once dirname(__DIR__).'/app/Auth/SessionUser.php';
header('Cache-Control: no-store');tnl_session();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('POST requerido.');}tnl_check_csrf();$identity=\TNL\Auth\SessionUser::require(tnl_db());
if(($_POST['action']??'')==='logout'){if(!empty($_COOKIE['tnl_device'])){require_once dirname(__DIR__).'/app/Admin/DeviceLock.php';(new \TNL\Admin\DeviceLock(tnl_db()))->forget((string)$_COOKIE['tnl_device']);setcookie('tnl_device','',['expires'=>time()-3600,'path'=>tnl_url(''),'httponly'=>true,'samesite'=>'Strict']);}$_SESSION=[];session_regenerate_id(true);header('Location: '.tnl_url('index.php'));exit;}
if(($_POST['action']??'')==='lock'){if(!empty($_COOKIE['tnl_device']))$_SESSION['pin_locked']=true;header('Location: '.tnl_url('lock.php'));exit;}http_response_code(400);echo 'Acción no disponible.';
