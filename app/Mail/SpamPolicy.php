<?php
declare(strict_types=1);
namespace TNL\Mail;
require_once __DIR__.'/PersonalAccounts.php';require_once __DIR__.'/ReplyComposer.php';
final class SpamPolicy {
 public function __construct(private \PDO $db) {$db->exec('CREATE TABLE IF NOT EXISTS spam_rules(principal TEXT NOT NULL,sender TEXT NOT NULL,created_at TEXT NOT NULL,PRIMARY KEY(principal,sender))');}
 public function company(string $email): bool {
  if(PersonalAccounts::corporate($this->db,$email))return true;
  $tables=$this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('mailboxes','external_accounts')")->fetchAll(\PDO::FETCH_COLUMN);
  if(count($tables)===2){$q=$this->db->prepare("SELECT 1 FROM mailboxes m JOIN external_accounts a ON a.owner='mailbox:'||m.id WHERE json_extract(a.config,'$.email')=? COLLATE NOCASE LIMIT 1");$q->execute([$email]);if($q->fetchColumn())return true;}return false;
 }
 public function address(string $header): string {return strtolower(ReplyComposer::address($header));}
 public function block(string $principal,string $header): string {
  $email=$this->address($header);
  if($email==='')throw new \InvalidArgumentException('No se identificó un único remitente para bloquear.');
  if($this->company($email))throw new \InvalidArgumentException('Los correos de la empresa no se pueden marcar como spam.');
  $this->db->prepare('INSERT OR IGNORE INTO spam_rules(principal,sender,created_at) VALUES(?,?,?)')->execute([$principal,$email,gmdate('c')]);return $email;
 }
 public function unblock(string $principal,string $header): void {$email=$this->address($header);$this->db->prepare('DELETE FROM spam_rules WHERE principal=? AND sender=?')->execute([$principal,$email]);}
 public function blocked(string $principal,string $header): bool {
  $email=$this->address($header);if($email===''||$this->company($email))return false;
  $q=$this->db->prepare('SELECT 1 FROM spam_rules WHERE principal=? AND sender=?');$q->execute([$principal,$email]);return (bool)$q->fetchColumn();
 }
 public function all(string $principal): array {$q=$this->db->prepare('SELECT sender,created_at FROM spam_rules WHERE principal=? ORDER BY created_at DESC');$q->execute([$principal]);return $q->fetchAll(\PDO::FETCH_ASSOC);}
}
