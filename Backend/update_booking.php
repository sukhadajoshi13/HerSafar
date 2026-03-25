<?php
ini_set('display_errors',1);
error_reporting(E_ALL);

require_once 'dbcon.php';
require_once 'functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php'); exit;
}
if (! verify_csrf($_POST['csrf'] ?? '')) {
    $_SESSION['msg'] = ['type'=>'error','text'=>'Invalid CSRF token.'];
    header('Location: dashboard.php'); exit;
}

$booking_id = (int)($_POST['booking_id'] ?? 0);
$new_seats = (int)($_POST['seats'] ?? 0);
$user_id = (int)($_SESSION['user']['id'] ?? 0);

if ($booking_id <= 0 || $new_seats <= 0 || $user_id <= 0) {
    $_SESSION['msg'] = ['type'=>'error','text'=>'Invalid input.'];
    header('Location: dashboard.php'); exit;
}

// Load booking and ensure it belongs to user and is pending
$stmt = $mysqli->prepare("SELECT ride_id, seats, status FROM bookings WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param('ii', $booking_id, $user_id);
$stmt->execute();
$bk = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (! $bk) {
    $_SESSION['msg'] = ['type'=>'error','text'=>'Booking not found or not authorized.'];
    header('Location: dashboard.php'); exit;
}
if ($bk['status'] !== 'pending') {
    $_SESSION['msg'] = ['type'=>'error','text'=>'Only pending bookings can be updated.'];
    header('Location: dashboard.php'); exit;
}

// Optional: ensure new seats <= ride.total_seats
$stmt = $mysqli->prepare("SELECT seats AS total_seats FROM rides WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $bk['ride_id']);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($r && $new_seats > (int)$r['total_seats']) {
    $_SESSION['msg'] = ['type'=>'error','text'=>'Requested seats exceed ride capacity.'];
    header('Location: dashboard.php'); exit;
}

// Update booking seats
$stmt = $mysqli->prepare("UPDATE bookings SET seats = ? WHERE id = ? AND user_id = ?");
$stmt->bind_param('iii', $new_seats, $booking_id, $user_id);
if ($stmt->execute()) {
    $_SESSION['msg'] = ['type'=>'success','text'=>'Booking updated. Driver will see the updated request.'];
} else {
    $_SESSION['msg'] = ['type'=>'error','text'=>'Failed to update booking: ' . $stmt->error];
}
$stmt->close();

header('Location: dashboard.php');
exit;
