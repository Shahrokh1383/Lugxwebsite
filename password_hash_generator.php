<?php
// hash_password.php
$password = 'admin1234567';
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
echo $hashedPassword;