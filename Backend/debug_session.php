<?php
require_once 'dbcon.php';
require_once 'functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: text/plain; charset=utf-8');

echo "=== SESSION ===\n";
var_export($_SESSION);
echo "\n\n=== COOKIE (from request) ===\n";
var_export($_COOKIE);
echo "\n\n=== SESSION NAME ===\n";
echo session_name() . " => " . (isset($_COOKIE[session_name()]) ? $_COOKIE[session_name()] : '(no cookie)') . "\n\n";

echo "=== user in session shorthand ===\n";
if (!empty($_SESSION['user'])) {
    echo "id: " . ($_SESSION['user']['id'] ?? '(no id)') . "\n";
    echo "name: " . ($_SESSION['user']['name'] ?? '(no name)') . "\n";
    echo "role: " . ($_SESSION['user']['role'] ?? '(no role)') . "\n";
} else {
    echo "no user key in session\n";
}

echo "\n=== PHP session settings ===\n";
echo "session.save_path=" . ini_get('session.save_path') . "\n";
echo "session.gc_maxlifetime=" . ini_get('session.gc_maxlifetime') . "\n";
echo "session.cookie_lifetime=" . ini_get('session.cookie_lifetime') . "\n";
echo "session.cookie_secure=" . ini_get('session.cookie_secure') . "\n";
echo "session.cookie_samesite=" . (ini_get('session.cookie_samesite') ?? '(n/a)') . "\n";
