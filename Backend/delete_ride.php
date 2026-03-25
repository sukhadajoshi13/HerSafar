<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once 'dbcon.php';
require_once 'functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

$uid = (int)($_SESSION['user']['id'] ?? 0);
$role = $_SESSION['user']['role'] ?? 'user';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error','Invalid request method.');
    header('Location: manage_rides.php');
    exit;
}

// CSRF
if (! verify_csrf($_POST['csrf'] ?? '')) {
    set_flash('error','Invalid CSRF token.');
    header('Location: manage_rides.php');
    exit;
}

$ride_id = (int)($_POST['ride_id'] ?? 0);
if ($ride_id <= 0) {
    set_flash('error','Invalid ride id.');
    header('Location: manage_rides.php');
    exit;
}

// load ride and driver
$stmt = $mysqli->prepare("SELECT id, driver_id, share_token FROM rides WHERE id = ? LIMIT 1");
if (! $stmt) {
    set_flash('error','Database error: ' . $mysqli->error);
    header('Location: manage_rides.php');
    exit;
}
$stmt->bind_param('i', $ride_id);
$stmt->execute();
$ride = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (! $ride) {
    set_flash('error','Ride not found.');
    header('Location: manage_rides.php');
    exit;
}

// permission check: driver or admin
if ((int)$ride['driver_id'] !== $uid && $role !== 'admin') {
    set_flash('error','You do not have permission to delete this ride.');
    header('Location: manage_rides.php');
    exit;
}

try {
    // start transaction
    $mysqli->begin_transaction();

    // 1) delete bookings for this ride
    $stmt = $mysqli->prepare("DELETE FROM bookings WHERE ride_id = ?");
    if (! $stmt) throw new Exception('Prepare failed (delete bookings): ' . $mysqli->error);
    $stmt->bind_param('i', $ride_id);
    if (! $stmt->execute()) { $stmt->close(); throw new Exception('Failed deleting bookings: ' . $stmt->error); }
    $stmt->close();

    // 2) delete any ride_group_shares (if your app uses this table)
    if (table_exists($mysqli, 'ride_group_shares')) {
        $stmt = $mysqli->prepare("DELETE FROM ride_group_shares WHERE ride_id = ?");
        if (! $stmt) throw new Exception('Prepare failed (delete ride_group_shares): ' . $mysqli->error);
        $stmt->bind_param('i', $ride_id);
        if (! $stmt->execute()) { $stmt->close(); throw new Exception('Failed deleting ride_group_shares: ' . $stmt->error); }
        $stmt->close();
    }

    // 3) delete any ride_shares (alternate table name)
    if (table_exists($mysqli, 'ride_shares')) {
        $stmt = $mysqli->prepare("DELETE FROM ride_shares WHERE ride_id = ?");
        if (! $stmt) throw new Exception('Prepare failed (delete ride_shares): ' . $mysqli->error);
        $stmt->bind_param('i', $ride_id);
        if (! $stmt->execute()) { $stmt->close(); throw new Exception('Failed deleting ride_shares: ' . $stmt->error); }
        $stmt->close();
    }

    // 4) optionally delete messages specifically tied to ride (if you store any). Adjust table/column names if you have them.
    if (table_exists($mysqli, 'ride_messages')) {
        $stmt = $mysqli->prepare("DELETE FROM ride_messages WHERE ride_id = ?");
        if ($stmt) { $stmt->bind_param('i',$ride_id); $stmt->execute(); $stmt->close(); }
    }

    // 5) finally delete the ride
    $stmt = $mysqli->prepare("DELETE FROM rides WHERE id = ? LIMIT 1");
    if (! $stmt) throw new Exception('Prepare failed (delete ride): ' . $mysqli->error);
    $stmt->bind_param('i', $ride_id);
    if (! $stmt->execute()) { $stmt->close(); throw new Exception('Failed deleting ride: ' . $stmt->error); }
    $stmt->close();

    // commit
    $mysqli->commit();

    // clear any ephemeral session share links created during ride creation
    if (!empty($_SESSION['last_generated_share_links'])) unset($_SESSION['last_generated_share_links']);

    set_flash('success','Ride deleted successfully.');
    header('Location: manage_rides.php');
    exit;

} catch (Exception $e) {
    // rollback and show error
    $mysqli->rollback();
    error_log('delete_ride error: ' . $e->getMessage());
    set_flash('error','Failed to delete ride: ' . $e->getMessage());
    header('Location: manage_rides.php');
    exit;
}

/**
 * Helper: check if table exists in current DB. Simple, avoids fatal when optional tables missing.
 */
function table_exists(mysqli $db, $table) {
    $ok = false;
    $t = preg_replace('/[^A-Za-z0-9_]/','',$table);
    if ($t === '') return false;
    $sql = "SHOW TABLES LIKE ?";
    if ($stmt = $db->prepare($sql)) {
        $stmt->bind_param('s', $t);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            $ok = ($res && $res->num_rows > 0);
        }
        $stmt->close();
    }
    return (bool)$ok;
}
