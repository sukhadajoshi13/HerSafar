<?php
// admin/ride_actions.php
require_once __DIR__ . '/../dbcon.php';
require_once __DIR__ . '/../functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    http_response_code(403); echo "Forbidden"; exit;
}

function flash_and_back($msg, $type='success', $back=null) {
    $_SESSION['admin_msg'] = ['type'=>$type,'text'=>$msg];
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($back) header('Location: ' . $back);
    elseif ($ref && parse_url($ref, PHP_URL_HOST) === ($_SERVER['HTTP_HOST'] ?? '')) header('Location: ' . $ref);
    else header('Location: rides_admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') flash_and_back('Invalid request method', 'error');

if (! verify_csrf($_POST['csrf'] ?? '')) flash_and_back('', 'error');

$action = $_POST['action'] ?? '';
$ride_id = isset($_POST['ride_id']) ? (int)$_POST['ride_id'] : 0;
if ($ride_id <= 0) flash_and_back('Invalid ride id', 'error');

if ($action === 'delete') {
    // delete ride -> bookings will cascade
    $stmt = $mysqli->prepare('DELETE FROM rides WHERE id = ?');
    if (! $stmt) flash_and_back('DB prepare error: ' . $mysqli->error, 'error');
    $stmt->bind_param('i', $ride_id);
    if ($stmt->execute()) {
        $stmt->close();
        flash_and_back('Ride deleted successfully.', 'success', 'rides_admin.php');
    } else {
        $err = $stmt->error; $stmt->close();
        flash_and_back('Failed to delete ride: ' . $err, 'error');
    }

} elseif ($action === 'update') {
    // pull fields and validate
    $from = trim($_POST['from_location'] ?? '');
    $to = trim($_POST['to_location'] ?? '');
    $ride_date = $_POST['ride_date'] ?? '';
    $ride_time = $_POST['ride_time'] ?? null;
    $seats = (int)($_POST['seats'] ?? 1);
    $available_seats = (int)($_POST['available_seats'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($from=='' || $to=='' || $ride_date=='') flash_and_back('From, To and Date are required', 'error');

    $stmt = $mysqli->prepare('UPDATE rides SET from_location=?, to_location=?, ride_date=?, ride_time=?, seats=?, available_seats=?, price=?, notes=? WHERE id = ?');
    if (! $stmt) flash_and_back('DB prepare error: ' . $mysqli->error, 'error');

    // use null for ride_time if empty
    $rt = $ride_time === '' ? null : $ride_time;
    $stmt->bind_param('ssssiiisi', $from, $to, $ride_date, $rt, $seats, $available_seats, $price, $notes, $ride_id);
    if ($stmt->execute()) {
        $stmt->close();
        flash_and_back('Ride updated successfully.', 'success', 'view_ride.php?id=' . $ride_id);
    } else {
        $err = $stmt->error; $stmt->close();
        flash_and_back('Failed to update ride: ' . $err, 'error');
    }

} else {
    flash_and_back('Unknown action', 'error');
}
