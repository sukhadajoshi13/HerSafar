<?php
require_once 'dbcon.php';
require_once 'functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// quick helper for flash messages
function flash_and_redirect($type, $text, $to='dashboard.php') {
    $_SESSION['msg'] = ['type'=>$type, 'text'=>$text];
    header('Location: ' . $to);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php'); exit;
}

// CSRF
if (!verify_csrf($_POST['csrf'] ?? '')) {
    flash_and_redirect('error', 'Invalid form submission (CSRF).', 'dashboard.php');
}

// get input
$input = trim((string)($_POST['shared_link'] ?? ''));

if ($input === '') {
    flash_and_redirect('error', 'Please enter a share link or token.', 'dashboard.php');
}

// If user pasted a full URL, try to extract common query params or path token
$token = '';

// 1) If input contains "share=" or "token=" or "t=", try to parse query string.
if (strpos($input, 'http') === 0 || strpos($input, '/') === 0 || strpos($input, '?') !== false) {
    $parts = parse_url($input);
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $qs);
        if (!empty($qs['share'])) $token = $qs['share'];
        elseif (!empty($qs['token'])) $token = $qs['token'];
        elseif (!empty($qs['t'])) $token = $qs['t'];
    }
    // also try last path segment if query not present
    if ($token === '' && !empty($parts['path'])) {
        $p = trim($parts['path'], "/");
        $segments = explode('/', $p);
        $last = end($segments);
        if ($last) $token = $last;
    }
}

// 2) If nothing found above, treat the whole input as token
if ($token === '') $token = $input;

// Normalize token: keep alnum and hex chars only
$token = preg_replace('/[^0-9a-zA-Z_\-]/', '', $token);

// Validate token length/format (adjust pattern to your token rules).
// If your tokens are hex 32 chars: use '/^[0-9a-fA-F]{32}$/'
// Here we accept tokens between 8 and 128 chars alnum/_-
if (!preg_match('/^[0-9A-Za-z_\-]{8,128}$/', $token)) {
    flash_and_redirect('error', 'Invalid share token format. Please paste the exact link or token.', 'dashboard.php');
}

// Optionally: verify token exists in DB (helps show nicer error if token unknown).
// Try checking both rides.share_token and ride_shares.token (if you have both tables)
$found = false;
$stmt = $mysqli->prepare("SELECT id FROM rides WHERE share_token = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($r) $found = true;
}

if (!$found) {
    // check ride_shares table (if you use it)
    $stmt2 = $mysqli->prepare("SELECT ride_id FROM ride_shares WHERE token = ? LIMIT 1");
    if ($stmt2) {
        $stmt2->bind_param('s', $token);
        $stmt2->execute();
        $r2 = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        if ($r2) $found = true;
    }
}

// If token not found, still redirect to ride.php with token (ride.php has resolver that can show proper error).
if (!$found) {
    // optional: still try redirecting and let ride.php show 'invalid' message
    header('Location: ride.php?share=' . urlencode($token));
    exit;
}

// Redirect to ride page using share param — ride.php will resolve token and handle group restrictions
header('Location: ride.php?share=' . urlencode($token));
exit;
