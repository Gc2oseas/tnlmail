<?php
declare(strict_types=1);
namespace TNL\Mail;
require_once __DIR__.'/ExternalAccount.php';
require_once dirname(__DIR__).'/Admin/Settings.php';
final class PersonalAccounts {
 public function __construct(private \PDO $db,private ExternalAccount $store) {
  $db->exec('CREATE TABLE IF NOT EXISTS personal_mailboxes(id INTEGER PRIMARY KEY AUTOINCREMENT,principal TEXT NOT NULL,label TEXT NOT NULL,created_at TEXT NOT NULL)');
 }
 public static function corporate(\PDO $db,string $email): bool {
  $domain=strtolower(trim(\TNL\Admin\Settings::get($db,'company_domain')));return $domain!==''&&strtolower(substr(strrchr(trim($email),'@')?:'',1))===$domain;
 }
 public function list(string $principal): array {$q=$this->db->prepare('SELECT id,label FROM personal_mailboxes WHERE principal=? ORDER BY id');$q->execute([$principal]);return $q->fetchAll(\PDO::FETCH_ASSOC);}
 public function allowed(int $id,string $principal): bool {$q=$this->db->prepare('SELECT 1 FROM personal_mailboxes WHERE id=? AND principal=?');$q->execute([$id,$principal]);return (bool)$q->fetchColumn();}
 public function owner(int $id,string $principal): string {if(!$this->allowed($id,$principal))throw new \InvalidArgumentException('Cuenta no disponible.');return 'personal:'.$id;}
 public function save(int $id,string $principal,string $label,array $input,string $password): int {
  if(!preg_match('/^(employee|admin):[1-9][0-9]*$/D',$principal)||trim($label)===''||strlen($label)>100)throw new \InvalidArgumentException('Nombre de cuenta inválido.');
  if(self::corporate($this->db,(string)($input['email']??'')))throw new \InvalidArgumentException('Las cuentas empresariales las gestiona administración.');
  if($id>0){$existing=$this->store->load($this->owner($id,$principal));if($existing&&self::corporate($this->db,$existing['email']))throw new \InvalidArgumentException('La cuenta empresarial está protegida.');}
  elseif(count($this->list($principal))>=20)throw new \InvalidArgumentException('Máximo 20 cuentas personales.');
  $this->db->beginTransaction();
  try{
   if($id<1){$this->db->prepare('INSERT INTO personal_mailboxes(principal,label,created_at) VALUES(?,?,?)')->execute([$principal,trim($label),gmdate('c')]);$id=(int)$this->db->lastInsertId();}
   else $this->db->prepare('UPDATE personal_mailboxes SET label=? WHERE id=? AND principal=?')->execute([trim($label),$id,$principal]);
   $this->store->save('personal:'.$id,$input,$password);$this->db->commit();return $id;
  }catch(\Throwable $e){$this->db->rollBack();throw $e;}
 }
 public function remove(int $id,string $principal): void {
  $owner=$id>0?$this->owner($id,$principal):$principal;$account=$this->store->load($owner);
  if($account&&self::corporate($this->db,$account['email']))throw new \InvalidArgumentException('No se puede eliminar la cuenta empresarial.');
  $this->db->beginTransaction();try{
   $this->db->prepare('DELETE FROM external_accounts WHERE owner=?')->execute([$owner]);
   if($id>0)$this->db->prepare('DELETE FROM personal_mailboxes WHERE id=? AND principal=?')->execute([$id,$principal]);
   $this->db->commit();
  }catch(\Throwable $e){$this->db->rollBack();throw $e;}
 }
}
