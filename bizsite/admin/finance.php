<?php
require_once 'auth.php';
require_auth();
require_once __DIR__ . '/../../portal/finance_helpers.php';

$slug = current_slug();
$info = read_info($slug);
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_stale_pending') {
    check_csrf();
    $paymentId = $_POST['payment_id'] ?? '';
    if ($paymentId) {
        $resp = supabase_rpc('cancel_pending_payment', ['p_payment_id' => $paymentId, 'p_business_slug' => $slug], null, true);
        $msg = ($resp['status'] === 200) ? 'ok:Cancelled — you can select those fees again below.' : 'error:Could not cancel that payment.';
    }
}

if (!$msg && isset($_GET['paid'])) {
    $msg = 'ok:Thanks! If your payment went through, it will show as paid here shortly.';
} elseif (!$msg && isset($_GET['cancelled'])) {
    $msg = 'error:Payment cancelled — nothing was charged.';
} elseif (!$msg && isset($_GET['error'])) {
    $msg = 'error:Could not start payment. Please try again.';
}

$chargesResp = supabase_table_select(
    'business_fee_charges',
    'business_slug=eq.' . urlencode($slug) . '&status=in.(unpaid,pending)&select=*,fee_types(name)&order=due_date.asc',
    null, true
);
$outstanding = $chargesResp['data'] ?? [];

$pendingPayments = supabase_table_select(
    'business_fee_payments',
    'business_slug=eq.' . urlencode($slug) . '&status=eq.pending&select=id,created_at',
    null, true
)['data'] ?? [];
$stalePaymentIds = [];
foreach ($pendingPayments as $p) {
    if ((time() - strtotime($p['created_at'])) / 60 > 30) $stalePaymentIds[] = $p['id'];
}
$chargeToPayment = [];
if ($pendingPayments) {
    $paymentIdList = implode(',', array_column($pendingPayments, 'id'));
    $pcResp = supabase_table_select('business_fee_payment_charges', 'payment_id=in.(' . $paymentIdList . ')&select=payment_id,charge_id', null, true);
    foreach ($pcResp['data'] ?? [] as $pc) $chargeToPayment[$pc['charge_id']] = $pc['payment_id'];
}

$historyResp = supabase_table_select(
    'business_fee_charges',
    'business_slug=eq.' . urlencode($slug) . '&status=eq.paid&select=*,fee_types(name)&order=updated_at.desc&limit=20',
    null, true
);
$paidHistory = $historyResp['data'] ?? [];

$totalOutstanding = array_sum(array_column($outstanding, 'amount_cents'));
$nextDue = null;
foreach ($outstanding as $c) {
    if ($c['status'] === 'unpaid') { $nextDue = $c['due_date']; break; }
}
$paidThisYear = 0;
foreach ($paidHistory as $c) {
    if (date('Y', strtotime($c['updated_at'])) === date('Y')) $paidThisYear++;
}

$xcdRate = (float) finance_get_setting('xcd_to_usd_rate', 2.7169);

