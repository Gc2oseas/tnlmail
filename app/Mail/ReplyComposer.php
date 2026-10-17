<?php
declare(strict_types=1);
namespace TNL\Mail;

final class ReplyComposer {
 public static function messageId(string $value): string {
  $value=trim($value);return strlen($value)<=250&&preg_match('/^<[^<>\s\x00-\x1f\x7f]+@[^<>\s\x00-\x1f\x7f]+>$/D',$value)?$value:'';
 }
 public static function thread(array $headers): array {
  $id=self::messageId((string)($headers['message-id']??''));if($id==='')return [];
  preg_match_all('/<[^<>\s\x00-\x1f\x7f]+@[^<>\s\x00-\x1f\x7f]+>/',(string)($headers['references']??'').' '.self::messageId((string)($headers['in-reply-to']??'')),$matches);
  $refs=array_values(array_unique(array_filter($matches[0]??[],fn($v)=>self::messageId($v)!==''&&$v!==$id)));$refs[]=$id;
  while(count($refs)>1&&strlen(implode(' ',$refs))>800)array_shift($refs);
  return ['in_reply_to'=>$id,'references'=>implode(' ',$refs)];
 }
 public static function address(string $header): string {
  if(preg_match('/[\r\n\x00]/',$header))return '';
  $header=trim($header);if(filter_var($header,FILTER_VALIDATE_EMAIL))return $header;
  // Conservador: una sola dirección. Listas o sintaxis ambigua requieren selección manual.
  if(preg_match('/^(?:"(?:[^"\\\\]|\\\\.)*"|[^",<>]*)\s*<([^<>]+)>$/D',$header,$m)&&filter_var(trim($m[1]),FILTER_VALIDATE_EMAIL))return trim($m[1]);return '';
 }
 public static function prepare(array $message,string $mode): array {
  if(!in_array($mode,['reply','forward'],true))throw new \InvalidArgumentException('Acción de mensaje inválida.');
  $h=$message['headers'];$subject=preg_replace('/[\r\n\x00]+/',' ',(string)($h['subject']??'(sin asunto)'));
  $prefix=$mode==='reply'?'Re: ':'Fwd: ';if(!preg_match($mode==='reply'?'/^re\s*:/i':'/^(fwd?|rv)\s*:/i',$subject))$subject=$prefix.$subject;
  $subject=self::cut($subject,200);
  $text=(string)($message['body']??'');if(strlen($text)>40000)$text=self::cut($text,40000)."\n[Texto original recortado]";
  if($mode==='reply'){
   $to=self::address((string)($h['reply-to']??''));if($to==='')$to=self::address((string)($h['from']??''));
   $body="\n\n----- Mensaje original -----\n".self::summary($h)."\n".preg_replace('/^/m','> ',$text);
   if(strlen($body)>49000)$body=self::cut($body,49000)."\n[Texto original recortado]";
   return ['to'=>$to,'subject'=>$subject,'body'=>$body,'thread'=>self::thread($h)];
  }
  return ['to'=>'','subject'=>$subject,'body'=>"\n\n----- Mensaje reenviado -----\n".self::summary($h)."\n".$text,'thread'=>[]];
 }
 private static function summary(array $h): string {
  $lines=[];foreach(['from'=>'De','to'=>'Para','date'=>'Fecha','subject'=>'Asunto'] as $key=>$label)if(isset($h[$key]))$lines[]=$label.': '.self::cut(preg_replace('/[\r\n\x00]+/',' ',(string)$h[$key]),1000);return implode("\n",$lines)."\n";
 }
 private static function cut(string $value,int $bytes): string {if(function_exists('mb_strcut'))return mb_strcut($value,0,$bytes,'UTF-8');$value=substr($value,0,$bytes);for($i=0;$i<4;$i++){if(preg_match('//u',$value)===1)return $value;$value=substr($value,0,-1);}if(function_exists('iconv')){$converted=@iconv('UTF-8','UTF-8//IGNORE',$value);if($converted!==false)return $converted;}return '';}
}
