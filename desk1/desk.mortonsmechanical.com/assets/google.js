'use strict';
(() => {
    const $ = id => document.getElementById(id);
    const days = ['MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY','SUNDAY'];
    let profile = null, revisions = {}, selected = '', nextPosts = '', busy = false;
    let operationId = crypto.randomUUID(), newAttempted = false;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    function notice(message, error=false) {
        $('notice').textContent = message; $('notice').classList.toggle('error', error);
    }
    async function api(action, body, query={}) {
        const params = new URLSearchParams({action,...query});
        const response = await fetch('/google-api.php?' + params, {
            method: body === undefined ? 'GET' : 'POST', credentials:'same-origin',
            headers: body === undefined ? {} : {'Content-Type':'application/json','X-CSRF-Token':csrf},
            ...(body === undefined ? {} : {body:JSON.stringify(body)})
        });
        let data;
        try {data = await response.json();} catch {throw Error('Unexpected server response. Check your login and hosting logs.');}
        if (!response.ok || data.error) throw Error(data.error || 'Google request failed.');
        return data;
    }
    async function run(task) {
        if (busy) return;
        busy = true;
        const controls = Array.from(document.querySelectorAll('button,input,select,textarea'));
        const prior = controls.map(el => el.disabled);
        controls.forEach(el => {el.disabled=true;});
        try {await task();} catch(e) {notice(e.message,true);}
        finally {controls.forEach((el,i)=>{el.disabled=prior[i];}); busy=false;}
    }
    function element(tag, text, props={}) {
        const el=document.createElement(tag); if (text!==null) el.textContent=text;
        Object.assign(el,props); return el;
    }
    function time(t) {return String(t?.hours||0).padStart(2,'0')+':'+String(t?.minutes||0).padStart(2,'0');}
    function date(d) {return d ? String(d.year).padStart(4,'0')+'-'+String(d.month).padStart(2,'0')+'-'+String(d.day).padStart(2,'0') : '';}
    function daySelect(value,label) {
        const s=element('select',null); s.setAttribute('aria-label',label);
        days.forEach(d=>s.append(element('option',d.toLowerCase(),{value:d})));s.value=value;return s;
    }
    function timeInput(value,label) {
        const el=element('input',null,{value,placeholder:'HH:MM',maxLength:5});
        el.setAttribute('aria-label',label);el.inputMode='text';return el;
    }
    function regularRow(p={openDay:'MONDAY',openTime:{hours:9},closeDay:'MONDAY',closeTime:{hours:17}}) {
        const tr=element('tr',null);
        [daySelect(p.openDay,'Opening day'),timeInput(time(p.openTime),'Opening time'),daySelect(p.closeDay,'Closing day'),timeInput(time(p.closeTime),'Closing time')].forEach(el=>{const td=element('td',null);td.append(el);tr.append(td);});
        const td=element('td',null), remove=element('button','Remove',{className:'secondary'});
        remove.addEventListener('click',()=>tr.remove());td.append(remove);tr.append(td);$('regular-rows').append(tr);
    }
    function specialRow(open='09:00',close='17:00') {
        const row=element('div',null,{className:'special-row'});
        row.append(timeInput(open,'Special opening time'),element('span','to'),timeInput(close,'Special closing time'));
        const remove=element('button','Remove',{className:'secondary'});remove.onclick=()=>row.remove();row.append(remove);$('special-rows').append(row);
    }
    function loadDate() {
        $('special-rows').replaceChildren();
        const matches=(profile?.specialHours?.specialHourPeriods||[]).filter(p=>date(p.startDate)===$('special-date').value);
        $('special-mode').value=matches.length ? (matches.some(p=>p.closed)?'closed':'hours') : 'closed';
        matches.filter(p=>!p.closed).forEach(p=>specialRow(time(p.openTime),time(p.closeTime)));
        if (!$('special-rows').children.length) specialRow();
        $('special-editor').hidden=$('special-mode').value!=='hours';
    }
    function renderProfile() {
        $('business-title').textContent=profile.title || 'Selected business';
        const status=profile.openInfo?.status || 'Unknown';
        $('business-status').textContent='Google business status: '+status.replaceAll('_',' ').toLowerCase();
        $('regular-rows').replaceChildren();
        (profile.regularHours?.periods||[]).forEach(regularRow);
        $('special-list').replaceChildren();
        const list=element('ul',null);
        (profile.specialHours?.specialHourPeriods||[]).forEach(p=>{
            const label=date(p.startDate)+(date(p.endDate)&&date(p.endDate)!==date(p.startDate)?' → '+date(p.endDate):'')+': '+(p.closed?'Closed':time(p.openTime)+'–'+time(p.closeTime));
            const li=element('li',null), button=element('button',label,{className:'secondary'});
            button.onclick=()=>{$('special-date').value=date(p.startDate);loadDate();};li.append(button);list.append(li);
        });
        $('special-list').append(list.children.length?list:element('p','No special dates set.'));
        loadDate();$('business').hidden=false;
    }
    async function refreshProfile() {
        const data=await api('profile'); profile=data.profile;revisions=data.revisions;selected=profile.name;renderProfile();
    }
    function renderPost(post) {
        const card=element('div',null,{className:'post'});
        card.append(element('p',(post.topicType || 'Post')+' · '+(post.state || 'Unknown')));
        const area=element('textarea',null,{value:post.summary,rows:5,maxLength:1500});
        area.setAttribute('aria-label','Post text');
        const save=element('button','Save post text to Google');
        save.onclick=()=>run(async()=>{
            if(!confirm('Update this post on '+profile.title+'?'))return;
            const data=await api('edit_post',{location:selected,name:post.name,revision:post.revision,summary:area.value});
            notice(data.message);
            try {await refreshPosts(false);}catch(e){notice(data.message+' Reloading posts failed: '+e.message,true);}
        });
        card.append(area,save);$('post-list').append(card);
    }
    async function refreshPosts(append=false) {
        const data=await api('posts',undefined,append && nextPosts?{pageToken:nextPosts}:{});
        if(!append)$('post-list').replaceChildren();
        data.posts.forEach(renderPost);
        if(!append&&!data.posts.length)$('post-list').append(element('p','No posts found.'));
        nextPosts=data.nextPageToken;$('more-posts').hidden=!nextPosts;
    }
    async function loadLocations(account, chosen='') {
        $('location').replaceChildren(element('option','Choose profile',{value:''}));
        if(!account)return;
        const data=await api('locations',undefined,{account});
        data.locations.forEach(l=>{
            const address=(l.storefrontAddress?.addressLines||[]).join(', ');
            $('location').append(element('option',(l.title||l.name)+(address?' — '+address:''),{value:l.name}));
        });
        $('location').value=chosen;
    }
    async function reload() {
        $('business').hidden=true;profile=null;selected='';nextPosts='';
        const status=await api('status');
        $('connection-status').textContent=status.connected?'Google is connected.':'Google is not connected.';
        $('disconnect').hidden=!status.connected;$('selection').hidden=!status.connected;
        if(!status.connected)return;
        const data=await api('accounts');
        $('account').replaceChildren(element('option','Choose account',{value:''}));
        data.accounts.forEach(a=>$('account').append(element('option',a.accountName||a.name,{value:a.name})));
        $('account').value=status.selected?.account||'';
        await loadLocations($('account').value,status.selected?.location||'');
        if(status.selected?.location) {
            await refreshProfile();
            try {await refreshPosts();} catch(e){$('post-list').textContent='Posts could not load. Use Reload Google data to retry.';notice('Hours loaded. '+e.message,true);}
        }
    }
    async function saveProfile(action,body) {
        const data=await api(action,{location:selected,...body});
        notice(data.message);
        try {await refreshProfile();}catch(e){$('business').hidden=true;notice(data.message+' Reload failed: '+e.message,true);}
    }
    $('reload').onclick=()=>run(reload);
    $('account').onchange=()=>run(()=>loadLocations($('account').value));
    $('select-profile').onclick=()=>run(async()=>{
        await api('select',{account:$('account').value,location:$('location').value});
        operationId=crypto.randomUUID();newAttempted=false;$('new-summary').value='';
        await reload();notice('Business selected.');
    });
    $('disconnect').onclick=()=>run(async()=>{
        if(!confirm('Remove the Google connection from this Service Desk? Your public profile stays unchanged.'))return;
        await api('disconnect',{});await reload();notice('Disconnected from this desk. To revoke Google permission too, remove the app in your Google account connections.');
    });
    $('add-regular').onclick=()=>regularRow();
    $('save-regular').onclick=()=>run(async()=>{
        if(!confirm('Replace regular hours on '+profile.title+' with the periods shown? Days without a row will be closed.'))return;
        const periods=Array.from($('regular-rows').children).map(tr=>{
            const v=Array.from(tr.querySelectorAll('select,input')).map(el=>el.value);
            return {openDay:v[0],openTime:v[1],closeDay:v[2],closeTime:v[3]};
        });
        await saveProfile('regular',{revision:revisions.regular,periods});
    });
    $('temporary').onclick=()=>run(async()=>{
        if(confirm('Mark '+profile.title+' TEMPORARILY CLOSED on Google until you manually reopen?'))
            await saveProfile('status',{revision:revisions.status,status:'CLOSED_TEMPORARILY'});
    });
    $('reopen').onclick=()=>run(async()=>{
        if(confirm('Reopen '+profile.title+' on Google? Existing regular and special hours will still apply.'))
            await saveProfile('status',{revision:revisions.status,status:'OPEN'});
    });
    $('special-date').onchange=loadDate;
    $('special-mode').onchange=()=>{$('special-editor').hidden=$('special-mode').value!=='hours';};
    $('add-special').onclick=()=>specialRow();
    $('save-special').onclick=()=>run(async()=>{
        const d=$('special-date').value;if(!d)throw Error('Choose a date.');
        if(!confirm('Save '+$('special-mode').selectedOptions[0].text+' for '+d+' on '+profile.title+'?'))return;
        const periods=Array.from($('special-rows').children).map(row=>{const v=row.querySelectorAll('input');return {open:v[0].value,close:v[1].value};});
        await saveProfile('special',{revision:revisions.special,date:d,mode:$('special-mode').value,periods});
    });
    $('more-posts').onclick=()=>run(()=>refreshPosts(true));
    $('create-post').onclick=()=>run(async()=>{
        if(newAttempted)throw Error('This submission has already been attempted. Reload posts to check the result, then choose Start another post.');
        if(!$('new-summary').value.trim())throw Error('Enter post text.');
        if(!confirm('Publish this update to '+profile.title+' on Google?'))return;
        newAttempted=true;
        const data=await api('create_post',{location:selected,operationId,summary:$('new-summary').value});
        notice(data.message);
        try {await refreshPosts();}catch(e){notice(data.message+' Reloading posts failed: '+e.message,true);}
    });
    $('reset-post').onclick=()=>{
        if(newAttempted&&!confirm('Have you checked Google and the post list for the last submission? Starting another can create a duplicate.'))return;
        operationId=crypto.randomUUID();newAttempted=false;$('new-summary').value='';
    };
    const today=new Date();$('special-date').value=today.getFullYear()+'-'+String(today.getMonth()+1).padStart(2,'0')+'-'+String(today.getDate()).padStart(2,'0');
    run(reload);
})();
