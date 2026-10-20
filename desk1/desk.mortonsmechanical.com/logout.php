<?php
require dirname(__DIR__).'/private/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
try{csrf();$_SESSION=[];session_destroy();header('Location: /login.php');}catch(Throwable $e){http_response_code(403);exit('Invalid request.');}
