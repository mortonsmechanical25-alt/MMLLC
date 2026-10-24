<?php
declare(strict_types=1);
define('DESK_UPDATER_REQUEST',true);
require dirname(__DIR__).'/private/bootstrap.php';
$owner=user();
$allowed=$cfg['update_owner_ids']??[1];
if(!is_array($allowed)||!in_array($owner,$allowed,true)){http_response_code(403);exit('Only the Service Desk owner may install updates.');}
require dirname(__DIR__).'/private/update-lib.php';
require dirname(__DIR__).'/private/tools-layout.php';
$notice='';$error=false;$preview=null;$history=[];$active=null;$updater=null;
try {
    $updater=new DeskUpdater(__DIR__,dirname(__DIR__).'/private');
    $updater->exclusive();
    if($_SERVER['REQUEST_METHOD']==='POST'){
        csrf();$action=$_POST['action']??'';
        if($action==='upload'){
            $file=$_FILES['package']??null;
            if(!$file||$file['error']!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']))throw new InvalidArgumentException('Upload failed. Choose a ZIP within your hosting upload limit (maximum 20 MB).');
            $preview=$updater->stage($file['tmp_name'],$owner);
            $_SESSION['desk_update_preview']=$preview['id'];
            $notice='Package checked. Review the files below before installing.';
        } elseif(in_array($action,['apply','restore'],true)) {
            $attempt=$_SESSION['desk_update_password_attempt']??['count'=>0,'start'=>time()];
            if(time()-$attempt['start']>900)$attempt=['count'=>0,'start'=>time()];
            if($attempt['count']>=5)throw new InvalidArgumentException('Too many incorrect passwords. Try again after 15 minutes.');
            $q=db()->prepare('SELECT password_hash FROM users WHERE id=?');$q->execute([$owner]);$hash=$q->fetchColumn();
            if(!is_string($_POST['password']??null)||!$hash||!password_verify($_POST['password'],$hash)){
                $attempt['count']++;$_SESSION['desk_update_password_attempt']=$attempt;
                throw new InvalidArgumentException('Your Service Desk password is incorrect.');
            }
            unset($_SESSION['desk_update_password_attempt']);
            if(($_POST['confirm']??'')!=='yes')throw new InvalidArgumentException('Confirm that you want to install or restore these files.');
            if($action==='apply'){
                if(!is_string($_POST['id']??null)||($_SESSION['desk_update_preview']??'')!==$_POST['id'])throw new InvalidArgumentException('This preview is no longer current. Upload the package again.');
                $done=$updater->apply($_POST['id'],$owner);unset($_SESSION['desk_update_preview']);
                $_SESSION['desk_update_notice']='Update installed: '.$done['title'].'. A backup of the replaced files is available below.';
            } else {
                $updater->restore(is_string($_POST['id']??null)?$_POST['id']:'',$owner);
                $_SESSION['desk_update_notice']='Original website files restored successfully.';
            }
            header('Location: /updates.php',true,303);exit;
        } elseif($action==='cancel') {
            unset($_SESSION['desk_update_preview']);$notice='Preview dismissed. No website files were changed.';
        } else throw new InvalidArgumentException('Unknown update action.');
    }
    if(!$preview&&!empty($_SESSION['desk_update_preview'])){
        try{$preview=$updater->staged($_SESSION['desk_update_preview'],$owner);}catch(Throwable $e){unset($_SESSION['desk_update_preview']);}
    }
    $active=$updater->active();$history=$updater->history($owner);
} catch(Throwable $e){
    $error=true;
    if($e instanceof InvalidArgumentException||$e instanceof RuntimeException)$notice=$e->getMessage();
    else {error_log('Mortons updater: '.$e->getMessage());$notice='Unable to process the update. Check your hosting error log.';}
    if($updater){try{$active=$updater->active();$history=$updater->history($owner);}catch(Throwable $ignored){}}
}
// Release locks before rendering the page; persistent active.json keeps interrupted updates blocked.
unset($updater);
if(!$notice&&!empty($_SESSION['desk_update_notice'])){$notice=$_SESSION['desk_update_notice'];unset($_SESSION['desk_update_notice']);}
desk_tools_start('Site updates','updates');
?>
<p class="page-intro">Upload a Service Desk update, review the changes, and install it from here.</p>
<div class="tool-notice <?=$error?'error':''?>" role="status" aria-live="polite"><?=h($notice)?></div>
<?php if($active): ?><div class="tool-notice error" role="alert">An update was interrupted. The desk is temporarily paused. Use Recover in the history below. If recovery fails, restore the numbered backup files using Plesk and the matching record.json in private/update-state.</div><?php endif; ?>
<div class="update-steps"><div class="update-step <?=$preview?'':'current'?>"><span>1</span>Upload package</div><div class="update-step <?=$preview?'current':''?>"><span>2</span>Review &amp; install</div><div class="update-step"><span>3</span>Ready to use</div></div>
<div class="tool-split"><div>
<?php if($preview&&!$active): ?>
<section class="tool-card"><h2><?=h($preview['title'])?></h2><p>Version <?=h($preview['version'])?> · <?=count($preview['files'])?> files · Preview valid for one hour.</p>
<div class="table-wrap file-preview"><table><thead><tr><th>File</th><th>Change</th><th>Size</th></tr></thead><tbody><?php foreach($preview['files'] as $f): ?><tr><td class="file-path"><?=h($f['path'])?></td><td><span class="badge <?=$f['before']===null?'green':''?>"><?=$f['before']===null?'New file':'Replace'?></span></td><td><?=number_format($f['size']/1024,1)?> KB</td></tr><?php endforeach; ?></tbody></table></div>
<form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="apply"><input type="hidden" name="id" value="<?=h($preview['id'])?>">
<label>Your Service Desk password<input type="password" name="password" autocomplete="current-password" required></label>
<label class="check-label"><input type="checkbox" name="confirm" value="yes" required>I trust this package and want to install the files shown above.</label>
<div class="actions"><button class="primary">Back up &amp; install update</button></div></form>
<form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="cancel"><button class="text-button">Cancel this update</button></form>
</section>
<?php else: ?>
<section class="tool-card"><h2>Upload an update</h2><p>Choose an update ZIP made for Mortons Mechanical Service Desk.</p>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="upload">
<div class="upload-zone" id="upload-zone"><div class="upload-icon" aria-hidden="true">↑</div><h3>Drop your update ZIP here</h3><p>Or choose a file from your device.</p><label for="update-package" class="muted">Update package</label><input id="update-package" type="file" name="package" accept=".zip,application/zip" required <?=$active?'disabled':''?>><small id="upload-name">ZIP files only · maximum 20 MB</small></div>
<div class="actions"><button class="primary" <?=$active?'disabled':''?>>Check update package</button></div></form></section>
<?php endif; ?>
<section class="tool-card"><h2>Update history</h2><p>Backups let you restore the files replaced by an update.</p>
<?php if(!$history): ?><div class="quiet">No updates installed yet.</div><?php endif; ?>
<?php foreach($history as $row): ?><div class="history-row"><div><strong><?=h($row['title'])?></strong><small><?=h($row['version'])?> · <?=h(date('M j, Y g:i a',$row['appliedAt']??$row['created']))?></small><small>Status: <?=h($row['status'])?></small></div>
<?php if(in_array($row['status'],['installed','applying','restoring'],true)): ?><details><summary class="text-button"><?=($active&&$active['id']===$row['id'])?'Recover':'Restore backup'?></summary><form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="restore"><input type="hidden" name="id" value="<?=h($row['id'])?>"><label>Service Desk password<input type="password" name="password" autocomplete="current-password" required></label><label class="check-label"><input type="checkbox" name="confirm" value="yes" required>Restore the original files from this backup.</label><button class="secondary">Restore files</button></form></details><?php endif; ?></div><?php endforeach; ?>
</section></div>
<aside><section class="tool-card tool-aside"><h2>Before you install</h2><ul><li>Use update ZIPs supplied for this Service Desk.</li><li>You can review every file before it changes.</li><li>Replaced files are backed up automatically.</li><li>The desk pauses briefly while files are being replaced.</li></ul><p>Customer records, invoices, and settings stay in your existing database. This updater installs website files; database changes are handled separately.</p></section>
<section class="tool-card"><h2>Hosting requirements</h2><p>PHP ZIP must be enabled. PHP needs write access to the website files and the private update storage folder.</p><p class="muted">The first updater installation is a one-time upload in Plesk. After that, use this page for compatible update ZIPs.</p></section></aside></div>
<script src="/assets/update-upload.js" defer></script>
<?php desk_tools_end(); ?>
