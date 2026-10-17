<?php
declare(strict_types=1);
namespace TNL\Mail;
require_once __DIR__.'/Mailboxes.php';require_once __DIR__.'/PersonalAccounts.php';
final class AccountAccess {
 public static function externalOwner(\PDO $db,ExternalAccount $store,array $identity,array $input,?string $remembered=null): string {
  $principal=$identity['principal'];$selection=(string)($input['account']??'');$personal=(int)($input['personal']??0);$box=(int)($input['box']??0);$explicit=$selection!==''||array_key_exists('personal',$input)||array_key_exists('box',$input);
  if($selection!==''&&!in_array($selection,['legacy','primary'],true)){if(!preg_match('/^(box|personal):([1-9][0-9]*)$/D',$selection,$m))throw new \InvalidArgumentException('Selección de cuenta inválida.');if($personal>0||$box>0)throw new \InvalidArgumentException('Selecciona un solo buzón.');if($m[1]==='personal')$personal=(int)$m[2];else $box=(int)$m[2];}
  if($personal>0&&$box>0)throw new \InvalidArgumentException('Selecciona un solo buzón.');
  if($explicit){if(in_array($selection,['legacy','primary'],true)&&($personal>0||$box>0))throw new \InvalidArgumentException('Selección de cuenta contradictoria.');return self::owner($db,$store,$identity,['personal'=>$personal,'box'=>$box]);}
  if($remembered!==null&&$remembered!==''){$owner=$remembered;if($owner===$principal){}elseif(preg_match('/^(personal|mailbox):([1-9][0-9]*)$/D',$owner,$m))$owner=self::owner($db,$store,$identity,[$m[1]==='personal'?'personal':'box'=>(int)$m[2]]);else throw new \InvalidArgumentException('Cuenta seleccionada no disponible.');$q=$db->prepare('SELECT 1 FROM external_accounts WHERE owner=?');$q->execute([$owner]);if(!$q->fetchColumn())throw new \InvalidArgumentException('La cuenta seleccionada ya no está conectada. Elige otro buzón.');return $owner;}
  $q=$db->prepare('SELECT config FROM external_accounts WHERE owner=?');$q->execute([$principal]);$primary=json_decode($q->fetchColumn()?:'{}',true);if(isset($primary['email'])&&!PersonalAccounts::corporate($db,$primary['email']))return $principal;
  if($identity['role']==='employee'){$assigned=(new Mailboxes($db))->listFor($identity['id']);if($assigned)return self::owner($db,$store,$identity,['box'=>$assigned[0]['id']]);}
  if($primary)return $principal;$accounts=(new PersonalAccounts($db,$store))->list($principal);return $accounts?self::owner($db,$store,$identity,['personal'=>$accounts[0]['id']]):$principal;
 }
 public static function personal(\PDO $db,string $owner,?array $account): bool {return $account!==null&&!PersonalAccounts::corporate($db,$account['email']);}
 public static function owner(\PDO $db,ExternalAccount $store,array $identity,array $input): string {
  $personal=(int)($input['personal']??0);$box=(int)($input['box']??0);
  if($personal>0)return (new PersonalAccounts($db,$store))->owner($personal,$identity['principal']);
  if($box>0){if($identity['role']!=='employee'||!(new Mailboxes($db))->allowed($box,$identity['id']))throw new \InvalidArgumentException('Buzón no asignado.');return 'mailbox:'.$box;}
  return $identity['principal'];
 }
}
