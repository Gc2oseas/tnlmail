<?php
declare(strict_types=1);
namespace TNL\Mail;
require_once dirname(__DIR__).'/UI/MessageHtml.php';
final class MessageBody {
 public static function html(string $body,string $format): string {
  if($format==='markdown'){require_once dirname(__DIR__).'/ThirdParty/Parsedown/Parsedown.php';$body=(new \Parsedown())->setSafeMode(true)->text($body);}
  elseif($format!=='html')return '';
  return \TNL\UI\MessageHtml::fragment($body,[],true);
 }
 public static function plain(string $body,string $format): string {return $format==='plain'?$body:html_entity_decode(strip_tags(str_replace(['</p>','</div>','<br>','</li>'],"\n",self::html($body,$format))),ENT_QUOTES|ENT_HTML5,'UTF-8');}
}
