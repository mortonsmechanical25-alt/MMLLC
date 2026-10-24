<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/private/update-lib.php';
$root=sys_get_temp_dir().'/mortons-updater-test-'.bin2hex(random_bytes(8));mkdir($root,0700);mkdir($root.'/public');mkdir($root.'/private');
$count=0;
function ok(bool $v,string $label):void{global $count;if(!$v)throw new RuntimeException($label);$count++;}
function reject(callable $fn,string $label):void{try{$fn();}catch(Throwable $e){ok(true,$label);return;}throw new RuntimeException($label);}
function package(array $payload,array $extra=[],?array $overrides=null):string{
 global $root;$p=$root.'/'.bin2hex(random_bytes(8)).'.zip';$z=new ZipArchive();$z->open($p,ZipArchive::CREATE);
 $files=[];foreach($payload as $name=>$content){$files[]=['path'=>$name,'sha256'=>hash('sha256',$content)];$z->addFromString($name,$content);}
 $m=['format'=>'mortons-desk-update-v1','title'=>'Updater test','version'=>'1.0.0','files'=>$files];
 if($overrides)$m=array_replace($m,$overrides);$z->addFromString('update.json',json_encode($m));foreach($extra as $name=>$content)$z->addFromString($name,$content);$z->close();return $p;
}
function clean(string $dir):void{foreach(scandir($dir) as $f){if($f==='.'||$f==='..')continue;$p=$dir.'/'.$f;if(is_dir($p)&&!is_link($p))clean($p);else unlink($p);}rmdir($dir);}
try{
 $u=new DeskUpdater($root.'/public',$root.'/private');$u->exclusive();
 foreach(['../config.php','public/../private/config.php','public/a/../../escape.php','public/.htaccess','public//x.php','public\\x.php','public/updates.php','private/config.php','private/google.php','private/bootstrap.php','private/update-lib.php','private/tools-layout.php','private/schema.sql','public/uploads/evil.php'] as $path)reject(fn()=>DeskUpdater::relative($path),'Reject unsafe/protected '.$path);
 file_put_contents($root.'/public/index.php','old index');file_put_contents($root.'/private/config.php','secret credential');
 $p=package(['public/index.php'=>'new index','public/assets/new.css'=>'body{color:green}']);
 $r=$u->stage($p,1);ok(count($r['files'])===2,'Payload preview');ok(file_get_contents($root.'/public/index.php')==='old index','Staging never changes installed files');
 reject(fn()=>$u->staged($r['id'],2),'Other owner cannot use staged package');
 $u->apply($r['id'],1);ok(file_get_contents($root.'/public/index.php')==='new index','Replaced file installed');ok(is_file($root.'/public/assets/new.css'),'New file installed');ok(file_get_contents($root.'/private/config.php')==='secret credential','Credentials preserved');ok($u->active()===null,'Maintenance cleared after success');
 reject(fn()=>$u->apply($r['id'],1),'Consumed preview cannot be replayed');
 $u->restore($r['id'],1);ok(file_get_contents($root.'/public/index.php')==='old index','Original file restored');ok(!file_exists($root.'/public/assets/new.css'),'New file removed by restore');ok($u->active()===null,'Maintenance cleared after restore');
 $r=$u->stage($p,1);file_put_contents($root.'/public/index.php','manual edit');reject(fn()=>$u->apply($r['id'],1),'Stale preview rejected');ok(file_get_contents($root.'/public/index.php')==='manual edit','Stale preview preserves later edit');
 $r=$u->stage($p,1);file_put_contents($root.'/private/update-state/stage-'.$r['id'].'/0','tampered');reject(fn()=>$u->apply($r['id'],1),'Staging tamper rejected');
 reject(fn()=>$u->stage(package(['public/x.css'=>'a'],['public/unlisted.js'=>'x']),1),'Unlisted payload rejected');
 reject(fn()=>$u->stage(package(['public/x.css'=>'a'],[],['delete'=>['public/index.php']]),1),'File deletion manifest rejected');
 reject(fn()=>$u->stage(package(['public/x.css'=>'a'],[],['migrations'=>['DROP TABLE users']]),1),'SQL migrations rejected');
 reject(fn()=>$u->stage(package(['public/x.css'=>'a'],[],['files'=>[['path'=>'public/x.css','sha256'=>str_repeat('0',64)]]]),1),'Payload hash mismatch rejected');
 $zpath=package(['public/symlink.php'=>'anything']);$z=new ZipArchive();$z->open($zpath);$z->setExternalAttributesName('public/symlink.php',ZipArchive::OPSYS_UNIX,0120777<<16);$z->close();reject(fn()=>$u->stage($zpath,1),'ZIP symbolic link rejected');
 symlink($root.'/private',$root.'/public/linked');reject(fn()=>$u->stage(package(['public/linked/index.php'=>'bad']),1),'Destination symbolic link rejected');unlink($root.'/public/linked');
 $r=$u->stage($p,1);$u->apply($r['id'],1);file_put_contents($root.'/public/index.php','later change');reject(fn()=>$u->restore($r['id'],1),'Restore refuses to overwrite later manual edit');
 file_put_contents($root.'/public/index.php','new index');$u->restore($r['id'],1);ok(file_get_contents($root.'/public/index.php')==='manual edit','Restores exact pre-update bytes');
 // Simulate a process stopping after one of two replacements, before the journal commits.
 $r=$u->stage($p,1);$u->apply($r['id'],1);$backup=$root.'/private/update-state/backup-'.$r['id'];$record=json_decode(file_get_contents($backup.'/record.json'),true);$record['status']='applying';file_put_contents($backup.'/record.json',json_encode($record));file_put_contents($root.'/private/update-state/active.json',json_encode(['id'=>$r['id']]));copy($backup.'/0',$root.'/public/index.php');
 reject(fn()=>$u->stage($p,1),'Interrupted update blocks new uploads');$u->restore($r['id'],1);ok($u->active()===null,'Interrupted update recovered');ok(!is_file($root.'/public/assets/new.css'),'Recovery removes new payload');
 // Existing request lock blocks a writer, and an exclusive writer blocks a reader.
 unset($u);$reader=fopen($root.'/private/update-state/lock','c');flock($reader,LOCK_SH);$writer=new DeskUpdater($root.'/public',$root.'/private');reject(fn()=>$writer->exclusive(),'Active website request blocks installation');flock($reader,LOCK_UN);fclose($reader);$writer->exclusive();$reader=fopen($root.'/private/update-state/lock','c');ok(!flock($reader,LOCK_SH|LOCK_NB),'Installer blocks concurrent website request');fclose($reader);unset($writer);
 echo 'Passed '.$count." updater integrity, rollback, and locking checks.\n";
} finally{unset($u,$writer);clean($root);}
