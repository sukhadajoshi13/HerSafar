<?php
// booking_actions.php - robust confirm/cancel handler with clear messages and safe redirects
ini_set('display_errors',1);
error_reporting(E_ALL);

require_once 'dbcon.php';
require_once 'functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function send_back($ride_id = null, $type='error', $text='Invalid request.') {
    $_SESSION['msg'] = ['type'=>$type, 'text'=>$text];
    // prefer returning to HTTP_REFERER if it's same-host and exists
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref && parse_url($ref, PHP_URL_HOST) === ($_SERVER['HTTP_HOST'] ?? '')) {
        header('Location: ' . $ref);
    } elseif ($ride_id) {
        header('Location: ride.php?id=' . (int)$ride_id);
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

// must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_back(null, 'error', 'Invalid request method.');
}

// CSRF
$token = $_POST['csrf'] ?? '';
if (! function_exists('verify_csrf') || ! verify_csrf($token)) {
    send_back(null, 'error', 'Invalid CSRF token. Try reloading the page and submitting again.');
}

// required fields
$action = $_POST['action'] ?? '';
$booking_id = (int)($_POST['booking_id'] ?? 0);
$actor_id = (int)($_SESSION['user']['id'] ?? 0);
$actor_role = $_SESSION['user']['role'] ?? '';

if ($booking_id <= 0) {
    send_back(null, 'error', 'Invalid booking id.');
}
if ($actor_id <= 0) {
    send_back(null, 'error', 'You must be logged in to perform that action.');
}

// load booking + ride
$stmt = $mysqli->prepare("
    SELECT b.id AS booking_id, b.ride_id, b.user_id AS passenger_id, b.seats, b.status,
           r.driver_id, r.available_seats, r.from_location, r.to_location
    FROM bookings b
    JOIN rides r ON b.ride_id = r.id
    WHERE b.id = ? LIMIT 1
");
if (! $stmt) {
    send_back(null, 'error', 'DB prepare error: ' . $mysqli->error);
}
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (! $booking) {
    send_back(null, 'error', 'Booking not found.');
}

// authorization: only driver of ride or admin can manage
if (! ($actor_role === 'admin' || $actor_id === (int)$booking['driver_id'])) {
    send_back($booking['ride_id'], 'error', 'Not authorized to manage this booking.');
}

$ride_id = (int)$booking['ride_id'];
$passenger_id = (int)$booking['passenger_id'];
$seats_needed = (int)$booking['seats'];
$ride_label = $booking['from_location'] . ' → ' . $booking['to_location'];

try {
    if ($action === 'confirm') {
        if ($booking['status'] !== 'pending') {
            send_back($ride_id, 'error', 'Only pending bookings can be confirmed.');
        }

        // begin transaction
        $mysqli->begin_transaction();

        // lock ride row
        $stmt = $mysqli->prepare("SELECT available_seats FROM rides WHERE id = ? FOR UPDATE");
        if (! $stmt) throw new Exception('DB prepare error (lock) ' . $mysqli->error);
        $stmt->bind_param('i', $ride_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $avail = (int)($row['available_seats'] ?? 0);
        if ($seats_needed > $avail) {
            $mysqli->rollback();
            send_back($ride_id, 'error', 'Not enough seats available to confirm this booking.');
        }

        // update booking -> confirmed
        $u1 = $mysqli->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?");
        $u1->bind_param('i', $booking_id);
        if (! $u1->execute()) throw new Exception('Failed to update booking: ' . $u1->error);
        $u1->close();

        // decrement seats
        $u2 = $mysqli->prepare("UPDATE rides SET available_seats = available_seats - ? WHERE id = ?");
        $u2->bind_param('ii', $seats_needed, $ride_id);
        if (! $u2->execute()) throw new Exception('Failed to decrement seats: ' . $u2->error);
        $u2->close();

        $mysqli->commit();

        // optional: notify passenger by message
        $msg = "Your booking for Ride #{$ride_id} ({$ride_label}) for {$seats_needed} seat(s) has been CONFIRMED.";
        $mstmt = $mysqli->prepare("INSERT INTO messages (sender_id, receiver_id, message, sent_at) VALUES (?, ?, ?, NOW())");
        if ($mstmt) {
            $mstmt->bind_param('iis', $actor_id, $passenger_id, $msg);
            $mstmt->execute();
            $mstmt->close();
        }

        send_back($ride_id, 'success', 'Booking confirmed. Passenger notified.');

    } elseif ($action === 'cancel') {
        if ($booking['status'] === 'cancelled') {
            send_back($ride_id, 'info', 'Booking already cancelled.');
        }

        $mysqli->begin_transaction();
        try {
            $wasConfirmed = ($booking['status'] === 'confirmed');

            // mark cancelled
            $u = $mysqli->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
            $u->bind_param('i', $booking_id);
            if (! $u->execute()) throw new Exception('Failed to cancel booking: ' . $u->error);
            $u->close();

            if ($wasConfirmed) {
                $restore = $seats_needed;
                $u2 = $mysqli->prepare("UPDATE rides SET available_seats = available_seats + ? WHERE id = ?");
                $u2->bind_param('ii', $restore, $ride_id);
                if (! $u2->execute()) throw new Exception('Failed to restore seats: ' . $u2->error);
                $u2->close();
            }

            $mysqli->commit();

            // notify passenger
            $msg = "Your booking for Ride #{$ride_id} ({$ride_label}) for {$seats_needed} seat(s) has been CANCELLED by the driver.";
            $mstmt = $mysqli->prepare("INSERT INTO messages (sender_id, receiver_id, message, sent_at) VALUES (?, ?, ?, NOW())");
            if ($mstmt) {
                $mstmt->bind_param('iis', $actor_id, $passenger_id, $msg);
                $mstmt->execute();
                $mstmt->close();
            }

            send_back($ride_id, 'success', 'Booking cancelled. Passenger notified.');
        } catch (Exception $e2) {
            $mysqli->rollback();
            send_back($ride_id, 'error', 'Failed to cancel booking: ' . $e2->getMessage());
        }
    } else {
        send_back($ride_id, 'error', 'Unknown action: ' . htmlspecialchars($action));
    }
} catch (Exception $e) {
    // catch any unexpected DB errors
    if ($mysqli->in_transaction) $mysqli->rollback();
    // log to error log for developer
    error_log("booking_actions error: " . $e->getMessage());
    send_back($ride_id, 'error', 'Server error: ' . $e->getMessage());
}
