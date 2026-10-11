<?php
require_once __DIR__ . '/../../admin/auth.php';
require_once __DIR__ . '/../supabase.php';
require_once __DIR__ . '/../tickets.php';
require_once __DIR__ . '/_chrome.php';

// Not admin/auth.php's require_auth(): its relative "index.php" redirect
// would resolve to this directory's own index.php, not /admin/index.php.
if (!admin_access_token()) {
    header('Location: /admin/index.php');
    exit;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf(); // admin/auth.php's CSRF helper — this page uses the core admin session
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? '';

    if ($action === 'approve') {
        supabase_table_update('events', 'id=eq.' . urlencode($id), ['status' => 'published'], null, true);
        $msg = 'ok:Event approved and published.';
    } elseif ($action === 'reject') {
        supabase_table_update('events', 'id=eq.' . urlencode($id), ['status' => 'rejected'], null, true);
        $msg = 'ok:Event rejected.';
    }
}

$organizerFilter = trim($_GET['organizer'] ?? '');

$eventsQuery = 'select=*&order=created_at.desc';
if ($organizerFilter) $eventsQuery .= '&organizer_id=eq.' . urlencode($organizerFilter);
$resp = supabase_table_select('events', $eventsQuery, null, true);
$events = ($resp['status'] === 200) ? $resp['data'] : [];
$pending = array_values(array_filter($events, fn($e) => $e['status'] === 'pending'));
$others  = array_values(array_filter($events, fn($e) => $e['status'] !== 'pending'));

$organizerFilterName = null;
if ($organizerFilter) {
    $r = supabase_table_select('profiles', 'id=eq.' . urlencode($organizerFilter) . '&select=username,display_name,first_name,last_name', null, true);
    if ($r['status'] === 200 && !empty($r['data'][0])) {
        $p = $r['data'][0];
        $fn = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
        $organizerFilterName = $fn !== '' ? $fn : ($p['display_name'] ?: $p['username']);
    }
}

$sales = tickets_sales_summary(array_column($events, 'id'), null, true);
$salesTotal = ['orders' => 0, 'tickets' => 0, 'gross_cents' => 0];
foreach ($sales as $sv) foreach ($salesTotal as $k => $_) $salesTotal[$k] += $sv[$k];

// Look up organizer usernames in one pass rather than one query per event.
$organizerIds = array_unique(array_column($events, 'organizer_id'));
$organizers = [];
if ($organizerIds) {
    $filter = 'id=in.(' . implode(',', array_map('urlencode', $organizerIds)) . ')&select=id,username,display_name';
    $orgResp = supabase_table_select('profiles', $filter, null, true);
    if ($orgResp['status'] === 200) {
        foreach ($orgResp['data'] as $o) $organizers[$o['id']] = $o;
    }
}
function organizer_label($id, $organizers) {
    $o = $organizers[$id] ?? null;
    return $o ? ($o['display_name'] ?: $o['username']) : 'Unknown';
}

function fmt_short($iso) {
    if (!$iso) return '';
    try { return (new DateTime($iso))->setTimezone(new DateTimeZone('America/Grenada'))->format('M j, Y g:i A'); }
    catch (Exception $e) { return ''; }
}

[$msgType, $msgText] = $msg ? explode(':', $msg, 2) : ['', ''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Events — Event Portal Admin</title>
<meta name="robots" content="noindex">
<?php admin_look_head(); ?>
<style>
.ev-actions { display:flex; gap:.4rem; flex-wrap:wrap; }
.ev-actions form { display:inline; }
</style>
</head>
<body>
<?php padmin_nav(); ?>
<main class="wrap">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
    <h1 class="page-title" style="margin:0">🎟️ Ticketed Events</h1>
    <a href="/portal/events.php" class="btn btn-outline" target="_blank">View public listing ↗</a>
  </div>

  <?php if ($msgText): ?><div class="alert alert-<?= $msgType === 'ok' ? 'ok' : 'error' ?>"><?= h($msgText) ?></div><?php endif; ?>

  <?php if ($organizerFilter): ?>
    <p style="margin-bottom:1rem"><a href="/admin/users.php?tab=portal">← Back to Portal Users</a></p>
    <div class="alert alert-ok">Showing events by <strong><?= h($organizerFilterName ?: 'this organizer') ?></strong> · <a href="events.php">show all events</a></div>
  <?php endif; ?>

  <div class="stat-grid">
    <div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-num"><?= count($pending) ?></div><div class="stat-label">Pending approval</div></div>
    <div class="stat-card"><div class="stat-icon">🎟️</div><div class="stat-num"><?= (int)$salesTotal['tickets'] ?></div><div class="stat-label">Tickets sold</div></div>
    <div class="stat-card"><div class="stat-icon">🧾</div><div class="stat-num"><?= (int)$salesTotal['orders'] ?></div><div class="stat-label">Paid orders</div></div>
    <div class="stat-card"><div class="stat-icon">💵</div><div class="stat-num" style="font-size:1.5rem"><?= tickets_money($salesTotal['gross_cents']) ?></div><div class="stat-label">Gross (before Stripe fees)</div></div>
  </div>

  <?php
  $renderTable = function ($list, $isPending) use ($sales, $organizers) { ?>
    <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Date</th><th>Event</th><th>Organizer</th><th>Status</th><th>Sales</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($list as $ev): $sv = $sales[$ev['id']] ?? null; ?>
        <tr>
          <td style="white-space:nowrap"><?= h(fmt_short($ev['start_at'])) ?></td>
          <td><strong><?= h($ev['title']) ?></strong><?= !empty($ev['venue']) ? '<br><small style="color:#7A6565">📍 ' . h($ev['venue']) . '</small>' : '' ?></td>
          <td><?= h(organizer_label($ev['organizer_id'], $organizers)) ?></td>
          <td><span class="status-pill status-<?= h($ev['status']) ?>"><?= h($ev['status']) ?></span></td>
          <td style="white-space:nowrap"><?= $sv ? (int)$sv['tickets'] . ' sold<br><small style="color:#7A6565">' . tickets_money($sv['gross_cents']) . '</small>' : '<span style="color:#7A6565">—</span>' ?></td>
          <td>
            <div class="ev-actions">
              <a href="event-media.php?id=<?= h($ev['id']) ?>" class="btn btn-sm btn-outline">🖼️ Banner &amp; Posters</a>
              <a href="../e.php?slug=<?= h($ev['slug']) ?>" class="btn btn-sm btn-outline" target="_blank"><?= $isPending ? 'Preview' : 'View' ?></a>
              <?php if ($isPending): ?>
              <form method="post">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="id" value="<?= h($ev['id']) ?>">
                <button type="submit" class="btn btn-sm btn-primary">Approve</button>
              </form>
              <form method="post" onsubmit="return confirm('Reject this event?')">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="id" value="<?= h($ev['id']) ?>">
                <button type="submit" class="btn btn-sm btn-danger">Reject</button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php }; ?>

  <div class="panel">
    <h2 class="panel-title">Pending Approval (<?= count($pending) ?>)</h2>
    <?php if (!$pending): ?><p style="color:#7A6565;text-align:center;padding:1.5rem">Nothing waiting for review.</p><?php else: $renderTable($pending, true); endif; ?>
  </div>

  <div class="panel">
    <h2 class="panel-title">All Other Events (<?= count($others) ?>)</h2>
    <?php if (!$others): ?><p style="color:#7A6565;text-align:center;padding:1.5rem">Nothing here yet.</p><?php else: $renderTable($others, false); endif; ?>
  </div>
</main>
</body>
</html>
