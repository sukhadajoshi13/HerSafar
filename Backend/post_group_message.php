<?php
require_once 'dbcon.php';
require_once 'functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

header('Vary: Accept');

$is_ajax = false;
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $is_ajax = true;
} elseif (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
    $is_ajax = true;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($is_ajax) {
        http_response_code(405);
        echo json_encode(['success'=>false,'error'=>'Invalid method']);
        exit;
    }
    header('Location: dashboard.php'); exit;
}

// read inputs
$csrf = $_POST['csrf'] ?? '';
$group_id = (int)($_POST['group_id'] ?? 0);
$message = trim($_POST['message'] ?? '');
$uid = (int)($_SESSION['user']['id'] ?? 0);

// basic validation
if (!verify_csrf($csrf)) {
    if ($is_ajax) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Invalid CSRF']); exit; }
    $_SESSION['msg'] = ['type'=>'error','text'=>'Invalid CSRF']; header('Location: dashboard.php'); exit;
}
if ($group_id <= 0 || $message === '') {
    if ($is_ajax) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Invalid input']); exit; }
    $_SESSION['msg'] = ['type'=>'error','text'=>'Invalid input']; header('Location: dashboard.php'); exit;
}

// ensure membership
$stmt = $mysqli->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param('ii', $group_id, $uid);
$stmt->execute();
$mem = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (! $mem) {
    if ($is_ajax) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Not a member']); exit; }
    $_SESSION['msg'] = ['type'=>'error','text'=>'You are not a member of that group.']; header('Location: group.php?id=' . $group_id); exit;
}

// insert message
$stmt = $mysqli->prepare("INSERT INTO group_messages (group_id, user_id, message, posted_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param('iis', $group_id, $uid, $message);
if (! $stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    if ($is_ajax) { http_response_code(500); echo json_encode(['success'=>false,'error'=>'DB error']); exit; }
    $_SESSION['msg'] = ['type'=>'error','text'=>'Failed to post message.']; header('Location: group_chat.php?group_id=' . $group_id); exit;
}
$insert_id = $stmt->insert_id;
$stmt->close();

// fetch inserted message with user name and posted_at
$stmt = $mysqli->prepare("SELECT gm.id, gm.message, gm.posted_at, u.id AS user_id, u.name FROM group_messages gm JOIN users u ON gm.user_id = u.id WHERE gm.id = ? LIMIT 1");
$stmt->bind_param('i', $insert_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($is_ajax) {
    // return JSON with raw fields - client will escape via textContent
    echo json_encode([
        'success' => true,
        'message' => $row['message'],
        'posted_at' => $row['posted_at'],
        'name' => $row['name'],
        'id' => (int)$row['id'],
        'user_id' => (int)$row['user_id']
    ]);
    exit;
} else {
    $_SESSION['msg'] = ['type'=>'success','text'=>'Message posted.'];
    header('Location: group_chat.php?group_id=' . $group_id);
    exit;
}
