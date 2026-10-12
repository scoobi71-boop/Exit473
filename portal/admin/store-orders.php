<?php
/** Store admin: orders with fulfillment status (new / ready / fulfilled / refunded). */
require_once __DIR__ . '/../../admin/auth.php';
require_once __DIR__ . '/../supabase.php';
require_once __DIR__ . '/../store_lib.php';
require_once __DIR__ . '/_chrome.php';
padmin_require(['site_owner', 'content_manager']);

$ready = store_ready();
$msg = '';
if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = $_POST['id'] ?? '';
    $fs = $_POST['fulfillment_status'] ?? '';
    if (in_array($fs, ['new', 'ready', 'fulfilled'], true) && preg_match('/^[0-9a-f-]{36}$/i', $id)) {
        // Only paid orders move through fulfillment; refunds are set by the Stripe webhook.
        supabase_table_update('store_orders', 'id=eq.' . urlencode($id) . '&status=eq.paid', ['fulfillment_status' => $fs, 'updated_at' => gmdate('c')], null, true);
        $msg = 'Order ' . store_ref($id) . ' marked ' . $fs . '.';
        if ($fs === 'ready' && !empty($_POST['notify'])) {
            $o = store_load_order($id);
            if ($o) {
                $set = store_settings();
                $body = $o['fulfillment'] === 'pickup'
                    ? '<p>Good news! Your Exit 473 Shop order <strong>' . store_ref($o['id']) . '</strong> is ready for pickup.</p><p>' . htmlspecialchars($set['pickup_instructions']) . '</p>'
                    : '<p>Your Exit 473 Shop order <strong>' . store_ref($o['id']) . '</strong> is packed and on its way soon. We\'ll contact you to arrange delivery.</p>';
                $r = resend_send_email($o['buyer_email'], 'Your Exit 473 order ' . store_ref($o['id']) . ' is ready', '<div style="font-family:Arial,sans-serif;max-width:560px">' . $body . '</div>');
                $msg .= ($r['status'] ?? 0) >= 200 && ($r['status'] ?? 0) < 300 ? ' Buyer notified by email.' : ' (Email could not be sent.)';
            }
        }
    }
}

