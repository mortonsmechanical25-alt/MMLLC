<?php
declare(strict_types=1);
require dirname(__DIR__).'/private/bootstrap.php';
$workspace=user();$identity=desk_require_owner();
require dirname(__DIR__).'/private/tools-layout.php';
$notice='';$error=false;$ready=desk_accounts_ready(db());
try {
    if($_SERVER['REQUEST_METHOD']==='POST'){
        csrf();
        if(!$ready)throw new InvalidArgumentException('Import accounts-schema.sql into the existing database first.');
        desk_verify_owner_password(db(),$identity['id'],$_POST['owner_password']??null);
        $action=$_POST['action']??'';
        if(!in_array($action,['create','save'],true))throw new InvalidArgumentException('Unknown account action.');
        $role=desk_member_role($_POST['role']??'viewer');
        $newHash=null;
        if($action==='create'||($_POST['new_password']??'')!==''){
            if(($_POST['new_password']??null)!==($_POST['confirm_password']??null))throw new InvalidArgumentException('The new passwords do not match.');
            $newHash=desk_new_password($_POST['new_password']??null);
        }
        db()->beginTransaction();
        $q=db()->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');$q->execute([$workspace]);
        if($action==='create'){
            $email=is_string($_POST['email']??null)?strtolower(trim($_POST['email'])):'';
            if(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a valid email address.');
            $q=db()->prepare('SELECT COUNT(*) FROM desk_memberships WHERE workspace_owner=?');$q->execute([$workspace]);
            if((int)$q->fetchColumn()>=50)throw new InvalidArgumentException('This desk supports up to 50 additional accounts.');
            $q=db()->prepare('SELECT id FROM users WHERE LOWER(email)=?');$q->execute([$email]);
            if($q->fetchColumn())throw new InvalidArgumentException('This email already has an account. Use a different address.');
            $q=db()->prepare('INSERT INTO users(email,password_hash) VALUES(?,?)');$q->execute([$email,$newHash]);$id=(int)db()->lastInsertId();
            $q=db()->prepare('INSERT INTO desk_memberships(user_id,workspace_owner,role,enabled) VALUES(?,?,?,1)');$q->execute([$id,$workspace,$role]);
            $notice='Account created. The new user can sign in with the email and password you set.';
        } else {
            $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
            if(!$id||$id===$identity['id'])throw new InvalidArgumentException('The workspace owner cannot be changed from this page.');
            $q=db()->prepare('SELECT user_id FROM desk_memberships WHERE user_id=? AND workspace_owner=? FOR UPDATE');$q->execute([$id,$workspace]);
            if(!$q->fetchColumn())throw new InvalidArgumentException('This account does not belong to your workspace.');
            $enabled=($_POST['enabled']??'')==='yes'?1:0;
            $q=db()->prepare('UPDATE desk_memberships SET role=?,enabled=? WHERE user_id=? AND workspace_owner=?');$q->execute([$role,$enabled,$id,$workspace]);
            if($newHash!==null){$q=db()->prepare('UPDATE users SET password_hash=? WHERE id=?');$q->execute([$newHash,$id]);}
            $notice='Account updated. Password, role, or access changes invalidate existing sessions on their next request.';
        }
        db()->commit();$_SESSION['desk_account_notice']=$notice;
        header('Location: /accounts.php',true,303);exit;
    }
} catch(Throwable $e){
    if(db()->inTransaction())db()->rollBack();$error=true;
    $notice=$e instanceof InvalidArgumentException?$e->getMessage():'Unable to save this account. Check the hosting error log.';
    if(!($e instanceof InvalidArgumentException))error_log('Mortons accounts: '.$e->getMessage());
}
if(!$notice&&!empty($_SESSION['desk_account_notice'])){$notice=$_SESSION['desk_account_notice'];unset($_SESSION['desk_account_notice']);}
$members=[];
if($ready){$q=db()->prepare('SELECT u.id,u.email,m.role,m.enabled,m.created_at FROM users u JOIN desk_memberships m ON m.user_id=u.id WHERE m.workspace_owner=? ORDER BY m.created_at,u.id');$q->execute([$workspace]);$members=$q->fetchAll(PDO::FETCH_ASSOC);}
desk_tools_start('Accounts','accounts');
?>
<p class="page-intro">Create logins for your team and choose what each person can do.</p>
<div class="tool-notice <?=$error?'error':''?>" role="status" aria-live="polite"><?=h($notice)?></div>
<?php if(!$ready): ?><div class="tool-notice error" role="alert">Account setup is required. Import private/accounts-schema.sql into your existing Service Desk database, then reload this page. Your current owner login still works.</div><?php endif; ?>
<div class="tool-split"><div>
<section class="tool-card"><h2>Workspace accounts</h2><p><?=count($members)+1?> account<?=count($members)?'s':''?> · All accounts use this Service Desk’s existing records.</p>
<div class="account-row"><div class="account-avatar">KS</div><div class="account-info"><strong><?=h($identity['email'])?></strong><small>Workspace owner · your account</small></div><span class="badge green">Owner</span></div>
<?php foreach($members as $member): ?><div class="account-row"><div class="account-avatar" aria-hidden="true"><?=h(strtoupper(substr($member['email'],0,1)))?></div><div class="account-info"><strong><?=h($member['email'])?></strong><small><?=h($member['role']==='viewer'?'Read-only':'Staff')?> · <?=(int)$member['enabled']===1?'Active':'Disabled'?></small></div><details><summary class="text-button">Manage</summary>
<form method="post" class="account-edit"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=h($member['id'])?>">
<label>Role<select name="role"><option value="viewer" <?=$member['role']==='viewer'?'selected':''?>>Read-only</option><option value="staff" <?=$member['role']==='staff'?'selected':''?>>Staff</option></select></label>
<label class="check-label"><input type="checkbox" name="enabled" value="yes" <?=(int)$member['enabled']===1?'checked':''?>>Allow this account to sign in</label>
<label>New password (optional)<input type="password" name="new_password" autocomplete="new-password" minlength="14" maxlength="72"></label>
<label>Confirm new password<input type="password" name="confirm_password" autocomplete="new-password" minlength="14" maxlength="72"></label>
<label>Your owner password<input type="password" name="owner_password" autocomplete="current-password" required></label>
<button class="primary">Save account</button></form></details></div><?php endforeach; ?>
<?php if(!$members): ?><p class="muted">No additional accounts yet. Create one below.</p><?php endif; ?></section>
<section class="tool-card"><h2>Create an account</h2><p>Set a login email, password, and role. Only you can create or manage accounts.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="create">
<label>Login email<input type="email" name="email" autocomplete="off" maxlength="254" required <?=$ready?'':'disabled'?>></label>
<label>Role<select name="role" <?=$ready?'':'disabled'?>><option value="viewer">Read-only — view the desk</option><option value="staff">Staff — work with desk records</option></select></label>
<div class="grid"><label>Password<input type="password" name="new_password" autocomplete="new-password" minlength="14" maxlength="72" required <?=$ready?'':'disabled'?>></label><label>Confirm password<input type="password" name="confirm_password" autocomplete="new-password" minlength="14" maxlength="72" required <?=$ready?'':'disabled'?>></label></div>
<small>Use 14–72 bytes. Passwords are stored as hashes and cannot be viewed later.</small>
<label>Your owner password<input type="password" name="owner_password" autocomplete="current-password" required <?=$ready?'':'disabled'?>></label>
<div class="actions"><button class="primary" <?=$ready?'':'disabled'?>>Create account</button></div></form>
</section></div><aside><section class="tool-card tool-aside"><h2>Choose the right access</h2><ul><li><strong>Read-only:</strong> view records and print documents. Changes are blocked by the server.</li><li><strong>Staff:</strong> manage customers, appointments, inventory, estimates, invoices, and work time.</li><li><strong>Owner:</strong> manage accounts, Google Business, and site updates. Your existing owner account keeps this access.</li></ul></section><section class="tool-card"><h2>For a review account</h2><p>Choose Read-only for someone who only needs to inspect the desk. You can disable the login when the review is finished.</p><p class="muted">The hosted preview uses sample accounts. Create real logins on your installed Service Desk.</p></section></aside></div>
<?php desk_tools_end(); ?>
