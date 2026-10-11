<?php
require_once 'auth.php';
require_role(['site_owner', 'finance']);
require_once __DIR__ . '/../portal/finance_helpers.php';
require_once __DIR__ . '/../portal/resend.php';

define('BIZ_DATA_ROOT', realpath(__DIR__ . '/../bizsite/data') . DIRECTORY_SEPARATOR);

function finance_business_name_from_info($slug) {
    $file = BIZ_DATA_ROOT . $slug . DIRECTORY_SEPARATOR . 'info.csv';
    if (!file_exists($file)) return ucwords(str_replace('-', ' ', $slug));
    $fh = fopen($file, 'r');
    if (!$fh) return ucwords(str_replace('-', ' ', $slug));
    while (($row = fgetcsv($fh)) !== false) {
        $k = trim(ltrim($row[0] ?? '', "\xEF\xBB\xBF"));
        if ($k === 'name' && !empty($row[1])) { fclose($fh); return $row[1]; }
    }
    fclose($fh);
    return ucwords(str_replace('-', ' ', $slug));
}

$tab = $_GET['tab'] ?? 'schedule';
if (!in_array($tab, ['schedule', 'businesses', 'settings', 'reminders'], true)) $tab = 'schedule';
$msg = '';
$adminId = $_SESSION['admin_user_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_fee_type') {
        $id = $_POST['id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $unitType = $_POST['unit_type'] ?? 'flat';
        $unitLabel = trim($_POST['unit_label'] ?? '');
        $rateDollars = (float)($_POST['rate_dollars'] ?? 0);
        $hasVariable = isset($_POST['has_variable_component']);
        $variableLabel = trim($_POST['variable_label'] ?? '');
        $isRecurring = isset($_POST['is_recurring']);
        $active = isset($_POST['active']);

        $existingFt = supabase_table_select('fee_types', 'id=eq.' . urlencode($id) . '&select=pricing_mode', null, true)['data'][0] ?? null;
        $isLicenseArea = ($existingFt['pricing_mode'] ?? 'standard') === 'license_area';

        if (!$id || $name === '') {
            $msg = 'error:Enter a name.';
        } elseif (!$isLicenseArea && $unitType === 'per_unit' && $unitLabel === '') {
            $msg = 'error:Enter a unit label (e.g. "day") for a per-unit fee.';
        } elseif (!$isLicenseArea && $hasVariable && $variableLabel === '') {
            $msg = 'error:Enter a label for the variable component.';
        } else {
            $update = [
                'name'         => $name,
                'is_recurring' => $isRecurring,
                'active'       => $active,
                'updated_at'   => date('c'),
            ];
            // License Fee's rate/unit/variable fields are computed per-business
            // from license_rates + business_license_terms, not editable here.
            if (!$isLicenseArea) {
                $update['unit_type'] = $unitType;
                $update['unit_label'] = $unitType === 'per_unit' ? $unitLabel : null;
                $update['rate_cents'] = (int) round($rateDollars * 100);
                $update['has_variable_component'] = $hasVariable;
                $update['variable_label'] = $hasVariable ? $variableLabel : null;
            }
            supabase_table_update('fee_types', 'id=eq.' . urlencode($id), $update, null, true);
            $msg = 'ok:Updated ' . $name . '.';
        }
    } elseif ($action === 'update_license_rate') {
        $unitType = $_POST['unit_type'] ?? '';
        $operatingDollars = (float)($_POST['operating_rate_dollars'] ?? 0);
        $customerDollars = (float)($_POST['customer_rate_dollars'] ?? 0);
        if (!in_array($unitType, ['fixed', 'movable'], true)) {
            $msg = 'error:Invalid unit type.';
        } else {
            supabase_table_update('license_rates', 'unit_type=eq.' . urlencode($unitType), [
                'operating_rate_cents' => (int) round($operatingDollars * 100),
                'customer_rate_cents'  => (int) round($customerDollars * 100),
                'updated_at'           => date('c'),
            ], null, true);
            $msg = 'ok:Updated ' . ucfirst($unitType) . ' rates.';
        }
    } elseif ($action === 'generate_monthly') {
        $period = date('Y-m-01');
        $resp1 = supabase_rpc('generate_monthly_recurring_charges', ['p_period' => $period, 'p_admin_id' => $adminId], null, true);
        $resp2 = supabase_rpc('generate_monthly_license_charges', ['p_period' => $period, 'p_admin_id' => $adminId], null, true);
        if ($resp1['status'] === 200 && $resp2['status'] === 200) {
            $count = (int)$resp1['data'] + (int)$resp2['data'];
            $msg = $count > 0
                ? "ok:Generated {$count} new charge" . ($count === 1 ? '' : 's') . ' for ' . date('F Y') . '.'
                : 'ok:No new charges — this month\'s recurring fees were already generated.';
        } else {
            $msg = 'error:Could not generate charges. Please try again.';
        }
    } elseif ($action === 'sync_businesses') {
        $existing = supabase_table_select('finance_businesses', 'select=slug', null, true);
        $existingSlugs = [];
        if ($existing['status'] === 200) foreach ($existing['data'] as $r) $existingSlugs[] = $r['slug'];

        $found = [];
        foreach (glob(BIZ_DATA_ROOT . '*', GLOB_ONLYDIR) as $dir) {
            $slug = basename($dir);
            if ($slug === 'demo') continue;
            $found[] = $slug;
            $name = finance_business_name_from_info($slug);
            if (in_array($slug, $existingSlugs, true)) {
                supabase_table_update('finance_businesses', 'slug=eq.' . urlencode($slug), [
                    'display_name' => $name, 'updated_at' => date('c'),
                ], null, true);
            } else {
                supabase_table_insert('finance_businesses', ['slug' => $slug, 'display_name' => $name, 'status' => 'active'], null, true);
            }
        }
        $newCount = count(array_diff($found, $existingSlugs));
        $msg = "ok:Synced " . count($found) . " business" . (count($found) === 1 ? '' : 'es') . " ({$newCount} new).";
    } elseif ($action === 'update_business_status') {
        $slug = $_POST['slug'] ?? '';
        $status = $_POST['status'] ?? '';
        if ($slug && in_array($status, ['active', 'inactive'], true)) {
            supabase_table_update('finance_businesses', 'slug=eq.' . urlencode($slug), [
                'status' => $status, 'updated_at' => date('c'),
            ], null, true);
            $msg = 'ok:Updated status.';
        }
    } elseif ($action === 'update_setting') {
        $rate = (float)($_POST['xcd_to_usd_rate'] ?? 0);
        if ($rate <= 0) {
            $msg = 'error:Enter a valid exchange rate.';
        } else {
            finance_set_setting('xcd_to_usd_rate', $rate);
            $msg = 'ok:Exchange rate updated.';
        }
    } elseif ($action === 'send_reminder') {
        $scope = $_POST['scope'] ?? 'all';
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $selected = $_POST['slugs'] ?? [];

        if ($subject === '' || $message === '') {
            $msg = 'error:Enter a subject and message.';
        } else {
            $bizResp = supabase_table_select('finance_businesses', 'status=eq.active&select=slug,display_name', null, true);
            $allActive = $bizResp['data'] ?? [];
            $targets = ($scope === 'selected')
                ? array_values(array_filter($allActive, fn($b) => in_array($b['slug'], $selected, true)))
                : $allActive;

            if (!$targets) {
                $msg = 'error:No businesses matched.';
            } else {
                $chargesResp = supabase_table_select('business_fee_charges', 'status=in.(unpaid,pending)&select=business_slug,amount_cents', null, true);
                $outstandingBySlug = [];
                foreach ($chargesResp['data'] ?? [] as $c) {
                    $outstandingBySlug[$c['business_slug']] = ($outstandingBySlug[$c['business_slug']] ?? 0) + $c['amount_cents'];
                }

                $reminderInsert = supabase_table_insert('fee_reminders', [
                    'sent_by_admin_id' => $adminId,
                    'subject'          => $subject,
                    'message'          => $message,
                    'recipient_scope'  => $scope === 'selected' ? 'selected' : 'all',
                ], null, true);
                $reminderId = $reminderInsert['data'][0]['id'] ?? null;

                $sentCount = 0;
                $failCount = 0;
                foreach ($targets as $biz) {
                    $slug = $biz['slug'];
                    $outstandingCents = $outstandingBySlug[$slug] ?? 0;
                    $profileResp = supabase_table_select('biz_profiles', 'slug=eq.' . urlencode($slug) . '&select=email', null, true);
                    $email = $profileResp['data'][0]['email'] ?? null;

                    if (!$email) {
                        supabase_table_insert('fee_reminder_recipients', [
                            'reminder_id'                => $reminderId,
                            'business_slug'               => $slug,
                            'email'                       => '',
                            'status'                      => 'failed',
                            'error'                       => 'No login email on file',
                            'outstanding_cents_snapshot'  => $outstandingCents,
                        ], null, true);
                        $failCount++;
                        continue;
                    }

                    $html = '<p>' . nl2br(h($message)) . '</p>'
                          . '<p>Outstanding balance: <strong>' . h(finance_money($outstandingCents, 'XCD')) . '</strong></p>';
                    $sendResp = finance_send_reminder_email($email, $subject, $html);
                    $ok = ($sendResp['status'] >= 200 && $sendResp['status'] < 300);

                    supabase_table_insert('fee_reminder_recipients', [
                        'reminder_id'                => $reminderId,
                        'business_slug'               => $slug,
                        'email'                       => $email,
                        'status'                      => $ok ? 'sent' : 'failed',
                        'error'                       => $ok ? null : ($sendResp['error'] ?: 'Send failed'),
                        'outstanding_cents_snapshot'  => $outstandingCents,
                    ], null, true);
                    $ok ? $sentCount++ : $failCount++;
                }
                $msg = "ok:Sent {$sentCount}, failed {$failCount}.";
            }
        }
    }
}

