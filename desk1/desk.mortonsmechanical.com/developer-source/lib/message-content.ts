import {received} from './cash';
const money=(n:number)=>new Intl.NumberFormat('en-US',{style:'currency',currency:'USD'}).format(n/100);
export function customerMessage(r:any,business:any){const name=business.business||'Mortons Mechanical LLC',phone=business.phone||'603-856-5389';
 if(r.kind==='job'){
 const status:Record<string,string>={'Scheduled':'Your vehicle service is scheduled.','Diagnosing':'We are diagnosing the issue with your vehicle.','Waiting on parts':'We are waiting on parts for your vehicle.','Working on vehicle':'We are working on your vehicle.','Completed':'The work on your vehicle is completed. Please contact us to arrange pickup or next steps.','Cancelled':'This job has been cancelled. Please contact us with any questions.'};
 return `${name}: Hi ${r.customer}, ${status[r.status]||'Here is an update on your vehicle.'}\nVehicle: ${r.vehicle}\nJob: ${r.number}\nCall or text ${phone} with questions.`;
 }
 if(!['estimate','invoice'].includes(r.kind))throw Error('Select a job, estimate or invoice');
 const parts=r.lines.map((l:any)=>`${l.description}${l.partNumber?' (P/N '+l.partNumber+')':''} — ${l.qty} × ${money(Math.round(l.price*100))} = ${money(Math.round(l.qty*l.price*100))}`).join('\n');
 const sub=r.lines.reduce((n:number,l:any)=>n+Math.round(l.qty*l.price*100),0);
 return `${name}\n${r.kind==='estimate'?'ESTIMATE':'INVOICE'} ${r.number}\nCustomer: ${r.customer}\nVehicle: ${r.vehicle||'Not specified'}\nDate: ${r.issued}\n${r.kind==='estimate'?'Valid through':'Due'}: ${r.due}\n\n${parts}\n\nSubtotal: ${money(sub)}${r.tax?'\nTax: '+money(Math.round(sub*r.tax/100)):''}${r.discount?'\nDiscount: -'+money(Math.round(r.discount*100)):''}\nTotal: ${money(r.total)}${r.kind==='invoice'?'\nPayments received: '+money(received(r))+'\nBalance due: '+money(r.total-received(r)):''}${r.paymentUrl&&r.status!=='Paid'?'\nPay '+money(r.stripeAmount??r.total)+' securely: '+r.paymentUrl:''}\n${business.terms?'\nTerms: '+business.terms+'\n':''}\nCall or text ${phone} with questions.`;
}
export function normalizePhone(value:string){let phone=value.replace(/[\s().-]/g,'');if(/^\d{10}$/.test(phone))phone='+1'+phone;else if(/^1\d{10}$/.test(phone))phone='+'+phone;if(!/^\+[1-9]\d{7,14}$/.test(phone))throw Error('Enter a valid customer phone number including country code');return phone;}
export function messageHtml(body:string){const safe=body.replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]!));return `<div style="font-family:Arial,sans-serif;color:#25352f;max-width:720px;margin:auto"><h2 style="color:#254f39">Mortons Mechanical LLC</h2><div style="white-space:pre-wrap;line-height:1.7">${safe}</div></div>`;}
