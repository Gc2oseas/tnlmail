<?php
declare(strict_types=1);
// Ejecutar desde CLI antes de cada commit que se vaya a desplegar.
$root=dirname(__DIR__);$version=$argv[1]??'';
if(!preg_match('/^[a-zA-Z0-9.+-]{1,60}$/D',$version)){fwrite(STDERR,"Uso: php scripts/build-manifest.php 0.2.2-dev\n");exit(1);}
$files=['bootstrap.php'=>hash_file('sha256',$root.'/bootstrap.php')];
foreach(['app','public'] as $dir){foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir,FilesystemIterator::SKIP_DOTS)) as $file){$relative=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));if($file->isLink())throw new RuntimeException('No se permiten enlaces.');if(in_array($relative,['app/Update/Engine.php','public/updates.php'],true))continue;if($file->isFile()&&preg_match('/\.(php|css|js|json|webmanifest)$/D',$relative))$files[$relative]=hash_file('sha256',$file->getPathname());}}
ksort($files);file_put_contents($root.'/tnl-update.json',json_encode(['format'=>1,'channel'=>'develop','version'=>$version,'files'=>$files],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
echo 'Manifest: '.count($files)." archivos\n";