$feeTypes = [];
$licenseRates = [];
$businesses = [];
$outstandingBySlug = [];
$xcdRate = '2.7169';
$reminders = [];
$reminderRecipients = [];
$adminNames = [];

if ($tab === 'schedule') {
    $resp = supabase_table_select('fee_types', 'select=*&order=sort_order.asc', null, true);
    $feeTypes = $resp['data'] ?? [];
    $lrResp = supabase_table_select('license_rates', 'select=*&order=unit_type.asc', null, true);
    $licenseRates = $lrResp['data'] ?? [];
} elseif ($tab === 'businesses') {
    $resp = supabase_table_select('finance_businesses', 'select=*&order=display_name.asc', null, true);
    $businesses = $resp['data'] ?? [];
    $chargesResp = supabase_table_select('business_fee_charges', 'status=in.(unpaid,pending)&select=business_slug,amount_cents', null, true);
    foreach ($chargesResp['data'] ?? [] as $c) {
        $outstandingBySlug[$c['business_slug']] = ($outstandingBySlug[$c['business_slug']] ?? 0) + $c['amount_cents'];
    }
} elseif ($tab === 'settings') {
    $xcdRate = finance_get_setting('xcd_to_usd_rate', '2.7169');
} elseif ($tab === 'reminders') {
    $resp = supabase_table_select('finance_businesses', 'status=eq.active&select=slug,display_name&order=display_name.asc', null, true);
    $businesses = $resp['data'] ?? [];

    $remResp = supabase_table_select('fee_reminders', 'select=*&order=created_at.desc', null, true);
    $reminders = $remResp['data'] ?? [];

    $adminIds = array_filter(array_unique(array_column($reminders, 'sent_by_admin_id')));
    if ($adminIds) {
        $idList = implode(',', $adminIds);
        $adminResp = supabase_table_select('admin_profiles', 'id=in.(' . $idList . ')&select=id,email,first_name,last_name', null, true);
        foreach ($adminResp['data'] ?? [] as $a) {
            $name = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
            $adminNames[$a['id']] = $name !== '' ? $name : $a['email'];
        }
    }

    if ($reminders) {
        $reminderIds = implode(',', array_column($reminders, 'id'));
        $recResp = supabase_table_select('fee_reminder_recipients', 'reminder_id=in.(' . $reminderIds . ')&select=*&order=created_at.asc', null, true);
        foreach ($recResp['data'] ?? [] as $r) {
            $reminderRecipients[$r['reminder_id']][] = $r;
        }
    }
}

