<?php
require_once __DIR__ . '/auth.php';
portal_require_auth();
$profile = portal_current_profile();
$token = portal_access_token();

$eventsResp = supabase_table_select('events', 'select=*&order=created_at.desc', $token);
$events = ($eventsResp['status'] === 200) ? $eventsResp['data'] : [];

function fmt_short($iso) {
    if (!$iso) return '';
    try { return (new DateTime($iso))->setTimezone(new DateTimeZone('America/Grenada'))->format('M j, Y g:i A'); }
    catch (Exception $e) { return ''; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Events — Exit 473 Event Portal</title>
<meta name="robots" content="noindex">
<?php include 'head_styles.php'; ?>
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
  <?php include 'nav.php'; ?>
  <div class="portal-wrap wide">
    <div class="portal-card" style="margin-bottom:1.25rem">
      <h1>Welcome<?= $profile ? ', ' . h($profile['display_name'] ?: $profile['username']) : '' ?></h1>
      <p class="portal-sub">
        Signed in as <?= h($_SESSION['portal_user_email'] ?? '') ?><?= $profile ? ' · @' . h($profile['username']) : '' ?>
      </p>
    </div>

    <div class="portal-card">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
        <h2 style="margin:0">My Events</h2>
        <a href="event-edit.php" class="btn btn-primary">+ Create Event</a>
      </div>

      <?php if (!$events): ?>
        <p style="color:var(--text-light)">You haven't created any events yet.</p>
      <?php else: ?>
        <?php foreach ($events as $ev): ?>
          <div class="event-row">
            <div>
              <div style="font-weight:700"><?= h($ev['title']) ?></div>
              <div style="color:var(--text-light);font-size:.85rem"><?= h(fmt_short($ev['start_at'])) ?></div>
            </div>
            <div style="display:flex;align-items:center;gap:.75rem">
              <span class="status-pill status-<?= h($ev['status']) ?>"><?= h($ev['status']) ?></span>
              <a href="e.php?slug=<?= h($ev['slug']) ?>" class="btn btn-sm btn-outline" target="_blank">View</a>
              <a href="event-edit.php?id=<?= h($ev['id']) ?>" class="btn btn-sm btn-outline">Edit</a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
