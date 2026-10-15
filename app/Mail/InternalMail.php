<?php
declare(strict_types=1);
namespace TNL\Mail;
final class InternalMail {
 public static function init(\PDO $db): void {
  $db->exec("CREATE TABLE IF NOT EXISTS internal_messages (id INTEGER PRIMARY KEY, sender_id INTEGER NOT NULL, recipient_id INTEGER NOT NULL, subject TEXT NOT NULL, body TEXT NOT NULL, created_at TEXT NOT NULL, read_at TEXT, deleted_by_sender INTEGER NOT NULL DEFAULT 0, deleted_by_recipient INTEGER NOT NULL DEFAULT 0, FOREIGN KEY(sender_id) REFERENCES users(id), FOREIGN KEY(recipient_id) REFERENCES users(id))");
  $db->exec('CREATE INDEX IF NOT EXISTS idx_internal_inbox ON internal_messages(recipient_id,deleted_by_recipient,id)');
  $db->exec('CREATE INDEX IF NOT EXISTS idx_internal_sent ON internal_messages(sender_id,deleted_by_sender,id)');
 }
 public static function send(\PDO $db,int $sender,string $recipient,string $subject,string $body): void {
  self::init($db);
  $recipient=strtolower(trim($recipient));$subject=trim($subject);$body=trim($body);
  if(strlen($subject)<1||strlen($subject)>200||strlen($body)<1||strlen($body)>50000)throw new \InvalidArgumentException('Asunto o mensaje inválido.');
  $s=$db->prepare('SELECT id FROM users WHERE internal_address=? AND active=1');$s->execute([$recipient]);$to=$s->fetchColumn();
  if(!$to)throw new \InvalidArgumentException('Destinatario interno no encontrado.');
  $s=$db->prepare('INSERT INTO internal_messages(sender_id,recipient_id,subject,body,created_at) VALUES(?,?,?,?,?)');
  $s->execute([$sender,(int)$to,$subject,$body,gmdate('c')]);
 }
 public static function list(\PDO $db,int $user,string $folder): array {
  self::init($db);$sent=$folder==='sent';
  $sql=$sent?'SELECT m.*,u.display_name peer_name,u.internal_address peer_address FROM internal_messages m JOIN users u ON u.id=m.recipient_id WHERE m.sender_id=? AND m.deleted_by_sender=0 ORDER BY m.id DESC LIMIT 100':'SELECT m.*,u.display_name peer_name,u.internal_address peer_address FROM internal_messages m JOIN users u ON u.id=m.sender_id WHERE m.recipient_id=? AND m.deleted_by_recipient=0 ORDER BY m.id DESC LIMIT 100';
  $s=$db->prepare($sql);$s->execute([$user]);return $s->fetchAll(\PDO::FETCH_ASSOC);
 }
 public static function read(\PDO $db,int $user,int $id): ?array {
  self::init($db);$s=$db->prepare('SELECT m.*,u.display_name sender_name,u.internal_address sender_address FROM internal_messages m JOIN users u ON u.id=m.sender_id WHERE m.id=? AND ((m.recipient_id=? AND m.deleted_by_recipient=0) OR (m.sender_id=? AND m.deleted_by_sender=0))');
  $s->execute([$id,$user,$user]);$m=$s->fetch(\PDO::FETCH_ASSOC);
  if(!$m)return null;
  if((int)$m['recipient_id']===$user&&$m['read_at']===null)$db->prepare('UPDATE internal_messages SET read_at=? WHERE id=? AND recipient_id=?')->execute([gmdate('c'),$id,$user]);
  return $m;
 }
 public static function delete(\PDO $db,int $user,int $id): void {
  self::init($db);
  $db->prepare('UPDATE internal_messages SET deleted_by_sender=1 WHERE id=? AND sender_id=?')->execute([$id,$user]);
  $db->prepare('UPDATE internal_messages SET deleted_by_recipient=1 WHERE id=? AND recipient_id=?')->execute([$id,$user]);
 }
}
