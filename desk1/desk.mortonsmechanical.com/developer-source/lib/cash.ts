export function cashReceived(r:any){return (r.cashPayments||[]).reduce((n:number,p:any)=>n+p.amount,0);}
export function received(r:any){const amount=cashReceived(r)+(r.stripePayments||[]).reduce((n:number,p:any)=>n+p.amount,0);return r.status==='Paid'&&!r.cashPayments?.length&&!r.stripePayments?.length?r.total:amount;}
export function paymentStatus(r:any){const amount=received({...r,status:'Unpaid'});return amount>=r.total?'Paid':amount>0?'Partially paid':'Unpaid';}
export function paymentMethod(r:any){return r.cashPayments?.length&&r.stripePayments?.length?'Cash and Stripe':r.cashPayments?.length?'Cash':r.stripePayments?.length?'Stripe':undefined;}
export function recordCash(r:any,amount:number,date:string,operationId:string){
 if(!r||r.kind!=='invoice')throw Error('Invoice not found');
 if(r.cashPayments?.some((p:any)=>p.id===operationId))return r;
 if(r.sessionId||r.checkoutPending)throw Error('Refresh or cancel the current Stripe payment link before receiving cash.');
 if(r.status==='Paid')throw Error('Invoice is already paid');
 if(!Number.isSafeInteger(amount)||amount<=0||amount>r.total-received(r))throw Error('Enter a cash amount greater than zero and no more than the balance due');
 if(!/^\d{4}-\d{2}-\d{2}$/.test(date)||!Number.isFinite(Date.parse(date+'T12:00:00Z'))||new Date(date+'T12:00:00Z').toISOString().slice(0,10)!==date)throw Error('Enter a valid payment date');
 if(typeof operationId!=='string'||! /^[a-zA-Z0-9-]{16,100}$/.test(operationId))throw Error('Invalid payment reference');
 const next={...r,cashPayments:[...(r.cashPayments||[]),{id:operationId,amount,date,method:'Cash',recordedAt:new Date().toISOString()}]};
 next.status=paymentStatus(next);next.paymentMethod=paymentMethod(next);return next;
}
export function reconcileStripe(r:any,s:any){
 if(r.stripePayments?.some((p:any)=>p.id===s.id))return r;
 if(r.sessionId!==s.id)throw Error('Stripe session does not match this invoice');
 const expected=r.stripeAmount??r.total;
 if(s.currency!=='usd'||s.metadata?.invoice_id!==r.id||s.amount_total!==expected)throw Error('Stripe payment amount or invoice does not match');
 if(s.payment_status==='paid'){
  if(expected>r.total-received(r))throw Error('Stripe payment exceeds the remaining balance');
  const next={...r,stripePayments:[...(r.stripePayments||[]),{id:s.id,amount:expected,date:new Date((s.created||Date.now()/1000)*1000).toISOString(),method:'Stripe',recordedAt:new Date().toISOString()}]};
  delete next.sessionId;delete next.paymentUrl;delete next.stripeAmount;next.checkoutGeneration=(r.checkoutGeneration||0)+1;
  next.status=paymentStatus(next);next.paymentMethod=paymentMethod(next);return next;
 }
 if(s.status==='expired'){const next={...r,checkoutGeneration:(r.checkoutGeneration||0)+1};delete next.sessionId;delete next.paymentUrl;delete next.stripeAmount;return next;}
 return r;
}
