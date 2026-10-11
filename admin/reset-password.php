<?php
require_once 'auth.php';

/**
 * Code-entry flow (not a clicked link): the same 6-digit {{ .Token }} from
 * Supabase's recovery email, verified via supabase_auth_verify_otp(). This
 * avoids the failure mode documented in portal/auth.php — Gmail/Outlook
 * link-scanners prefetching and burning a single-use confirmation link
 * before the person ever clicks it.
 */
$email  = trim($_GET['email'] ?? $_POST['email'] ?? '');
$error  = '';
$resent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? 'reset';
    $email  = trim($_POST['email'] ?? $email);

    if ($action === 'resend') {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            supabase_auth_recover($email);
            $resent = true;
        } else {
            $error = 'Enter a valid email address first.';
        }
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
                    admin_store_session($verify['data']);
                    admin_record_login($verify['data']['user']['id']);
                    header('Location: dashboard.php'); exit;
                }
                $error = 'Could not update your password. Please try again.';
            } else {
                $msg = $verify['data']['error_description'] ?? $verify['data']['msg'] ?? '';
                $error = (stripos($msg, 'expired') !== false || stripos($msg, 'invalid') !== false)
                    ? 'That code is incorrect or has expired. Double-check it, or resend a new one below.'
                    : ($msg ?: 'Could not verify that code. Please try again.');
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
<title>Reset Password — Exit 473 Admin</title>
<meta name="robots" content="noindex">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#1A0505;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem}
.card{background:#fff;border-radius:16px;padding:2.5rem 2rem;width:100%;max-width:380px;box-shadow:0 20px 60px rgba(0,0,0,.5)}
h1{font-size:1.3rem;color:#1A0505;margin-bottom:.5rem}
p.sub{font-size:.88rem;color:#7A6565;margin-bottom:1.5rem}
label{display:block;font-size:.85rem;font-weight:600;color:#3D2E2E;margin-bottom:.4rem}
input[type=email],input[type=password],input[type=text]{width:100%;padding:.7rem .9rem;border:2px solid #EAE2E2;border-radius:8px;font-size:1rem;outline:none}
input:focus{border-color:#CC0000}
.row{margin-bottom:1rem}
.btn{width:100%;margin-top:.5rem;padding:.8rem;background:#CC0000;color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer}
.btn:hover{background:#990000}
.btn-outline{width:100%;margin-top:.5rem;padding:.7rem;background:#fff;color:#3D2E2E;border:1.5px solid #D0C4C4;border-radius:8px;font-size:.9rem;font-weight:600;cursor:pointer}
.btn-outline:hover{border-color:#CC0000;color:#CC0000}
.error{background:#FFF0F0;border:1px solid #FFAAAA;color:#990000;padding:.65rem .9rem;border-radius:8px;font-size:.88rem;margin-bottom:1rem}
.ok{background:#E8F5E9;border:1px solid #A5D6A7;color:#1B5E20;padding:.65rem .9rem;border-radius:8px;font-size:.88rem;margin-bottom:1rem}
.hint{text-align:center;font-size:.78rem;color:#7A6565;margin-top:1rem}
a{color:#CC0000;text-decoration:none}
hr{border:none;border-top:1px solid #F0E8E8;margin:1.5rem 0}
</style>
</head>
<body>
<div class="card">
  <h1>Reset Your Password</h1>
  <p class="sub">Enter the code we emailed to <?= $email ? '<strong>' . h($email) . '</strong>' : 'you' ?>, then choose a new password.</p>
  <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
  <?php if ($resent): ?><div class="ok">A new code has been sent.</div><?php endif; ?>

  <form method="post">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <div class="row">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required autocomplete="email" value="<?= h($email) ?>">
    </div>
    <div class="row">
      <label for="code">Reset Code</label>
      <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="8" style="text-align:center;letter-spacing:.3em;font-size:1.2rem" placeholder="000000">
    </div>
    <div class="row">
      <label for="np">New Password</label>
      <input type="password" id="np" name="new_password" minlength="8" autocomplete="new-password" placeholder="At least 8 characters">
    </div>
    <div class="row">
      <label for="cp">Confirm Password</label>
      <input type="password" id="cp" name="confirm_password" minlength="8" autocomplete="new-password">
    </div>
    <button class="btn" type="submit" name="action" value="reset">Set Password →</button>
    <button class="btn-outline" type="submit" name="action" value="resend" formnovalidate>Resend Code</button>
  </form>

  <p class="hint"><a href="index.php">← Back to login</a></p>
</div>
</body>
</html>
