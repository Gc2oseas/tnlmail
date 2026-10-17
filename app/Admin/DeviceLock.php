<?php
declare(strict_types=1);
namespace TNL\Admin;
final class DeviceLock {
 public function __construct(private \PDO $db) {
  $db->exec('CREATE TABLE IF NOT EXISTS trusted_devices(token_hash TEXT PRIMARY KEY,role TEXT NOT NULL,user_id INTEGER NOT NULL,version INTEGER NOT NULL,pin_hash TEXT NOT NULL,attempts INTEGER NOT NULL DEFAULT 0,expires INTEGER NOT NULL)');
 }
 public function create(string $role,int $id,int $version,string $pin): string {
  if(!in_array($role,['admin','employee'],true)||$id<1||!preg_match('/^[0-9]{6}$/D',$pin))throw new \InvalidArgumentException('Usa un PIN de 6 dígitos.');
  $token=bin2hex(random_bytes(32));$this->db->prepare('INSERT INTO trusted_devices(token_hash,role,user_id,version,pin_hash,expires) VALUES(?,?,?,?,?,?)')->execute([hash('sha256',$token),$role,$id,$version,password_hash($pin,PASSWORD_DEFAULT),time()+30*86400]);return $token;
 }
 public function forget(string $token): void {$this->db->prepare('DELETE FROM trusted_devices WHERE token_hash=?')->execute([hash('sha256',$token)]);}
 public function unlock(string $token,string $pin): array {
  $hash=hash('sha256',$token);$this->db->beginTransaction();
  try {
   // Reserve an attempt before the expensive hash check; concurrent requests cannot bypass the limit.
   $q=$this->db->prepare('UPDATE trusted_devices SET attempts=attempts+1 WHERE token_hash=? AND expires>? AND attempts<5');$q->execute([$hash,time()]);
   if(!$q->rowCount())throw new \RuntimeException('Dispositivo caducado o bloqueado. Olvida el dispositivo e inicia sesión con tu contraseña.');
   $q=$this->db->prepare('SELECT * FROM trusted_devices WHERE token_hash=?');$q->execute([$hash]);$device=$q->fetch(\PDO::FETCH_ASSOC);$this->db->commit();
  }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
  if(!password_verify($pin,$device['pin_hash']))throw new \RuntimeException('PIN incorrecto. Tras 5 intentos será necesario iniciar sesión de nuevo.');
  $employee=$device['role']==='employee';
  $q=$this->db->prepare($employee?'SELECT id,display_name,role,credentials_version,must_change_password FROM users WHERE id=? AND active=1':'SELECT id,username FROM admins WHERE id=?');$q->execute([$device['user_id']]);$user=$q->fetch(\PDO::FETCH_ASSOC);
  if(!$user||($employee&&((int)$user['credentials_version']!==(int)$device['version']||$user['must_change_password']))){$this->forget($token);throw new \RuntimeException('Las credenciales cambiaron. Inicia sesión de nuevo.');}
  $this->db->prepare('UPDATE trusted_devices SET attempts=0 WHERE token_hash=?')->execute([$hash]);return ['role'=>$device['role'],'user'=>$user];
 }
}
