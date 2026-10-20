import {elapsedSeconds,jobStatuses} from './jobs';
export function updateDocumentWork(r:any,action:string,operationId:string,rate?:number,status?:string,now=new Date().toISOString()){
 if(!r||!['estimate','invoice'].includes(r.kind))throw Error('Estimate or invoice not found');
 if(typeof operationId!=='string'||! /^[a-f0-9-]{36}$/i.test(operationId))throw Error('Invalid operation reference');
 const sessions=r.timeSessions||[],active=sessions.find((s:any)=>!s.end);
 if(action==='status'){
  if(!jobStatuses.includes(status!))throw Error('Select repair progress');
  if(active&&['Completed','Cancelled'].includes(status!))throw Error('Clock out before completing the work');
  return {...r,repairStatus:status};
 }
 if(action==='start'&&sessions.some((s:any)=>s.id===operationId)||action==='stop'&&sessions.some((s:any)=>s.stopId===operationId))return r;
 if(r.sessionId||r.checkoutPending||r.cashPayments?.length||r.stripePayments?.length||r.status==='Paid')throw Error('This invoice is locked by payments or a payment link');
 if(action==='start'){
  if(active)throw Error('Already clocked in');
  rate=sessions.length?Number(r.hourlyRate):rate;
  if(!Number.isFinite(rate)||rate!<=0||rate!>100000)throw Error('Enter your labor rate before clocking in');
  return {...r,hourlyRate:rate,timeSessions:[...sessions,{id:operationId,start:now}],updatedAt:now};
 }
 if(action!=='stop')throw Error('Invalid timer action');
 if(!active)throw Error('Not clocked in');
 const next={...r,timeSessions:sessions.map((s:any)=>s.id===active.id?{...s,end:now,stopId:operationId}:s),updatedAt:now};
 const hours=Math.max(0.01,Math.round(elapsedSeconds(next)/36)/100);
 const line={description:'Labor (tracked time)',unit:'hrs',qty:hours,price:Number(r.hourlyRate),trackedDocumentId:r.id};
 const index=r.lines.findIndex((l:any)=>l.trackedDocumentId===r.id);
 next.lines=r.lines.map((l:any,i:number)=>i===index?line:{...l});if(index<0)next.lines.push(line);
 const sub=next.lines.reduce((n:number,l:any)=>n+Math.round(l.qty*l.price*100),0);
 next.total=Math.max(0,sub+Math.round(sub*r.tax/100)-Math.round(r.discount*100));
 if(!Number.isSafeInteger(next.total)||next.total>99999999)throw Error('Total is out of range');
 return next;
}
