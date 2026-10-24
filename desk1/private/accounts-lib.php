<?php
declare(strict_types=1);
function desk_accounts_ready(PDO $pdo):bool {
    static $ready;
    if($ready===null)$ready=(bool)$pdo->query("SHOW TABLES LIKE 'desk_memberships'")->fetchColumn();
    return $ready;
}
function desk_identity_from_row(array $r):array {
    $member=$r['workspace_owner']!==null;
    $identity=['id'=>(int)$r['id'],'email'=>$r['email'],'workspace'=>$member?(int)$r['workspace_owner']:(int)$r['id'],'role'=>$member?$r['role']:'owner','enabled'=>!$member||(int)$r['enabled']===1];
    if(!in_array($identity['role'],['owner','staff','viewer'],true)||$identity['workspace']<1)throw new RuntimeException('Invalid workspace membership.');
    $identity['stamp']=hash('sha256',$r['password_hash'].'|'.$identity['workspace'].'|'.$identity['role'].'|'.(int)$identity['enabled']);
    return $identity;
}
function desk_account_identity(PDO $pdo,int $id):?array {
    $sql=desk_accounts_ready($pdo)?'SELECT u.id,u.email,u.password_hash,m.workspace_owner,m.role,m.enabled FROM users u LEFT JOIN desk_memberships m ON m.user_id=u.id WHERE u.id=?':'SELECT id,email,password_hash,NULL AS workspace_owner,NULL AS role,NULL AS enabled FROM users WHERE id=?';
    $q=$pdo->prepare($sql);$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
    return $r?desk_identity_from_row($r):null;
}
function desk_current_account():array {
    return $GLOBALS['desk_current_identity']??throw new RuntimeException('Sign in required.');
}
function desk_role_allows(array $identity,string $method,string $script):bool {
    if(!$identity['enabled'])return false;
    return $identity['role']!=='viewer'||in_array($method,['GET','HEAD'],true)||$script==='logout.php';
}
function desk_require_owner(bool $api=false):array {
    $identity=desk_current_account();
    if($identity['role']!=='owner'){
        if($api)jsonout(['error'=>'Only the workspace owner can manage this page.'],403);
        http_response_code(403);exit('Only the workspace owner can manage this page.');
    }
    return $identity;
}
function desk_new_password(mixed $password):string {
    if(!is_string($password)||strlen($password)<14||strlen($password)>72||str_contains($password,"\0"))throw new InvalidArgumentException('Use a password between 14 and 72 bytes.');
    return password_hash($password,PASSWORD_DEFAULT);
}
function desk_member_role(mixed $role):string {
    if(!in_array($role,['viewer','staff'],true))throw new InvalidArgumentException('Choose Read-only or Staff.');
    return $role;
}
function desk_verify_owner_password(PDO $pdo,int $id,mixed $password):void {
    $attempt=$_SESSION['desk_account_password_attempt']??['count'=>0,'start'=>time()];
    if(time()-$attempt['start']>900)$attempt=['count'=>0,'start'=>time()];
    if($attempt['count']>=5)throw new InvalidArgumentException('Too many incorrect owner passwords. Try again after 15 minutes.');
    $q=$pdo->prepare('SELECT password_hash FROM users WHERE id=?');$q->execute([$id]);$hash=$q->fetchColumn();
    if(!is_string($password)||!$hash||!password_verify($password,$hash)){
        $attempt['count']++;$_SESSION['desk_account_password_attempt']=$attempt;
        throw new InvalidArgumentException('Your owner password is incorrect.');
    }
    unset($_SESSION['desk_account_password_attempt']);
}
