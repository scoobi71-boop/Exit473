<?php
require_once __DIR__ . '/../../admin/auth.php';
require_once __DIR__ . '/../supabase.php';
require_once __DIR__ . '/../tickets.php';

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
<?php include '../head_styles.php'; ?>
<style>
.status-pill { display:inline-block; padding:.2rem .6rem; border-radius:99px; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
.status-pending { background:#FFF8E1; color:#8D6E00; border:1px solid #FFE082; }
.status-published { background:#E8F5E9; color:#1B5E20; border:1px solid #A5D6A7; }
.status-rejected { background:#FFEBEE; color:#B71C1C; border:1px solid #FFCDD2; }
.status-cancelled { background:#ECEFF1; color:#455A64; border:1px solid #CFD8DC; }
.event-row { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:1rem 0; border-bottom:1px solid var(--gray-300); flex-wrap:wrap; }
.event-row:last-child { border-bottom:none; }
</style>
</head>
<body data-portal>
  <nav class="portal-nav">
    <div class="brand">Exit 473 <span>Event Portal Admin</span></div>
    <div class="portal-nav-links">
      <a href="index.php">Overview</a>
      <a href="events.php" class="active">Events</a>
    </div>
    <div class="portal-nav-right">
      <a href="/admin/dashboard.php">Core Admin</a>
      &nbsp;·&nbsp;
      <a href="/admin/logout.php">Log Out</a>
    </div>
  </nav>
  <div class="portal-wrap wide">

    <?php if ($msgText): ?><div class="portal-alert portal-alert-<?= $msgType ?>"><?= h($msgText) ?></div><?php endif; ?>

    <?php if ($organizerFilter): ?>
      <p style="margin-bottom:1rem"><a href="/admin/users.php?tab=portal">← Back to Portal Users</a></p>
      <div class="portal-alert portal-alert-ok" style="margin-bottom:1.25rem">
        Showing events by <strong><?= h($organizerFilterName ?: 'this organizer') ?></strong> — <a href="events.php">show all events</a>
      </div>
    <?php endif; ?>

    <div class="portal-card" style="margin-bottom:1.25rem">
      <h1>Pending Approval <small style="font-weight:400;font-size:1rem;color:var(--text-light)">(<?= count($pending) ?>)</small></h1>
      <?php if (!$pending): ?>
        <p style="color:var(--text-light)">Nothing waiting for review.</p>
      <?php else: ?>
        <?php foreach ($pending as $ev): ?>
          <div class="event-row">
            <div>
              <div style="font-weight:700"><?= h($ev['title']) ?></div>
              <div style="color:var(--text-light);font-size:.85rem">
                <?= h(fmt_short($ev['start_at'])) ?> · by <?= h(organizer_label($ev['organizer_id'], $organizers)) ?>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:.5rem">
              <a href="../e.php?slug=<?= h($ev['slug']) ?>" class="btn btn-sm btn-outline" target="_blank">Preview</a>
              <form method="post" style="display:inline">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="id" value="<?= h($ev['id']) ?>">
                <button type="submit" class="btn btn-sm btn-primary">Approve</button>
              </form>
              <form method="post" style="display:inline" onsubmit="return confirm('Reject this event?')">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="id" value="<?= h($ev['id']) ?>">
                <button type="submit" class="btn btn-sm btn-danger">Reject</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="portal-card" style="margin-bottom:1.25rem">
      <h2>Ticket Sales</h2>
      <p class="portal-sub" style="margin-bottom:0"><?= (int)$salesTotal['tickets'] ?> tickets · <?= (int)$salesTotal['orders'] ?> paid orders · <?= tickets_money($salesTotal['gross_cents']) ?> gross (before Stripe fees; all funds go to the Exit 473 Stripe account)</p>
    </div>

    <div class="portal-card">
      <h2>All Other Events <small style="font-weight:400;font-size:.85rem;color:var(--text-light)">(<?= count($others) ?>)</small></h2>
      <?php if (!$others): ?>
        <p style="color:var(--text-light)">Nothing here yet.</p>
      <?php else: ?>
        <?php foreach ($others as $ev): ?>
          <div class="event-row">
            <div>
              <div style="font-weight:700"><?= h($ev['title']) ?></div>
              <div style="color:var(--text-light);font-size:.85rem">
                <?= h(fmt_short($ev['start_at'])) ?> · by <?= h(organizer_label($ev['organizer_id'], $organizers)) ?>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:.75rem">
              <?php if (!empty($sales[$ev['id']])): ?><span style="font-size:.85rem;color:var(--text-light)"><?= (int)$sales[$ev['id']]['tickets'] ?> sold · <?= tickets_money($sales[$ev['id']]['gross_cents']) ?></span><?php endif; ?>
              <span class="status-pill status-<?= h($ev['status']) ?>"><?= h($ev['status']) ?></span>
              <a href="../e.php?slug=<?= h($ev['slug']) ?>" class="btn btn-sm btn-outline" target="_blank">View</a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>
</body>
</html>
