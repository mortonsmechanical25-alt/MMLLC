<?php
require dirname(__DIR__).'/private/bootstrap.php';
$owner=user(true);$action=$_GET['action']??'records';$method=$_SERVER['REQUEST_METHOD'];
try{
 if($method!=='GET')csrf();
 if($action==='records'&&$method==='GET')jsonout(records($owner));
 if($action==='payments'&&$method==='GET')jsonout(['connected'=>!empty($cfg['stripe_key'])&&!empty($cfg['stripe_webhook_secret']),'test'=>str_contains($cfg['stripe_key']??'','_test_')]);
 if($action==='messages'&&$method==='GET')jsonout(messageconfig());
 if(($action!=='records'&&$method!=='POST')||!in_array($method,['POST','DELETE'],true))jsonout(['error'=>'Method not allowed'],405);
 $raw=file_get_contents('php://input');if(strlen($raw)>3000000)throw new InvalidArgumentException('Request too large.');$r=json_decode($raw,true,512,JSON_THROW_ON_ERROR);if(!is_array($r))throw new InvalidArgumentException('Invalid request.');
 db()->beginTransaction();lockowner($owner);
 if($action==='records'){
  $old=readrecord($owner,$r['id']??'',true);
  if($old&&paymentlocked($old))throw new InvalidArgumentException('Invoices with recorded payments or payment links cannot be edited or deleted.');
  if($method==='DELETE'){
   if(!$old)throw new InvalidArgumentException('Record not found.');
   if($old['kind']==='message'||!empty($old['timeSessions']))throw new InvalidArgumentException('Message history and tracked time cannot be deleted.');
   if($old['kind']==='job')foreach(records($owner) as $d)if(($d['jobId']??'')===$old['id'])throw new InvalidArgumentException('This record is linked to a document.');
   $q=db()->prepare('DELETE FROM records WHERE owner=? AND id=?');$q->execute([$owner,$r['id']]);$result=['deleted'=>true];
  }else{
   unset($r['syncNotice']);if(($r['kind']??'')==='job'&&!$old)throw new InvalidArgumentException('Create work on an estimate or invoice.');if($old&&$old['kind']!==($r['kind']??''))throw new InvalidArgumentException('Record type cannot change.');
   if($old&&isset($old['updatedAt'])&&($r['updatedAt']??'')!==$old['updatedAt'])throw new InvalidArgumentException('Record changed. Reopen it before editing.');
   if(($r['kind']??'')==='invoice'){
    if($old){$r['sourceEstimateId']=$old['sourceEstimateId']??null;$r['sourceEstimate']=$old['sourceEstimate']??null;}
    elseif(!empty($r['sourceEstimateId'])){$source=readrecord($owner,$r['sourceEstimateId']);if(!$source||$source['kind']!=='estimate')throw new InvalidArgumentException('Source estimate not found.');foreach(records($owner) as $existing)if($existing['kind']==='invoice'&&($existing['sourceEstimateId']??'')===$source['id'])throw new InvalidArgumentException('An invoice already exists for this estimate. Open it from the estimate.');$r=invoicefromestimate($source,$r);}
   }
   if(in_array($r['kind']??'',['estimate','invoice'])){$r['timeSessions']=$old['timeSessions']??[];$r['repairStatus']=$old['repairStatus']??'Scheduled';if(!empty($old['timeSessions']))$r['hourlyRate']=$old['hourlyRate'];foreach($r['lines']??[] as $idx=>$line)if(($line['trackedDocumentId']??'')!==($old['id']??null))unset($r['lines'][$idx]['trackedDocumentId']);}
   $r=validate($r,$owner);$r['updatedAt']=nowiso();saverecord($owner,$r);$notice=syncestimate($owner,$old??$r,$r);$result=$r;if($notice)$result['syncNotice']=$notice;
  }
 }elseif($action==='inventory'){
  if(!is_string($r['code']??null)||strlen($r['code'])>200||trim($r['code'])===''||!in_array($r['mode']??'', ['add','remove'])||!is_int($r['quantity']??null)||$r['quantity']<1||$r['quantity']>100000||!preg_match('/^[a-f0-9-]{36}$/i',$r['operationId']??''))throw new InvalidArgumentException('Enter a valid scan code and quantity.');
  $q=db()->prepare('SELECT part,status FROM stock_scans WHERE owner=? AND id=?');$q->execute([$owner,$r['operationId']]);$prior=$q->fetch(PDO::FETCH_ASSOC);
  if($prior){if($prior['status']==='rejected')throw new InvalidArgumentException('Not enough stock.');$result=['part'=>readrecord($owner,$prior['part']),'duplicate'=>true];}
  else{
   $matches=array_values(array_filter(records($owner),fn($p)=>$p['kind']==='part'&&in_array(trim($r['code']),[trim($p['barcode']??''),trim($p['partNumber']??'')],true)));
   if(count($matches)!==1)throw new InvalidArgumentException(count($matches)?'This code matches multiple parts.':'No matching part. Add this barcode to a part first.');
   $p=readrecord($owner,$matches[0]['id'],true);$delta=$r['mode']==='add'?$r['quantity']:-$r['quantity'];$status=$p['stock']+$delta<0?'rejected':'applied';
   $q=db()->prepare('INSERT INTO stock_scans(owner,id,part,status) VALUES(?,?,?,?)');$q->execute([$owner,$r['operationId'],$p['id'],$status]);
   if($status==='rejected'){db()->commit();throw new InvalidArgumentException('Not enough stock to remove that quantity.');}
   $p['stock']+=$delta;saverecord($owner,$p);$result=['part'=>$p,'delta'=>$delta];
  }
 }elseif($action==='work'){
  $old=readrecord($owner,$r['id']??'',true);if(!$old)throw new InvalidArgumentException('Document not found.');
  if(($r['action']??'')==='start')foreach(records($owner) as $other)if($other['id']!==$old['id']&&running($other))throw new InvalidArgumentException('Clock out of your current record first.');
  $next=workupdate($old,$r['action']??'',$r['operationId']??'',$r['hourlyRate']??null,$r['status']??null);saverecord($owner,$next);$notice=syncestimate($owner,$old,$next);$result=$next;if($notice)$result['syncNotice']=$notice;
 }elseif($action==='jobs'){
  // Old imported job timers can be stopped; new work is always clocked on documents.
  $old=readrecord($owner,$r['id']??'',true);operation($r['operationId']??null);if(!$old||$old['kind']!=='job'||($r['action']??'')!=='stop')throw new InvalidArgumentException('Open an estimate or invoice to track work.');
  if(!running($old))throw new InvalidArgumentException('Not clocked in.');foreach($old['timeSessions'] as &$s)if(empty($s['end'])){$s['end']=nowiso();$s['stopId']=$r['operationId'];}unset($s);saverecord($owner,$old);$result=$old;
 }elseif($action==='cash'){
  $old=readrecord($owner,$r['id']??'',true);if(!$old)throw new InvalidArgumentException('Invoice not found.');$next=cashupdate($old,$r);saverecord($owner,$next);$result=$next;
 }elseif($action==='payments'){
  if(empty($cfg['stripe_key'])||empty($cfg['stripe_webhook_secret']))throw new InvalidArgumentException('Configure the Stripe key and webhook signing secret in private/config.php.');
  $invoice=readrecord($owner,$r['id']??'',true);if(!$invoice||$invoice['kind']!=='invoice')throw new InvalidArgumentException('Invoice not found.');
  if(!in_array($r['action']??'', ['create','refresh','expire'],true))throw new InvalidArgumentException('Invalid payment action.');
  if(!empty($invoice['sessionId'])){
   $s=stripe('checkout/sessions/'.rawurlencode($invoice['sessionId']));
   if($r['action']==='expire'&&($s['payment_status']??'')!=='paid'){
    if(($s['status']??'')==='complete')throw new InvalidArgumentException('Payment is still processing. Refresh it before receiving cash.');
    if(($s['status']??'')==='open')$s=stripe('checkout/sessions/'.rawurlencode($invoice['sessionId']).'/expire',[],null,true);
   }
   $invoice=verifypayment($invoice,$s);saverecord($owner,$invoice);$result=$invoice;
  }elseif($r['action']==='create'&&$invoice['status']!=='Paid'){
   if(running($invoice))throw new InvalidArgumentException('Clock out before creating a payment link.');
   $amount=$invoice['checkoutPending']['amount']??$r['amount']??($invoice['total']-paidamount($invoice));if(!is_int($amount)||$amount<50||$amount>$invoice['total']-paidamount($invoice))throw new InvalidArgumentException('Stripe amount must be at least $0.50 and no more than the balance.');
   if(empty($invoice['checkoutPending']))$invoice['checkoutPending']=['id'=>uuid(),'amount'=>$amount];
   // Keep this reservation even if Stripe responds late or the request is interrupted.
   saverecord($owner,$invoice);db()->commit();$reservation=$invoice['checkoutPending'];
   $params=['mode'=>'payment','line_items'=>[['price_data'=>['currency'=>'usd','product_data'=>['name'=>'Mortons Mechanical - '.$invoice['number'].' - payment'],'unit_amount'=>$amount],'quantity'=>1]],'metadata'=>['invoice_id'=>$invoice['id'],'owner'=>(string)$owner,'checkout_operation'=>$reservation['id']],'success_url'=>$cfg['base_url'].'/?payment=complete','cancel_url'=>$cfg['base_url'].'/?payment=cancelled','integration_identifier'=>$cfg['integration_identifier']];
   if(!empty($invoice['email']))$params['customer_email']=$invoice['email'];
   $s=stripe('checkout/sessions',$params,hash('sha256',$owner.'-'.$invoice['id'].'-'.$reservation['id']));
   db()->beginTransaction();lockowner($owner);$invoice=readrecord($owner,$invoice['id'],true);
   if(($invoice['checkoutPending']['id']??'')===$reservation['id']){$invoice['sessionId']=$s['id'];$invoice['paymentUrl']=$s['url'];$invoice['stripeAmount']=$amount;unset($invoice['checkoutPending']);saverecord($owner,$invoice);}
   elseif(($invoice['sessionId']??'')!==$s['id']&&$invoice['status']!=='Paid')throw new InvalidArgumentException('Payment changed. Refresh the invoice.');$result=$invoice;
  }else $result=$invoice;
 }elseif($action==='messages'){
  $source=readrecord($owner,$r['recordId']??'');if(!$source)throw new InvalidArgumentException('Document not found.');$m=messageprepare($source,$r);$existing=readrecord($owner,$m['id']);
  if($existing){$result=$existing;if($result['status']==='Sending')$result['error']='Submission is pending or unconfirmed. Check the provider before composing another message.';}
  else{saverecord($owner,$m);db()->commit();if($m['status']==='Sending'){$m=dispatchmessage($m);db()->beginTransaction();lockowner($owner);saverecord($owner,$m);}else{db()->beginTransaction();lockowner($owner);}$result=$m;}
 }elseif($action==='import'){
  if(!is_array($r['records']??null)||count($r['records'])>2000)throw new InvalidArgumentException('Invalid backup.');if(count(records($owner)))throw new InvalidArgumentException('Import into an empty desk. Existing records are preserved.');
  $items=$r['records'];usort($items,fn($a,$b)=>(($b['kind']??'')==='customer')<=>(($a['kind']??'')==='customer'));
  foreach($items as $item){if(!empty($item['sessionId'])||!empty($item['checkoutPending']))throw new InvalidArgumentException('Cancel active payment links and finish pending payments before migrating this backup.');if(!is_array($item)||readrecord($owner,$item['id']??''))throw new InvalidArgumentException('Invalid or duplicate record in backup.');
   if(($item['kind']??'')==='message'){$validated=$item;if(!is_string($item['id']??null)||strlen($item['id'])>100||strlen(json_encode($item))>60000||!isset($item['createdAt'],$item['body'],$item['customer'],$item['channel'],$item['status']))throw new InvalidArgumentException('Invalid message backup.');}
   else $validated=validate($item,$owner);
   if(in_array($item['kind'],['estimate','invoice','job'])){
    $sessions=$item['timeSessions']??[];if(!is_array($sessions))throw new InvalidArgumentException('Invalid time backup.');$validated['timeSessions']=$sessions;foreach($sessions as $s)if(!is_array($s)||!is_string($s['id']??null)||!is_string($s['start']??null)||!strtotime($s['start'])||(!empty($s['end'])&&(!strtotime($s['end'])||strtotime($s['end'])<strtotime($s['start']))))throw new InvalidArgumentException('Invalid work session.');
    if(!numeric($validated['hourlyRate']??0))throw new InvalidArgumentException('Invalid labor rate.');
   }
   if($item['kind']==='invoice'){
    foreach(['cashPayments','stripePayments'] as $key){$ledger=$item[$key]??[];if(!is_array($ledger))throw new InvalidArgumentException('Invalid payment backup.');$ids=[];foreach($ledger as $p){if(!is_array($p)||!is_string($p['id']??null)||isset($ids[$p['id']])||!is_int($p['amount']??null)||$p['amount']<=0||!is_string($p['date']??null)||!strtotime($p['date']))throw new InvalidArgumentException('Invalid payment ledger.');$ids[$p['id']]=true;}$validated[$key]=$ledger;}
    if(paidamount($validated)>$validated['total'])throw new InvalidArgumentException('Imported payments exceed invoice total.');$validated['status']=paystatus($validated);if(!empty($validated['cashPayments'])||!empty($validated['stripePayments']))$validated['paymentMethod']=methodlabel($validated);
    if(($item['status']??'')==='Paid'&&!paidamount($validated)){$validated['status']='Paid';$validated['notes']=($validated['notes']??'')."\nImported paid record; payment predates this installation.";}
   }
   unset($validated['syncNotice'],$validated['sessionId'],$validated['paymentUrl'],$validated['stripeAmount'],$validated['checkoutPending']);saverecord($owner,$validated);
  }
  $active=array_filter(records($owner),fn($i)=>running($i));if(count($active)>1)throw new InvalidArgumentException('Backup has more than one running timer. Clock out in the old desk before exporting.');$result=['imported'=>count($items)];
 }else throw new InvalidArgumentException('Unknown action.');
 db()->commit();jsonout($result);
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if($e instanceof InvalidArgumentException||$e instanceof JsonException)jsonout(['error'=>$e->getMessage()],400);error_log('Mortons Desk: '.$e->getMessage());jsonout(['error'=>'Unable to complete request. Check the hosting error log.'],500);}
