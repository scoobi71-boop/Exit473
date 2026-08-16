<?php
require_once 'auth.php';
if (!empty($_SESSION['admin_ok'])) { header('Location: dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (verify_pw($_POST['password'] ?? '')) {
        $_SESSION['admin_ok'] = true;
        header('Location: dashboard.php'); exit;
    }
    $error = 'Incorrect password. Try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Login — Exit 473</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#1A0505;min-height:100vh;display:flex;align-items:center;justify-content:center}
.card{background:#fff;border-radius:16px;padding:2.5rem 2rem;width:100%;max-width:380px;box-shadow:0 20px 60px rgba(0,0,0,.5)}
.logo{text-align:center;margin-bottom:1.75rem}
.logo-circle{width:64px;height:64px;background:linear-gradient(135deg,#CC0000,#990000);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:1.6rem;margin-bottom:.75rem}
.logo h1{font-size:1.4rem;font-weight:700;color:#1A0505}
.logo p{font-size:.8rem;color:#7A6565;margin-top:.2rem;letter-spacing:.06em;text-transform:uppercase}
label{display:block;font-size:.85rem;font-weight:600;color:#3D2E2E;margin-bottom:.4rem}
input[type=password]{width:100%;padding:.7rem .9rem;border:2px solid #EAE2E2;border-radius:8px;font-size:1rem;transition:border-color .2s;outline:none}
input[type=password]:focus{border-color:#CC0000}
.btn{width:100%;margin-top:1.2rem;padding:.8rem;background:#CC0000;color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;transition:background .2s}
.btn:hover{background:#990000}
.error{background:#FFF0F0;border:1px solid #FFAAAA;color:#990000;padding:.65rem .9rem;border-radius:8px;font-size:.88rem;margin-bottom:1rem}
.hint{text-align:center;font-size:.78rem;color:#7A6565;margin-top:1rem}
a{color:#CC0000;text-decoration:none}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-circle">🏝️</div>
    <h1>Exit 473</h1>
    <p>Admin Portal</p>
  </div>
  <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <div style="margin-bottom:1rem">
      <label for="pw">Password</label>
      <input type="password" id="pw" name="password" autofocus autocomplete="current-password" placeholder="Enter admin password">
    </div>
    <button class="btn" type="submit">Log In →</button>
  </form>
  <p class="hint"><a href="../index.html">← Back to website</a></p>
</div>
</body>
</html>
