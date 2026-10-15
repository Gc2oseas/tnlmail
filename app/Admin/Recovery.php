<?php
declare(strict_types=1);
namespace TNL\Admin;
final class Recovery {
 public static function init(\PDO $db): void {
  $db->exec("CREATE TABLE IF NOT EXISTS recovery_requests (id INTEGER PRIMARY KEY, username_hint TEXT NOT NULL, details TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','resolved')), created_at TEXT NOT NULL, resolved_at TEXT)");
  $db->exec("CREATE TABLE IF NOT EXISTS credential_events (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, admin_id INTEGER, action TEXT NOT NULL, created_at TEXT NOT NULL)");
  $cols=$db->query('PRAGMA table_info(users)')->fetchAll(\PDO::FETCH_ASSOC);
  if(!in_array('credentials_version',array_column($cols,'name'),true))$db->exec('ALTER TABLE users ADD COLUMN credentials_version INTEGER NOT NULL DEFAULT 1');
  if(!in_array('must_change_password',array_column($cols,'name'),true))$db->exec('ALTER TABLE users ADD COLUMN must_change_password INTEGER NOT NULL DEFAULT 0');
 }
 public static function request(\PDO $db,string $username,string $details): void {
  self::init($db);$username=trim($username);$details=trim($details);
  if(strlen($username)<3||strlen($username)>100||strlen($details)>500)throw new \InvalidArgumentException('Datos inválidos.');
  $recent=$db->prepare("SELECT count(*) FROM recovery_requests WHERE username_hint=? AND created_at>=?");
  $recent->execute([$username,gmdate('c',time()-3600)]);
  if((int)$recent->fetchColumn()>=3)return;
  $s=$db->prepare('INSERT INTO recovery_requests(username_hint,details,created_at) VALUES(?,?,?)');
  $s->execute([$username,$details,gmdate('c')]);
 }
 public static function pending(\PDO $db): array {
  self::init($db);return $db->query("SELECT id,username_hint,details,created_at FROM recovery_requests WHERE status='pending' ORDER BY id DESC LIMIT 100")->fetchAll(\PDO::FETCH_ASSOC);
 }
 public static function resolve(\PDO $db,int $id): void {
  self::init($db);$s=$db->prepare("UPDATE recovery_requests SET status='resolved',resolved_at=? WHERE id=? AND status='pending'");$s->execute([gmdate('c'),$id]);
 }
 public static function reset(\PDO $db,int $userId,int $adminId,string $password): void {
  self::init($db);
  if(strlen($password)<12||strlen($password)>256)throw new \InvalidArgumentException('Contraseña: entre 12 y 256 caracteres.');
  $db->beginTransaction();
  try{
   $s=$db->prepare('UPDATE users SET password_hash=?,credentials_version=credentials_version+1,must_change_password=1 WHERE id=?');
   $s->execute([password_hash($password,PASSWORD_DEFAULT),$userId]);
   if($s->rowCount()!==1)throw new \InvalidArgumentException('Usuario inexistente.');
   $s=$db->prepare('INSERT INTO credential_events(user_id,admin_id,action,created_at) VALUES(?,?,?,?)');
   $s->execute([$userId,$adminId,'password_reset',gmdate('c')]);$db->commit();
  }catch(\Throwable $e){$db->rollBack();throw $e;}
 }
 public static function change(\PDO $db,int $userId,string $old,string $new): bool {
  self::init($db);if(strlen($new)<12||strlen($new)>256)throw new \InvalidArgumentException('La nueva contraseña debe tener entre 12 y 256 caracteres.');
  $s=$db->prepare('SELECT password_hash FROM users WHERE id=? AND active=1');$s->execute([$userId]);$hash=$s->fetchColumn();
  if(!$hash||!password_verify($old,(string)$hash))return false;
  $s=$db->prepare('UPDATE users SET password_hash=?,must_change_password=0,credentials_version=credentials_version+1 WHERE id=? AND password_hash=?');
  $s->execute([password_hash($new,PASSWORD_DEFAULT),$userId,$hash]);return $s->rowCount()===1;
 }
}
