<?php
declare(strict_types=1);
namespace TNL\UI;
require_once __DIR__.'/MessageHtml.php';
final class Conversation {
 public static function render(array $messages,bool $remote,string $currentId=''): void {
  $seen=[];$unique=[];foreach($messages as $m){$id=$m['headers']['message-id']??'';if($id!==''&&isset($seen[$id])){if(!empty($m['outgoing']))$unique[$seen[$id]]['outgoing']=true;continue;}if($id!=='')$seen[$id]=count($unique);$unique[]=$m;}
  usort($unique,fn($a,$b)=>(strtotime($a['headers']['date']??'')?:0)<=>(strtotime($b['headers']['date']??'')?:0));
  if(!$unique)return;
  echo '<section class="conversation-panel chat-thread" aria-label="Conversación"><h2>Conversación · '.count($unique).' mensajes</h2>';
  foreach($unique as $m){$out=!empty($m['outgoing']);echo '<article class="chat-message '.($out?'chat-outgoing':'chat-incoming').'"><header><strong>'.\tnl_escape($out?'Tú':($m['headers']['from']??'Remitente')).'</strong><time>'.\tnl_escape($m['headers']['date']??'').'</time></header>';if(!empty($m['html']))echo '<iframe class="mail-html-view" title="'.($out?'Mensaje enviado':'Mensaje recibido').'" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" data-mail-view srcdoc="'.\tnl_escape(MessageHtml::document($m,$remote)).'"></iframe>';else echo '<pre class="message-text">'.\tnl_escape($m['body']??'').'</pre>';echo '</article>';}
  echo '</section>';
 }
}
