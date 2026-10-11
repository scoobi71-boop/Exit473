<?php
require_once 'auth.php';
if (biz_access_token() && biz_current_profile()) { header('Location: dashboard.php'); exit; }

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email    = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    $resp = supabase_auth_login($email, $password);
    if ($resp['status'] === 200 && !empty($resp['data']['access_token'])) {
        biz_store_session($resp['data']);
        if (!biz_current_profile()) {
            biz_logout();
            $error = 'This account is not set up as a business admin.';
        } else {
            biz_record_login($resp['data']['user']['id']);
            header('Location: dashboard.php'); exit;
        }
    } else {
        $error = 'Incorrect email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Business Admin Login</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#1E2A35;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem}
.card{background:#fff;border-radius:14px;padding:2.5rem 2rem;width:100%;max-width:380px;box-shadow:0 20px 60px rgba(0,0,0,.4)}
.logo{text-align:center;margin-bottom:2rem}
.logo-icon{width:56px;height:56px;background:linear-gradient(135deg,#2D6A4F,#1B4D35);border-radius:14px;display:inline-flex;align-items:center;justify-content:center;font-size:1.5rem;margin-bottom:.75rem}
.logo h1{font-size:1.3rem;font-weight:700;color:#1E2A35}
.logo p{font-size:.8rem;color:#718096;margin-top:.2rem;text-transform:uppercase;letter-spacing:.07em}
label{display:block;font-size:.8rem;font-weight:600;color:#4A5568;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.4rem}
input{width:100%;padding:.7rem .9rem;border:2px solid #DDE3EA;border-radius:8px;font-size:.95rem;outline:none;transition:border-color .2s;margin-bottom:1rem}
input:focus{border-color:#2D6A4F}
.btn{width:100%;padding:.8rem;background:#2D6A4F;color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;transition:background .2s}
.btn:hover{background:#1B4D35}
.error{background:#FFF0F0;border:1px solid #FFAAAA;color:#990000;padding:.65rem .9rem;border-radius:8px;font-size:.85rem;margin-bottom:1rem}
.hint{text-align:center;font-size:.78rem;color:#718096;margin-top:1rem}
.hint a{color:#2D6A4F}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-icon">🏪</div>
    <h1>Business Portal</h1>
    <p>Admin Login</p>
  </div>
  <?php if ($error): ?>
    <div class="error"><?= h($error) ?></div>
  <?php endif; ?>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <div>
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required autofocus autocomplete="email" placeholder="you@example.com" value="<?= h($email) ?>">
    </div>
    <div>
      <label for="pw">Password</label>
      <input type="password" id="pw" name="password" required autocomplete="current-password" placeholder="Your password">
    </div>
    <button class="btn" type="submit">Log In →</button>
  </form>
  <p class="hint"><a href="forgot-password.php">Forgot password?</a> · <a href="../index.php">← Back to site</a></p>
</div>
</body>
</html>
