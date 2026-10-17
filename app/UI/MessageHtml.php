<?php
declare(strict_types=1);
namespace TNL\UI;
final class MessageHtml {
 public static function fragment(string $html,array $images=[],bool $remote=false,bool $collapseQuotes=false): string {
  if(!class_exists('DOMDocument')||strlen($html)>1048576)return '';
  $previous=libxml_use_internal_errors(true);$doc=new \DOMDocument();
  try{if(!$doc->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING))return '';}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
  $body=$doc->getElementsByTagName('body')->item(0);if(!$body)return '';
  $render=function(\DOMNode $node,bool $pre=false)use(&$render,$images,$remote,$collapseQuotes): string {
   if($node instanceof \DOMText){$text=$node->textContent;if(!$pre)$text=preg_replace('/[\s\x{00A0}]+/u',' ',$text)??'';return htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
   if(!$node instanceof \DOMElement)return '';$tag=strtolower($node->tagName);if($tag==='div'&&preg_match('/(?:gmail_quote|yahoo_quoted|moz-quote)/i',$node->getAttribute('class')))$tag='blockquote';
   if($node->hasAttribute('hidden')||preg_match('/(?:^|;)\s*(?:display\s*:\s*none|visibility\s*:\s*hidden|mso-hide\s*:\s*all)(?:\s*!important)?\s*(?:;|$)/i',$node->getAttribute('style')))return '';
   if(in_array($tag,['script','style','iframe','object','embed','svg','math','form','input','button','select','textarea','meta','link','base','head','video','audio','canvas','template'],true))return '';
   if($tag==='img'){
    $src=trim($node->getAttribute('src'));$alt=htmlspecialchars($node->getAttribute('alt')?:'Imagen',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$safe='';
    if(str_starts_with(strtolower($src),'cid:'))$safe=$images[trim(rawurldecode(substr($src,4)), '<> ')]??'';else $safe=$images[$src]??'';
    if($safe===''&&strlen($src)<=700000&&preg_match('~^data:image/(?:png|jpeg|gif|webp);base64,~i',$src)){ $bytes=base64_decode(substr($src,strpos($src,',')+1),true);$info=$bytes!==false?@getimagesizefromstring($bytes):false;if($info&&in_array($info['mime'],['image/png','image/jpeg','image/gif','image/webp'],true))$safe='data:'.$info['mime'].';base64,'.base64_encode($bytes); }
    if(str_starts_with($src,'//'))$src='https:'.$src;if(str_starts_with(strtolower($src),'http://'))$src='https://'.substr($src,7);
    if($safe!==''&&!preg_match('~^data:image/(?:png|jpeg|gif|webp);base64,[A-Za-z0-9+/=]+$~D',$safe))$safe='';
    if($safe===''&&$remote&&self::https($src))$safe=$src;
    if($safe==='')return '<span class="mail-image-placeholder">'.$alt.' · imagen externa o no disponible</span>';
    return '<img src="'.htmlspecialchars($safe,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'" alt="'.$alt.'" referrerpolicy="no-referrer" loading="eager">';
   }
   $children='';foreach($node->childNodes as $child)$children.=$render($child,$pre||$tag==='pre');
   if(!in_array($tag,['div','p','span','table','colgroup','col','tbody','thead','tfoot','tr','td','th','b','strong','i','em','u','s','a','br','hr','h1','h2','h3','h4','h5','h6','ul','ol','li','blockquote','details','summary','pre','code','center'],true))return $children;
   if($tag==='table'&&trim(str_replace("\xC2\xA0",' ',strip_tags(html_entity_decode($children,ENT_QUOTES|ENT_HTML5,'UTF-8'))))===''&&!str_contains($children,'<img')&&!str_contains($children,'mail-image-placeholder'))return '';
   if($tag==='blockquote'&&$collapseQuotes)return '<details class="quoted-mail"><summary>Mostrar texto citado</summary><blockquote>'.$children.'</blockquote></details>';
   if(in_array($tag,['div','p','span','center'],true)&&trim(strip_tags(html_entity_decode($children,ENT_QUOTES|ENT_HTML5,'UTF-8')))===''&&!str_contains($children,'<img')&&!str_contains($children,'mail-image-placeholder'))return '';
   if($tag==='br'||$tag==='hr')return '<'.$tag.'>';
   $attrs='';if($tag==='a'){$href=trim($node->getAttribute('href'));if(self::https($href))$attrs=' href="'.htmlspecialchars($href,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'" target="_blank" rel="noopener noreferrer"';}
   if(in_array($tag,['td','th'],true))foreach(['colspan','rowspan'] as $a){$value=(int)$node->getAttribute($a);if($value>0&&$value<=20)$attrs.=' '.$a.'="'.$value.'"';}
   $styles=[];$style=$node->getAttribute('style');
   foreach(explode(';',$style) as $rule){$pair=explode(':',$rule,2);if(count($pair)!==2)continue;$prop=strtolower(trim($pair[0]));$value=strtolower(trim($pair[1]));
    if(in_array($prop,['text-align','vertical-align'],true)&&preg_match('/^(left|right|center|justify|top|middle|bottom)$/D',$value))$styles[$prop]=$value;
    if($prop==='font-weight'&&preg_match('/^(normal|bold|[1-9]00)$/D',$value))$styles[$prop]=$value;
   }
   if(in_array($tag,['table','td','th','col'],true)){$width=trim($node->getAttribute('width'));if($width===''&&preg_match('/(?:^|;)\s*width\s*:\s*([0-9.]+(?:px|%))/i',$style,$wm))$width=$wm[1];if(preg_match('/^([0-9]{1,4})(%|px)?$/D',$width,$wm)){$n=(int)$wm[1];$unit=$wm[2]??'px';if($n>0)$styles['width']=min($unit==='%'?100:1000,$n).$unit;}}
   if($styles){$css='';foreach($styles as $k=>$v)$css.=$k.':'.$v.';';$attrs.=' style="'.$css.'"';}
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
  $css=htmlspecialchars(\tnl_url('assets/message.css?v=0.8.1'),ENT_QUOTES,'UTF-8');
  $csp="default-src 'none'; img-src data:".($remote?" https:":"")."; style-src 'self' 'unsafe-inline'; script-src 'none'; connect-src 'none'; base-uri 'none'; form-action 'none'";
  return '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="'.htmlspecialchars($csp,ENT_QUOTES,'UTF-8').'"><link rel="stylesheet" href="'.$css.'"></head><body>'.self::fragment($message['html']??'',$message['inline_images']??[],$remote,true).'</body></html>';
 }
}
