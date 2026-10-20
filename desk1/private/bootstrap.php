<?php
declare(strict_types=1);
$configPath=__DIR__.'/config.php';
if(!is_file($configPath)){http_response_code(503);exit('Setup required: copy private/config.example.php to private/config.php and configure the database.');}
$cfg=require $configPath;
ini_set('display_errors','0');
date_default_timezone_set('America/New_York');
header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
session_name('mortons_desk');session_set_cookie_params(['httponly'=>true,'secure'=>str_starts_with($cfg['base_url'],'https://'),'samesite'=>'Lax','path'=>'/']);session_start();
if(!isset($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));
function db():PDO {global $cfg;static $pdo;if(!$pdo)$pdo=new PDO($cfg['dsn'],$cfg['db_user'],$cfg['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);return $pdo;}
function h(mixed $s):string{return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function jsonout(mixed $data,int $status=200):never{http_response_code($status);header('Content-Type: application/json');echo json_encode($data,JSON_THROW_ON_ERROR);exit;}
function user(bool $api=false):int{if(empty($_SESSION['user'])||time()-($_SESSION['last']??0)>28800){unset($_SESSION['user']);if($api)jsonout(['error'=>'Sign in required. Refresh the page to log in.'],401);header('Location: /login.php');exit;}$_SESSION['last']=time();return (int)$_SESSION['user'];}
function csrf():void{if(!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??$_POST['csrf']??''))throw new InvalidArgumentException('Please refresh the page and try again.');}
function records(int $owner):array{$q=db()->prepare('SELECT data FROM records WHERE owner=? ORDER BY updated DESC');$q->execute([$owner]);return array_map(fn($x)=>json_decode($x['data'],true,512,JSON_THROW_ON_ERROR),$q->fetchAll(PDO::FETCH_ASSOC));}
function readrecord(int $owner,string $id,bool $lock=false):?array{$q=db()->prepare('SELECT data FROM records WHERE owner=? AND id=?'.($lock?' FOR UPDATE':''));$q->execute([$owner,$id]);$v=$q->fetchColumn();return $v===false?null:json_decode($v,true,512,JSON_THROW_ON_ERROR);}
function saverecord(int $owner,array $r):void{$q=db()->prepare('INSERT INTO records(owner,id,kind,data,updated) VALUES(?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE data=VALUES(data),updated=UTC_TIMESTAMP()');$q->execute([$owner,$r['id'],$r['kind'],json_encode($r,JSON_THROW_ON_ERROR)]);}
function total(array $r):int{$sub=0;foreach($r['lines'] as $l)$sub+=(int)round($l['qty']*$l['price']*100);return max(0,$sub+(int)round($sub*$r['tax']/100)-(int)round($r['discount']*100));}
function numeric(mixed $v):bool{return (is_int($v)||is_float($v))&&is_finite((float)$v)&&$v>=0;}
function validate(array $r,int $owner):array{
 if(!in_array($r['kind']??'', ['appointment','part','estimate','invoice','settings','customer','job'],true)||!is_string($r['id']??null)||strlen($r['id'])>100||$r['id']==='')throw new InvalidArgumentException('Invalid record.');
 if(strlen(json_encode($r))>60000)throw new InvalidArgumentException('Record too large.');
 foreach(['customer','phone','email','address','location','vehicle','identifier','notes','issue','number','business','area','terms','name','partNumber','barcode','supplier'] as $k)if(isset($r[$k])&&(!is_string($r[$k])||strlen($r[$k])>5000))throw new InvalidArgumentException('Invalid text field.');
 if(in_array($r['kind'],['customer','appointment','estimate','invoice'])&&trim($r['customer']??'')==='')throw new InvalidArgumentException('Customer name is required.');
 if(!empty($r['customerId'])){ $c=readrecord($owner,$r['customerId']);if(!$c||$c['kind']!=='customer')throw new InvalidArgumentException('Saved customer no longer exists.'); }
 if($r['kind']==='part'){if(empty($r['name']))throw new InvalidArgumentException('Part name is required.');foreach(['stock','reorder','cost','sell'] as $k)if(!numeric($r[$k]??null))throw new InvalidArgumentException('Enter valid part quantities and prices.');}
 if($r['kind']==='appointment'){
  $start=strtotime($r['start']??'');$end=strtotime($r['end']??'');if(!$start||!$end||$end<=$start||!in_array($r['status']??'', ['Scheduled','In progress','Completed','Cancelled']))throw new InvalidArgumentException('Enter valid appointment times and status.');
  if($r['status']!=='Cancelled')foreach(records($owner) as $a)if($a['kind']==='appointment'&&$a['id']!==$r['id']&&$a['status']!=='Cancelled'&&$start<strtotime($a['end'])&&$end>strtotime($a['start']))throw new InvalidArgumentException('This appointment overlaps another booking.');
 }
 if(in_array($r['kind'],['estimate','invoice'])){
  if(!is_array($r['lines']??null)||!count($r['lines'])||count($r['lines'])>250||!numeric($r['tax']??null)||$r['tax']>100||!numeric($r['discount']??null))throw new InvalidArgumentException('Invalid document amounts.');
  foreach($r['lines'] as $l){foreach(['partNumber','unit'] as $key)if(isset($l[$key])&&(!is_string($l[$key])||strlen($l[$key])>200))throw new InvalidArgumentException('Invalid line details.');if(!is_array($l)||!is_string($l['description']??null)||trim($l['description'])===''||strlen($l['description'])>5000||!numeric($l['qty']??null)||$l['qty']<=0||!numeric($l['price']??null))throw new InvalidArgumentException('Invalid line item.');}
  foreach(['issued','due'] as $k)if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$r[$k]??'')||!strtotime($r[$k]))throw new InvalidArgumentException('Enter valid document dates.');
  $r['total']=total($r);if($r['total']>99999999)throw new InvalidArgumentException('Total is out of range.');
  $r['status']=$r['kind']==='invoice'?'Unpaid':($r['status']??'Draft');unset($r['sessionId'],$r['paymentUrl'],$r['checkoutGeneration'],$r['checkoutPending'],$r['cashPayments'],$r['stripePayments'],$r['paymentMethod'],$r['stripeAmount']);
 }
 return $r;
}
function stripe(string $path,array $params=[],?string $idem=null,bool $post=false):array{global $cfg;if(empty($cfg['stripe_key']))throw new InvalidArgumentException('Stripe is not configured.');$c=curl_init('https://api.stripe.com/v1/'.$path);$headers=['Authorization: Bearer '.$cfg['stripe_key'],'Stripe-Version: 2026-08-26.dahlia'];if($idem)$headers[]='Idempotency-Key: '.$idem;curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,CURLOPT_HTTPHEADER=>$headers]);if($params||$post)curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($params)]);$raw=curl_exec($c);$status=curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);if($raw===false||$status>=400)throw new RuntimeException('Stripe request failed. Check the server key and Stripe permissions.');return json_decode($raw,true,512,JSON_THROW_ON_ERROR);}
require __DIR__.'/workflows.php';