[$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Finance — <?= h($info['name'] ?? 'Admin') ?></title>
<?php include 'head_styles.php'; ?>
<style>
.fee-table input[type=checkbox]{width:16px;height:16px;cursor:pointer;accent-color:#2D6A4F}
.pay-bar{position:sticky;bottom:0;background:#fff;border-top:1px solid #EEF2F5;padding:1rem;margin-top:1rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;border-radius:0 0 10px 10px}
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<main class="wrap">
  <h1 class="page-title">Finance</h1>

  <?php if ($msg_text): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= h($msg_text) ?></div>
  <?php endif; ?>

  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-num"><?= h(finance_money($totalOutstanding, 'XCD')) ?></div>
      <div class="stat-label">Total Outstanding</div>
    </div>
    <div class="stat-card">
      <div class="stat-num" style="font-size:1.3rem"><?= $nextDue ? h(date('M j, Y', strtotime($nextDue))) : '—' ?></div>
      <div class="stat-label">Next Due Date</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $paidThisYear ?></div>
      <div class="stat-label">Payments This Year</div>
    </div>
  </div>

  <div class="panel">
    <h2 class="panel-title">Outstanding Fees</h2>
    <?php if ($outstanding): ?>
    <form method="post" action="finance_pay.php" id="pay-form">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <div class="table-wrap">
      <table class="data-table fee-table">
        <thead>
          <tr>
            <th><input type="checkbox" id="select-all"></th>
            <th>Fee</th><th>Due Date</th><th>Amount</th><th>Status</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($outstanding as $c): ?>
          <?php
            $isStalePending = $c['status'] === 'pending' && in_array($chargeToPayment[$c['id']] ?? null, $stalePaymentIds, true);
          ?>
          <tr>
            <td>
              <?php if ($c['status'] === 'unpaid'): ?>
                <input type="checkbox" class="fee-check" name="charge_ids[]" value="<?= h($c['id']) ?>" data-amount="<?= (int)$c['amount_cents'] ?>">
              <?php endif; ?>
            </td>
            <td><?= h($c['description'] ?: ($c['fee_types']['name'] ?? '')) ?></td>
            <td><?= h(date('M j, Y', strtotime($c['due_date']))) ?></td>
            <td><?= h(finance_money($c['amount_cents'], $c['currency'])) ?></td>
            <td>
              <?php if ($c['status'] === 'unpaid'): ?>
                Unpaid
              <?php elseif ($isStalePending): ?>
                Payment in progress
                <form method="post" style="display:inline" onsubmit="return confirm('Cancel this payment attempt so you can try again?')">
                  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="action" value="cancel_stale_pending">
                  <input type="hidden" name="payment_id" value="<?= h($chargeToPayment[$c['id']]) ?>">
                  <button type="submit" class="btn btn-outline btn-sm" style="margin-left:.4rem">Cancel &amp; retry</button>
                </form>
              <?php else: ?>
                Payment in progress
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <div class="pay-bar">
        <span>Selected: <strong id="selected-total">EC$0.00</strong> <span style="color:#718096;font-size:.85rem">(≈ <span id="selected-total-usd">US$0.00</span> charged via Stripe)</span></span>
        <button type="submit" class="btn btn-primary" id="pay-btn" disabled>Pay Selected</button>
      </div>
    </form>
    <?php else: ?>
      <div class="empty-state">No outstanding fees right now. 🎉</div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2 class="panel-title">Payment History</h2>
    <?php if ($paidHistory): ?>
    <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Fee</th><th>Paid On</th><th>Amount</th></tr></thead>
      <tbody>
      <?php foreach ($paidHistory as $c): ?>
        <tr>
          <td><?= h($c['description'] ?: ($c['fee_types']['name'] ?? '')) ?></td>
          <td><?= h(date('M j, Y', strtotime($c['updated_at']))) ?></td>
          <td><?= h(finance_money($c['amount_cents'], $c['currency'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php else: ?><p style="color:#718096">No payments yet.</p><?php endif; ?>
  </div>
</main>
<script>
(function() {
  const XCD_RATE = <?= json_encode($xcdRate) ?>;
  const checks = Array.from(document.querySelectorAll('.fee-check'));
  const selectAll = document.getElementById('select-all');
  const totalEl = document.getElementById('selected-total');
  const totalUsdEl = document.getElementById('selected-total-usd');
  const payBtn = document.getElementById('pay-btn');

  function updateTotal() {
    let cents = 0;
    let anyChecked = false;
    checks.forEach(c => { if (c.checked) { cents += parseInt(c.dataset.amount, 10); anyChecked = true; } });
    totalEl.textContent = 'EC$' + (cents / 100).toFixed(2);
    totalUsdEl.textContent = 'US$' + (cents / 100 / XCD_RATE).toFixed(2);
    payBtn.disabled = !anyChecked;
  }

  if (selectAll) {
    selectAll.addEventListener('change', () => {
      checks.forEach(c => { c.checked = selectAll.checked; });
      updateTotal();
    });
  }
  checks.forEach(c => c.addEventListener('change', updateTotal));
  updateTotal();
})();
</script>
</body>
</html>
