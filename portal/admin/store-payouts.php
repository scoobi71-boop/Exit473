<?php
/** Store admin: payouts report per merchant (gross, commission retained, owed), mark paid, CSV. */
require_once __DIR__ . '/../../admin/auth.php';
require_once __DIR__ . '/../supabase.php';
require_once __DIR__ . '/../store_lib.php';
require_once __DIR__ . '/_chrome.php';
padmin_require(['site_owner', 'content_manager', 'finance']);

$ready = store_ready();
$msg = '';
if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_paid') {
    check_csrf();
    $r = supabase_rpc('record_store_payout', ['p_store_id' => $_POST['store_id'] ?? '', 'p_note' => store_substr(trim($_POST['note'] ?? ''), 0, 200) ?: null], null, true);
    $msg = ($r['status'] === 200 && !empty($r['data']['payout_id']))
        ? 'Recorded a payout of ' . store_money($r['data']['amount_cents']) . ' (' . (int)$r['data']['item_count'] . ' items).'
        : 'Nothing owed to that merchant.';
}
$report = []; $payouts = [];
if ($ready) {
    $r = supabase_rpc('store_payout_report', [], null, true);
    $report = $r['status'] === 200 ? $r['data'] : [];
    $pr = supabase_table_select('store_payouts', 'select=*,stores(name)&order=created_at.desc&limit=50', null, true);
    $payouts = $pr['status'] === 200 ? $pr['data'] : [];
}
if ($ready && ($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="exit473-store-payouts-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['store', 'type', 'commission_percent', 'items_sold', 'gross_usd', 'commission_retained_usd', 'merchant_share_usd', 'paid_out_usd', 'owed_usd', 'last_payout']);
    foreach ($report as $s) fputcsv($out, [$s['store_name'], $s['store_type'], $s['store_type'] === 'house' ? 100 : $s['commission_percent'], $s['items_sold'],
        number_format($s['gross_cents'] / 100, 2, '.', ''), number_format($s['commission_cents'] / 100, 2, '.', ''), number_format($s['payout_total_cents'] / 100, 2, '.', ''),
        number_format($s['paid_out_cents'] / 100, 2, '.', ''), number_format($s['owed_cents'] / 100, 2, '.', ''), $s['last_payout_at']]);
    fclose($out); exit;
}
$tot = ['gross' => 0, 'comm' => 0, 'owed' => 0, 'paid' => 0];
foreach ($report as $s) { $tot['gross'] += $s['gross_cents']; $tot['comm'] += $s['commission_cents']; $tot['owed'] += $s['owed_cents']; $tot['paid'] += $s['paid_out_cents']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Store Payouts — Exit 473 Admin</title>
<meta name="robots" content="noindex">
<?php admin_look_head(); ?>
</head>
<body>
<?php padmin_nav('store'); ?>
<main class="wrap">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
    <h1 class="page-title" style="margin:0">💸 Merchant Payouts</h1>
    <?php if ($ready): ?><a class="btn btn-outline" href="?export=csv">⬇ Download CSV</a><?php endif; ?>
  </div>
  <?php padmin_store_tabs('store-payouts.php'); ?>
  <?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
  <?php if (!$ready): padmin_store_not_ready(); else: ?>
  <div class="stat-grid">
    <div class="stat-card"><div class="stat-icon">🧺</div><div class="stat-num" style="font-size:1.5rem"><?= store_money($tot['gross']) ?></div><div class="stat-label">Product sales (paid)</div></div>
    <div class="stat-card"><div class="stat-icon">🏦</div><div class="stat-num" style="font-size:1.5rem"><?= store_money($tot['comm']) ?></div><div class="stat-label">Retained by Exit 473</div></div>
    <div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-num" style="font-size:1.5rem"><?= store_money($tot['owed']) ?></div><div class="stat-label">Owed to merchants</div></div>
    <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-num" style="font-size:1.5rem"><?= store_money($tot['paid']) ?></div><div class="stat-label">Paid out</div></div>
  </div>
  <div class="panel">
    <h2 class="panel-title">By Merchant</h2>
    <?php if (!$report): ?><p style="color:#7A6565;text-align:center;padding:1.5rem">No stores yet.</p><?php else: ?>
    <div class="table-wrap"><table class="data-table">
      <thead><tr><th>Store</th><th>Commission</th><th>Items sold</th><th>Gross</th><th>Retained</th><th>Paid out</th><th>Owed</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($report as $s): ?>
        <tr>
          <td><strong><?= h($s['store_name']) ?></strong><br><small style="color:#7A6565"><?= h($s['store_type']) ?><?= $s['last_payout_at'] ? ' · last paid ' . h(store_time($s['last_payout_at'], 'M j, Y')) : '' ?></small></td>
          <td><?= $s['store_type'] === 'house' ? '100%' : store_pct($s['commission_percent']) ?></td>
          <td><?= (int)$s['items_sold'] ?></td>
          <td><?= store_money($s['gross_cents']) ?></td>
          <td><?= store_money($s['commission_cents']) ?></td>
          <td><?= store_money($s['paid_out_cents']) ?></td>
          <td><strong style="color:<?= $s['owed_cents'] > 0 ? '#B71C1C' : '#1B5E20' ?>"><?= store_money($s['owed_cents']) ?></strong></td>
          <td>
            <?php if ($s['owed_cents'] > 0): ?>
            <form method="post" style="display:flex;gap:.3rem;flex-wrap:wrap" onsubmit="return confirm('Record that <?= h(addslashes($s['store_name'])) ?> was paid <?= store_money($s['owed_cents']) ?>?')">
              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="mark_paid"><input type="hidden" name="store_id" value="<?= h($s['store_id']) ?>">
              <input type="text" name="note" placeholder="e.g. cash / bank ref" style="width:130px">
              <button class="btn btn-sm btn-primary" type="submit">Mark paid</button>
            </form>
            <?php else: ?><span style="color:#7A6565">—</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
    <p class="hint" style="margin-top:1rem">Commission and merchant share are fixed per item at purchase time. "Mark paid" covers every unpaid item from paid orders for that merchant. Pay the merchant outside the site (cash, bank transfer), then record it here.</p>
  </div>
  <div class="panel">
    <h2 class="panel-title">Payout History</h2>
    <?php if (!$payouts): ?><p style="color:#7A6565;text-align:center;padding:1rem">No payouts recorded yet.</p><?php else: ?>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Date</th><th>Store</th><th>Items</th><th>Amount</th><th>Note</th></tr></thead><tbody>
    <?php foreach ($payouts as $p): ?><tr><td><?= h(store_time($p['created_at'])) ?></td><td><?= h($p['stores']['name'] ?? '') ?></td><td><?= (int)$p['item_count'] ?></td><td><?= store_money($p['amount_cents']) ?></td><td><?= h($p['note'] ?? '') ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</main>
</body>
</html>
