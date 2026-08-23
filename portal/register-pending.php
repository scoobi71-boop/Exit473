<?php
require_once __DIR__ . '/auth.php';

$email   = trim($_GET['email'] ?? '');
$resent  = false;
$error   = '';
$codeErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    portal_check_csrf();
    $email  = trim($_POST['email'] ?? $email);
    $action = $_POST['action'] ?? '';

    if ($action === 'resend' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $resp = supabase_auth_resend($email);
        $resent = in_array($resp['status'], [200, 201], true);
        if (!$resent) $error = 'Could not resend the email right now. Please try again shortly.';

    } elseif ($action === 'verify_code') {
        $code = trim($_POST['code'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $code === '') {
            $codeErr = 'Enter the 6-digit code from your email.';
        } else {
            $resp = supabase_auth_verify_otp($email, $code, 'signup');
            if ($resp['status'] === 200 && !empty($resp['data']['access_token'])) {
                portal_store_session($resp['data']);
                header('Location: dashboard.php'); exit;
            }
            $msg = $resp['data']['error_description'] ?? $resp['data']['msg'] ?? '';
            $codeErr = (stripos($msg, 'expired') !== false || stripos($msg, 'invalid') !== false)
                ? 'That code is incorrect or has expired. Double-check it, or resend a new one below.'
                : ($msg ?: 'Could not verify that code. Please try again.');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Check Your Email — Exit 473 Event Portal</title>
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
    <div class="portal-card" style="text-align:center">
      <h1>Check Your Email</h1>
      <p class="portal-sub">
        We sent a 6-digit verification code to <strong><?= h($email) ?></strong>.
        Enter it below to activate your account.
      </p>
      <?php if ($codeErr): ?><div class="portal-alert portal-alert-error"><?= h($codeErr) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= h(portal_csrf_token()) ?>">
        <input type="hidden" name="action" value="verify_code">
        <input type="hidden" name="email" value="<?= h($email) ?>">
        <div class="portal-form-row">
          <label for="code">Verification Code</label>
          <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required
                 maxlength="8" style="text-align:center;letter-spacing:.3em;font-size:1.2rem" placeholder="000000">
        </div>
        <button class="btn btn-primary btn-lg" type="submit" style="width:100%">Verify →</button>
      </form>

      <hr style="border:none;border-top:1px solid var(--gray-300);margin:1.5rem 0">

      <?php if ($resent): ?><div class="portal-alert portal-alert-ok">Verification email resent.</div><?php endif; ?>
      <?php if ($error): ?><div class="portal-alert portal-alert-error"><?= h($error) ?></div><?php endif; ?>
      <p class="portal-sub" style="margin-bottom:.75rem">Didn't get it, or the code expired?</p>
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= h(portal_csrf_token()) ?>">
        <input type="hidden" name="action" value="resend">
        <input type="hidden" name="email" value="<?= h($email) ?>">
        <button class="btn btn-outline" type="submit">Resend Code</button>
      </form>
      <p class="portal-links"><a href="login.php">Already verified? Log in →</a></p>
    </div>
  </div>
</body>
</html>
