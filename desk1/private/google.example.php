<?php
// Copy to google.php beside config.php. Keep both outside the web root.
return [
    'client_id' => '',
    'client_secret' => '',
    // Generate once: php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
    'encryption_key' => '',
    // Local Service Desk user IDs allowed to manage Google.
    'allowed_users' => [1],
];
