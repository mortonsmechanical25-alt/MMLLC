<?php
return [
 'dsn'=>'mysql:host=localhost;dbname=deskpri;charset=utf8mb4',
 'db_user'=>'deskpri',
 'db_password'=>'x1~9f69mH',
 'base_url'=>'https://desk.mortonsmechanical.com',
 // Create a random installation code of at least 24 characters; clear it after setup.
 'install_token'=>'hhhhhhhhhhhhhhhhhhhhhhhhh',
 'stripe_key'=>'',
 'stripe_webhook_secret'=>'',
 'integration_identifier'=>'mortons_desk_abcdefgh',
 // Twilio setup is paused. Leave these empty until your sender is ready.
 'twilio_account_sid'=>'',
 'twilio_auth_token'=>'',
 'twilio_messaging_service_sid'=>'',
 // Optional email sending through Resend.
 'resend_api_key'=>'',
 'message_from_email'=>'',
];
