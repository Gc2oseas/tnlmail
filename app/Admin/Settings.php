<?php
declare(strict_types=1);
namespace TNL\Admin;
final class Settings {
 public static function init(\PDO $db): void {
  $db->exec('CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
 }
 public static function get(\PDO $db,string $key,string $default=''): string {
  self::init($db); $s=$db->prepare('SELECT value FROM settings WHERE key=?');$s->execute([$key]);$v=$s->fetchColumn();return $v===false?$default:(string)$v;
 }
 public static function set(\PDO $db,string $key,string $value): void {
  self::init($db);$s=$db->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value');$s->execute([$key,$value]);
 }
}
