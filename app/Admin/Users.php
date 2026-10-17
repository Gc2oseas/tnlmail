<?php
declare(strict_types=1);
namespace TNL\Admin;
final class Users {
 public static function init(\PDO $db): void {
  $db->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, username TEXT NOT NULL UNIQUE, display_name TEXT NOT NULL, internal_address TEXT NOT NULL UNIQUE, role TEXT NOT NULL CHECK(role IN ('employee','manager')), active INTEGER NOT NULL DEFAULT 1, can_send_external INTEGER NOT NULL DEFAULT 0, password_hash TEXT, created_at TEXT NOT NULL)");
  $cols=$db->query('PRAGMA table_info(users)')->fetchAll(\PDO::FETCH_ASSOC);
  if(!in_array('password_hash',array_column($cols,'name'),true))$db->exec('ALTER TABLE users ADD COLUMN password_hash TEXT');
 }
 public static function all(\PDO $db): array {
  self::init($db);return $db->query('SELECT id,username,display_name,internal_address,role,active,can_send_external FROM users ORDER BY id DESC')->fetchAll(\PDO::FETCH_ASSOC);
 }
 public static function create(\PDO $db,array $input): void {
  self::init($db);
  $username=strtolower(trim((string)($input['username']??'')));
  $name=trim((string)($input['display_name']??''));
  $domain=strtolower(trim((string)($input['domain']??'')));
  if(!preg_match('/^[a-z0-9][a-z0-9._-]{2,49}$/',$username)||strlen($name)<2||strlen($name)>100||!filter_var('x@'.$domain,FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Usuario, nombre o dominio inválido.');
  if(strlen((string)($input['password']??''))<12)throw new \InvalidArgumentException('Contraseña de mínimo 12 caracteres.');
  $role=($input['role']??'')==='manager'?'manager':'employee';
  $stmt=$db->prepare('INSERT INTO users(username,display_name,internal_address,role,can_send_external,password_hash,created_at) VALUES(?,?,?,?,?,?,?)');
  $stmt->execute([$username,$name,$username.'@'.$domain,$role,empty($input['can_send_external'])?0:1,password_hash((string)$input['password'],PASSWORD_DEFAULT),gmdate('c')]);
 }

 public static function update(\PDO $db,array $input): void {
  self::init($db);$id=(int)($input['user_id']??0);$name=trim((string)($input['display_name']??''));$email=strtolower(trim((string)($input['internal_address']??'')));
  if($id<1||strlen($name)<2||strlen($name)>100||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)throw new \InvalidArgumentException('Nombre o dirección inválidos.');
  $db->prepare('UPDATE users SET display_name=?,internal_address=? WHERE id=?')->execute([$name,$email,$id]);
 }
}
