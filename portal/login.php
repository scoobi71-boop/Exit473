<?php
require_once __DIR__ . '/auth.php';

if (portal_access_token()) { header('Location: dashboard.php'); exit; }

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    portal_check_csrf();
    $email    = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    $resp = supabase_auth_login($email, $password);
    if ($resp['status'] === 200 && !empty($resp['data']['access_token'])) {
        portal_store_session($resp['data']);
        $next = $_GET['next'] ?? '';
        // Only ever redirect to an internal /portal/ path — "next" is
        // attacker-controllable via the query string (open-redirect guard).
        $redirect = (strpos($next, '/portal/') === 0) ? $next : 'dashboard.php';
        header('Location: ' . $redirect); exit;
    }
    $msg = $resp['data']['error_description'] ?? $resp['data']['msg'] ?? '';
    if (stripos($msg, 'not confirmed') !== false) {
        $error = 'Please verify your email before logging in — check your inbox, or <a href="register-pending.php?email=' . urlencode($email) . '">resend the link</a>.';
    } elseif ($resp['status'] === 400) {
        $error = 'Incorrect email or password.';
    } else {
        $error = 'Login failed. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Log In — Exit 473 Event Portal</title>
<meta name="robots" content="noindex">
<?php include 'head_styles.php'; ?>
</head>
<body data-portal>
  <nav class="portal-nav">
    <div class="brand">Exit 473 <span>Event Portal</span></div>
    <div class="portal-nav-links"></div>
    <div class="portal-nav-right"><a href="../index.html">Exit 473 ↗</a></div>
  </nav>
  <div class="portal-wrap">
    <div class="portal-card">
      <h1>Log In</h1>
      <p class="portal-sub">Manage your events and ticket sales.</p>
      <?php if ($error): ?><div class="portal-alert portal-alert-error"><?= $error /* may contain a trusted inline link, built above */ ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= h(portal_csrf_token()) ?>">
        <div class="portal-form-row">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required autocomplete="email" value="<?= h($email) ?>" placeholder="you@example.com">
        </div>
        <div class="portal-form-row">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button class="btn btn-primary btn-lg" type="submit" style="width:100%">Log In →</button>
      </form>
      <p class="portal-links">New here? <a href="register.php">Create an organizer account</a></p>
    </div>
  </div>
</body>
</html>
