<?php
/**
 * Event Portal Admin — gated by the SAME login as the core site admin
 * (admin/auth.php's Supabase-backed session), not a separate account. This
 * intentionally reuses admin/auth.php rather than portal/auth.php's
 * organizer login, so any core site admin (either role) can moderate
 * events here. The interface itself still lives entirely under
 * portal/admin/, separate from /admin/'s own pages.
 */
require_once __DIR__ . '/../../admin/auth.php';
require_once __DIR__ . '/../supabase.php';

// Not admin/auth.php's require_auth(): its relative "index.php" redirect
// would resolve to this directory's own index.php, not /admin/index.php.
if (!admin_access_token()) {
    header('Location: /admin/index.php');
    exit;
}

$eventsResp = supabase_table_select('events', 'select=status', null, true);
$events = ($eventsResp['status'] === 200) ? $eventsResp['data'] : [];
$pendingCount = count(array_filter($events, fn($e) => $e['status'] === 'pending'));
$publishedCount = count(array_filter($events, fn($e) => $e['status'] === 'published'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Event Portal Admin — Exit 473</title>
<meta name="robots" content="noindex">
<?php include '../head_styles.php'; ?>
</head>
<body data-portal>
  <nav class="portal-nav">
    <div class="brand">Exit 473 <span>Event Portal Admin</span></div>
    <div class="portal-nav-links">
      <a href="index.php" class="active">Overview</a>
      <a href="events.php">Events</a>
    </div>
    <div class="portal-nav-right">
      <a href="/admin/dashboard.php">Core Admin</a>
      &nbsp;·&nbsp;
      <a href="/admin/logout.php">Log Out</a>
    </div>
  </nav>
  <div class="portal-wrap wide">
    <div class="portal-card">
      <h1>Event Portal Admin</h1>
      <p class="portal-sub">Signed in with your site admin login.</p>
      <div style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-top:1rem">
        <a href="events.php" class="btn btn-primary">
          <?= $pendingCount ?> event<?= $pendingCount !== 1 ? 's' : '' ?> awaiting approval →
        </a>
        <span style="align-self:center;color:var(--text-light)"><?= $publishedCount ?> currently published</span>
      </div>
    </div>
  </div>
</body>
</html>
