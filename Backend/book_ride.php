<?php
// book_ride.php — robust booking request handler (creates pending booking and notifies driver)
ini_set('display_errors',1);
error_reporting(E_ALL);

require_once 'dbcon.php';
require_once 'functions.php'; // must provide csrf_token() and verify_csrf()

if (session_status() === PHP_SESSION_NONE) session_start();

function redirect_with_msg($ride_id, $type, $text) {
    $_SESSION['msg'] = ['type'=>$type, 'text'=>$text];
    header('Location: ride.php?id=' . (int)$ride_id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php'); exit;
}

// check CSRF
$token = $_POST['csrf'] ?? '';
if (! function_exists('verify_csrf') || ! verify_csrf($token)) {
    $rid = (int)($_POST['ride_id'] ?? 0);
    redirect_with_msg($rid, 'error', 'Invalid CSRF token. Reload the page and try again.');
}

// check logged in
$user_id = (int)($_SESSION['user']['id'] ?? 0);
if ($user_id <= 0) {
    header('Location: login.php'); exit;
}

// gather inputs
$ride_id = (int)($_POST['ride_id'] ?? 0);
$seats = (int)($_POST['seats'] ?? 0);

if ($ride_id <= 0 || $seats <= 0) {
    redirect_with_msg($ride_id, 'error', 'Invalid ride or seat selection.');
}

// load ride
$stmt = $mysqli->prepare("SELECT id, driver_id, seats, available_seats, from_location, to_location FROM rides WHERE id = ? LIMIT 1");
if (! $stmt) redirect_with_msg($ride_id, 'error', 'DB error: ' . $mysqli->error);
$stmt->bind_param('i', $ride_id);
$stmt->execute();
$ride = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (! $ride) redirect_with_msg($ride_id, 'error', 'Ride not found.');

// prevent driver booking own ride
if ((int)$ride['driver_id'] === $user_id) redirect_with_msg($ride_id, 'error', 'You are the driver for this ride.');

// prevent duplicate existing booking row for same user & ride
$stmt = $mysqli->prepare("SELECT id, status FROM bookings WHERE ride_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param('ii', $ride_id, $user_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($existing) {
    redirect_with_msg($ride_id, 'info', "You already have a booking for this ride (status: {$existing['status']}).");
}

// optional: prevent seats > total capacity
if ($seats > (int)$ride['seats']) {
    redirect_with_msg($ride_id, 'error', 'Requested seats exceed ride capacity.');
}

// insert pending booking
$stmt = $mysqli->prepare("INSERT INTO bookings (ride_id, user_id, seats, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
if (! $stmt) redirect_with_msg($ride_id, 'error', 'DB error (prepare insert): ' . $mysqli->error);
$stmt->bind_param('iii', $ride_id, $user_id, $seats);
if (! $stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    redirect_with_msg($ride_id, 'error', 'Failed to create booking: ' . $err);
}
$booking_id = $stmt->insert_id;
$stmt->close();

// create in-app message to driver (non-blocking)
$passenger_name = $_SESSION['user']['name'] ?? 'Passenger';
$msg_text = "{$passenger_name} requested {$seats} seat(s) for Ride #{$ride_id} ({$ride['from_location']} → {$ride['to_location']}). Please confirm or cancel.";

$stmt = $mysqli->prepare("INSERT INTO messages (sender_id, receiver_id, message, sent_at) VALUES (?, ?, ?, NOW())");
if ($stmt) {
    $sender = $user_id;
    $receiver = (int)$ride['driver_id'];
    $stmt->bind_param('iis', $sender, $receiver, $msg_text);
    $stmt->execute(); // don't fail flow on messages error
    $stmt->close();
}

redirect_with_msg($ride_id, 'success', 'Booking request submitted — waiting for driver confirmation.');
