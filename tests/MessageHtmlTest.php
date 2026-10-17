<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Mail/ExternalMail.php';require dirname(__DIR__).'/app/UI/MessageHtml.php';
function yes(bool $v,string $label): void {if(!$v)throw new RuntimeException($label);echo "PASS: $label\n";}
$png='iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jZ3sAAAAASUVORK5CYII=';
$raw="Content-Type: multipart/related; boundary=outer\r\n\r\n--outer\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n<html><body><div style='height:5000px'><h2>Four images</h2>";
for($i=1;$i<=4;$i++)$raw.="<img src='cid:image$i' height='2000' onerror='alert(1)'>";
$raw.="<p style='height:9000px'>&nbsp;</p></div><script>alert(1)</script><img src='https://tracker.outside.test/pixel'><a href='javascript:alert(1)'>Bad link</a></body></html>\r\n";
for($i=1;$i<=4;$i++)$raw.="--outer\r\nContent-Type: image/png\r\nContent-ID: <image$i>\r\nContent-Transfer-Encoding: base64\r\n\r\n$png\r\n";
$raw.="--outer--\r\n";$view=\TNL\Mail\MimeView::extract($raw);
yes(count($view['inline_images'])===4,'extract four CID images from multipart MIME');
$html=\TNL\UI\MessageHtml::fragment($view['html'],$view['inline_images']);
yes(substr_count($html,'<img ')===4&&substr_count($html,'data:image/png;base64,')===4,'embedded images render using their MIME data');
yes(!str_contains($html,'5000')&&!str_contains($html,'9000')&&!str_contains($html,'height=')&&!str_contains($html,'style='),'remove artificial layout heights and source styles');
yes(!str_contains($html,'<script')&&!str_contains($html,'onerror')&&!str_contains($html,'javascript:'),'strip active email content and event handlers');
yes(!str_contains($html,'https://tracker.outside.test'),'remote tracking image is not requested by default');
$loaded=\TNL\UI\MessageHtml::fragment($view['html'],$view['inline_images'],true);
yes(str_contains($loaded,'src="https://tracker.outside.test/pixel"'),'external images render only when explicitly enabled');
yes(\TNL\UI\MessageHtml::fragment('<div style="height:9999px"><p>&nbsp;</p><table height="9999"><tr><td>&nbsp;</td></tr></table></div>')==='','empty newsletter spacer blocks are removed');
yes(!str_contains(\TNL\UI\MessageHtml::fragment('<img src="cid:evil">',['evil'=>'data:image/svg+xml;base64,AAAA']),'data:image/svg'),'CID SVG cannot inject active image content');
