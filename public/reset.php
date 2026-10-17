<?php
/**
 * StudentOrbit — "Forgot password" step 2: enter the OTP + a new password.
 *   GET  reset.php   -> show the code + new-password form (needs reset_email in session)
 *   POST reset.php   -> verify the OTP and set the new password
 */
require __DIR__ . '/auth.php';
require_once __DIR__ . '/../src/PasswordReset.php';

if (current_user()) {
    header('Location: index.php');
    exit;
}

// Must have come from forgot.php.
$email = $_SESSION['reset_email'] ?? '';
if ($email === '') {
    header('Location: forgot.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp     = trim($_POST['otp'] ?? '');
    $pass    = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if ($otp === '' || $pass === '') {
        $error = 'Enter the code and a new password.';
    } elseif ($pass !== $confirm) {
        $error = 'The two passwords do not match.';
    } else {
        $res = PasswordReset::verify($email, $otp, $pass);
        if ($res['ok']) {
            unset($_SESSION['reset_email']);
            $_SESSION['flash'] = 'Password updated. Please sign in with your new password.';
            header('Location: login.php');
            exit;
        }
        $error = $res['error'] ?? 'Could not reset the password.';
    }
}
?><!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset password — StudentOrbit</title>
<link rel="stylesheet" href="assets/css/app.css">
<script>
  (function () {
    var t = localStorage.getItem('so-theme');
    if (!t) t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', t);
  })();
</script>
<style>
  .login-wrap { min-height: 100vh; display: grid; place-items: center; padding: 20px; }
  .login-card { width: min(380px, 94vw); }
  .login-brand { display: flex; align-items: center; gap: 12px; justify-content: center; margin-bottom: 20px; }
  .login-brand .logo { width: 44px; height: 44px; font-size: 22px; }
  .login-card h1 { font-size: 20px; margin: 0; }
  .login-card .tagline { font-size: 13px; color: var(--muted); margin: 2px 0 0; }
  .login-card form { display: grid; gap: 14px; }
  .login-card label { font-size: 13px; color: var(--muted); display: grid; gap: 6px; }
  .login-card .err { background: color-mix(in srgb, var(--danger) 15%, transparent); color: var(--danger); padding: 9px 12px; border-radius: 9px; font-size: 14px; }
  .login-card .btn { width: 100%; text-align: center; padding: 11px; font-size: 15px; }
  .login-card .links { text-align: center; margin-top: 14px; font-size: 13px; }
  .login-card .links a { color: var(--primary); text-decoration: none; }
  .login-card .help { font-size: 13px; color: var(--muted); margin: -2px 0 2px; }
  .login-card input[name="otp"] { letter-spacing: 6px; font-size: 20px; text-align: center; }
</style>
</head>
<body>
<div class="login-wrap">
  <div class="card login-card">
    <div class="login-brand">
      <span class="logo" style="display:grid;place-items:center;background:var(--primary);color:#fff;border-radius:12px">◆</span>
      <div>
        <h1>Reset password</h1>
        <p class="tagline">Enter the code we emailed</p>
      </div>
    </div>
    <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <p class="help">A code was sent to <strong><?= htmlspecialchars($email) ?></strong>. It expires in 10 minutes.</p>
    <form method="post" autocomplete="off">
      <label>6-digit code
        <input type="text" name="otp" inputmode="numeric" pattern="[0-9]*" maxlength="6" required autofocus>
      </label>
      <label>New password (min 8 characters)
        <input type="password" name="password" minlength="8" required>
      </label>
      <label>Confirm new password
        <input type="password" name="confirm" minlength="8" required>
      </label>
      <button class="btn" type="submit">Set new password</button>
    </form>
    <div class="links"><a href="forgot.php">Request a new code</a> &nbsp;·&nbsp; <a href="login.php">Back to sign in</a></div>
  </div>
</div>
</body>
</html>
