<?php

require_once 'dbcon.php';
require_once 'functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$uid = (int)$_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error','Invalid request method.');
    header('Location: manage_groups.php'); exit;
}
if (! verify_csrf($_POST['csrf'] ?? '')) {
    set_flash('error','Invalid CSRF token.');
    header('Location: manage_groups.php'); exit;
}

$ride_id = (int)($_POST['ride_id'] ?? 0);
$group_id = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;

if ($ride_id <= 0) {
    set_flash('error','Invalid ride.');
    header('Location: manage_groups.php'); exit;
}

// ensure current user is driver of ride
$stmt = $mysqli->prepare("SELECT id FROM rides WHERE id = ? AND driver_id = ? LIMIT 1");
$stmt->bind_param('ii', $ride_id, $uid);
$stmt->execute();
$ok = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (! $ok) {
    set_flash('error','Not authorized to share this ride.');
    header('Location: manage_groups.php'); exit;
}

// if group_id provided, ensure owner owns that group (cannot create share scoped to group you don't own)
if ($group_id) {
    $stmt = $mysqli->prepare("SELECT id FROM groups WHERE id = ? AND owner_id = ? LIMIT 1");
    $stmt->bind_param('ii', $group_id, $uid);
    $stmt->execute();
    $g = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (! $g) {
        set_flash('error','Invalid group selection.');
        header('Location: manage_groups.php'); exit;
    }
}

// create token
$token = bin2hex(random_bytes(24)); // 48 chars hex
$stmt = $mysqli->prepare("INSERT INTO ride_shares (ride_id, group_id, token, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
if ($group_id) $stmt->bind_param('iisi', $ride_id, $group_id, $token, $uid);
else {
    // bind null: use NULL in param by passing null via variable and 'iisi' will fail; so use separate prepare
    $stmt = $mysqli->prepare("INSERT INTO ride_shares (ride_id, group_id, token, created_by, created_at) VALUES (?, NULL, ?, ?, NOW())");
    $stmt->bind_param('isi', $ride_id, $token, $uid);
}
if ($stmt->execute()) {
    set_flash('success','Share link created.');
} else {
    set_flash('error','Failed to create share: ' . $stmt->error);
}
$stmt->close();
header('Location: manage_groups.php');
exit;
