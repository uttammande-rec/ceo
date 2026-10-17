<?php
/**
 * StudentOrbit — "Forgot password" step 1: request an OTP by email.
 *   GET  forgot.php          -> show the email form
 *   POST forgot.php          -> create + email an OTP, then go to reset.php
 */
require __DIR__ . '/auth.php';
require_once __DIR__ . '/../src/PasswordReset.php';

// Already signed in? No need to reset.
if (current_user()) {
    header('Location: index.php');
    exit;
}

$error  = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email === '') {
        $error = 'Please enter your email.';
    } else {
        try {
            $res = PasswordReset::request($email);
            if (isset($res['cooldown'])) {
                $error = "Please wait {$res['cooldown']}s before requesting another code.";
            } else {
                // Carry the email to the reset page (sent whether or not the account exists).
                $_SESSION['reset_email'] = $email;
                header('Location: reset.php');
                exit;
            }
        } catch (Throwable $e) {
            // Mail/SMTP failure — show a safe message, log the detail server-side.
            error_log('[StudentOrbit] password-reset email failed: ' . $e->getMessage());
            $error = 'Could not send the reset email. Please try again later or contact the administrator.';
        }
    }
}
?><!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Forgot password — StudentOrbit</title>
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
</style>
</head>
<body>
<div class="login-wrap">
  <div class="card login-card">
    <div class="login-brand">
      <span class="logo" style="display:grid;place-items:center;background:var(--primary);color:#fff;border-radius:12px">◆</span>
      <div>
        <h1>Forgot password</h1>
        <p class="tagline">We'll email you a one-time code</p>
      </div>
    </div>
    <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <p class="help">Enter the email for your account. If it exists, a 6-digit code valid for 10 minutes will be sent.</p>
    <form method="post" autocomplete="on">
      <label>Email
        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
      </label>
      <button class="btn" type="submit">Send reset code</button>
    </form>
    <div class="links"><a href="login.php">&larr; Back to sign in</a></div>
  </div>
</div>
</body>
</html>
