<?php
require_once __DIR__ . '/dbcon.php';
require_once __DIR__ . '/functions.php';
session_start();

// require login
if (empty($_SESSION['user'])) {
    http_response_code(403);
    echo "Not authenticated.";
    exit;
}

$doc_id = isset($_GET['doc_id']) ? (int)$_GET['doc_id'] : 0;
$force_download = isset($_GET['dl']) && $_GET['dl'] == '1';

if ($doc_id <= 0) {
    http_response_code(400);
    echo "Missing document id.";
    exit;
}

// fetch document record
$stmt = $mysqli->prepare("SELECT id, user_id, type, file_path, mime, size_bytes FROM user_documents WHERE id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo "DB error.";
    exit;
}
$stmt->bind_param('i', $doc_id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$doc) {
    http_response_code(404);
    echo "Document not found.";
    exit;
}

// authorization: admin or owner
$is_admin = (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin');
$is_owner = ((int)$_SESSION['user']['id'] === (int)$doc['user_id']);
if (!($is_admin || $is_owner)) {
    http_response_code(403);
    echo "You are not authorized to download this file.";
    exit;
}

// resolve absolute path and ensure it's inside uploads directory
$uploadsRoot = realpath(__DIR__ . '/uploads/docs');
if ($uploadsRoot === false) {
    http_response_code(500);
    echo "Uploads directory not found on server.";
    exit;
}

$requested = realpath(__DIR__ . '/' . $doc['file_path']);
if ($requested === false || strpos($requested, $uploadsRoot) !== 0) {
    http_response_code(404);
    echo "File missing or invalid path.";
    exit;
}

if (!is_file($requested) || !is_readable($requested)) {
    http_response_code(404);
    echo "File not available.";
    exit;
}

// send headers and file
$filename = basename($requested);
$mime = $doc['mime'] ?: mime_content_type($requested);
$size = filesize($requested);

// common headers
header_remove();
if ($force_download) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
} else {
    header('Content-Type: ' . $mime);
    // inline for images/pdf; force download for others
    if (in_array($mime, ['image/jpeg','image/png','application/pdf'], true)) {
        header('Content-Disposition: inline; filename="' . rawurlencode($filename) . '"');
    } else {
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
    }
}
header('Content-Length: ' . $size);
header('Cache-Control: private, max-age=10800, must-revalidate');
header('Pragma: public');

// stream file
$fp = fopen($requested, 'rb');
if ($fp === false) {
    http_response_code(500);
    echo "Failed to open file.";
    exit;
}
while (!feof($fp)) {
    echo fread($fp, 8192);
    flush();
}
fclose($fp);
exit;
