<?php
declare(strict_types=1);
namespace TNL\Mail;
final class RecoveryTrash {
 public function __construct(private \PDO $db) {
  $db->exec("CREATE TABLE IF NOT EXISTS mail_recovery(id INTEGER PRIMARY KEY,owner TEXT NOT NULL,email TEXT NOT NULL,folder TEXT NOT NULL,validity INTEGER NOT NULL,uid INTEGER NOT NULL,subject TEXT NOT NULL,raw BLOB NOT NULL,created_at TEXT NOT NULL,restored_at TEXT,UNIQUE(owner,email,folder,validity,uid))");
 }
 public function preserve(string $owner,string $email,string $folder,int $validity,int $uid,string $raw): int {
  if($raw===''||strlen($raw)>1048576||$validity<1||$uid<1)throw new \RuntimeException('No se puede guardar íntegro el mensaje. No se borrará.');
  $existing=$this->db->prepare('SELECT id,raw FROM mail_recovery WHERE owner=? AND email=? AND folder=? AND validity=? AND uid=?');$existing->execute([$owner,$email,$folder,$validity,$uid]);$saved=$existing->fetch(\PDO::FETCH_ASSOC);
  if($saved){if(!hash_equals($saved['raw'],$raw))throw new \RuntimeException('La identidad del mensaje coincide con otra copia diferente. No se borrará; revisa el servidor configurado.');return (int)$saved['id'];}
  $size=(int)$this->db->query('SELECT COALESCE(SUM(length(raw)),0) FROM mail_recovery')->fetchColumn();
  if($size+strlen($raw)>250*1048576)throw new \RuntimeException('La papelera administrativa alcanzó 250 MB. No se borrará el mensaje.');
  $headers=ImapClient::headers(preg_split('/\r?\n\r?\n/',$raw,2)[0]);
  $q=$this->db->prepare('INSERT OR IGNORE INTO mail_recovery(owner,email,folder,validity,uid,subject,raw,created_at) VALUES(?,?,?,?,?,?,?,?)');
  $q->bindValue(1,$owner);$q->bindValue(2,$email);$q->bindValue(3,$folder);$q->bindValue(4,$validity);$q->bindValue(5,$uid);$q->bindValue(6,$headers['subject']??'(sin asunto)');$q->bindValue(7,$raw,\PDO::PARAM_LOB);$q->bindValue(8,gmdate('c'));$q->execute();
  $q=$this->db->prepare('SELECT id FROM mail_recovery WHERE owner=? AND email=? AND folder=? AND validity=? AND uid=?');$q->execute([$owner,$email,$folder,$validity,$uid]);return (int)$q->fetchColumn();
 }
 public function all(): array {return $this->db->query('SELECT id,owner,email,subject,created_at,restored_at,length(raw) AS bytes FROM mail_recovery ORDER BY id DESC LIMIT 100')->fetchAll(\PDO::FETCH_ASSOC);}
 public function get(int $id): array {$q=$this->db->prepare('SELECT * FROM mail_recovery WHERE id=?');$q->execute([$id]);$r=$q->fetch(\PDO::FETCH_ASSOC);if(!$r)throw new \RuntimeException('Copia no encontrada.');return $r;}
 public function restored(int $id): void {$this->db->prepare('UPDATE mail_recovery SET restored_at=? WHERE id=?')->execute([gmdate('c'),$id]);}
}
