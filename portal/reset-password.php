<?php
/**
 * Organizer password reset, step 2: enter the 6-digit code from Supabase's
 * recovery email plus a new password. Code entry (not a clicked link) so mail
 * scanners can't burn a single-use link; mirrors admin/reset-password.php.
 */
require_once __DIR__ . '/auth.php';
$email  = trim($_GET['email'] ?? $_POST['email'] ?? '');
$error  = '';
$resent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    portal_check_csrf();
    $action = $_POST['action'] ?? 'reset';
    if ($action === 'resend') {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) { supabase_auth_recover($email); $resent = true; }
        else $error = 'Enter a valid email address first.';
    } else {
        $code = trim($_POST['code'] ?? '');
        $np   = (string)($_POST['new_password'] ?? '');
        $cp   = (string)($_POST['confirm_password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $code === '') {
            $error = 'Enter your email and the code from your inbox.';
        } elseif (strlen($np) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($np !== $cp) {
            $error = 'Passwords do not match.';
        } else {
            $verify = supabase_auth_verify_otp($email, $code, 'recovery');
            if ($verify['status'] === 200 && !empty($verify['data']['access_token'])) {
                $update = supabase_request('PUT', '/auth/v1/user', [
                    'access_token' => $verify['data']['access_token'],
                    'body'         => ['password' => $np],
                ]);
                if ($update['status'] === 200) {
                    portal_store_session($verify['data']);
                    header('Location: dashboard.php'); exit;
                }
                $error = 'Could not update your password. Please try again.';
            } else {
                $m = $verify['data']['error_description'] ?? $verify['data']['msg'] ?? '';
                $error = (stripos($m, 'expired') !== false || stripos($m, 'invalid') !== false)
                    ? 'That code is incorrect or has expired. Double-check it, or resend a new one below.'
                    : ($m ?: 'Could not verify that code. Please try again.');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reset Password — Exit 473 Event Portal</title>
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
      <h1>Set a New Password</h1>
      <p class="portal-sub">Check your inbox for a 6-digit code (it can take a minute; check spam too).</p>
      <?php if ($resent): ?><div class="portal-alert portal-alert-ok">A new code is on its way.</div><?php endif; ?>
      <?php if ($error): ?><div class="portal-alert portal-alert-error"><?= h($error) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= h(portal_csrf_token()) ?>">
        <input type="hidden" name="action" value="reset">
        <div class="portal-form-row"><label for="email">Email</label>
          <input type="email" id="email" name="email" required value="<?= h($email) ?>"></div>
        <div class="portal-form-row"><label for="code">Code</label>
          <input type="text" id="code" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="10" placeholder="123456"></div>
        <div class="portal-form-row"><label for="new_password">New Password</label>
          <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password"></div>
        <div class="portal-form-row"><label for="confirm_password">Confirm New Password</label>
          <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password"></div>
        <button class="btn btn-primary btn-lg" type="submit" style="width:100%">Update Password →</button>
      </form>
      <form method="post" style="margin-top:.75rem;text-align:center">
        <input type="hidden" name="_csrf" value="<?= h(portal_csrf_token()) ?>">
        <input type="hidden" name="action" value="resend">
        <input type="hidden" name="email" value="<?= h($email) ?>">
        <button class="btn btn-sm btn-outline" type="submit">Resend code</button>
      </form>
      <p class="portal-links"><a href="login.php">Back to log in</a></p>
    </div>
  </div>
</body>
</html>
