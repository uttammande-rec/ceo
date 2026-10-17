<?php
/**
 * StudentOrbit — login + logout.
 *   GET  login.php           -> show form (or redirect to app if already in)
 *   POST login.php           -> authenticate
 *   GET  login.php?logout=1  -> sign out
 */
require __DIR__ . '/auth.php';

if (isset($_GET['logout'])) {
    logout();
    header('Location: login.php');
    exit;
}

if (current_user()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $pass  = $_POST['password'] ?? '';
    if (attempt_login($email, $pass)) {
        header('Location: index.php');
        exit;
    }
    $error = 'Invalid email or password.';
}

// One-time success notice (e.g. after a password reset).
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
?><!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in — StudentOrbit</title>
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
  .login-card .ok { background: color-mix(in srgb, var(--success, #16a34a) 15%, transparent); color: var(--success, #16a34a); padding: 9px 12px; border-radius: 9px; font-size: 14px; }
  .login-card .links { text-align: center; margin-top: 14px; font-size: 13px; }
  .login-card .links a { color: var(--primary); text-decoration: none; }
</style>
</head>
<body>
<div class="login-wrap">
  <div class="card login-card">
    <div class="login-brand">
      <span class="logo" style="display:grid;place-items:center;background:var(--primary);color:#fff;border-radius:12px">◆</span>
      <div>
        <h1>StudentOrbit</h1>
        <p class="tagline">CEO Dashboard — sign in</p>
      </div>
    </div>
    <?php if ($flash): ?><div class="ok"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="on">
      <label>Email
        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
      </label>
      <label>Password
        <input type="password" name="password" required>
      </label>
      <button class="btn" type="submit">Sign in</button>
    </form>
    <div class="links"><a href="forgot.php">Forgot password?</a></div>
  </div>
</div>
</body>
</html>
