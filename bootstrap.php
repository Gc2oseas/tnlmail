<?php
declare(strict_types=1);
if(is_file(__DIR__.'/storage/deploy-maintenance')&&!in_array(basename((string)($_SERVER['SCRIPT_FILENAME']??'')),['mcp.php','updates.php'],true)&&PHP_SAPI!=='cli'){
    http_response_code(503);header('Retry-After: 30');exit('Actualización en curso. Intenta nuevamente en unos segundos.');
}
const TNL_ROOT = __DIR__;
// The updater creates new directories as 0700. Only this public route needs
// web-server traversal; storage and application directories stay private.
$adminRouteDir=__DIR__.'/public/admin';
if(PHP_OS_FAMILY!=='Windows'&&is_dir($adminRouteDir)&&!is_link($adminRouteDir)){
    $routeMode=fileperms($adminRouteDir);
    if($routeMode!==false&&($routeMode&0055)!==0055&&!chmod($adminRouteDir,0755))error_log('TNL Mail: cannot set public/admin traversal permissions');
}
unset($adminRouteDir,$routeMode);
function tnl_path(string $relative): string {
    if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/')) {
        throw new InvalidArgumentException('Invalid relative path');
    }
    return TNL_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}
function tnl_db(): PDO {
    $path = tnl_path('storage/system.sqlite');
    if (!is_file($path)) { throw new RuntimeException('TNL Mail is not installed.'); }
    $db = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA busy_timeout = 5000');
    return $db;
}
function tnl_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    if (!headers_sent()) {
        session_name('tnl_session');
        session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','httponly'=>true,'samesite'=>'Lax']);
    }
    session_start();
    if(PHP_SAPI!=='cli'&&is_file(tnl_path('storage/system.sqlite'))){
        $page=basename((string)($_SERVER['SCRIPT_FILENAME']??''));
        $device=(string)($_COOKIE['tnl_device']??'');
        if($device!==''&&!in_array($page,['mcp.php','updates.php','lock.php'],true)){
            if(($_POST['action']??'')==='logout'){
                require_once TNL_ROOT.'/app/Admin/DeviceLock.php';
                (new \TNL\Admin\DeviceLock(tnl_db()))->forget($device);
                setcookie('tnl_device','',['expires'=>time()-3600,'path'=>tnl_url(''),'httponly'=>true,'samesite'=>'Strict']);
            }else{
                $last=(int)($_SESSION['pin_last_activity']??time());
                if(!isset($_SESSION['admin_id'])&&!isset($_SESSION['employee_id'])||time()-$last>900)$_SESSION['pin_locked']=true;
                if(!empty($_SESSION['pin_locked'])){header('Location: '.tnl_url('lock.php'));exit;}
                $_SESSION['pin_last_activity']=time();
                header('Refresh: 905; url='.tnl_url('lock.php'));
            }
        }
    }
}
function tnl_csrf(): string {
    tnl_session();
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function tnl_check_csrf(): void {
    if (!hash_equals(tnl_csrf(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419); exit('Solicitud caducada. Recarga la página.');
    }
}
function tnl_escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function tnl_url(string $route): string {
    $script=str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME']??'/index.php'));
    $base=preg_replace('~/(?:admin/)?[^/]*$~','/',$script);
    $requested=(string)(parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH)??'');
    if(str_ends_with($base,'/public/')&&!str_starts_with($requested,$base))$base=substr($base,0,-7);
    return $base.$route;
}
