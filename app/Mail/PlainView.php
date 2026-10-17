<?php
declare(strict_types=1);
namespace TNL\Mail;
final class PlainView {
 public static function html(string $body,bool $flowed=false,bool $delsp=false): string {
  $rows=preg_split('/\r\n|\r|\n/',$body);$html='';$depth=0;$pending='';$pendingDepth=0;$soft=false;
  $emit=function(string $text,int $level)use(&$html,&$depth){while($depth<$level){$html.='<blockquote>';$depth++;}while($depth>$level){$html.='</blockquote>';$depth--;}$html.='<pre>'.($text===''?' ':htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')).'</pre>';};
  foreach($rows as $line){preg_match('/^(>*) ?(.*)$/D',$line,$m);$level=min(8,strlen($m[1]??''));$text=$m[2]??$line;if($level===0&&!$flowed)$text=$line;$next=$flowed&&str_ends_with($text,' ')&&$text!=='-- ';
   if($soft&&$level===$pendingDepth)$pending.=($delsp?'':' ').$text;else{if($pending!=='')$emit($pending,$pendingDepth);$pending=$text;$pendingDepth=$level;}if(!$next){$emit($pending,$pendingDepth);$pending='';}else $pending=rtrim($pending,' ');$soft=$next;
  }if($pending!=='')$emit($pending,$pendingDepth);while($depth-->0)$html.='</blockquote>';return $html;
 }
}
