<?php
/**
 * portal/admin/event-media.php?id=<event id>
 *
 * Admin: upload, replace or remove an event's banner and posters. Uses the
 * core admin session and the service-role key, so it works for any event
 * without changing its approval status.
 */
require_once __DIR__ . '/../../admin/auth.php';
require_once __DIR__ . '/../supabase.php';
require_once __DIR__ . '/../event_media.php';

if (!admin_access_token()) { header('Location: /admin/index.php'); exit; }

$id = $_GET['id'] ?? ($_POST['event_id'] ?? '');
function admin_media_load($id) {
    if (!preg_match('/^[0-9a-f-]{36}$/i', (string)$id)) return null;
    $r = supabase_table_select('events', 'id=eq.' . urlencode($id) . '&select=*', null, true);
    return ($r['status'] === 200 && !empty($r['data'][0])) ? $r['data'][0] : null;
}
$event = admin_media_load($id);
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $mr = media_handle_action($_POST['action'] ?? '', $event, null, true);
    if (isset($mr['error'])) $err = $mr['error']; elseif (!empty($mr['ok'])) $msg = $mr['ok'];
    $event = admin_media_load($id);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Banner &amp; Posters — Event Portal Admin</title>
<meta name="robots" content="noindex">
<?php include '../head_styles.php'; ?>
</head>
<body data-portal>
  <nav class="portal-nav">
    <div class="brand">Exit 473 <span>Event Portal Admin</span></div>
    <div class="portal-nav-links">
      <a href="index.php">Overview</a>
      <a href="events.php" class="active">Events</a>
    </div>
    <div class="portal-nav-right"><a href="/admin/dashboard.php">Core Admin</a> &nbsp;·&nbsp; <a href="/admin/logout.php">Log Out</a></div>
  </nav>
  <div class="portal-wrap wide">
    <p><a href="events.php">← All events</a></p>
    <?php if (!$event): ?>
      <div class="portal-card"><h1>Event not found</h1></div>
    <?php else: ?>
      <?php if ($msg): ?><div class="portal-alert portal-alert-ok"><?= h($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="portal-alert portal-alert-error"><?= h($err) ?></div><?php endif; ?>
      <div class="portal-card" style="margin-bottom:1.25rem">
        <h1 style="margin-bottom:.25rem"><?= h($event['title']) ?></h1>
        <p class="portal-sub" style="margin-bottom:0">Status: <?= h($event['status']) ?> · <a href="../e.php?slug=<?= h($event['slug']) ?>" target="_blank">View event page ↗</a></p>
      </div>
      <?= media_render_manager($event, '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">', 'event-media.php?id=' . rawurlencode($event['id'])) ?>
    <?php endif; ?>
  </div>
</body>
</html>
