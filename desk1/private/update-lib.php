<?php
declare(strict_types=1);

/** Owner-supplied code packages only. Never extracts ZIP paths directly. */
final class DeskUpdater {
    public string $public;
    public string $private;
    public string $state;
    private $lock;
    private const LIMIT = 52428800;

    public function __construct(string $public, string $private) {
        $this->public = realpath($public) ?: throw new RuntimeException('Public folder unavailable.');
        $this->private = realpath($private) ?: throw new RuntimeException('Private folder unavailable.');
        if ($this->private === $this->public || str_starts_with($this->private, $this->public.'/')) throw new RuntimeException('The private folder must be outside the public folder.');
        $this->state = $this->private.'/update-state';
        if (is_link($this->state)) throw new RuntimeException('Update storage cannot be a symbolic link.');
        if (!is_dir($this->state) && !mkdir($this->state,0700)) throw new RuntimeException('The private folder is not writable.');
        if (!is_writable($this->state)) throw new RuntimeException('Update storage is not writable.');
        if (is_link($this->state.'/lock')) throw new RuntimeException('Invalid update lock.');
        $this->lock = fopen($this->state.'/lock','c');
        if (!$this->lock) throw new RuntimeException('Cannot open update lock.');
    }
    public function exclusive(): void {
        if (!flock($this->lock,LOCK_EX|LOCK_NB)) throw new RuntimeException('The desk is busy. Wait for active requests to finish and try again.');
    }
    public function __destruct() {if(is_resource($this->lock)){flock($this->lock,LOCK_UN);fclose($this->lock);}}
    public static function relative(string $path): void {
        if (strlen($path)>200 || !preg_match('~^(public|private)/[A-Za-z0-9_-][A-Za-z0-9_./-]*$~D',$path)) throw new InvalidArgumentException('Invalid package path.');
        foreach(explode('/',$path) as $part) if($part===''||$part==='.'||$part==='..'||str_starts_with($part,'.')) throw new InvalidArgumentException('Unsafe package path.');
        $file=substr($path,strpos($path,'/')+1);
        if(str_starts_with($path,'private/')) {
            if(!in_array($file,['google-lib.php','workflows.php'],true)) throw new InvalidArgumentException('Package cannot change private configuration, updater, bootstrap, or database files.');
        } else {
            if(in_array(strtolower($file),['updates.php','setup.php','webhook.php'],true) || str_starts_with(strtolower($file),'uploads/')) throw new InvalidArgumentException('Package contains a protected file.');
            if(!preg_match('/\.(php|js|css|svg|png|jpg|jpeg|webp|ico|html|json|md)$/Di',$file)) throw new InvalidArgumentException('Unsupported file type in package.');
        }
    }
    public function target(string $path, bool $create=false): string {
        self::relative($path);
        [$area,$rel]=explode('/',$path,2);
        $root=$area==='public'?$this->public:$this->private;
        $parts=explode('/',$rel);array_pop($parts);$parent=$root;
        foreach($parts as $p){
            $parent.='/'.$p;
            if(is_link($parent)) throw new RuntimeException('Symbolic links are not allowed in update destinations.');
            if(!is_dir($parent)) {
                if(file_exists($parent)||!$create) throw new RuntimeException('A destination folder is missing.');
                if(!mkdir($parent,0755)) throw new RuntimeException('Cannot create destination folder.');
            }
        }
        $dest=$root.'/'.$rel;
        if(is_link($dest)|| (file_exists($dest)&&!is_file($dest))) throw new RuntimeException('Destination is not a regular file.');
        if(!str_starts_with(realpath(dirname($dest)).'/', $root.'/')) throw new RuntimeException('Destination escaped its root.');
        return $dest;
    }
    private function destination(string $path): string {
        // Check existing parents without creating directories during preview.
        self::relative($path);[$area,$rel]=explode('/',$path,2);$root=$area==='public'?$this->public:$this->private;
        $walk=$root;foreach(explode('/',$rel) as $part){$walk.='/'.$part;if(is_link($walk))throw new RuntimeException('Symbolic links are not supported.');}
        if(file_exists($walk)&&!is_file($walk))throw new RuntimeException('Destination is not a file.');
        return $walk;
    }
    private static function id(string $id): void {if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new InvalidArgumentException('Invalid update ID.');}
    private static function json(string $file): array {
        if(is_link($file)||!is_file($file))throw new RuntimeException('Update record unavailable.');
        $data=json_decode(file_get_contents($file),true,64,JSON_THROW_ON_ERROR);
        if(!is_array($data))throw new RuntimeException('Invalid update record.');return $data;
    }
    private static function save(string $file,array $value): void {
        $tmp=$file.'.'.bin2hex(random_bytes(8));
        $raw=json_encode($value,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT);
        if(file_put_contents($tmp,$raw,LOCK_EX)!==strlen($raw)){@unlink($tmp);throw new RuntimeException('Cannot save update journal.');}
        chmod($tmp,0600);
        if(!rename($tmp,$file)){@unlink($tmp);throw new RuntimeException('Cannot commit update journal.');}
    }
    private static function fingerprint(string $file): ?string {return is_file($file)?hash_file('sha256',$file):null;}
    public function stage(string $zipPath,int $owner): array {
        if(!class_exists('ZipArchive'))throw new RuntimeException('Enable PHP ZIP (ZipArchive) in Plesk before uploading an update.');
        if(filesize($zipPath)>20971520)throw new InvalidArgumentException('Update ZIP exceeds 20 MB.');
        if(is_file($this->state.'/active.json'))throw new RuntimeException('Recover the interrupted update before uploading another.');
        $zip=new ZipArchive();if($zip->open($zipPath)!==true)throw new InvalidArgumentException('This file is not a readable ZIP.');
        try {
            if($zip->numFiles>501)throw new InvalidArgumentException('Package has too many files.');
            $names=[];$total=0;
            for($i=0;$i<$zip->numFiles;$i++){
                $st=$zip->statIndex($i);$name=$st['name'];
                if($name!=='update.json')self::relative($name);
                $lower=strtolower($name);if(isset($names[$lower]))throw new InvalidArgumentException('Duplicate package paths.');$names[$lower]=$i;
                $opsys=0;$attrs=0;$zip->getExternalAttributesIndex($i,$opsys,$attrs);
                $mode=($attrs>>16)&0170000;if($mode!==0&&$mode!==0100000)throw new InvalidArgumentException('ZIP links and non-file entries are forbidden.');
                $total+=$st['size'];if($total>self::LIMIT||$st['size']>20971520)throw new InvalidArgumentException('Expanded update is too large.');
                if(($st['encryption_method']??0)!==0)throw new InvalidArgumentException('Encrypted ZIP entries are unsupported.');
            }
            $raw=$zip->getFromName('update.json',65537);
            if($raw===false||strlen($raw)>65536)throw new InvalidArgumentException('Missing or oversized update.json. Use a Service Desk update package.');
            $manifest=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
            if(($manifest['format']??'')!=='mortons-desk-update-v1'||!is_string($manifest['version']??null)||strlen($manifest['version'])>100||!is_string($manifest['title']??null)||strlen($manifest['title'])>200||!is_array($manifest['files']??null)||!array_is_list($manifest['files'])||!count($manifest['files'])||count($manifest['files'])>500)throw new InvalidArgumentException('Invalid update manifest.');
            if(!empty($manifest['migrations'])||!empty($manifest['delete']))throw new InvalidArgumentException('Database migrations and file deletions cannot run through this updater.');
            $id=bin2hex(random_bytes(16));$dir=$this->state.'/stage-'.$id;
            if(!mkdir($dir,0700))throw new RuntimeException('Cannot create staging directory.');
            $files=[];$seen=[];
            foreach($manifest['files'] as $i=>$entry){
                if(!is_array($entry)||!is_string($entry['path']??null)||!preg_match('/^[a-f0-9]{64}$/D',$entry['sha256']??''))throw new InvalidArgumentException('Invalid manifest file.');
                $path=$entry['path'];self::relative($path);$key=strtolower($path);
                if(isset($seen[$key])||!isset($names[$key])||$zip->getNameIndex($names[$key])!==$path)throw new InvalidArgumentException('Manifest paths must exactly match unique ZIP entries.');$seen[$key]=true;
                $dest=$this->destination($path);$stream=$zip->getStream($path);if(!$stream)throw new RuntimeException('Cannot read update payload.');
                $out=fopen($dir.'/'.$i,'xb');if(!$out){fclose($stream);throw new RuntimeException('Cannot stage payload.');}
                $written=stream_copy_to_stream($stream,$out,20971521);fclose($stream);fclose($out);chmod($dir.'/'.$i,0600);
                $stat=$zip->statName($path);
                if($written!==$stat['size']||!hash_equals($entry['sha256'],hash_file('sha256',$dir.'/'.$i)))throw new InvalidArgumentException('File integrity check failed.');
                $files[]=['path'=>$path,'sha256'=>$entry['sha256'],'size'=>$written,'before'=>self::fingerprint($dest)];
            }
            if(count($seen)+1!==count($names))throw new InvalidArgumentException('ZIP contains files that are not in its manifest.');
            $record=['id'=>$id,'owner'=>$owner,'title'=>$manifest['title'],'version'=>$manifest['version'],'files'=>$files,'created'=>time(),'status'=>'staged'];
            self::save($dir.'/record.json',$record);return $record;
        } finally {$zip->close();}
    }
    public function staged(string $id,int $owner): array {
        self::id($id);$r=self::json($this->state.'/stage-'.$id.'/record.json');
        if($r['owner']!==$owner||$r['status']!=='staged'||$r['created']<time()-3600)throw new RuntimeException('Upload preview expired or was already used. Upload the package again.');return $r;
    }
    private static function atomic(string $source,string $dest,int $mode): void {
        $tmp=dirname($dest).'/.desk-write-'.bin2hex(random_bytes(12));
        if(!copy($source,$tmp)){@unlink($tmp);throw new RuntimeException('Cannot prepare replacement file.');}
        if(!chmod($tmp,$mode)||!rename($tmp,$dest)){@unlink($tmp);throw new RuntimeException('Cannot replace website file.');}
        if(function_exists('opcache_invalidate'))@opcache_invalidate($dest,true);
        clearstatcache(true,$dest);
    }
    public function apply(string $id,int $owner): array {
        if(is_file($this->state.'/active.json'))throw new RuntimeException('Recover the interrupted update first.');
        $r=$this->staged($id,$owner);$stage=$this->state.'/stage-'.$id;$needed=0;
        foreach($r['files'] as $i=>$f){
            $dest=$this->destination($f['path']);$needed+=$f['size']+(is_file($dest)?filesize($dest):0);
            if(self::fingerprint($dest)!==$f['before'])throw new RuntimeException('A website file changed since preview. Upload the ZIP again.');
            if(!is_file($stage.'/'.$i)||!hash_equals($f['sha256'],hash_file('sha256',$stage.'/'.$i)))throw new RuntimeException('Staged file changed. Upload the ZIP again.');
            $parent=dirname($dest);while(!is_dir($parent))$parent=dirname($parent);
            if(!is_writable($parent)||(is_file($dest)&&!is_writable($dest)))throw new RuntimeException('Website files are not writable by PHP. Check Plesk ownership and permissions.');
        }
        foreach([$this->public,$this->state] as $disk){$free=disk_free_space($disk);if($free!==false&&$free<$needed*3+10485760)throw new RuntimeException('Not enough free space for backups and replacements.');}
        $backup=$this->state.'/backup-'.$id;if(!mkdir($backup,0700))throw new RuntimeException('Cannot create backup.');
        foreach($r['files'] as $i=>&$f){
            $dest=$this->destination($f['path']);$f['mode']=is_file($dest)?(fileperms($dest)&0777):($f['path'][0]==='p'&&str_starts_with($f['path'],'private/')?0600:0644);
            if($f['before']!==null){if(!copy($dest,$backup.'/'.$i)||!hash_equals($f['before'],hash_file('sha256',$backup.'/'.$i)))throw new RuntimeException('Backup failed; website files were not changed.');chmod($backup.'/'.$i,0600);}
        }unset($f);
        $r['status']='applying';$r['appliedAt']=time();self::save($backup.'/record.json',$r);
        self::save($this->state.'/active.json',['id'=>$id]);
        $rStage=$r;$rStage['status']='consumed';self::save($stage.'/record.json',$rStage);
        try {
            foreach($r['files'] as $i=>$f){$dest=$this->target($f['path'],true);self::atomic($stage.'/'.$i,$dest,$f['mode']);if(!hash_equals($f['sha256'],hash_file('sha256',$dest)))throw new RuntimeException('Replacement verification failed.');}
            $r['status']='installed';self::save($backup.'/record.json',$r);
            if(!unlink($this->state.'/active.json'))throw new RuntimeException('Cannot leave maintenance mode. Use recovery.');
            return $r;
        } catch(Throwable $e){
            try{$this->restore($id,$owner,true);}catch(Throwable $restore){throw new RuntimeException('Update interrupted. Use Recover below. Backups remain in private/update-state.');}
            throw new RuntimeException('Update failed and original files were restored. '.$e->getMessage());
        }
    }
    public function restore(string $id,int $owner,bool $automatic=false): void {
        self::id($id);$dir=$this->state.'/backup-'.$id;$r=self::json($dir.'/record.json');
        if($r['owner']!==$owner||!in_array($r['status'],['installed','applying','restoring'],true))throw new RuntimeException('Backup cannot be restored.');
        $active=$this->active();if($active&&$active['id']!==$id)throw new RuntimeException('Recover the active update first.');
        // Never overwrite a subsequent manual edit or a newer update.
        foreach($r['files'] as $i=>$f){
            $dest=$this->destination($f['path']);$current=self::fingerprint($dest);
            if($current!==$f['sha256']&&$current!==$f['before'])throw new RuntimeException('A file has changed since this update. Restore its backup manually in Plesk to preserve later edits.');
            if($f['before']!==null&&(!is_file($dir.'/'.$i)||!hash_equals($f['before'],hash_file('sha256',$dir.'/'.$i))))throw new RuntimeException('Backup integrity check failed.');
        }
        $r['status']='restoring';self::save($dir.'/record.json',$r);self::save($this->state.'/active.json',['id'=>$id]);
        foreach($r['files'] as $i=>$f){
            $dest=$this->target($f['path'],true);
            if($f['before']!==null)self::atomic($dir.'/'.$i,$dest,$f['mode']);
            elseif(is_file($dest)&&!unlink($dest))throw new RuntimeException('Could not remove a newly installed file.');
            if(self::fingerprint($dest)!==$f['before'])throw new RuntimeException('Restored file verification failed.');
            if(function_exists('opcache_invalidate'))@opcache_invalidate($dest,true);
        }
        if(!unlink($this->state.'/active.json'))throw new RuntimeException('Cannot leave maintenance mode.');
        $r['status']='restored';self::save($dir.'/record.json',$r);
    }
    public function active(): ?array {return is_file($this->state.'/active.json')?self::json($this->state.'/active.json'):null;}
    public function history(int $owner): array {
        $rows=[];foreach(glob($this->state.'/backup-*/record.json')?:[] as $p){$r=self::json($p);if($r['owner']===$owner)$rows[]=$r;}
        usort($rows,fn($a,$b)=>($b['appliedAt']??0)<=>($a['appliedAt']??0));return array_slice($rows,0,10);
    }
}
