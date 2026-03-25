<?php
ini_set('display_errors',1); error_reporting(E_ALL);
require_once 'dbcon.php';

$email = 'admin@hersafar.com';
$plain = '---------'; // the password you try to login with

$stmt = $mysqli->prepare('SELECT password FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    echo "No user found with email: $email";
    exit;
}

$hash = $row['password'];
echo "Hash from DB:\n<pre>" . htmlspecialchars($hash) . "</pre>\n\n";

if (password_verify($plain, $hash)) {
    echo "<strong>OK</strong> — the password <em>matches</em> the hash.";
} else {
    echo "<strong>FAIL</strong> — the password does NOT match the hash.";
    // Show info about possible reasons
    echo "\n\n(If FAIL, you probably hashed a different plain password earlier.)";
}
