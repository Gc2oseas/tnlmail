<?php
declare(strict_types=1);
namespace TNL\UI;
final class SenderNotice {
 public static function render(bool $personal,bool $dynamic=false): void {
  if(!$personal&&!$dynamic)return;
  echo '<aside class="sender-privacy-note" role="note"'.($dynamic?' data-personal-warning':'').(!$personal?' hidden':'').'><strong>Estás usando tu cuenta personal.</strong> Esta empresa podría guardar una copia de tu correo enviado. Si necesitas contactar con un cliente, usa tu cuenta empresarial o el correo público en <a href="'.\tnl_escape(\tnl_url('reception-mail.php')).'" target="_top">Recepción asignada</a>.</aside>';
 }
}
