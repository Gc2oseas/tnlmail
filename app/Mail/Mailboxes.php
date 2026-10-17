<?php
declare(strict_types=1);
namespace TNL\Mail;
require_once __DIR__.'/ExternalAccount.php';
final class Mailboxes {
 public function __construct(private \PDO $db) {
  $db->exec('CREATE TABLE IF NOT EXISTS mailboxes(id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, label TEXT NOT NULL, created_at TEXT NOT NULL)');
 }
 public function listFor(int $userId): array {
  $q=$this->db->prepare('SELECT id,label FROM mailboxes WHERE user_id=? ORDER BY id');$q->execute([$userId]);return $q->fetchAll(\PDO::FETCH_ASSOC);
 }
 public function allowed(int $id,int $userId): bool {
  $q=$this->db->prepare('SELECT 1 FROM mailboxes WHERE id=? AND user_id=?');$q->execute([$id,$userId]);return (bool)$q->fetchColumn();
 }
 public function assign(int $id,int $userId,string $label,array $input,string $password,ExternalAccount $store): int {
  $label=trim($label);if($label===''||strlen($label)>100)throw new \InvalidArgumentException('Nombre de buzón inválido.');
  $q=$this->db->prepare('SELECT id FROM users WHERE id=? AND active=1');$q->execute([$userId]);if(!$q->fetchColumn())throw new \InvalidArgumentException('Usuario no disponible.');
  $this->db->beginTransaction();
  try {
   if($id>0){$q=$this->db->prepare('SELECT id FROM mailboxes WHERE id=?');$q->execute([$id]);if(!$q->fetchColumn())throw new \InvalidArgumentException('Buzón inexistente.');$this->db->prepare('UPDATE mailboxes SET user_id=?,label=? WHERE id=?')->execute([$userId,$label,$id]);}
   else {$this->db->prepare('INSERT INTO mailboxes(user_id,label,created_at) VALUES(?,?,?)')->execute([$userId,$label,gmdate('c')]);$id=(int)$this->db->lastInsertId();}
   $store->save('mailbox:'.$id,$input,$password);$this->db->commit();return $id;
  }catch(\Throwable $e){$this->db->rollBack();throw $e;}
 }
 public function all(): array {return $this->db->query('SELECT m.*,u.display_name FROM mailboxes m JOIN users u ON u.id=m.user_id ORDER BY m.id')->fetchAll(\PDO::FETCH_ASSOC);}
}
