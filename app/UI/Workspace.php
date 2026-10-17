<?php
declare(strict_types=1);
namespace TNL\UI;
final class Workspace {
 public static function icon(string $name): string {
  $paths=['mail'=>'<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/>','home'=>'<path d="m3 10 9-7 9 7v10H3Z"/><path d="M9 20v-7h6v7"/>','users'=>'<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6m3 10v-3a6 6 0 0 0-2-4"/>','building'=>'<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 7h1m4 0h1M9 11h1m4 0h1M10 21v-5h4v5"/>','shield'=>'<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6Z"/><path d="m8 12 3 3 5-6"/>','update'=>'<path d="M20 7v5h-5M4 17v-5h5"/><path d="M6 7a7 7 0 0 1 12-1l2 3M4 15l2 3a7 7 0 0 0 12-1"/>','code'=>'<path d="m8 7-5 5 5 5m8-10 5 5-5 5M14 4l-4 16"/>','arrow'=>'<path d="M5 12h14m-6-6 6 6-6 6"/>','compose'=>'<path d="m15 5 4 4M4 20l4-1L21 6l-4-4L4 15Z"/>','settings'=>'<path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="3"/><circle cx="15" cy="17" r="3"/>','send'=>'<path d="m3 3 19 9-19 9 4-9Z M7 12h15"/>'];
  return '<svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['mail']).'</svg>';
 }
 public static function sidebar(string $role,string $active,string $name=''): void {
  self::actions($role);
  $admin=$role==='admin';$links=$admin?[
   ['home','Inicio','admin/','home'],['mail','Correo externo','external.php?as=admin','external'],['users','Equipo','?page=users','users'],['building','Empresa','?page=settings','settings'],
  ]:[['home','Mi espacio','employee.php','home'],['mail','Correo externo','external.php','external'],['mail','Correo interno','mail.php','internal']];
  // Los enlaces de administración siempre parten de index.php, también desde otras pantallas.
  if($admin){$links[2][2]='admin/?page=users';$links[3][2]='admin/?page=settings';}
  $secondary=$admin?[['mail','Recuperar correos','admin/?page=trash','trash'],['shield','Seguridad','recovery.php','security'],['update','Actualizaciones','updates.php','updates'],['code','Despliegue GitHub','deployment.php','deployment']]:[['shield','Seguridad','recovery.php','security']];
  if($admin)$secondary[]=['send','Supervisor de rutas','admin/?page=routes','routes'];
  $secondary[]=['mail','Recepción asignada','reception-mail.php','reception-mail'];if($admin)$secondary[]=['settings','Recepción compartida','reception.php','reception'];$secondary[]=['home','Avisos','notices.php','notices'];
  $secondary[]=['users','Mi perfil y cuentas','profile.php','profile'];
  $secondary[]=['shield','Bloqueo del dispositivo','lock.php','lock'];
  echo '<aside class="sidebar workspace-sidebar"><a class="brand" href="'.htmlspecialchars(\tnl_url($admin?'admin/':'index.php'),ENT_QUOTES,'UTF-8').'"><span class="brand-mark">'.self::icon('mail').'</span><span>TNL<span class="brand-light"> Mail</span></span></a><button type="button" class="nav-toggle button-ghost" aria-label="Abrir navegación" aria-expanded="false" aria-controls="workspace-navigation" data-nav-toggle>Menú <span aria-hidden="true">☰</span></button><div class="workspace-menu" id="workspace-navigation"><p class="nav-caption">ESPACIO DE TRABAJO</p><nav aria-label="Principal">';
  foreach($links as [$icon,$label,$href,$key])self::link($icon,$label,$href,$active===$key,$key);
  echo '</nav><p class="nav-caption nav-caption-secondary">'.($admin?'ADMINISTRACIÓN':'TU CUENTA').'</p><nav aria-label="Configuración">';foreach($secondary as [$icon,$label,$href,$key])self::link($icon,$label,$href,$active===$key,$key);
  echo '</nav><div class="sidebar-footer"><a href="'.htmlspecialchars(\tnl_url('profile.php'),ENT_QUOTES,'UTF-8').'">Mi perfil</a><span class="user-avatar">'.($admin?'A':'U').'</span><div><strong>'.htmlspecialchars($name?:($admin?'Administración':'Mi cuenta'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</strong><span>'.($admin?'Administrador':'Espacio personal').'</span></div></div></div></aside>';
 }
 public static function actions(string $role): void {
  $url=fn($p)=>htmlspecialchars(\tnl_url($p),ENT_QUOTES,'UTF-8');$csrf=htmlspecialchars(\tnl_csrf(),ENT_QUOTES,'UTF-8');
  echo '<aside class="account-actions" aria-label="Acciones de tu cuenta"><a href="'.$url('profile.php').'" title="Configuración">'.self::icon('settings').'<span>Configuración</span></a><form method="post" action="'.$url('session.php').'"><input type="hidden" name="csrf" value="'.$csrf.'"><button name="action" value="lock" title="Bloquear o configurar PIN">'.self::icon('shield').'<span>Bloquear</span></button><button name="action" value="logout">'.self::icon('arrow').'<span>Cerrar sesión</span></button></form></aside>';
 }
 private static function link(string $icon,string $label,string $href,bool $active,string $key=''): void {echo '<a class="nav'.($active?' active':'').'" href="'.htmlspecialchars(\tnl_url($href),ENT_QUOTES,'UTF-8').'"'.($active?' aria-current="page"':'').'>'.self::icon($icon).'<span>'.$label.'</span>'.(in_array($key,['external','internal','reception-mail','notices'],true)?'<span class="nav-count" data-unread-count="'.(['external'=>'external','internal'=>'internal','reception-mail'=>'shared','notices'=>'notices'][$key]).'" aria-label="Mensajes nuevos">…</span>':'').'</a>';}
 public static function authBrand(): void {echo '<a class="auth-brand" href="index.php"><span class="brand-mark">'.self::icon('mail').'</span><span>TNL Mail</span></a>';}
}
