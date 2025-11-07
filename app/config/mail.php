<?php
return[
    'host' => getenv('MAIL_HOST') ?: 'smtp.gmail.com',
    'username' => getenv('MAIL_USERNAME') ?: 'lugxgamingcenter@gmail.com',
    'password' => getenv('MAIL_PASSWORD') ?: 'lugx12345678',
    'port' => getenv('MAIL_PORT') ?: 587,
    'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls', // ssl or tls
    'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'lugxgamingcenter@gmail.com',
    'from_name' => getenv('MAIL_FROM_NAME') ?: 'Lugxwebsite Support'
];
