<?php
/**
 * portal/admin/_chrome.php
 *
 * Main-admin header for the portal admin pages: same markup and links as
 * admin/nav.php, but with absolute /admin/ URLs (admin/nav.php uses relative
 * links that only work from /admin/). Keep the two in sync.
 */
require_once dirname(__DIR__) . '/admin_look.php';

function padmin_nav() {
    $p = function_exists('admin_current_profile') ? admin_current_profile() : null;
    $role = $p['role'] ?? ''; ?>
<nav class="admin-nav">
  <div class="brand">
    Exit 473
    <span>Admin Portal</span>
  </div>
  <div class="nav-links-admin">
    <a href="/admin/dashboard.php">Dashboard</a>
    <?php if (in_array($role, ['site_owner', 'content_manager'], true)): ?>
      <a href="/admin/events.php">Events</a>
      <a href="/portal/admin/events.php" class="active">🎟️ Ticketing</a>
      <a href="/admin/businesses.php">Businesses</a>
      <a href="/admin/spotlight.php">✨ Spotlight</a>
      <a href="/admin/calypso-artists.php">Artists</a>
      <a href="/admin/calypso-tents.php">🎪 Tents</a>
      <a href="/admin/artist-uploads.php">📥 Uploads</a>
    <?php else: ?>
      <a href="/portal/admin/events.php" class="active">🎟️ Ticketing</a>
    <?php endif; ?>
    <?php if (in_array($role, ['site_owner', 'finance'], true)): ?>
      <a href="/admin/finance.php">💰 Finance</a>
    <?php endif; ?>
    <?php if ($role === 'site_owner'): ?>
      <a href="/admin/users.php">👤 Users</a>
    <?php endif; ?>
  </div>
  <div class="nav-right">
    <?php if (!empty($_SESSION['admin_user_email'])): ?>
      <span style="color:#7A6565"><?= h($_SESSION['admin_user_email']) ?></span>
    <?php endif; ?>
    <a href="/admin/account.php">My Account</a>
    <a href="/index.html" target="_blank">View Site ↗</a>
    <a href="/admin/logout.php">Log Out</a>
  </div>
</nav>
<?php }
