<?php
require dirname(__DIR__).'/private/bootstrap.php';
try{
 if($_SERVER['REQUEST_METHOD']!=='POST'||empty($cfg['stripe_webhook_secret']))jsonout(['error'=>'Unavailable'],400);
 $payload=file_get_contents('php://input');$sig=$_SERVER['HTTP_STRIPE_SIGNATURE']??'';$timestamp=0;$signatures=[];
 foreach(explode(',',$sig) as $piece){[$k,$v]=array_pad(explode('=',$piece,2),2,'');if($k==='t')$timestamp=(int)$v;if($k==='v1')$signatures[]=$v;}
 $expected=hash_hmac('sha256',$timestamp.'.'.$payload,$cfg['stripe_webhook_secret']);$valid=false;foreach($signatures as $s)if(hash_equals($expected,$s))$valid=true;
 if(!$valid||abs(time()-$timestamp)>300)jsonout(['error'=>'Invalid signature'],400);
 $event=json_decode($payload,true,512,JSON_THROW_ON_ERROR);
 if(in_array($event['type']??'', ['checkout.session.completed','checkout.session.async_payment_succeeded','checkout.session.expired'],true)){
  $s=$event['data']['object'];$owner=(int)($s['metadata']['owner']??0);$id=$s['metadata']['invoice_id']??'';
  db()->beginTransaction();lockowner($owner);$r=readrecord($owner,$id,true);
  if($r&&$r['kind']==='invoice'){if(($r['sessionId']??'')===($s['id']??'')||($r['checkoutPending']['id']??'none')===($s['metadata']['checkout_operation']??'other')||count(array_filter($r['stripePayments']??[],fn($p)=>$p['id']===($s['id']??''))))saverecord($owner,verifypayment($r,$s));}db()->commit();
 }
 jsonout(['received'=>true]);
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();error_log('Mortons webhook: '.$e->getMessage());jsonout(['error'=>'Webhook processing failed'],500);}
