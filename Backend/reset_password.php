<?php
require_once 'dbcon.php';
require_once 'functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$token = preg_replace('/[^0-9a-f]/i','', $token);
$errors = [];
$success = '';

if (!$token) {
    $errors[] = 'Invalid or missing token.';
} else {
    // load reset row
    $stmt = $mysqli->prepare("SELECT id, user_id, expires_at FROM password_resets WHERE token = ? LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) $errors[] = 'Invalid reset token.';
    elseif (strtotime($row['expires_at']) < time()) $errors[] = 'Reset token expired.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    if (!verify_csrf($_POST['csrf'] ?? '')) $errors[] = 'Invalid CSRF token.';
    $pw = $_POST['password'] ?? '';
    $pw2 = $_POST['password_confirm'] ?? '';
    if (strlen($pw) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($pw !== $pw2) $errors[] = 'Passwords do not match.';
    if (empty($errors)) {
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        // update user password
        $stmt = $mysqli->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $hash, $row['user_id']);
        if ($stmt->execute()) {
            // delete all reset tokens for this user
            $stmt->close();
            $stmt2 = $mysqli->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $stmt2->bind_param('i', $row['user_id']);
            $stmt2->execute();
            $stmt2->close();
            $success = 'Password updated. You can now <a href="login.php">login</a>.';
        } else {
            $errors[] = 'Failed to set password.';
        }
    }
}

$csrf = csrf_token();
?>
<!doctype html><html><head><meta charset="utf-8"><title>Reset password</title></head><body style="font-family:Arial;padding:18px">
<div style="max-width:600px;margin:auto;background:#fff;padding:18px;border-radius:8px">
  <h2>Reset password</h2>
  <?php if($errors): foreach($errors as $e) echo '<div style="color:red">'.htmlspecialchars($e).'</div>'; endif; ?>
  <?php if($success): echo '<div style="color:green">'.$success.'</div>'; endif; ?>

  <?php if(empty($success) && empty($errors)): ?>
  <form method="POST">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
    <label>New password<br><input type="password" name="password" required style="width:100%;padding:8px"></label>
    <label style="margin-top:8px">Confirm password<br><input type="password" name="password_confirm" required style="width:100%;padding:8px"></label>
    <div style="margin-top:10px"><button type="submit">Set new password</button></div>
  </form>
  <?php endif; ?>

</div>
</body></html>
