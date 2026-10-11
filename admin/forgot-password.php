<?php
require_once 'auth.php';
if (admin_access_token()) { header('Location: dashboard.php'); exit; }

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
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
<title>Forgot Password — Exit 473 Admin</title>
<meta name="robots" content="noindex">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#1A0505;min-height:100vh;display:flex;align-items:center;justify-content:center}
.card{background:#fff;border-radius:16px;padding:2.5rem 2rem;width:100%;max-width:380px;box-shadow:0 20px 60px rgba(0,0,0,.5)}
h1{font-size:1.3rem;color:#1A0505;margin-bottom:.5rem}
p.sub{font-size:.88rem;color:#7A6565;margin-bottom:1.5rem}
label{display:block;font-size:.85rem;font-weight:600;color:#3D2E2E;margin-bottom:.4rem}
input[type=email]{width:100%;padding:.7rem .9rem;border:2px solid #EAE2E2;border-radius:8px;font-size:1rem;outline:none}
input[type=email]:focus{border-color:#CC0000}
.btn{width:100%;margin-top:1.2rem;padding:.8rem;background:#CC0000;color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer}
.btn:hover{background:#990000}
.error{background:#FFF0F0;border:1px solid #FFAAAA;color:#990000;padding:.65rem .9rem;border-radius:8px;font-size:.88rem;margin-bottom:1rem}
.hint{text-align:center;font-size:.78rem;color:#7A6565;margin-top:1rem}
a{color:#CC0000;text-decoration:none}
</style>
</head>
<body>
<div class="card">
  <h1>Forgot Password</h1>
  <p class="sub">Enter your email and we'll send you a code to reset your password.</p>
  <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" autofocus autocomplete="email" required value="<?= h($email) ?>" placeholder="you@example.com">
    <button class="btn" type="submit">Send Reset Code →</button>
  </form>
  <p class="hint"><a href="index.php">← Back to login</a></p>
</div>
</body>
</html>
