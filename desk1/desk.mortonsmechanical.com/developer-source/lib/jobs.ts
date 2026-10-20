export const jobStatuses=['Scheduled','Diagnosing','Waiting on parts','Working on vehicle','Completed','Cancelled'];
export function elapsedSeconds(r:any,now=Date.now()){return (r.timeSessions||[]).reduce((n:number,s:any)=>n+Math.max(0,Math.floor(((s.end?Date.parse(s.end):now)-Date.parse(s.start))/1000)),0);}
export function updateJob(r:any,action:string,operationId:string,status?:string,now=new Date().toISOString()){
 if(!r||r.kind!=='job')throw Error('Job not found');
 if(typeof operationId!=='string'||! /^[a-f0-9-]{36}$/i.test(operationId))throw Error('Invalid operation reference');
 const sessions=r.timeSessions||[],active=sessions.find((s:any)=>!s.end);
 if(action==='start'){
  if(sessions.some((s:any)=>s.id===operationId))return r;
  if(active)throw Error('This job is already clocked in');if(['Completed','Cancelled'].includes(r.status))throw Error('Reopen this job before clocking in');
  return {...r,timeSessions:[...sessions,{id:operationId,start:now}],updatedAt:now};
 }
 if(action==='stop'){
  if(sessions.some((s:any)=>s.stopId===operationId))return r;
  if(!active)throw Error('This job is not clocked in');
  return {...r,timeSessions:sessions.map((s:any)=>s.id===active.id?{...s,end:now,stopId:operationId}:s),updatedAt:now};
 }
 if(action==='status'){
  if(!jobStatuses.includes(status!))throw Error('Select a valid job status');
  if(active&&['Completed','Cancelled'].includes(status!))throw Error('Clock out before completing or cancelling this job');
  if(r.statusHistory?.some((s:any)=>s.id===operationId))return r;
  if(r.status===status)return r;
  return {...r,status,statusHistory:[...(r.statusHistory||[]),{id:operationId,status,date:now}],updatedAt:now};
 }
 throw Error('Invalid job action');
}
