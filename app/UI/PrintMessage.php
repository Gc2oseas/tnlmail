<?php
declare(strict_types=1);
namespace TNL\UI;
require_once __DIR__.'/MessageHtml.php';
final class PrintMessage {
 public static function render(array $message): void {
  $h=$message['headers'];?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=\tnl_escape($h['subject']??'Correo')?> · TNL Mail</title><link rel="stylesheet" href="assets/print.css?v=0.7.0"><link rel="stylesheet" href="assets/message.css?v=0.7.0"><script src="assets/print.js?v=0.7.0" defer></script></head><body><nav class="print-tools"><button type="button" data-print>Imprimir / Guardar como PDF</button><p>En el diálogo de impresión puedes elegir «Guardar como PDF». Incluye el contenido visible y sus imágenes incrustadas; no los archivos adjuntos independientes.</p></nav><main><p class="brand">TNL Mail</p><h1><?=\tnl_escape($h['subject']??'(sin asunto)')?></h1><dl><?php foreach(['from'=>'De','to'=>'Para','date'=>'Fecha'] as $key=>$label):?><dt><?=$label?></dt><dd><?=\tnl_escape((string)($h[$key]??''))?></dd><?php endforeach;?></dl><?php if(!empty($message['html'])):?><div class="printed-mail"><?=MessageHtml::fragment($message['html'],$message['inline_images']??[],($_GET['images']??'')==='1')?></div><?php else:?><pre><?=\tnl_escape((string)$message['body'])?></pre><?php endif;?></main></body></html><?php
 }
}