$filter = $_GET['f'] ?? 'open';
$orders = [];
if ($ready) {
    $q = 'select=*,store_order_items(name,store_name,quantity,line_total_cents)&order=created_at.desc&limit=200';
    if ($filter === 'open') $q .= '&status=eq.paid&fulfillment_status=in.(new,ready)';
    elseif ($filter === 'fulfilled') $q .= '&status=eq.paid&fulfillment_status=eq.fulfilled';
    elseif ($filter === 'refunded') $q .= '&status=eq.refunded';
    elseif ($filter === 'unpaid') $q .= '&status=in.(pending,expired)';
    else $q .= '&status=in.(paid,refunded)';
    $r = supabase_table_select('store_orders', $q, null, true);
    $orders = $r['status'] === 200 ? $r['data'] : [];
    $all = supabase_table_select('store_orders', 'select=status,fulfillment_status,total_cents&status=eq.paid', null, true);
    $paid = $all['status'] === 200 ? $all['data'] : [];
}
$stat = ['new' => 0, 'ready' => 0, 'gross' => 0];
foreach ($paid ?? [] as $o) { if (isset($stat[$o['fulfillment_status']])) $stat[$o['fulfillment_status']]++; $stat['gross'] += $o['total_cents']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Store Orders — Exit 473 Admin</title>
<meta name="robots" content="noindex">
<?php admin_look_head(); ?>
<style>.fs-new{background:#FFF8E1;color:#8D6E00;border:1px solid #FFE082}.fs-ready{background:#E0EEFF;color:#1565C0;border:1px solid #BBDEFB}.fs-fulfilled{background:#E8F5E9;color:#1B5E20;border:1px solid #A5D6A7}.fs-refunded{background:#FFEBEE;color:#B71C1C;border:1px solid #FFCDD2}</style>
</head>
<body>
<?php padmin_nav('store'); ?>
<main class="wrap">
  <h1 class="page-title">🛍️ Store Orders</h1>
  <?php padmin_store_tabs('store-orders.php'); ?>
  <?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
  <?php if (!$ready): padmin_store_not_ready(); else: ?>
  <div class="stat-grid">
    <div class="stat-card"><div class="stat-icon">🆕</div><div class="stat-num"><?= $stat['new'] ?></div><div class="stat-label">New (to prepare)</div></div>
    <div class="stat-card"><div class="stat-icon">📦</div><div class="stat-num"><?= $stat['ready'] ?></div><div class="stat-label">Ready</div></div>
    <div class="stat-card"><div class="stat-icon">🧾</div><div class="stat-num"><?= count($paid) ?></div><div class="stat-label">Paid orders</div></div>
    <div class="stat-card"><div class="stat-icon">💵</div><div class="stat-num" style="font-size:1.5rem"><?= store_money($stat['gross']) ?></div><div class="stat-label">Gross (incl. delivery)</div></div>
  </div>
  <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:1rem">
    <?php foreach (['open' => 'Open', 'fulfilled' => 'Fulfilled', 'refunded' => 'Refunded', 'all' => 'All paid', 'unpaid' => 'Unpaid / abandoned'] as $k => $l): ?>
      <a class="btn btn-sm <?= $filter === $k ? 'btn-primary' : 'btn-outline' ?>" href="?f=<?= $k ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>
  <div class="panel">
    <h2 class="panel-title"><?= count($orders) ?> Order<?= count($orders) === 1 ? '' : 's' ?></h2>
    <?php if (!$orders): ?><p style="color:#7A6565;text-align:center;padding:1.5rem">No orders here.</p><?php else: ?>
    <div class="table-wrap"><table class="data-table">
      <thead><tr><th>Order</th><th>Buyer</th><th>Items</th><th>Fulfillment</th><th>Total</th><th>Status</th><th>Update</th></tr></thead>
      <tbody>
      <?php foreach ($orders as $o): $fs = $o['status'] === 'refunded' ? 'refunded' : $o['fulfillment_status']; ?>
        <tr>
          <td style="white-space:nowrap"><strong><?= h(store_ref($o['id'])) ?></strong><br><small style="color:#7A6565"><?= h(store_time($o['paid_at'] ?: $o['created_at'], 'M j, g:i A')) ?></small></td>
          <td><?= h($o['buyer_name'] ?? '') ?><br><small><?= h($o['buyer_email']) ?><?= $o['buyer_phone'] ? '<br>' . h($o['buyer_phone']) : '' ?></small></td>
          <td><?php foreach ($o['store_order_items'] as $it): ?><div><?= h($it['name']) ?> × <?= (int)$it['quantity'] ?> <small style="color:#7A6565">(<?= h($it['store_name']) ?>)</small></div><?php endforeach; ?>
            <?php if (!empty($o['admin_note'])): ?><div style="color:#B71C1C;font-size:.8rem">⚠ <?= h($o['admin_note']) ?></div><?php endif; ?></td>
          <td><?= $o['fulfillment'] === 'delivery' ? '🚚 Delivery<br><small>' . nl2br(h($o['delivery_address'] ?? '')) . '</small>' : '🏪 Pickup' ?></td>
          <td style="white-space:nowrap"><?= store_money($o['total_cents']) ?></td>
          <td><span class="status-pill fs-<?= h($fs) ?>"><?= h($o['status'] === 'paid' ? $fs : $o['status']) ?></span></td>
          <td>
            <?php if ($o['status'] === 'paid'): ?>
            <form method="post" style="display:flex;flex-direction:column;gap:.3rem">
              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= h($o['id']) ?>">
              <select name="fulfillment_status" style="padding:.35rem;border:1.5px solid #EAE2E2;border-radius:6px">
                <?php foreach (['new', 'ready', 'fulfilled'] as $s): ?><option value="<?= $s ?>" <?= $o['fulfillment_status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
              </select>
              <label style="font-size:.75rem"><input type="checkbox" name="notify" value="1" checked> Email buyer when ready</label>
              <button class="btn btn-sm btn-outline" type="submit">Save</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
    <p class="hint" style="margin-top:1rem">Refunds are issued in the Stripe dashboard; a full refund marks the order refunded here and returns unfulfilled stock automatically.</p>
  </div>
  <?php endif; ?>
</main>
</body>
</html>
