<?php
// join_group.php
require_once 'dbcon.php';
require_once 'functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Helper: extract token from a string (raw token or a URL containing token)
function extract_token_from_input(string $input): string {
    $input = trim($input);
    if ($input === '') return '';
    // If input looks like a full URL, try to parse query param "token" or "t"
    if (stripos($input, 'http') === 0 || strpos($input, '/') !== false) {
        $u = @parse_url($input);
        if ($u !== false && !empty($u['query'])) {
            parse_str($u['query'], $q);
            if (!empty($q['token'])) return $q['token'];
            if (!empty($q['t'])) return $q['t'];
        }
        // also try last path segment as token (e.g. /join/<token>)
        if (!empty($u['path'])) {
            $parts = array_values(array_filter(explode('/', $u['path'])));
            $last = end($parts);
            if ($last) return $last;
        }
    }
    return $input;
}

// Accept token from GET or POST (POST useful for dashboard join form)
$raw = '';
if (!empty($_GET['token'])) $raw = $_GET['token'];
elseif (!empty($_POST['token'])) $raw = $_POST['token'];
elseif (!empty($_POST['join_token'])) $raw = $_POST['join_token']; // alternate name
else {
    // if no token provided, show friendly message and stop
    set_flash('error', 'No join token provided.');
    header('Location: dashboard.php'); exit;
}

$token = extract_token_from_input($raw);

// Basic normalization: allow hex tokens (0-9a-fA-F) and hyphens/underscores if you used them.
// Here we will allow alphanum + -_ and trim to reasonable length (<=128)
$token = preg_replace('/[^0-9A-Za-z_\-]/', '', $token);
$token = substr($token, 0, 128);

if ($token === '') {
    set_flash('error', 'Invalid join token format.');
    header('Location: dashboard.php'); exit;
}

// Look up the group by token
$stmt = $mysqli->prepare("SELECT id, name, owner_id FROM groups WHERE join_token = ? LIMIT 1");
if (! $stmt) {
    error_log('DB prepare failed join_group: ' . $mysqli->error);
    set_flash('error', 'Server error. Try again later.');
    header('Location: dashboard.php'); exit;
}
$stmt->bind_param('s', $token);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (! $group) {
    // helpful debug: you can log token attempts
    error_log("Join token not found: " . $token);
    set_flash('error', 'Invalid or expired join link. Please ask the group owner to resend the correct link.');
    header('Location: dashboard.php'); exit;
}

// If not logged in, save token intent and redirect to login
if (!is_logged_in()) {
    $_SESSION['_join_after_login'] = $token;
    set_flash('info', 'Please log in to join the group. After login you will be added automatically.');
    header('Location: login.php'); exit;
}

// If logged in, add membership if not already
$uid = (int)$_SESSION['user']['id'];
$stmt = $mysqli->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param('ii', $group['id'], $uid);
$stmt->execute();
$exists = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($exists) {
    set_flash('info', 'You are already a member of this group.');
    header('Location: group.php?id=' . (int)$group['id']); exit;
}

// add the user as member
$stmt = $mysqli->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'member')");
$stmt->bind_param('ii', $group['id'], $uid);
if ($stmt->execute()) {
    $stmt->close();
    set_flash('success', 'You have joined the group: ' . $group['name']);
    header('Location: group.php?id=' . (int)$group['id']); exit;
} else {
    error_log('Failed to insert group_members: ' . $stmt->error);
    $stmt->close();
    set_flash('error', 'Failed to join group. Try again or contact support.');
    header('Location: dashboard.php'); exit;
}
