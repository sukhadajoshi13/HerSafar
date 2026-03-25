<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../dbcon.php';
require_once __DIR__ . '/../functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
function admin_flash_and_redirect($type, $text, $fallback = '/hersafar/admin/users.php') {
    $_SESSION['admin_msg'] = ['type' => $type, 'text' => $text];

    // Use HTTP_REFERER if same-host and non-empty, otherwise fallback.
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref) {
        $parsed = parse_url($ref);
        $hostMatches = (!isset($parsed['host']) || strtolower($parsed['host']) === strtolower($_SERVER['HTTP_HOST']));
        if ($hostMatches && isset($parsed['path'])) {
            header('Location: ' . $ref);
            exit;
        }
    }

    header('Location: ' . $fallback);
    exit;
}

function is_safe_upload_path($absPath) {
    $uploadsDir = realpath(__DIR__ . '/../uploads');
    if ($uploadsDir === false) return false;
    $target = realpath($absPath);
    if ($target === false) return false;
    // ensure the target path begins with uploadsDir
    return strpos($target, $uploadsDir) === 0;
}
if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    admin_flash_and_redirect('error', 'Invalid request method.');
}

// CSRF
if (!verify_csrf($_POST['csrf'] ?? '')) {
    admin_flash_and_redirect('error', 'Invalid CSRF token.');
}

$action = trim((string)($_POST['action'] ?? ''));
$adminId = (int)($_SESSION['user']['id'] ?? 0);
$targetUserId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

// minimal validation
if ($targetUserId <= 0 && $action !== 'refresh_user_cache') {
    admin_flash_and_redirect('error', 'Missing or invalid user id.');
}

try {
    if ($action === 'toggle_verify') {
        // flip verified flag
        $stmt = $mysqli->prepare("SELECT verified FROM users WHERE id = ? LIMIT 1");
        if (!$stmt) throw new Exception('DB error: ' . $mysqli->error);
        $stmt->bind_param('i', $targetUserId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) throw new Exception('User not found.');

        $new = $row['verified'] ? 0 : 1;
        $u = $mysqli->prepare("UPDATE users SET verified = ? WHERE id = ? LIMIT 1");
        if (!$u) throw new Exception('DB error: ' . $mysqli->error);
        $u->bind_param('ii', $new, $targetUserId);
        if (!$u->execute()) { $err = $u->error; $u->close(); throw new Exception('Failed to update verified status: ' . $err); }
        $u->close();

        admin_flash_and_redirect('success', $new ? 'User verified.' : 'User unverified.');
    }

    if ($action === 'toggle_active') {
        // prevent admin deactivating self
        if ($targetUserId === $adminId) {
            admin_flash_and_redirect('error', 'You cannot deactivate your own admin account.');
        }

        $stmt = $mysqli->prepare("SELECT active FROM users WHERE id = ? LIMIT 1");
        if (!$stmt) throw new Exception('DB error: ' . $mysqli->error);
        $stmt->bind_param('i', $targetUserId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) throw new Exception('User not found.');

        $new = $row['active'] ? 0 : 1;
        $u = $mysqli->prepare("UPDATE users SET active = ? WHERE id = ? LIMIT 1");
        if (!$u) throw new Exception('DB error: ' . $mysqli->error);
        $u->bind_param('ii', $new, $targetUserId);
        if (!$u->execute()) { $err = $u->error; $u->close(); throw new Exception('Failed to update active status: ' . $err); }
        $u->close();

        admin_flash_and_redirect('success', $new ? 'User activated.' : 'User deactivated.');
    }

    if ($action === 'send_message') {
        // Insert a message (admin -> user) for verification feedback
        $message = trim((string)($_POST['message'] ?? ''));
        if ($message === '') throw new Exception('Message cannot be empty.');

        // ensure table admin_user_messages exists (admin_id, user_id, message, created_at)
        $stmt = $mysqli->prepare("INSERT INTO admin_user_messages (admin_id, user_id, message, created_at) VALUES (?, ?, ?, NOW())");
        if (!$stmt) throw new Exception('DB error: ' . $mysqli->error);
        $stmt->bind_param('iis', $adminId, $targetUserId, $message);
        if (!$stmt->execute()) { $err = $stmt->error; $stmt->close(); throw new Exception('Failed to send message: ' . $err); }
        $stmt->close();

        // Optionally you can add notification/email logic here

        admin_flash_and_redirect('success', 'Message sent to user.');
    }

    if ($action === 'delete_document') {
        $docId = isset($_POST['doc_id']) ? (int)$_POST['doc_id'] : 0;
        if ($docId <= 0) throw new Exception('Missing document id.');

        // fetch document row
        $stmt = $mysqli->prepare("SELECT file_path FROM user_documents WHERE id = ? LIMIT 1");
        if (!$stmt) throw new Exception('DB error: ' . $mysqli->error);
        $stmt->bind_param('i', $docId);
        $stmt->execute();
        $doc = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$doc) throw new Exception('Document not found.');

        // Protect: don't unlink outside uploads/; use realpath checks
        $absPath = realpath(__DIR__ . '/../' . ltrim($doc['file_path'], '/'));
        if ($absPath && is_safe_upload_path($absPath) && is_file($absPath)) {
            @unlink($absPath); // best-effort removal of file
        }

        $d = $mysqli->prepare("DELETE FROM user_documents WHERE id = ? LIMIT 1");
        if (!$d) throw new Exception('DB error: ' . $mysqli->error);
        $d->bind_param('i', $docId);
        if (!$d->execute()) { $err = $d->error; $d->close(); throw new Exception('Failed to delete document record: ' . $err); }
        $d->close();

        admin_flash_and_redirect('success', 'Document deleted.');
    }

    if ($action === 'refresh_user_cache') {
        // Placeholder if you maintain a cache or other background tasks
        admin_flash_and_redirect('success', 'Refreshed.');
    }

    // Unknown action
    admin_flash_and_redirect('error', 'Unknown action: ' . htmlspecialchars($action));
} catch (Exception $e) {
    admin_flash_and_redirect('error', 'Action failed: ' . $e->getMessage());
}