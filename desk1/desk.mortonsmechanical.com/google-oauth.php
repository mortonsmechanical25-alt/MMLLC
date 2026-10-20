<?php
declare(strict_types=1);
require dirname(__DIR__).'/private/bootstrap.php';
require dirname(__DIR__).'/private/google-lib.php';
$owner=user();
// Permit this form's redirect to Google's authorization endpoint.
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self' https://accounts.google.com");
try {
    gb_allowed($owner); gb_ready(); $c=gb_config();
    $redirect=rtrim($cfg['base_url'],'/').'/google-oauth.php';
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        csrf();
        $state=bin2hex(random_bytes(32));
        $verifier=rtrim(strtr(base64_encode(random_bytes(48)),'+/','-_'),'=');
        $_SESSION['gb_oauth']=['state'=>$state,'verifier'=>$verifier,'owner'=>$owner,'expires'=>time()+600];
        $query=['client_id'=>$c['client_id'],'redirect_uri'=>$redirect,'response_type'=>'code','scope'=>'https://www.googleapis.com/auth/business.manage','access_type'=>'offline','prompt'=>'consent','state'=>$state,'code_challenge'=>rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'='),'code_challenge_method'=>'S256'];
        header('Location: https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query($query),true,303); exit;
    }
    if ($_SERVER['REQUEST_METHOD']!=='GET') throw new InvalidArgumentException('Method not allowed.');
    $pending=$_SESSION['gb_oauth']??null;
    unset($_SESSION['gb_oauth']);
    if (!$pending||$pending['owner']!==$owner||$pending['expires']<time()||!is_string($_GET['state']??null)||!hash_equals($pending['state'],$_GET['state']))
        throw new InvalidArgumentException('Google sign-in expired or was started in another session. Start Connect Google again.');
    if (isset($_GET['error'])) throw new InvalidArgumentException('Google sign-in was cancelled or denied. Your existing connection was not replaced.');
    if(!is_string($_GET['code']??null)||strlen($_GET['code'])>4096) throw new InvalidArgumentException('Missing authorization code.');
    $token=gb_http('https://oauth2.googleapis.com/token','POST',['client_id'=>$c['client_id'],'client_secret'=>$c['client_secret'],'code'=>$_GET['code'],'code_verifier'=>$pending['verifier'],'redirect_uri'=>$redirect,'grant_type'=>'authorization_code'],null,true);
    if (empty($token['refresh_token'])) throw new InvalidArgumentException('Google did not grant offline access. Remove this app from your Google account permissions and reconnect.');
    if (!in_array('https://www.googleapis.com/auth/business.manage',explode(' ',$token['scope']??''),true)) throw new InvalidArgumentException('Business management permission was not granted. Reconnect and allow it.');
    $q=db()->prepare("INSERT INTO google_connections(owner,refresh_token) VALUES(?,?) ON DUPLICATE KEY UPDATE refresh_token=VALUES(refresh_token),account_name='',location_name=''");
    $q->execute([$owner,gb_encrypt($token['refresh_token'],$owner)]);
    session_regenerate_id(true);
    $_SESSION['gb_notice']='Google connected. Choose your account and business profile.';
} catch (Throwable $e) {
    if ($e instanceof InvalidArgumentException) $_SESSION['gb_notice']=$e->getMessage();
    else {error_log('Mortons Google OAuth: '.$e->getMessage());$_SESSION['gb_notice']='Google connection failed. Check hosting logs and the Google database migration.';}
}
header('Location: /google.php',true,303);
