<?php
$current = basename($_SERVER['PHP_SELF']);
function nav_active($file) {
    global $current;
    return $current === $file ? ' active' : '';
}
$nav_profile = function_exists('admin_current_profile') ? admin_current_profile() : null;
?>
<nav class="admin-nav">
  <div class="brand">
    Exit 473
    <span>Admin Portal</span>
  </div>
  <div class="nav-links-admin">
    <a href="dashboard.php"  class="<?= nav_active('dashboard.php') ?>">Dashboard</a>
    <?php if (in_array($nav_profile['role'] ?? '', ['site_owner', 'content_manager'], true)): ?>
      <a href="events.php"     class="<?= nav_active('events.php') ?>">Events</a>
      <a href="/portal/admin/events.php">🎟️ Ticketing</a>
      <a href="/portal/admin/store.php">🛍️ Store</a>
      <a href="businesses.php"      class="<?= nav_active('businesses.php') ?>">Businesses</a>
      <a href="spotlight.php"       class="<?= nav_active('spotlight.php') ?>">✨ Spotlight</a>
      <a href="calypso-artists.php" class="<?= nav_active('calypso-artists.php') ?>">Artists</a>
      <a href="calypso-tents.php"   class="<?= nav_active('calypso-tents.php') ?>">🎪 Tents</a>
      <a href="artist-uploads.php" class="<?= nav_active('artist-uploads.php') ?>">📥 Uploads</a>
    <?php endif; ?>
    <?php if (in_array($nav_profile['role'] ?? '', ['site_owner', 'finance'], true)): ?>
      <a href="finance.php" class="<?= nav_active('finance.php') ?>">💰 Finance</a>
    <?php endif; ?>
    <?php if (($nav_profile['role'] ?? '') === 'site_owner'): ?>
      <a href="users.php" class="<?= nav_active('users.php') ?>">👤 Users</a>
    <?php endif; ?>
  </div>
  <div class="nav-right">
    <?php if (!empty($_SESSION['admin_user_email'])): ?>
      <span style="color:#7A6565"><?= h($_SESSION['admin_user_email']) ?></span>
    <?php endif; ?>
    <a href="account.php" class="<?= nav_active('account.php') ?>">My Account</a>
    <a href="../index.html" target="_blank">View Site ↗</a>
    <a href="logout.php">Log Out</a>
  </div>
</nav>
