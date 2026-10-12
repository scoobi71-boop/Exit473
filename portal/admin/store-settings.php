<?php
/** Store admin: delivery fee, pickup instructions. */
require_once __DIR__ . '/../../admin/auth.php';
require_once __DIR__ . '/../supabase.php';
require_once __DIR__ . '/../store_lib.php';
require_once __DIR__ . '/_chrome.php';
padmin_require(['site_owner', 'content_manager']);

$ready = store_ready();
$msg = ''; $err = '';
if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $fee = round((float)($_POST['delivery_fee'] ?? -1) * 100);
    $pick = !empty($_POST['pickup_enabled']); $del = !empty($_POST['delivery_enabled']);
    if ($fee < 0) $err = 'Delivery fee can\'t be negative.';
    elseif (!$pick && !$del) $err = 'Enable at least one of pickup or delivery.';
    else {
        $r = supabase_table_update('store_settings', 'id=eq.1', ['delivery_fee_cents' => (int)$fee, 'pickup_enabled' => $pick, 'delivery_enabled' => $del,
            'pickup_instructions' => store_substr(trim($_POST['pickup_instructions'] ?? ''), 0, 500), 'delivery_note' => store_substr(trim($_POST['delivery_note'] ?? ''), 0, 500), 'updated_at' => gmdate('c')], null, true);
        $msg = $r['status'] === 200 ? 'Settings saved.' : ''; if (!$msg) $err = 'Could not save (HTTP ' . $r['status'] . ').';
    }
}
$s = [];
if ($ready) { $r = supabase_table_select('store_settings', 'id=eq.1&select=*', null, true); $s = $r['data'][0] ?? []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Store Settings — Exit 473 Admin</title>
<meta name="robots" content="noindex">
<?php admin_look_head(); ?>
</head>
<body>
<?php padmin_nav('store'); ?>
<main class="wrap">
  <h1 class="page-title">⚙️ Store Settings</h1>
  <?php padmin_store_tabs('store-settings.php'); ?>
  <?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-error"><?= h($err) ?></div><?php endif; ?>
  <?php if (!$ready): padmin_store_not_ready(); else: ?>
  <div class="panel">
    <h2 class="panel-title">Pickup &amp; Delivery</h2>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <div class="form-grid">
        <div class="form-row"><label class="checkbox-label"><input type="checkbox" name="pickup_enabled" value="1" <?= !empty($s['pickup_enabled']) ? 'checked' : '' ?>> Offer pickup (free)</label></div>
        <div class="form-row"><label class="checkbox-label"><input type="checkbox" name="delivery_enabled" value="1" <?= !empty($s['delivery_enabled']) ? 'checked' : '' ?>> Offer delivery</label></div>
        <div class="form-row"><label>Delivery fee (USD, flat per order)</label><input type="number" name="delivery_fee" min="0" step="0.01" value="<?= h(number_format(($s['delivery_fee_cents'] ?? 0) / 100, 2, '.', '')) ?>"></div>
        <div class="form-row"></div>
        <div class="form-row" style="grid-column:1/-1"><label>Pickup instructions</label><textarea name="pickup_instructions" rows="3"><?= h($s['pickup_instructions'] ?? '') ?></textarea><span class="hint">Shown at checkout, on the receipt and in the "ready for pickup" email.</span></div>
        <div class="form-row" style="grid-column:1/-1"><label>Delivery note</label><textarea name="delivery_note" rows="2"><?= h($s['delivery_note'] ?? '') ?></textarea></div>
      </div>
      <div style="margin-top:1.25rem"><button class="btn btn-primary" type="submit">💾 Save Settings</button></div>
    </form>
  </div>
  <?php endif; ?>
</main>
</body>
</html>
