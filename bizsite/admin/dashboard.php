<?php
require_once 'auth.php';
require_auth();
require_once __DIR__ . '/../../portal/finance_helpers.php';
$slug  = current_slug();
$info  = read_info($slug);
$menu  = read_csv(menu_path($slug));

$outstandingResp = supabase_table_select('business_fee_charges', 'business_slug=eq.' . urlencode($slug) . '&status=in.(unpaid,pending)&select=amount_cents', null, true);
$totalOutstanding = array_sum(array_column($outstandingResp['data'] ?? [], 'amount_cents'));
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
    <a href="finance.php" class="stat-card">
      <div style="font-size:1.6rem;margin-bottom:.3rem">💰</div>
      <div class="stat-num" style="<?= $totalOutstanding > 0 ? 'color:#B8590B' : '' ?>"><?= h(finance_money($totalOutstanding, 'XCD')) ?></div>
      <div class="stat-label">Finance — Outstanding</div>
    </a>
  </div>
</main>
</body>
</html>
