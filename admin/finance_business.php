<?php
require_once 'auth.php';
require_role(['site_owner', 'finance']);
require_once __DIR__ . '/../portal/finance_helpers.php';

$slug = $_GET['slug'] ?? '';
if (!$slug) { header('Location: finance.php?tab=businesses'); exit; }

$bizResp = supabase_table_select('finance_businesses', 'slug=eq.' . urlencode($slug) . '&select=*', null, true);
$biz = $bizResp['data'][0] ?? null;
if (!$biz) { header('Location: finance.php?tab=businesses'); exit; }

$adminId = $_SESSION['admin_user_id'] ?? null;
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'apply_charge') {
        $feeTypeId = $_POST['fee_type_id'] ?? '';
        $quantity = (float)($_POST['quantity'] ?? 1);
        $variableDollars = (float)($_POST['variable_dollars'] ?? 0);
        $dueDate = $_POST['due_date'] ?? '';
        $description = trim($_POST['description'] ?? '');

        $ftResp = supabase_table_select('fee_types', 'id=eq.' . urlencode($feeTypeId) . '&select=*', null, true);
        $ft = $ftResp['data'][0] ?? null;

        if (!$ft) {
            $msg = 'error:Choose a fee type.';
        } elseif ($quantity <= 0) {
            $msg = 'error:Quantity must be greater than zero.';
        } elseif (!$dueDate) {
            $msg = 'error:Choose a due date.';
        } else {
            $baseCents = (int) round($ft['rate_cents'] * $quantity);
            $variableCents = $ft['has_variable_component'] ? (int) round($variableDollars * 100) : 0;
            supabase_table_insert('business_fee_charges', [
                'business_slug'         => $slug,
                'fee_type_id'           => $ft['id'],
                'period'                => null,
                'description'           => $description !== '' ? $description : $ft['name'],
                'quantity'              => $quantity,
                'base_amount_cents'     => $baseCents,
                'variable_amount_cents' => $variableCents,
                'currency'              => $ft['currency'],
                'due_date'              => $dueDate,
                'status'                => 'unpaid',
                'created_by_admin_id'   => $adminId,
            ], null, true);
            $msg = 'ok:Charge applied.';
        }
    } elseif ($action === 'waive_charge') {
        $chargeId = $_POST['charge_id'] ?? '';
        $reason = trim($_POST['waived_reason'] ?? '');
        if ($chargeId) {
            supabase_table_update('business_fee_charges', 'id=eq.' . urlencode($chargeId) . '&business_slug=eq.' . urlencode($slug), [
                'status' => 'waived', 'waived_reason' => $reason ?: null, 'updated_at' => date('c'),
            ], null, true);
            $msg = 'ok:Charge waived.';
        }
    } elseif ($action === 'void_charge') {
        $chargeId = $_POST['charge_id'] ?? '';
        if ($chargeId) {
            supabase_table_update('business_fee_charges', 'id=eq.' . urlencode($chargeId) . '&business_slug=eq.' . urlencode($slug), [
                'status' => 'void', 'updated_at' => date('c'),
            ], null, true);
            $msg = 'ok:Charge voided.';
        }
    } elseif ($action === 'record_manual_payment') {
        $chargeIds = $_POST['charge_ids'] ?? [];
        $method = $_POST['method'] ?? '';
        $reference = trim($_POST['reference'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (!$chargeIds) {
            $msg = 'error:Select at least one unpaid fee to record a payment for.';
        } elseif (!in_array($method, ['cash', 'bank_transfer', 'cheque', 'other'], true)) {
            $msg = 'error:Choose a payment method.';
        } else {
            $resp = supabase_rpc('record_manual_payment', [
                'p_business_slug' => $slug,
                'p_charge_ids'    => array_values($chargeIds),
                'p_method'        => $method,
                'p_reference'     => $reference ?: null,
                'p_notes'         => $notes ?: null,
                'p_admin_id'      => $adminId,
            ], null, true);
            $msg = $resp['status'] === 200
                ? 'ok:Payment recorded.'
                : 'error:' . ($resp['data']['message'] ?? 'Could not record the payment.');
        }
    } elseif ($action === 'update_license_terms') {
        $unitType = $_POST['unit_type'] ?? '';
        $operatingArea = (float)($_POST['operating_area_sqft'] ?? 0);
        $customerArea = (float)($_POST['customer_area_sqft'] ?? 0);
        $discountDollars = (float)($_POST['discount_dollars'] ?? 0);
        $discountRecurring = isset($_POST['discount_is_recurring']);

        if (!in_array($unitType, ['fixed', 'movable'], true)) {
            $msg = 'error:Choose a unit type.';
        } elseif ($operatingArea <= 0) {
            $msg = 'error:Enter the operating area in square feet.';
        } elseif ($discountDollars < 0) {
            $msg = 'error:Discount cannot be negative.';
        } else {
            $existing = supabase_table_select('business_license_terms', 'business_slug=eq.' . urlencode($slug) . '&select=business_slug', null, true);
            $payload = [
                'unit_type'            => $unitType,
                'operating_area_sqft'  => $operatingArea,
                'customer_area_sqft'   => $customerArea,
                'discount_cents'       => (int) round($discountDollars * 100),
                'discount_is_recurring' => $discountRecurring,
                'updated_at'           => date('c'),
            ];
            if ($existing['status'] === 200 && !empty($existing['data'])) {
                supabase_table_update('business_license_terms', 'business_slug=eq.' . urlencode($slug), $payload, null, true);
            } else {
                $payload['business_slug'] = $slug;
                supabase_table_insert('business_license_terms', $payload, null, true);
            }
            $msg = 'ok:License terms saved.';
        }
    }
}

$feeTypesResp = supabase_table_select('fee_types', 'active=eq.true&select=*&order=sort_order.asc', null, true);
$feeTypes = $feeTypesResp['data'] ?? [];
$feeTypesById = [];
foreach ($feeTypes as $ft) $feeTypesById[$ft['id']] = $ft;

$licenseTerms = supabase_table_select('business_license_terms', 'business_slug=eq.' . urlencode($slug) . '&select=*', null, true)['data'][0] ?? null;
$licenseRates = supabase_table_select('license_rates', 'select=*', null, true)['data'] ?? [];
$licenseRatesByType = [];
foreach ($licenseRates as $lr) $licenseRatesByType[$lr['unit_type']] = $lr;
$estimatedGrossLicenseCents = null;
$estimatedMonthlyLicenseCents = null;
$estimatedDiscountCents = 0;
if ($licenseTerms && isset($licenseRatesByType[$licenseTerms['unit_type']])) {
    $r = $licenseRatesByType[$licenseTerms['unit_type']];
    $estimatedGrossLicenseCents = (int) round(
        $licenseTerms['operating_area_sqft'] * $r['operating_rate_cents']
        + $licenseTerms['customer_area_sqft'] * $r['customer_rate_cents']
    );
    $estimatedDiscountCents = min((int)($licenseTerms['discount_cents'] ?? 0), $estimatedGrossLicenseCents);
    $estimatedMonthlyLicenseCents = $estimatedGrossLicenseCents - $estimatedDiscountCents;
}

$chargesResp = supabase_table_select('business_fee_charges', 'business_slug=eq.' . urlencode($slug) . '&select=*,fee_types(name,unit_label)&order=created_at.desc', null, true);
$charges = $chargesResp['data'] ?? [];

$outstanding = 0;
foreach ($charges as $c) if (in_array($c['status'], ['unpaid', 'pending'], true)) $outstanding += $c['amount_cents'];

[$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($biz['display_name']) ?> — Finance — Exit 473 Admin</title>
<?php include 'head_styles.php'; ?>
<style>
.table-wrap{overflow-x:auto}
.users-table{width:100%;table-layout:fixed;border-collapse:collapse;font-size:.88rem}
.users-table th{background:#1A0505;color:#F5C840;text-align:left;padding:.6rem .75rem;font-weight:600;font-size:.78rem;text-transform:uppercase;letter-spacing:.06em}
.users-table td{padding:.6rem .75rem;border-bottom:1px solid #EAE2E2;vertical-align:top;word-break:break-word;overflow-wrap:anywhere}
.users-table tr:hover td{background:#FFF9F7}
.actions-cell{white-space:nowrap;text-align:right}
.status-badge{display:inline-block;padding:.15rem .55rem;border-radius:99px;font-size:.72rem;font-weight:700;text-transform:uppercase}
.status-unpaid{background:#FFF0E0;color:#B8590B}
.status-pending{background:#E0EEFF;color:#1565C0}
.status-paid{background:#E6F4ED;color:#1B5E20}
.status-waived{background:#ECEFF1;color:#37474F}
.status-void{background:#F5F5F5;color:#9E9E9E}
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<main class="wrap">
  <p><a href="finance.php?tab=businesses">← All Businesses</a></p>
  <h1 class="page-title"><?= h($biz['display_name']) ?> <span style="font-size:.9rem;font-weight:400;color:#7A6565">(<?= h($slug) ?>)</span></h1>

  <?php if ($msg_text): ?>
    <div class="alert alert-<?= $msg_type ?>" style="max-width:720px"><?= h($msg_text) ?></div>
  <?php endif; ?>

  <div class="stat-grid" style="max-width:640px">
    <div class="stat-card">
      <div class="stat-num"><?= h(finance_money($outstanding, 'XCD')) ?></div>
      <div class="stat-label">Outstanding</div>
    </div>
    <a href="finance_statement.php?slug=<?= urlencode($slug) ?>&period=<?= date('Y-m') ?>" target="_blank" class="stat-card">
      <div style="font-size:1.6rem;margin-bottom:.3rem">📄</div>
      <div class="stat-label">Statement (<?= h(date('M Y')) ?>)</div>
    </a>
    <a href="/bizsite/index.php?biz=<?= urlencode($slug) ?>" target="_blank" class="stat-card">
      <div style="font-size:1.6rem;margin-bottom:.3rem">🌐</div>
      <div class="stat-label">View Site</div>
    </a>
  </div>

  <div class="panel">
    <h2 class="panel-title">Ledger</h2>
    <?php if ($charges): ?>
    <div class="table-wrap">
    <table class="users-table">
      <thead>
        <tr><th></th><th>Fee</th><th>Due Date</th><th>Amount</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($charges as $c): ?>
        <?php $payable = in_array($c['status'], ['unpaid'], true); ?>
        <tr>
          <td><?php if ($payable): ?><input type="checkbox" name="charge_ids[]" value="<?= h($c['id']) ?>" form="pay-form"><?php endif; ?></td>
          <td><?= h($c['description'] ?: ($c['fee_types']['name'] ?? '')) ?></td>
          <td><?= h(date('M j, Y', strtotime($c['due_date']))) ?></td>
          <td><?= h(finance_money($c['amount_cents'], $c['currency'])) ?></td>
          <td><span class="status-badge status-<?= h($c['status']) ?>"><?= h($c['status']) ?></span></td>
          <td class="actions-cell">
            <?php if (in_array($c['status'], ['unpaid', 'pending'], true)): ?>
              <form method="post" style="display:inline" onsubmit="return promptWaive(this)">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="waive_charge">
                <input type="hidden" name="charge_id" value="<?= h($c['id']) ?>">
                <input type="hidden" name="waived_reason" class="waive-reason-input">
                <button class="btn btn-outline btn-sm" type="submit">Waive</button>
              </form>
              <form method="post" style="display:inline" onsubmit="return confirm('Void this charge? This marks it as a mistake, not a payment or forgiveness.')">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="void_charge">
                <input type="hidden" name="charge_id" value="<?= h($c['id']) ?>">
                <button class="btn btn-danger btn-sm" type="submit">Void</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php else: ?><p style="color:#7A6565">No charges yet.</p><?php endif; ?>
  </div>

  <div class="panel" style="max-width:480px">
    <h2 class="panel-title">Record a Manual Payment</h2>
    <p style="color:#7A6565;font-size:.9rem;margin-bottom:1rem">Check one or more unpaid fees above, then fill this in.</p>
    <form method="post" id="pay-form">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="record_manual_payment">
      <div class="form-row">
        <label>Payment Method</label>
        <select name="method" required>
          <option value="cash">Cash</option>
          <option value="bank_transfer">Bank Transfer</option>
          <option value="cheque">Cheque</option>
          <option value="other">Other</option>
        </select>
      </div>
      <div class="form-row">
        <label>Reference</label>
        <input type="text" name="reference" placeholder="Cheque #, transfer ref, receipt #...">
      </div>
      <div class="form-row">
        <label>Notes</label>
        <textarea name="notes" rows="2" placeholder="Optional details"></textarea>
      </div>
      <button class="btn btn-primary" type="submit" style="margin-top:.75rem">Record Payment</button>
    </form>
  </div>

  <div class="panel" style="max-width:480px">
    <h2 class="panel-title">License Terms</h2>
    <p style="color:#7A6565;font-size:.9rem;margin-bottom:1rem">Drives the monthly License Fee for this business — area × the rate for its unit type (edit rates on the <a href="finance.php?tab=schedule">Fee Schedule</a> page).</p>
    <?php if ($estimatedMonthlyLicenseCents !== null): ?>
      <p style="margin-bottom:1rem">
        <strong>Current monthly License Fee: <?= h(finance_money($estimatedMonthlyLicenseCents, 'XCD')) ?></strong>
        <?php if ($estimatedDiscountCents > 0): ?>
          <br><span style="color:#7A6565;font-size:.85rem"><?= h(finance_money($estimatedGrossLicenseCents, 'XCD')) ?> less <?= h(finance_money($estimatedDiscountCents, 'XCD')) ?> <?= ($licenseTerms['discount_is_recurring'] ?? true) ? 'recurring discount' : 'one-time discount (applies to the next generated charge only)' ?></span>
        <?php endif; ?>
      </p>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="update_license_terms">
      <div class="form-grid">
        <div class="form-row" style="grid-column:1/-1">
          <label>Unit Type</label>
          <select name="unit_type" required>
            <option value="">Choose...</option>
            <option value="fixed" <?= ($licenseTerms['unit_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed (Licensor-owned)</option>
            <option value="movable" <?= ($licenseTerms['unit_type'] ?? '') === 'movable' ? 'selected' : '' ?>>Movable (Licensee-owned)</option>
          </select>
        </div>
        <div class="form-row">
          <label>Operating Area (sq ft)</label>
          <input type="number" step="0.01" min="0" name="operating_area_sqft" required value="<?= h($licenseTerms['operating_area_sqft'] ?? '') ?>">
        </div>
        <div class="form-row">
          <label>Customer Area (sq ft)</label>
          <input type="number" step="0.01" min="0" name="customer_area_sqft" value="<?= h($licenseTerms['customer_area_sqft'] ?? '0') ?>">
        </div>
        <div class="form-row">
          <label>Discount (EC$/month)</label>
          <input type="number" step="0.01" min="0" name="discount_dollars" value="<?= h(number_format(($licenseTerms['discount_cents'] ?? 0) / 100, 2, '.', '')) ?>">
        </div>
        <div class="form-row" style="align-self:end">
          <label style="display:flex;align-items:center;gap:.4rem;font-weight:400">
            <input type="checkbox" name="discount_is_recurring" <?= ($licenseTerms['discount_is_recurring'] ?? true) ? 'checked' : '' ?>>
            Recurring (uncheck to apply once, then reset to $0)
          </label>
        </div>
      </div>
      <button class="btn btn-primary" type="submit" style="margin-top:.75rem">Save License Terms</button>
    </form>
  </div>

  <div class="panel" style="max-width:560px">
    <h2 class="panel-title">Apply a Charge</h2>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="apply_charge">
      <div class="form-grid">
        <div class="form-row" style="grid-column:1/-1">
          <label>Fee Type</label>
          <select name="fee_type_id" id="fee-type-select" required onchange="onFeeTypeChange()">
            <option value="">Choose a fee...</option>
            <?php foreach ($feeTypes as $ft): ?>
              <option value="<?= h($ft['id']) ?>"
                data-unit-type="<?= h($ft['unit_type']) ?>"
                data-unit-label="<?= h($ft['unit_label'] ?? '') ?>"
                data-has-variable="<?= $ft['has_variable_component'] ? '1' : '0' ?>"
                data-variable-label="<?= h($ft['variable_label'] ?? '') ?>"
                data-rate="<?= h(number_format($ft['rate_cents'] / 100, 2, '.', '')) ?>">
                <?= h($ft['name']) ?> (<?= h(finance_money($ft['rate_cents'], $ft['currency'])) ?><?= $ft['unit_type'] === 'per_unit' ? '/' . h($ft['unit_label']) : '' ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row" id="quantity-row" style="display:none">
          <label>Quantity (<span id="unit-label-text"></span>)</label>
          <input type="number" step="0.01" min="0.01" name="quantity" id="quantity-input" value="1">
        </div>
        <div class="form-row" id="variable-row" style="display:none">
          <label id="variable-label-text">Variable Amount (EC$)</label>
          <input type="number" step="0.01" min="0" name="variable_dollars" id="variable-input" value="0">
        </div>
        <div class="form-row">
          <label>Due Date</label>
          <input type="date" name="due_date" required value="<?= h(date('Y-m-d', strtotime('+14 days'))) ?>">
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label>Description (optional)</label>
          <input type="text" name="description" placeholder="e.g. 3 days late in March">
        </div>
      </div>
      <button class="btn btn-primary" type="submit" style="margin-top:1rem">Apply Charge</button>
    </form>
  </div>
</main>
<script>
function onFeeTypeChange() {
  const sel = document.getElementById('fee-type-select');
  const opt = sel.options[sel.selectedIndex];
  const unitType = opt.dataset.unitType;
  const hasVariable = opt.dataset.hasVariable === '1';

  const qtyRow = document.getElementById('quantity-row');
  qtyRow.style.display = unitType === 'per_unit' ? '' : 'none';
  document.getElementById('unit-label-text').textContent = opt.dataset.unitLabel || '';

  const varRow = document.getElementById('variable-row');
  varRow.style.display = hasVariable ? '' : 'none';
  document.getElementById('variable-label-text').textContent = (opt.dataset.variableLabel || 'Variable Amount') + ' (EC$)';
}

function promptWaive(form) {
  const reason = prompt('Reason for waiving this charge (optional):', '');
  if (reason === null) return false; // cancelled
  form.querySelector('.waive-reason-input').value = reason;
  return confirm('Waive this charge? This marks it as forgiven, not paid.');
}
</script>
</body>
</html>
