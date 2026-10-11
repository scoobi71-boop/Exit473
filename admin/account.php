<?php
require_once 'auth.php';
require_auth();

$profile = admin_current_profile();

$pw_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    check_csrf();
    $np = (string)($_POST['new_password'] ?? '');
    $cp = (string)($_POST['confirm_password'] ?? '');
    if (strlen($np) < 8) {
        $pw_msg = 'error:Password must be at least 8 characters.';
    } elseif ($np !== $cp) {
        $pw_msg = 'error:Passwords do not match.';
    } else {
        $resp = supabase_request('PUT', '/auth/v1/user', [
            'access_token' => admin_access_token(),
            'body'         => ['password' => $np],
        ]);
        $pw_msg = ($resp['status'] === 200) ? 'ok:Password updated successfully.' : 'error:Could not update your password. Please try again.';
    }
}
[$pw_type, $pw_text] = $pw_msg ? explode(':', $pw_msg, 2) : ['', ''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Account — Exit 473 Admin</title>
<?php include 'head_styles.php'; ?>
</head>
<body>
<?php include 'nav.php'; ?>
<main class="wrap">
  <h1 class="page-title">My Account</h1>

  <div class="panel" style="max-width:480px">
    <h2 class="panel-title">Account Details</h2>
    <p><strong>Email:</strong> <?= h($_SESSION['admin_user_email'] ?? '') ?></p>
    <p><strong>Role:</strong> <?= h($profile['role'] ?? 'unknown') ?></p>
  </div>

  <div class="panel" style="max-width:480px">
    <h2 class="panel-title">Change Password</h2>
    <?php if ($pw_text): ?>
      <div class="alert alert-<?= $pw_type ?>"><?= h($pw_text) ?></div>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <div class="form-row">
        <label>New Password</label>
        <input type="password" name="new_password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters">
      </div>
      <div class="form-row">
        <label>Confirm Password</label>
        <input type="password" name="confirm_password" required minlength="8" autocomplete="new-password" placeholder="Repeat new password">
      </div>
      <button class="btn btn-primary" type="submit">Update Password</button>
    </form>
  </div>
</main>
</body>
</html>
