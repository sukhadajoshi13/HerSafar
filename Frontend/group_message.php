<?php
require_once 'dbcon.php';
require_once 'functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: dashboard.php'); exit; }
if (!verify_csrf($_POST['csrf'] ?? '')) { $_SESSION['msg']=['type'=>'error','text'=>'Invalid CSRF']; header('Location: dashboard.php'); exit; }

$group_id = (int)($_POST['group_id'] ?? 0);
$message = trim($_POST['message'] ?? '');
$uid = (int)$_SESSION['user']['id'];

if ($group_id <= 0 || $message === '') { $_SESSION['msg']=['type'=>'error','text'=>'Invalid input']; header('Location: dashboard.php'); exit; }

// ensure membership
$stmt = $mysqli->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param('ii', $group_id, $uid);
$stmt->execute();
$mem = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (! $mem) { $_SESSION['msg']=['type'=>'error','text'=>'Not a member']; header('Location: group.php?id=' . $group_id); exit; }

// insert message
$stmt = $mysqli->prepare("INSERT INTO group_messages (group_id, user_id, message, posted_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param('iis', $group_id, $uid, $message);
if ($stmt->execute()) {
    $_SESSION['msg']=['type'=>'success','text'=>'Message posted.'];
} else {
    $_SESSION['msg']=['type'=>'error','text'=>'Failed to post message.'];
}
$stmt->close();
header('Location: group_chat.php?group_id=' . $group_id);
exit;
