export function invoiceLocked(r:any){return !!(r.sessionId||r.checkoutPending||r.cashPayments?.length||r.stripePayments?.length||r.status==='Paid'||r.timeSessions?.some((s:any)=>!s.end));}
export function invoiceFromEstimate(estimate:any,invoice:any){
 const next={...invoice};
 for(const key of ['customerId','customer','phone','email','vehicle','identifier','address','issue','notes','tax','discount','repairStatus']){if(estimate[key]===undefined)delete next[key];else next[key]=estimate[key];}
 if(!invoice.timeSessions?.length)next.hourlyRate=estimate.hourlyRate;
 next.lines=estimate.lines.map((l:any)=>{const line={...l};delete line.trackedDocumentId;return line;});
 next.lines.push(...(invoice.lines||[]).filter((l:any)=>l.trackedDocumentId===invoice.id).map((l:any)=>({...l})));
 next.sourceEstimateId=estimate.id;next.sourceEstimate=estimate.number;
 const sub=next.lines.reduce((s:number,l:any)=>s+Math.round(l.qty*l.price*100),0);
 next.total=Math.max(0,sub+Math.round(sub*next.tax/100)-Math.round(next.discount*100));
 if(!Number.isSafeInteger(next.total)||next.total>99999999)throw Error('Linked invoice total is out of range');
 return next;
}
export function linkedInvoices(estimate:any,all:any[]){const unique=all.filter(r=>r.kind==='estimate'&&r.number===estimate.number).length===1;return all.filter(r=>r.kind==='invoice'&&(r.sourceEstimateId===estimate.id||(!r.sourceEstimateId&&unique&&r.sourceEstimate===estimate.number)));}
