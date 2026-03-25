<?php

$password = 'hello123@';  

$hash = password_hash($password, PASSWORD_BCRYPT);

echo "Password: " . htmlspecialchars($password) . "<br>";
echo "Generated hash: <br><pre>" . htmlspecialchars($hash) . "</pre>";
