<?php
/** Organizer password reset, step 1: email a 6-digit recovery code (same code flow as admin/forgot-password.php). */
require_once __DIR__ . '/auth.php';
if (portal_access_token()) { header('Location: dashboard.php'); exit; }
$error = '';
$email = trim($_GET['email'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    portal_check_csrf();
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        supabase_auth_recover($email);
        header('Location: reset-password.php?email=' . urlencode($email)); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Forgot Password — Exit 473 Event Portal</title>
<meta name="robots" content="noindex">
<?php include 'head_styles.php'; ?>
</head>
<body data-portal>
  <nav class="portal-nav">
    <div class="brand">Exit 473 <span>Event Portal</span></div>
    <div class="portal-nav-links"></div>
    <div class="portal-nav-right"><a href="/members.php">Members</a> &nbsp;·&nbsp; <a href="../index.html">Exit 473 ↗</a></div>
  </nav>
  <div class="portal-wrap">
    <div class="portal-card">
      <h1>Forgot Password</h1>
      <p class="portal-sub">Enter your account email and we'll send you a 6-digit code to set a new password.</p>
      <?php if ($error): ?><div class="portal-alert portal-alert-error"><?= h($error) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= h(portal_csrf_token()) ?>">
        <div class="portal-form-row">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required autocomplete="email" value="<?= h($email) ?>" placeholder="you@example.com">
        </div>
        <button class="btn btn-primary btn-lg" type="submit" style="width:100%">Send Reset Code →</button>
      </form>
      <p class="portal-links"><a href="reset-password.php">I already have a code</a> · <a href="login.php">Back to log in</a></p>
    </div>
  </div>
</body>
</html>
