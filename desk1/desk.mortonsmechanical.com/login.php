<?php
require dirname(__DIR__).'/private/bootstrap.php';
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{csrf();$email=strtolower(trim($_POST['email']??''));$identity=hash('sha256',($_SERVER['REMOTE_ADDR']??'').'|'.$email);
 db()->beginTransaction();$q=db()->prepare('INSERT IGNORE INTO login_attempts(identity,attempts,window_start) VALUES(?,0,?)');$q->execute([$identity,time()]);$q=db()->prepare('SELECT attempts,window_start FROM login_attempts WHERE identity=? FOR UPDATE');$q->execute([$identity]);$attempt=$q->fetch(PDO::FETCH_ASSOC);
 if(time()-$attempt['window_start']>900){$q=db()->prepare('UPDATE login_attempts SET attempts=0,window_start=? WHERE identity=?');$q->execute([time(),$identity]);$attempt['attempts']=0;}
 if($attempt['attempts']>=10){db()->commit();throw new InvalidArgumentException('Too many attempts. Try again in 15 minutes.');}
 $q=db()->prepare('UPDATE login_attempts SET attempts=attempts+1 WHERE identity=?');$q->execute([$identity]);db()->commit();
 $q=db()->prepare('SELECT id,password_hash FROM users WHERE LOWER(email)=?');$q->execute([$email]);$account=$q->fetch(PDO::FETCH_ASSOC);
 if(!$account||!password_verify($_POST['password']??'',$account['password_hash']))throw new InvalidArgumentException('Email or password is incorrect.');
 $accountIdentity=desk_account_identity(db(),(int)$account['id']);if(!$accountIdentity||!$accountIdentity['enabled'])throw new InvalidArgumentException('Email or password is incorrect.');
 session_regenerate_id(true);$_SESSION['account_stamp']=$accountIdentity['stamp'];$_SESSION['user']=(int)$account['id'];$_SESSION['last']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));$q=db()->prepare('DELETE FROM login_attempts WHERE identity=?');$q->execute([$identity]);header('Location: /');exit;
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();$error=$e instanceof InvalidArgumentException?$e->getMessage():'Login unavailable. Complete setup or check the hosting error log.';}
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login - Mortons Mechanical</title><link rel="stylesheet" href="/assets/app.css"></head><body><main class="auth-card"><img src="/mortons-logo.png" alt="Mortons Mechanical"><h1>Service Desk</h1><p>Sign in to your private workspace.</p><?php if($error):?><p class="alert"><?=h($error)?></p><?php endif?><form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><label>Email<input type="email" name="email" autocomplete="username" required></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><button class="primary">Sign in</button></form></main></body></html>

