<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
foreach([
 ['/index.php','/','/admin/'],
 ['/admin/index.php','/admin/','/admin/'],
 ['/tnlmaildev/public/index.php','/tnlmaildev/','/tnlmaildev/admin/'],
 ['/tnlmaildev/public/admin/index.php','/tnlmaildev/admin/','/tnlmaildev/admin/'],
 ['/tnlmaildev/public/admin/index.php','/tnlmaildev/public/admin/','/tnlmaildev/public/admin/']
] as [$script,$requested,$expected]){
 $_SERVER['SCRIPT_NAME']=$script;$_SERVER['REQUEST_URI']=$requested;
 if(tnl_url('admin/')!==$expected)throw new RuntimeException('Route failed: '.$script);
}
echo "PASS: root, admin, rewritten and direct public routes preserve mount path\n";
