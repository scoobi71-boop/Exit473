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
require_once __DIR__ . '/_chrome.php';

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
<?php admin_look_head(); ?>
</head>
<body>
<?php padmin_nav(); ?>
<main class="wrap">
  <p style="margin-bottom:1rem"><a href="events.php">← All ticketed events</a></p>
  <?php if (!$event): ?>
    <div class="panel"><h1 class="page-title" style="margin:0">Event not found</h1></div>
  <?php else: ?>
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
      <h1 class="page-title" style="margin:0">🖼️ <?= h($event['title']) ?></h1>
      <div style="display:flex;gap:.5rem;align-items:center"><span class="status-pill status-<?= h($event['status']) ?>"><?= h($event['status']) ?></span>
      <a class="btn btn-outline" href="../e.php?slug=<?= h($event['slug']) ?>" target="_blank">View event page ↗</a></div>
    </div>
    <?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= h($err) ?></div><?php endif; ?>
    <?= media_render_manager($event, '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">', 'event-media.php?id=' . rawurlencode($event['id'])) ?>
  <?php endif; ?>
</main>
</body>
</html>
