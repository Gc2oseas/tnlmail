<?php
declare(strict_types=1);
// Ejecutar desde CLI antes de cada commit que se vaya a desplegar.
$root=dirname(__DIR__);$version=$argv[1]??'';
if(!preg_match('/^[a-zA-Z0-9.+-]{1,60}$/D',$version)){fwrite(STDERR,"Uso: php scripts/build-manifest.php 0.2.2-dev\n");exit(1);}
function release_hash(string $file): string {$bytes=file_get_contents($file);if($bytes===false)throw new RuntimeException('No se pudo leer fuente.');$normalized=str_replace("\r\n","\n",$bytes);if($bytes!==$normalized&&file_put_contents($file,$normalized,LOCK_EX)!==strlen($normalized))throw new RuntimeException('No se pudo normalizar fuente.');return hash('sha256',$normalized);}
$files=['bootstrap.php'=>release_hash($root.'/bootstrap.php')];
foreach(['app','public'] as $dir){foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir,FilesystemIterator::SKIP_DOTS)) as $file){$relative=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));if($file->isLink())throw new RuntimeException('No se permiten enlaces.');if(in_array($relative,['app/Update/Engine.php','public/updates.php'],true))continue;if($file->isFile()&&preg_match('/\.(php|css|js|json|webmanifest)$/D',$relative))$files[$relative]=release_hash($file->getPathname());}}
ksort($files);file_put_contents($root.'/tnl-update.json',json_encode(['format'=>1,'channel'=>'develop','version'=>$version,'files'=>$files],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
echo 'Manifest: '.count($files)." archivos\n";
