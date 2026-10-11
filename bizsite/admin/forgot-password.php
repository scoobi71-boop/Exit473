<?php
require_once 'auth.php';
if (biz_access_token() && biz_current_profile()) { header('Location: dashboard.php'); exit; }

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
<title>Forgot Password — Business Admin</title>
<meta name="robots" content="noindex">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#1E2A35;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem}
.card{background:#fff;border-radius:14px;padding:2.5rem 2rem;width:100%;max-width:380px;box-shadow:0 20px 60px rgba(0,0,0,.4)}
h1{font-size:1.3rem;color:#1E2A35;margin-bottom:.5rem}
p.sub{font-size:.88rem;color:#718096;margin-bottom:1.5rem}
label{display:block;font-size:.8rem;font-weight:600;color:#4A5568;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.4rem}
input[type=email]{width:100%;padding:.7rem .9rem;border:2px solid #DDE3EA;border-radius:8px;font-size:.95rem;outline:none}
input[type=email]:focus{border-color:#2D6A4F}
.btn{width:100%;margin-top:1.2rem;padding:.8rem;background:#2D6A4F;color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer}
.btn:hover{background:#1B4D35}
.error{background:#FFF0F0;border:1px solid #FFAAAA;color:#990000;padding:.65rem .9rem;border-radius:8px;font-size:.88rem;margin-bottom:1rem}
.hint{text-align:center;font-size:.78rem;color:#718096;margin-top:1rem}
a{color:#2D6A4F;text-decoration:none}
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
