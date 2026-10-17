<?php
declare(strict_types=1);
namespace TNL\Mail;
require_once dirname(__DIR__).'/Admin/Users.php';
final class Directory {
 public static function search(\PDO $db,string $query): array {
  \TNL\Admin\Users::init($db);$query=strtolower(trim($query));
  if(strlen($query)<2||strlen($query)>80)return [];
  $prefix=str_replace(['!','%','_'],['!!','!%','!_'],$query).'%';
  $q=$db->prepare("SELECT id,username,display_name,job_title,department FROM users WHERE active=1 AND (username LIKE ? ESCAPE '!' OR display_name LIKE ? ESCAPE '!' OR job_title LIKE ? ESCAPE '!') ORDER BY username LIMIT 20");$q->execute([$prefix,$prefix,$prefix]);$rows=$q->fetchAll(\PDO::FETCH_ASSOC);
  foreach($rows as &$r)$r['label']=self::label($r);return $rows;
 }
 public static function label(array $user): string {
  $context=implode(' · ',array_filter([$user['job_title']??'',$user['department']??'']));
  return $user['username'].($context!==''?' ('.$context.')':'').' — '.$user['display_name'];
 }
 public static function resolve(\PDO $db,string $recipient): array {
  \TNL\Admin\Users::init($db);$recipient=strtolower(trim($recipient));
  if($recipient===''||strlen($recipient)>254||preg_match('/[\r\n\x00]/',$recipient))throw new \InvalidArgumentException('Destinatario inválido.');
  $q=$db->prepare('SELECT id,username,display_name,internal_address,job_title,department FROM users WHERE active=1 AND (username=? COLLATE NOCASE OR internal_address=? COLLATE NOCASE) LIMIT 2');$q->execute([$recipient,$recipient]);$rows=$q->fetchAll(\PDO::FETCH_ASSOC);
  if(count($rows)!==1)throw new \InvalidArgumentException(count($rows)>1?'Dirección ambigua. Selecciona el usuario exacto.':'Destinatario interno no encontrado. Selecciona un usuario del directorio.');
  return $rows[0];
 }
}
