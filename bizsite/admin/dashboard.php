<?php
require_once 'auth.php';
require_auth();
$slug  = current_slug();
$info  = read_info($slug);
$menu  = read_csv(menu_path($slug));

$pw_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    check_csrf();
    $np = trim($_POST['new_password'] ?? '');
    $cp = trim($_POST['confirm_password'] ?? '');
    if (strlen($np) < 6)     $pw_msg = 'error:Password must be at least 6 characters.';
    elseif ($np !== $cp)     $pw_msg = 'error:Passwords do not match.';
    elseif (change_pw($slug, $np)) $pw_msg = 'ok:Password updated.';
    else                     $pw_msg = 'error:Could not save password.';
}
[$pw_type, $pw_text] = $pw_msg ? explode(':', $pw_msg, 2) : ['',''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard — <?= h($info['name'] ?? 'Admin') ?></title>
<?php include 'head_styles.php'; ?>
</head>
<body>
<?php include 'nav.php'; ?>
<main class="wrap">
  <h1 class="page-title">Dashboard</h1>

  <div class="stat-grid">
    <a href="edit_info.php" class="stat-card">
      <div style="font-size:1.6rem;margin-bottom:.3rem">🏪</div>
      <div class="stat-label">Business Info</div>
    </a>
    <?php if (has_menu($info)): ?>
    <a href="menu_items.php" class="stat-card">
      <div style="font-size:1.6rem;margin-bottom:.3rem">🍽️</div>
      <div class="stat-num"><?= count($menu) ?></div>
      <div class="stat-label">Menu Items</div>
    </a>
    <?php endif; ?>
    <a href="../index.php?biz=<?= h($slug) ?>" target="_blank" class="stat-card">
      <div style="font-size:1.6rem;margin-bottom:.3rem">🌐</div>
      <div class="stat-num">↗</div>
      <div class="stat-label">View Site</div>
    </a>
  </div>

  <div class="panel" style="max-width:460px">
    <h2 class="panel-title">Change Password</h2>
    <?php if ($pw_text): ?>
      <div class="alert alert-<?= $pw_type ?>"><?= h($pw_text) ?></div>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <div class="form-grid" style="grid-template-columns:1fr">
        <div class="form-row">
          <label>New Password</label>
          <input type="password" name="new_password" required minlength="6" placeholder="Minimum 6 characters">
        </div>
        <div class="form-row">
          <label>Confirm Password</label>
          <input type="password" name="confirm_password" required minlength="6" placeholder="Repeat password">
        </div>
      </div>
      <button class="btn btn-primary" type="submit" style="margin-top:1rem">Update Password</button>
    </form>
  </div>
</main>
</body>
</html>
