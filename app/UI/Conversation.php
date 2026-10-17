<?php
declare(strict_types=1);
namespace TNL\UI;
require_once __DIR__.'/MessageHtml.php';
final class Conversation {
 public static function render(array $messages,bool $remote,string $currentId=''): void {
  $seen=[];$unique=[];foreach($messages as $m){$id=$m['headers']['message-id']??'';if($id!==''&&isset($seen[$id]))continue;if($id!=='')$seen[$id]=true;$unique[]=$m;}
  usort($unique,fn($a,$b)=>(strtotime($a['headers']['date']??'')?:0)<=>(strtotime($b['headers']['date']??'')?:0));
  if(count($unique)<2)return;
  echo '<section class="panel conversation-panel"><h2>Conversación · '.count($unique).' mensajes</h2><p>Mensajes relacionados disponibles para tu cuenta.</p>';
  foreach($unique as $m){if($currentId!==''&&($m['headers']['message-id']??'')===$currentId)continue;echo '<details><summary>'.\tnl_escape(($m['headers']['from']??'').' · '.($m['headers']['date']??'')).'</summary>';if(!empty($m['html']))echo '<iframe class="mail-html-view" title="Mensaje de la conversación" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" data-mail-view srcdoc="'.\tnl_escape(MessageHtml::document($m,$remote)).'"></iframe>';else echo '<pre class="message-text">'.\tnl_escape($m['body']??'').'</pre>';echo '</details>';}
  echo '</section>';
 }
}
