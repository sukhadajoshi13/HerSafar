<?php
if (session_status() === PHP_SESSION_NONE) {
    // secure session cookie settings (adjust domain/path as needed)
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? true : false;
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => $cookieParams['lifetime'] ?? 0,
        'path'     => $cookieParams['path'] ?? '/',
        'domain'   => $cookieParams['domain'] ?? '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax' // change to 'Strict' if desired
    ]);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function refresh_user_session(mysqli $mysqli) {
    if (empty($_SESSION['user']['id'])) return;
    $uid = (int)$_SESSION['user']['id'];
    $stmt = $mysqli->prepare("SELECT id,name,email,role,verified FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) return;
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($u) {
        $_SESSION['user']['id'] = (int)$u['id'];
        $_SESSION['user']['name'] = $u['name'];
        $_SESSION['user']['email'] = $u['email'];
        $_SESSION['user']['role'] = $u['role'];
        // optionally:
        $_SESSION['user']['verified'] = (int)$u['verified'];
    }
}

/**
 * Verify posted token against session token
 * Pass the raw posted value: verify_csrf($_POST['csrf'] ?? '')
 */
function verify_csrf($token): bool {
    if (empty($token) || empty($_SESSION['_csrf_token'])) {
        return false;
    }
    // timing-safe compare
    return hash_equals((string)$_SESSION['_csrf_token'], (string)$token);
}

// Optional: convenience to rotate token after successful action (uncomment if desired)
// function csrf_rotate() { unset($_SESSION['_csrf_token']); }

// -------------------- Flash messaging --------------------
/**
 * Store a flash message (type: 'success','error','info')
 */
function set_flash(string $type, string $text): void {
    $_SESSION['_flash'] = ['type' => $type, 'text' => $text];
}

/**
 * Get and remove one flash message (returns array or null)
 */
function get_flash(): ?array {
    if (!empty($_SESSION['_flash'])) {
        $f = $_SESSION['_flash'];
        unset($_SESSION['_flash']);
        return $f;
    }
    return null;
}

// -------------------- Authentication helpers --------------------
/**
 * Return true if user session exists
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user']['id']);
}

/**
 * Require login; redirect to login.php if not logged in
 */
function require_login(): void {
    if (!is_logged_in()) {
        // store target page to return after login if desired:
        $_SESSION['_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: /login.php');
        exit;
    }
}

/**
 * Log user into session (pass array with id, name, email, role)
 */
function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'    => isset($user['id']) ? (int)$user['id'] : 0,
        'name'  => $user['name'] ?? '',
        'email' => $user['email'] ?? '',
        'role'  => $user['role'] ?? 'user'
    ];
}



// -------------------- Small helpers (optional) --------------------
/**
 * Safe HTML escape helper (short)
 */
function e($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// functions.php — include this updated logout function

function logout() {
    // Make sure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Clear all session variables
    $_SESSION = [];

    // Remove session cookie (PHPSESSID)
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Destroy the session completely
    session_destroy();

    // Optional: clear any "remember me" or custom cookies
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
        unset($_COOKIE['remember_token']);
    }

    // Send no-cache headers to prevent old data showing
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    }
}
// functions.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function logout_user(bool $clear_remember_cookie = true): void {
    // Unset all session variables
    $_SESSION = [];

    // Delete the session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }

    // Destroy the session data
    session_destroy();

    // Optionally clear "remember me" cookie
    if ($clear_remember_cookie && isset($_COOKIE['remember_me'])) {
        setcookie('remember_me', '', time() - 3600, '/');
        unset($_COOKIE['remember_me']);
    }
}
