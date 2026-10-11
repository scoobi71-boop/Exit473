<?php
require_once __DIR__ . '/auth.php';

if (portal_access_token()) { header('Location: dashboard.php'); exit; }

$error = '';
$values = ['email' => '', 'username' => '', 'first_name' => '', 'last_name' => '', 'phone' => '', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    portal_check_csrf();
    $email      = trim($_POST['email'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $password   = (string)($_POST['password'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $values     = ['email' => $email, 'username' => $username, 'first_name' => $first_name, 'last_name' => $last_name, 'phone' => $phone, 'address' => $address];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($first_name === '' || $last_name === '') {
        $error = 'Please enter your first and last name.';
    } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
        $error = 'Please enter a valid phone number.';
    } elseif ($address === '') {
        $error = 'Please enter your address.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,32}$/', $username)) {
        $error = 'Username must be 3–32 characters (letters, numbers, "_", "-", ".").';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        // Friendly pre-check before hitting the database's hard uniqueness constraint.
        $taken = supabase_table_select('profiles', 'username=eq.' . urlencode($username) . '&select=id', null, true);
        if ($taken['status'] === 200 && !empty($taken['data'])) {
            $error = 'That username is already taken.';
        } else {
            $resp = supabase_auth_signup($email, $password, [
                'username'   => $username,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'phone'      => $phone,
                'address'    => $address,
            ], portal_url('verify.php'));
            if (in_array($resp['status'], [200, 201], true)) {
                header('Location: register-pending.php?email=' . urlencode($email)); exit;
            }
            $msg = $resp['data']['msg'] ?? $resp['data']['error_description'] ?? $resp['data']['message'] ?? '';
            if (stripos($msg, 'already registered') !== false || stripos($msg, 'already exists') !== false) {
                $error = 'An account with that email already exists.';
            } elseif (stripos($msg, 'duplicate') !== false || stripos($msg, 'unique') !== false) {
                $error = 'That username is already taken.';
            } else {
                $error = $msg ?: 'Registration failed. Please try again.';
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
<title>Create an Organizer Account — Exit 473 Event Portal</title>
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
      <h1>Create an Organizer Account</h1>
      <p class="portal-sub">Register to create event pages and sell tickets through Exit 473.</p>
      <?php if ($error): ?><div class="portal-alert portal-alert-error"><?= h($error) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= h(portal_csrf_token()) ?>">
        <div class="portal-form-row">
          <label for="first_name">First Name</label>
          <input type="text" id="first_name" name="first_name" required autocomplete="given-name" value="<?= h($values['first_name']) ?>">
        </div>
        <div class="portal-form-row">
          <label for="last_name">Last Name</label>
          <input type="text" id="last_name" name="last_name" required autocomplete="family-name" value="<?= h($values['last_name']) ?>">
        </div>
        <div class="portal-form-row">
          <label for="phone">Phone</label>
          <input type="tel" id="phone" name="phone" required autocomplete="tel" value="<?= h($values['phone']) ?>" placeholder="(473) 555-0100">
        </div>
        <div class="portal-form-row">
          <label for="address">Address</label>
          <input type="text" id="address" name="address" required autocomplete="street-address" value="<?= h($values['address']) ?>" placeholder="Street, Parish, Grenada">
        </div>
        <div class="portal-form-row">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required autocomplete="email" value="<?= h($values['email']) ?>" placeholder="you@example.com">
        </div>
        <div class="portal-form-row">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" required autocomplete="username" value="<?= h($values['username']) ?>" placeholder="e.g. spicemas_events">
          <div class="hint">Shown publicly as the organizer of your events.</div>
        </div>
        <div class="portal-form-row">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required autocomplete="new-password" placeholder="At least 8 characters">
        </div>
        <button class="btn btn-primary btn-lg" type="submit" style="width:100%">Create Account →</button>
      </form>
      <p class="portal-links">Already have an account? <a href="login.php">Log in</a></p>
    </div>
  </div>
</body>
</html>
