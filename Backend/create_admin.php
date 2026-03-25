<?php
require_once 'dbcon.php';
$name = 'Site Admin';
$email = 'admin@hersafar.com';
$password = '--------?';
$hash = password_hash($password, PASSWORD_DEFAULT);
$phone = '0000000000';
$gender = 'female';
$role = 'admin';
$verified = 1;
$active = 1;

$stmt = $mysqli->prepare('INSERT INTO users (name,email,password,phone,gender,role,verified,active,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
$stmt->bind_param('sssssiii', $name, $email, $hash, $phone, $gender, $role, $verified, $active);
if ($stmt->execute()) echo "Admin created id: " . $stmt->insert_id . PHP_EOL;
else echo "Error: " . $stmt->error;
$stmt->close();
