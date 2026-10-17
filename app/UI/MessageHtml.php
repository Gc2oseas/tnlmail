<?php
declare(strict_types=1);
namespace TNL\UI;
final class MessageHtml {
 public static function fragment(string $html,array $images=[],bool $remote=false): string {
  if(!class_exists('DOMDocument')||strlen($html)>1048576)return '';
  $previous=libxml_use_internal_errors(true);$doc=new \DOMDocument();
  try{if(!$doc->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING))return '';}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
  $body=$doc->getElementsByTagName('body')->item(0);if(!$body)return '';
  $render=function(\DOMNode $node,bool $pre=false)use(&$render,$images,$remote): string {
   if($node instanceof \DOMText){$text=$node->textContent;if(!$pre)$text=preg_replace('/[\s\x{00A0}]+/u',' ',$text)??'';return htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
   if(!$node instanceof \DOMElement)return '';$tag=strtolower($node->tagName);
   if(in_array($tag,['script','style','iframe','object','embed','svg','math','form','input','button','select','textarea','meta','link','base','head','video','audio','canvas','template'],true))return '';
   if($tag==='img'){
    $src=trim($node->getAttribute('src'));$alt=htmlspecialchars($node->getAttribute('alt')?:'Imagen',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$safe='';
    if(str_starts_with(strtolower($src),'cid:'))$safe=$images[rawurldecode(substr($src,4))]??'';
    if($safe!==''&&!preg_match('~^data:image/(?:png|jpeg|gif|webp);base64,[A-Za-z0-9+/=]+$~D',$safe))$safe='';
    if($safe===''&&$remote&&self::https($src))$safe=$src;
    if($safe==='')return '<span class="mail-image-placeholder">'.$alt.' · imagen externa o no disponible</span>';
    return '<img src="'.htmlspecialchars($safe,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'" alt="'.$alt.'" referrerpolicy="no-referrer" loading="eager">';
   }
   $children='';foreach($node->childNodes as $child)$children.=$render($child,$pre||$tag==='pre');
   if(!in_array($tag,['div','p','span','table','tbody','thead','tfoot','tr','td','th','b','strong','i','em','u','s','a','br','hr','h1','h2','h3','h4','h5','h6','ul','ol','li','blockquote','pre','code','center'],true))return $children;
   if(in_array($tag,['div','p','span','tr','td','th','table','tbody','center'],true)&&trim(strip_tags(html_entity_decode($children,ENT_QUOTES|ENT_HTML5,'UTF-8')))===''&&!str_contains($children,'<img')&&!str_contains($children,'mail-image-placeholder'))return '';
   if($tag==='br'||$tag==='hr')return '<'.$tag.'>';
   $attrs='';if($tag==='a'){$href=trim($node->getAttribute('href'));if(self::https($href))$attrs=' href="'.htmlspecialchars($href,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'" target="_blank" rel="noopener noreferrer"';}
   if(in_array($tag,['td','th'],true))foreach(['colspan','rowspan'] as $a){$value=(int)$node->getAttribute($a);if($value>0&&$value<=20)$attrs.=' '.$a.'="'.$value.'"';}
   return '<'.$tag.$attrs.'>'.$children.'</'.$tag.'>';
  };
  $out='';foreach($body->childNodes as $node)$out.=$render($node);
  return preg_replace('/(?:<br>\s*){3,}/','<br><br>',$out)??'';
 }
 private static function https(string $url): bool {
  if(preg_match('/[\x00-\x20]/',$url))return false;$parts=parse_url($url);
  return is_array($parts)&&($parts['scheme']??'')==='https'&&!isset($parts['user'])&&!isset($parts['pass'])&&filter_var($url,FILTER_VALIDATE_URL)!==false;
 }
 public static function document(array $message,bool $remote=false): string {
  $css=htmlspecialchars(\tnl_url('assets/message.css?v=0.7.0'),ENT_QUOTES,'UTF-8');
  $csp="default-src 'none'; img-src data:".($remote?" https:":"")."; style-src 'self'; script-src 'none'; connect-src 'none'; base-uri 'none'; form-action 'none'";
  return '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="'.htmlspecialchars($csp,ENT_QUOTES,'UTF-8').'"><link rel="stylesheet" href="'.$css.'"></head><body>'.self::fragment($message['html']??'',$message['inline_images']??[],$remote).'</body></html>';
 }
}
