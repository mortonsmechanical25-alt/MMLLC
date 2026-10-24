<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/private/accounts-lib.php';
$dsn=getenv('DESK_TEST_DSN');
if(!$dsn||!str_contains($dsn,'dbname=desk_accounts_test'))throw new RuntimeException('Use the isolated desk_accounts_test database.');
$db=new PDO($dsn,getenv('DESK_TEST_USER'),getenv('DESK_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$count=0;
function checkAccount(bool $ok,string $message):void{global $count;if(!$ok)throw new RuntimeException($message);$count++;}
function rejectAccount(callable $fn,string $message):void{try{$fn();}catch(InvalidArgumentException $e){checkAccount(true,$message);return;}throw new RuntimeException($message);}
$db->exec('DROP TABLE IF EXISTS desk_memberships');$db->exec('DROP TABLE IF EXISTS users');
$db->exec('CREATE TABLE users(id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,email VARCHAR(254) UNIQUE NOT NULL,password_hash VARCHAR(255) NOT NULL) ENGINE=InnoDB');
$password='Desk-test-password-123!';$hash=password_hash($password,PASSWORD_DEFAULT);
$insert=$db->prepare('INSERT INTO users(id,email,password_hash) VALUES(?,?,?)');
foreach([1=>'owner@example.test',2=>'independent@example.test',3=>'viewer@example.test',4=>'staff@example.test',5=>'disabled@example.test'] as $id=>$email)$insert->execute([$id,$email,$hash]);
if(in_array('--legacy',$argv,true)){
    $owner=desk_account_identity($db,1);$other=desk_account_identity($db,2);
    checkAccount($owner['role']==='owner'&&$owner['workspace']===1,'Legacy owner preserved without migration');
    checkAccount($other['workspace']===2,'Existing separate workspace preserved');
    echo 'Passed '.$count." legacy login compatibility checks.\n";exit;
}
$db->exec(file_get_contents(dirname(__DIR__,2).'/private/accounts-schema.sql'));
$db->exec("INSERT INTO desk_memberships(user_id,workspace_owner,role,enabled) VALUES(3,1,'viewer',1),(4,1,'staff',1),(5,1,'viewer',0)");
$owner=desk_account_identity($db,1);$viewer=desk_account_identity($db,3);$staff=desk_account_identity($db,4);$disabled=desk_account_identity($db,5);
checkAccount($owner['id']===1&&$owner['role']==='owner'&&$owner['workspace']===1,'Original owner retains identity');
checkAccount($viewer['id']===3&&$viewer['workspace']===1&&$viewer['role']==='viewer','Viewer maps to existing workspace while retaining login ID');
checkAccount($staff['id']===4&&$staff['workspace']===1&&$staff['role']==='staff','Staff maps to same workspace');
checkAccount(desk_account_identity($db,2)['workspace']===2,'Other original owner is not attached to this workspace');
checkAccount(desk_account_identity($db,9999)===null,'Removed account cannot authenticate');
foreach(['GET','HEAD'] as $method)checkAccount(desk_role_allows($viewer,$method,'api.php'),'Viewer allowed to read');
foreach(['POST','DELETE','PATCH','PUT'] as $method)checkAccount(!desk_role_allows($viewer,$method,'api.php'),'Viewer denied business writes');
checkAccount(desk_role_allows($viewer,'POST','logout.php'),'Viewer can sign out');
checkAccount(!desk_role_allows($disabled,'GET','api.php'),'Disabled user denied');
checkAccount(desk_role_allows($staff,'POST','api.php'),'Staff can work with records');
$old=$viewer['stamp'];$db->exec("UPDATE desk_memberships SET role='staff' WHERE user_id=3");
checkAccount(desk_account_identity($db,3)['stamp']!==$old,'Role change revokes session stamp');
$db->exec("UPDATE desk_memberships SET role='viewer' WHERE user_id=3");$old=desk_account_identity($db,3)['stamp'];
$db->exec('UPDATE desk_memberships SET enabled=0 WHERE user_id=3');checkAccount(desk_account_identity($db,3)['stamp']!==$old,'Disabling revokes session stamp');
$db->exec('UPDATE desk_memberships SET enabled=1 WHERE user_id=3');$old=desk_account_identity($db,3)['stamp'];
$q=$db->prepare('UPDATE users SET password_hash=? WHERE id=3');$q->execute([desk_new_password('Replacement-password-123!')]);
checkAccount(desk_account_identity($db,3)['stamp']!==$old,'Password reset revokes session stamp');
$q->execute([$hash]);
rejectAccount(fn()=>desk_new_password('short'),'Weak password rejected');
rejectAccount(fn()=>desk_new_password(str_repeat('x',73)),'Bcrypt truncation rejected');
rejectAccount(fn()=>desk_new_password("Password-with-null\0character"),'Null byte rejected');
rejectAccount(fn()=>desk_member_role('owner'),'Owner role cannot be granted through account creation');
checkAccount(password_verify($password,desk_new_password($password)),'Passwords are hashed');
// Isolated fixtures for HTTP authorization tests. No production config is read.
foreach(['login_attempts','records','stock_scans'] as $table)$db->exec('DROP TABLE IF EXISTS '.$table);
$db->exec('CREATE TABLE login_attempts(identity CHAR(64) PRIMARY KEY, attempts INT NOT NULL,window_start INT NOT NULL) ENGINE=InnoDB');
$db->exec('CREATE TABLE records(owner INT UNSIGNED NOT NULL,id VARCHAR(100) NOT NULL,kind VARCHAR(20) NOT NULL,data LONGTEXT NOT NULL,updated DATETIME NOT NULL,PRIMARY KEY(owner,id)) ENGINE=InnoDB');
$db->exec('CREATE TABLE stock_scans(owner INT UNSIGNED NOT NULL,id VARCHAR(100) NOT NULL,part VARCHAR(100) NOT NULL,status VARCHAR(20) NOT NULL,PRIMARY KEY(owner,id)) ENGINE=InnoDB');
echo 'Passed '.$count." account role, workspace, and session checks.\n";
