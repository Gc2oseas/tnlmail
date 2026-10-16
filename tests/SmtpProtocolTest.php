<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Mail/ExternalMail.php';
use PHPMailer\PHPMailer\SMTP;use TNL\Mail\ExternalMail;
final class FakeSmtp extends SMTP {
 public bool $open=false;public bool $authenticated=false;public string $payload='';public array $recipients=[];public array $options=[];public string $endpoint='';
 public function connect($host,$port=null,$timeout=30,$options=[]){$this->open=true;$this->options=$options;$this->endpoint=$host.':'.$port;return true;}
 public function connected(){return $this->open;}
 public function hello($host=''){return true;}
 public function authenticate($username,$password,$authtype=null,$OAuth=null){$this->authenticated=$username==='sender@example.test'&&$password==='synthetic-password';return $this->authenticated;}
 public function getServerExt($name){return $name==='AUTH'?['LOGIN']:false;}
 public function mail($from){return $this->authenticated&&$from==='sender@example.test';}
 public function recipient($address,$dsn=''){$this->recipients[]=$address;return true;}
 public function data($msg_data){$this->payload=$msg_data;return true;}
 public function quit($close_on_error=true){$this->open=false;return true;}
 public function close(){$this->open=false;}
}
$fake=new FakeSmtp();$config=['email'=>'sender@example.test','password'=>'synthetic-password','smtp_host'=>'smtp.example.test'];$id=ExternalMail::send($config,'recipient@example.test','Prueba ñ','Texto de prueba',$fake);
if(!$fake->authenticated||$fake->recipients!==['recipient@example.test']||$id===''||!str_contains($fake->payload,'text/plain')||!str_contains($fake->payload,'Texto de prueba'))throw new RuntimeException('SMTP composition failed');echo "PASS: SMTP authentication, envelope and plain-text MIME through PHPMailer\n";
if($fake->endpoint!=='ssl://smtp.example.test:465'||!$fake->options['ssl']['verify_peer']||!$fake->options['ssl']['verify_peer_name']||$fake->options['ssl']['allow_self_signed'])throw new RuntimeException('TLS policy failed');echo "PASS: SMTP uses direct TLS 465 with certificate verification\n";
if(str_contains($fake->payload,'synthetic-password'))throw new RuntimeException('Password in message');echo "PASS: credentials are not included in message content\n";
