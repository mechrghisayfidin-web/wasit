<?php
$hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
$password = '123456';

echo "Test: ";
var_dump(password_verify($password, $hash));

echo "<br>New hash: ";
echo password_hash($password, PASSWORD_DEFAULT);