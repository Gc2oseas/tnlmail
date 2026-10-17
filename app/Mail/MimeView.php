<?php
declare(strict_types=1);
namespace TNL\Mail;
final class MimeView {
 public static function extract(string $raw): array {
  $result=['html'=>'','inline_images'=>[]];$parts=0;
  self::walk($raw,$result,$parts,0);return $result;
 }
 private static function walk(string $raw,array &$out,int &$parts,int $depth): void {
  if(++$parts>80||$depth>8)return;
  $split=preg_split('/\r?\n\r?\n/',$raw,2);$headers=ImapClient::headers($split[0]);$body=$split[1]??'';$type=$headers['content-type']??'text/plain';
  if(preg_match('/^multipart\//i',$type)&&preg_match('/boundary\s*=\s*(?:"([^"]+)"|([^;\s]+))/i',$type,$m)){
   $boundary=$m[1]!==''?$m[1]:$m[2];$children=preg_split('/(?:^|\r?\n)--'.preg_quote($boundary,'/').'(?:--)?[ \t]*(?:\r?\n|$)/',$body,82);
   foreach(array_slice($children,1) as $child)if(trim($child)!=='')self::walk($child,$out,$parts,$depth+1);return;
  }
  $encoding=strtolower($headers['content-transfer-encoding']??'');
  if($encoding==='base64'){$decoded=base64_decode(preg_replace('/\s+/','',$body),true);$body=$decoded===false?'':$decoded;}elseif($encoding==='quoted-printable')$body=quoted_printable_decode($body);
  if(preg_match('~^(image/(?:png|jpeg|gif|webp))(?:;|$)~i',$type,$m)){
   $cid=trim($headers['content-id']??'',"<> \t");$info=@getimagesizefromstring($body);
   if(strlen($cid)<250&&strlen($body)<=524288&&$info&&in_array(strtolower($info['mime']??''),['image/png','image/jpeg','image/gif','image/webp'],true)){ $url='data:'.strtolower($info['mime']).';base64,'.base64_encode($body);if($cid!=='')$out['inline_images'][$cid]=$url;$location=trim($headers['content-location']??'');if($location!==''&&strlen($location)<2048)$out['inline_images'][$location]=$url; }
   return;
  }
  if(str_contains(strtolower($headers['content-disposition']??''),'attachment'))return;
  if(preg_match('~^text/(?:markdown|x-markdown)(?:;|$)~i',$type)&&$out['html']===''){require_once dirname(__DIR__).'/ThirdParty/Parsedown/Parsedown.php';$out['html']=(new \Parsedown())->setSafeMode(true)->text($body);}
  if(preg_match('~^text/html(?:;|$)~i',$type)&&$out['html']===''){
   if(preg_match('/charset\s*=\s*"?([^;"\s]+)/i',$type,$m)&&function_exists('iconv')){$converted=@iconv($m[1],'UTF-8//IGNORE',$body);if($converted!==false)$body=$converted;}
   $out['html']=$body;
  }
 }
}