[$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Finance — Exit 473 Admin</title>
<?php include 'head_styles.php'; ?>
<style>
.table-wrap{overflow-x:auto}
.users-table{width:100%;table-layout:fixed;border-collapse:collapse;font-size:.88rem}
.users-table th{background:#1A0505;color:#F5C840;text-align:left;padding:.6rem .75rem;font-weight:600;font-size:.78rem;text-transform:uppercase;letter-spacing:.06em}
.users-table td{padding:.6rem .75rem;border-bottom:1px solid #EAE2E2;vertical-align:top;word-break:break-word;overflow-wrap:anywhere}
.users-table tr:hover td{background:#FFF9F7}
.users-table .edit-row{display:none}
.users-table .edit-row.open{display:table-row}
.users-table .edit-row td{background:#FBF7F5}
.users-table .actions-cell{text-align:right}
.finance-tabs{display:flex;gap:.5rem;margin-bottom:1.5rem;flex-wrap:wrap}
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<main class="wrap">
  <h1 class="page-title">Finance</h1>

  <?php if ($msg_text): ?>
    <div class="alert alert-<?= $msg_type ?>" style="max-width:720px"><?= h($msg_text) ?></div>
  <?php endif; ?>

  <div class="finance-tabs">
    <a href="?tab=schedule" class="btn <?= $tab === 'schedule' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Fee Schedule</a>
    <a href="?tab=businesses" class="btn <?= $tab === 'businesses' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Businesses</a>
    <a href="?tab=reminders" class="btn <?= $tab === 'reminders' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Reminders</a>
    <a href="?tab=settings" class="btn <?= $tab === 'settings' ? 'btn-primary' : 'btn-outline' ?> btn-sm">Settings</a>
  </div>

  <?php if ($tab === 'schedule'): ?>
  <div class="panel">
    <h2 class="panel-title">Fee Schedule</h2>
    <div class="table-wrap">
    <table class="users-table">
      <thead>
        <tr><th>Name</th><th>Category</th><th>Rate</th><th>Variable</th><th>Recurring</th><th>Active</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($feeTypes as $i => $ft): ?>
        <?php
          $rowId = 'fee-edit-' . $i;
          $isLicenseArea = ($ft['pricing_mode'] ?? 'standard') === 'license_area';
          $rateText = $isLicenseArea
              ? 'Per business (see License Rates below)'
              : finance_money($ft['rate_cents'], $ft['currency']) . ($ft['unit_type'] === 'per_unit' ? ' / ' . h($ft['unit_label']) : '');
        ?>
        <tr>
          <td><strong><?= h($ft['name']) ?></strong></td>
          <td style="text-transform:capitalize"><?= h($ft['category']) ?></td>
          <td><?= $rateText ?></td>
          <td><?= $ft['has_variable_component'] ? h($ft['variable_label']) : '—' ?></td>
          <td><?= $ft['is_recurring'] ? 'Yes' : 'No' ?></td>
          <td><?= $ft['active'] ? 'Yes' : 'No' ?></td>
          <td class="actions-cell">
            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('<?= $rowId ?>').classList.toggle('open')">Edit</button>
          </td>
        </tr>
        <tr class="edit-row" id="<?= $rowId ?>">
          <td colspan="7">
            <form method="post">
              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="update_fee_type">
              <input type="hidden" name="id" value="<?= h($ft['id']) ?>">
              <div class="form-grid">
                <div class="form-row">
                  <label>Name</label>
                  <input type="text" name="name" required value="<?= h($ft['name']) ?>">
                </div>
                <?php if ($isLicenseArea): ?>
                  <div class="form-row" style="grid-column:1/-1">
                    <span class="hint">This fee's amount is computed per business from its unit type and measured area (set per-business on each business's Ledger page) and the rates below — not a fixed rate here.</span>
                  </div>
                <?php else: ?>
                  <div class="form-row">
                    <label>Rate (EC$)</label>
                    <input type="number" step="0.01" min="0" name="rate_dollars" required value="<?= h(number_format($ft['rate_cents'] / 100, 2, '.', '')) ?>">
                  </div>
                  <div class="form-row">
                    <label>Unit Type</label>
                    <select name="unit_type" onchange="this.form.querySelector('.unit-label-row').style.display = this.value === 'per_unit' ? '' : 'none'">
                      <option value="flat" <?= $ft['unit_type'] === 'flat' ? 'selected' : '' ?>>Flat</option>
                      <option value="per_unit" <?= $ft['unit_type'] === 'per_unit' ? 'selected' : '' ?>>Per Unit</option>
                    </select>
                  </div>
                  <div class="form-row unit-label-row" style="<?= $ft['unit_type'] === 'per_unit' ? '' : 'display:none' ?>">
                    <label>Unit Label</label>
                    <input type="text" name="unit_label" value="<?= h($ft['unit_label'] ?? '') ?>" placeholder="e.g. day, sq ft-day, occurrence">
                  </div>
                  <div class="form-row">
                    <label class="checkbox-label"><input type="checkbox" name="has_variable_component" <?= $ft['has_variable_component'] ? 'checked' : '' ?>> Has a variable component</label>
                    <input type="text" name="variable_label" value="<?= h($ft['variable_label'] ?? '') ?>" placeholder="e.g. Usage charge, Repair costs" style="margin-top:.4rem">
                  </div>
                <?php endif; ?>
                <div class="form-row">
                  <label class="checkbox-label"><input type="checkbox" name="is_recurring" <?= $ft['is_recurring'] ? 'checked' : '' ?>> Auto-generate monthly for every business</label>
                </div>
                <div class="form-row">
                  <label class="checkbox-label"><input type="checkbox" name="active" <?= $ft['active'] ? 'checked' : '' ?>> Active</label>
                </div>
              </div>
              <button class="btn btn-primary btn-sm" type="submit" style="margin-top:.75rem">Save Changes</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>

  <div class="panel" style="max-width:560px">
    <h2 class="panel-title">License Fee Rates</h2>
    <p style="color:#7A6565;font-size:.9rem;margin-bottom:1rem">Per-sq-ft monthly rates by unit type. Each business's actual License Fee is area × these rates, using the area set on that business's Ledger page.</p>
    <div class="table-wrap">
    <table class="users-table">
      <thead><tr><th>Unit Type</th><th>Operating Area</th><th>Customer Area</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($licenseRates as $i => $lr): ?>
        <?php $rowId = 'lr-edit-' . $i; ?>
        <tr>
          <td style="text-transform:capitalize"><?= h($lr['unit_type']) ?><?= $lr['unit_type'] === 'fixed' ? ' (Licensor-owned)' : ' (Licensee-owned)' ?></td>
          <td><?= h(finance_money($lr['operating_rate_cents'], 'XCD')) ?>/sq ft</td>
          <td><?= h(finance_money($lr['customer_rate_cents'], 'XCD')) ?>/sq ft</td>
          <td class="actions-cell">
            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('<?= $rowId ?>').classList.toggle('open')">Edit</button>
          </td>
        </tr>
        <tr class="edit-row" id="<?= $rowId ?>">
          <td colspan="4">
            <form method="post">
              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="update_license_rate">
              <input type="hidden" name="unit_type" value="<?= h($lr['unit_type']) ?>">
              <div class="form-grid">
                <div class="form-row">
                  <label>Operating Area Rate (EC$/sq ft)</label>
                  <input type="number" step="0.01" min="0" name="operating_rate_dollars" required value="<?= h(number_format($lr['operating_rate_cents'] / 100, 2, '.', '')) ?>">
                </div>
                <div class="form-row">
                  <label>Customer Area Rate (EC$/sq ft)</label>
                  <input type="number" step="0.01" min="0" name="customer_rate_dollars" required value="<?= h(number_format($lr['customer_rate_cents'] / 100, 2, '.', '')) ?>">
                </div>
              </div>
              <button class="btn btn-primary btn-sm" type="submit" style="margin-top:.75rem">Save Changes</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>

  <div class="panel" style="max-width:560px">
    <h2 class="panel-title">Monthly Recurring Charges</h2>
    <p style="color:#7A6565;font-size:.9rem;margin-bottom:1rem">Generates this month's recurring fees — Utility Administration, License Fees, and any other recurring fee — for every active business. Safe to click more than once — already-generated charges are never duplicated.</p>
    <form method="post" onsubmit="return confirm('Generate this month\'s recurring charges for every active business?')">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="generate_monthly">
      <button class="btn btn-primary" type="submit">Generate This Month's Recurring Charges (<?= h(date('F Y')) ?>)</button>
    </form>
  </div>
  <?php endif; ?>

  <?php if ($tab === 'businesses'): ?>
  <div class="panel" style="max-width:400px">
    <h2 class="panel-title">Sync Businesses</h2>
    <p style="color:#7A6565;font-size:.9rem;margin-bottom:1rem">Scans bizsite/data/ for any business folder not yet tracked here.</p>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="sync_businesses">
      <button class="btn btn-outline" type="submit">Sync Businesses</button>
    </form>
  </div>

  <div class="panel">
    <h2 class="panel-title">Businesses</h2>
    <?php if ($businesses): ?>
    <div class="table-wrap">
    <table class="users-table">
      <thead>
        <tr><th>Business</th><th>Slug</th><th>Status</th><th>Outstanding</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($businesses as $i => $b): ?>
        <?php $rowId = 'biz-status-' . $i; $outstanding = $outstandingBySlug[$b['slug']] ?? 0; ?>
        <tr>
          <td><a href="finance_business.php?slug=<?= urlencode($b['slug']) ?>"><strong><?= h($b['display_name']) ?></strong></a></td>
          <td><?= h($b['slug']) ?></td>
          <td style="text-transform:capitalize"><?= h($b['status']) ?></td>
          <td><?= $outstanding > 0 ? '<strong>' . h(finance_money($outstanding, 'XCD')) . '</strong>' : '—' ?></td>
          <td class="actions-cell">
            <a href="finance_business.php?slug=<?= urlencode($b['slug']) ?>" class="btn btn-outline btn-sm">Ledger →</a>
            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('<?= $rowId ?>').classList.toggle('open')">Status</button>
          </td>
        </tr>
        <tr class="edit-row" id="<?= $rowId ?>">
          <td colspan="5">
            <form method="post">
              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="update_business_status">
              <input type="hidden" name="slug" value="<?= h($b['slug']) ?>">
              <div class="form-row" style="max-width:220px">
                <label>Status</label>
                <select name="status">
                  <option value="active" <?= $b['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                  <option value="inactive" <?= $b['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
                <span class="hint">Inactive businesses are skipped by monthly charge generation.</span>
              </div>
              <button class="btn btn-primary btn-sm" type="submit" style="margin-top:.75rem">Save</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php else: ?><p style="color:#7A6565">No businesses found — click Sync Businesses above.</p><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($tab === 'settings'): ?>
  <div class="panel" style="max-width:480px">
    <h2 class="panel-title">Currency Settings</h2>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="update_setting">
      <div class="form-row">
        <label>EC$ (XCD) per US$1</label>
        <input type="number" step="0.0001" min="0.0001" name="xcd_to_usd_rate" required value="<?= h($xcdRate) ?>">
        <span class="hint">Used to convert EC$ fee amounts to US$ for Stripe checkout (Stripe doesn't support Grenada-domiciled accounts). The currency board peg is 2.7169.</span>
      </div>
      <button class="btn btn-primary" type="submit" style="margin-top:.75rem">Save</button>
    </form>
  </div>
  <?php endif; ?>

  <?php if ($tab === 'reminders'): ?>
  <div class="panel" style="max-width:640px">
    <h2 class="panel-title">Send a Reminder</h2>
    <form method="post" id="reminder-form">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="send_reminder">
      <div class="form-row">
        <label>Recipients</label>
        <label class="checkbox-label"><input type="radio" name="scope" value="all" checked onchange="document.getElementById('slug-picker').style.display='none'"> All active businesses</label>
        <label class="checkbox-label"><input type="radio" name="scope" value="selected" onchange="document.getElementById('slug-picker').style.display=''"> Selected businesses</label>
      </div>
      <div id="slug-picker" style="display:none;border:1px solid #EAE2E2;border-radius:8px;padding:.75rem;max-height:220px;overflow-y:auto;margin-bottom:1rem">
        <?php foreach ($businesses as $b): ?>
          <label class="checkbox-label" style="margin-bottom:.4rem"><input type="checkbox" name="slugs[]" value="<?= h($b['slug']) ?>"> <?= h($b['display_name']) ?></label><br>
        <?php endforeach; ?>
      </div>
      <div class="form-row">
        <label>Subject</label>
        <input type="text" name="subject" required placeholder="Outstanding fees reminder">
      </div>
      <div class="form-row">
        <label>Message</label>
        <textarea name="message" rows="5" required placeholder="Please settle your outstanding balance by..."></textarea>
      </div>
      <button class="btn btn-primary" type="submit" style="margin-top:.75rem">Send Reminder</button>
    </form>
  </div>

  <div class="panel">
    <h2 class="panel-title">Reminder Log</h2>
    <?php if ($reminders): ?>
    <div class="table-wrap">
    <table class="users-table">
      <thead>
        <tr><th>Subject</th><th>Scope</th><th>Sent By</th><th>Sent At</th><th>Recipients</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($reminders as $i => $r): ?>
        <?php
          $rowId = 'rem-detail-' . $i;
          $recipients = $reminderRecipients[$r['id']] ?? [];
          $sentAt = new DateTime($r['created_at']);
          $sentAt->setTimezone(new DateTimeZone('America/New_York'));
        ?>
        <tr>
          <td><?= h($r['subject']) ?></td>
          <td style="text-transform:capitalize"><?= h($r['recipient_scope']) ?></td>
          <td><?= h($adminNames[$r['sent_by_admin_id']] ?? '—') ?></td>
          <td><?= h($sentAt->format('M j, Y g:ia')) ?> ET</td>
          <td><?= count($recipients) ?></td>
          <td class="actions-cell">
            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('<?= $rowId ?>').classList.toggle('open')">Details</button>
          </td>
        </tr>
        <tr class="edit-row" id="<?= $rowId ?>">
          <td colspan="6">
            <div class="table-wrap">
            <table class="users-table">
              <thead><tr><th>Business</th><th>Email</th><th>Status</th><th>Outstanding</th><th>Error</th></tr></thead>
              <tbody>
              <?php foreach ($recipients as $rec): ?>
                <tr>
                  <td><?= h($rec['business_slug']) ?></td>
                  <td><?= h($rec['email']) ?></td>
                  <td style="text-transform:capitalize"><?= h($rec['status']) ?></td>
                  <td><?= $rec['outstanding_cents_snapshot'] !== null ? h(finance_money($rec['outstanding_cents_snapshot'], 'XCD')) : '—' ?></td>
                  <td><?= h($rec['error'] ?? '—') ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php else: ?><p style="color:#7A6565">No reminders sent yet.</p><?php endif; ?>
  </div>
  <?php endif; ?>
</main>
</body>
</html>
