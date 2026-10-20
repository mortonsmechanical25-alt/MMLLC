<?php
require dirname(__DIR__).'/private/bootstrap.php';
$error='';$done=false;
try{db()->query('SELECT id FROM users LIMIT 1');if(db()->query('SELECT COUNT(*) FROM users')->fetchColumn()>0){http_response_code(403);exit('Setup is locked. Use login.php.');}}catch(PDOException $e){/* First installation. */}
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{csrf();if(strlen($cfg['install_token'])<24||!hash_equals($cfg['install_token'],$_POST['token']??''))throw new InvalidArgumentException('Invalid installation code.');
 $email=trim($_POST['email']??'');$password=$_POST['password']??'';if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<14)throw new InvalidArgumentException('Use a valid email and password of at least 14 characters.');
 // Serialize initial account setup; never allow two owners to be created via setup.
 $lock=db()->query("SELECT GET_LOCK('mortons_desk_setup',10)")->fetchColumn();if(!$lock)throw new RuntimeException('Setup busy. Try again.');
 foreach(explode(';',file_get_contents(dirname(__DIR__).'/private/schema.sql')) as $sql)if(trim($sql))db()->exec($sql);
 if(db()->query('SELECT COUNT(*) FROM users')->fetchColumn()>0)throw new InvalidArgumentException('Setup already completed.');
 $q=db()->prepare('INSERT INTO users(email,password_hash) VALUES(?,?)');$q->execute([$email,password_hash($password,PASSWORD_DEFAULT)]);$done=true;
 }catch(Throwable $e){$error=$e instanceof InvalidArgumentException?$e->getMessage():'Setup failed. Check database credentials, permissions, and the hosting error log.';error_log('Mortons setup: '.$e->getMessage());}finally{try{db()->query("SELECT RELEASE_LOCK('mortons_desk_setup')");}catch(Throwable $e){}}
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Setup - Mortons Mechanical</title><link rel="stylesheet" href="/assets/app.css"></head><body><main class="auth-card"><img src="/mortons-logo.png" alt="Mortons Mechanical"><h1>Service Desk setup</h1><?php if($done): ?><p>Your account is ready. Clear the installation code in private/config.php and remove setup.php.</p><a class="primary" href="/login.php">Sign in</a><?php else: ?><p>Create your private owner account.</p><?php if($error):?><p class="alert"><?=h($error)?></p><?php endif?><form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><label>Installation code<input name="token" type="password" required autocomplete="off"></label><label>Email<input name="email" type="email" required autocomplete="username"></label><label>Password (14 characters minimum)<input name="password" type="password" required minlength="14" autocomplete="new-password"></label><button class="primary">Create owner account</button></form><?php endif?></main></body></html>
