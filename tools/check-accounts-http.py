"""Exercise real PHP login and account permissions against disposable fixtures."""
import http.cookiejar,json,os,re,shutil,subprocess,tempfile,time,urllib.error,urllib.parse,urllib.request
from pathlib import Path

root=Path(__file__).resolve().parent.parent
assert 'dbname=desk_accounts_test' in os.environ['DESK_TEST_DSN']
password='Desk-test-password-123!'
count=0
def check(value,message):
 global count
 assert value,message
 count+=1
def client():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def request(c,path,fields=None,body=None,token=None):
 data=urllib.parse.urlencode(fields).encode() if fields is not None else json.dumps(body).encode() if body is not None else None
 headers={}
 if body is not None:headers['Content-Type']='application/json'
 if token:headers['X-CSRF-Token']=token
 try:
  with c.open(urllib.request.Request(base+path,data=data,headers=headers),timeout=10) as r:return r.status,r.read().decode()
 except urllib.error.HTTPError as e:return e.code,e.read().decode()
def csrf(html):
 m=re.search(r'name="csrf" value="([a-f0-9]+)"',html) or re.search(r'name="csrf-token" content="([a-f0-9]+)"',html)
 assert m,'CSRF token missing'
 return m.group(1)
def login(email,pw=password):
 c=client();_,page=request(c,'/login.php');status,page=request(c,'/login.php',{'csrf':csrf(page),'email':email,'password':pw})
 return c,status,page

with tempfile.TemporaryDirectory(prefix='desk-account-http-') as temp:
 temp=Path(temp)
 shutil.copytree(root/'desk1/desk.mortonsmechanical.com',temp/'public')
 shutil.copytree(root/'desk1/private',temp/'private',ignore=shutil.ignore_patterns('config.php','google.php','update-state'))
 # PHP getenv reads only the isolated test credentials provided by the workflow.
 (temp/'private/config.php').write_text("<?php return ['dsn'=>getenv('DESK_TEST_DSN'),'db_user'=>getenv('DESK_TEST_USER'),'db_password'=>getenv('DESK_TEST_PASSWORD'),'base_url'=>'http://127.0.0.1:8137'];")
 base='http://127.0.0.1:8137'
 with (temp/'php.log').open('w') as log:
  process=subprocess.Popen(['php','-S','127.0.0.1:8137','-t',str(temp/'public')],stdout=log,stderr=log)
  try:
   for _ in range(50):
    try:urllib.request.urlopen(base+'/login.php',timeout=1);break
    except Exception:time.sleep(.1)
   owner,status,page=login('owner@example.test');check(status==200 and 'id="root"' in page,'Owner login works')
   for attempt in range(11):
    _,login_status,login_page=login('owner@example.test');check(login_status==200 and 'id="root"' in login_page,'Successful login resets throttling')
   _,page=request(owner,'/accounts.php');owner_csrf=csrf(page)
   status,result=request(owner,'/accounts.php',{'csrf':owner_csrf,'action':'create','email':'reviewer-http@example.test','role':'viewer','new_password':password,'confirm_password':password,'owner_password':password})
   check(status==200 and 'reviewer-http@example.test' in result,'Owner can create read-only login')
   member_id=re.search(r'reviewer-http@example.test[\s\S]*?name="id" value="(\d+)"',result).group(1)
   viewer,status,page=login('reviewer-http@example.test');check(status==200 and 'Read-only account:' in page,'New read-only user can log in to desk')
   token=csrf(page)
   status,result=request(viewer,'/api.php?action=records');check(status==200 and json.loads(result)==[],'Viewer reads existing workspace')
   for path in ['/accounts.php','/updates.php','/google.php','/google-api.php?action=status']:
    status,_=request(viewer,path);check(status==403,'Viewer blocked from privileged page '+path)
   status,_=request(viewer,'/api.php?action=records',body={'id':'blocked','kind':'customer','customer':'Blocked'},token=token);check(status==403,'Read-only write blocked server-side')
   staff,status,page=login('staff@example.test');check(status==200,'Staff login works');token=csrf(page)
   status,result=request(staff,'/api.php?action=records',body={'id':'fixture-customer','kind':'customer','customer':'Fixture customer'},token=token)
   check(status==200,'Staff can save a business record')
   status,result=request(owner,'/api.php?action=records');check(status==200 and any(r['id']=='fixture-customer' for r in json.loads(result)),'Staff and owner share records')
   other,status,page=login('independent@example.test');status,result=request(other,'/api.php?action=records');check(status==200 and json.loads(result)==[],'Existing unrelated workspace stays isolated')
   for path in ['/accounts.php','/updates.php','/google-api.php?action=status']:
    status,_=request(staff,path);check(status==403,'Staff blocked from owner controls '+path)
   _,page=request(owner,'/accounts.php');owner_csrf=csrf(page)
   _,result=request(owner,'/accounts.php',{'csrf':owner_csrf,'action':'save','id':member_id,'role':'viewer','owner_password':password})
   check('Account updated.' in result,'Owner can disable login')
   status,_=request(viewer,'/api.php?action=records');check(status==401,'Disabled account loses active session')
   _,status,page=login('reviewer-http@example.test');check('Email or password is incorrect.' in page and 'id="root"' not in page,'Disabled account cannot log in again')
   _,page=request(owner,'/accounts.php');owner_csrf=csrf(page)
   _,result=request(owner,'/accounts.php',{'csrf':owner_csrf,'action':'save','id':'2','role':'staff','enabled':'yes','owner_password':password});check('does not belong' in result,'Owner cannot modify another workspace account')
   _,result=request(owner,'/accounts.php',{'csrf':owner_csrf,'action':'create','email':'no-csrf@example.test','role':'viewer','new_password':password,'confirm_password':password,'owner_password':'incorrect-password'});check('owner password is incorrect' in result,'Account changes require owner reauthentication')
   print('Passed',count,'real PHP login and account authorization checks.')
  finally:
   process.terminate();process.wait(timeout=10)
