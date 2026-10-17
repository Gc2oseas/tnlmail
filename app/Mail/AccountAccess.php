<?php
declare(strict_types=1);
namespace TNL\Mail;
require_once __DIR__.'/Mailboxes.php';require_once __DIR__.'/PersonalAccounts.php';
final class AccountAccess {
 public static function owner(\PDO $db,ExternalAccount $store,array $identity,array $input): string {
  $personal=(int)($input['personal']??0);$box=(int)($input['box']??0);
  if($personal>0)return (new PersonalAccounts($db,$store))->owner($personal,$identity['principal']);
  if($box>0){if($identity['role']!=='employee'||!(new Mailboxes($db))->allowed($box,$identity['id']))throw new \InvalidArgumentException('Buzón no asignado.');return 'mailbox:'.$box;}
  return $identity['principal'];
 }
}
