<?php
ini_set('display_errors',1); error_reporting(E_ALL);
require_once 'dbcon.php';


$email = 'admin@hersafar.com';
$plain_password = '--------';
$name = 'Site Admin';

$hash = password_hash($plain_password, PASSWORD_DEFAULT);

$stmt = $mysqli->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if ($row) {
    $uid = (int)$row['id'];
    $u = $mysqli->prepare('UPDATE users SET password = ?, role = ?, active = 1, verified = 1 WHERE id = ?');
    $role = 'admin';
    $u->bind_param('ssi', $hash, $role, $uid);
    if ($u->execute()) {
        echo "Updated admin user (id={$uid}). New password is: {$plain_password}\n";
    } else {
        echo "Failed to update: " . $u->error;
    }
    $u->close();
} else {
    $ins = $mysqli->prepare('INSERT INTO users (name,email,password,phone,gender,role,verified,active,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
    $phone='0000000000'; $gender='female'; $role='admin'; $verified=1; $active=1;
    $ins->bind_param('sssssiii', $name, $email, $hash, $phone, $gender, $role, $verified, $active);
    if ($ins->execute()) {
        echo "Inserted admin user id: " . $ins->insert_id . ". Password: {$plain_password}\n";
    } else {
        echo "Insert failed: " . $ins->error;
    }
    $ins->close();
}
?>
