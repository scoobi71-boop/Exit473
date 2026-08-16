<?php
$current = basename($_SERVER['PHP_SELF']);
function nav_active($file) {
    global $current;
    return $current === $file ? ' active' : '';
}
?>
<nav class="admin-nav">
  <div class="brand">
    Exit 473
    <span>Admin Portal</span>
  </div>
  <div class="nav-links-admin">
    <a href="dashboard.php"  class="<?= nav_active('dashboard.php') ?>">Dashboard</a>
    <a href="events.php"     class="<?= nav_active('events.php') ?>">Events</a>
    <a href="businesses.php"      class="<?= nav_active('businesses.php') ?>">Businesses</a>
    <a href="spotlight.php"       class="<?= nav_active('spotlight.php') ?>">✨ Spotlight</a>
    <a href="calypso-artists.php" class="<?= nav_active('calypso-artists.php') ?>">Artists</a>
    <a href="calypso-tents.php"   class="<?= nav_active('calypso-tents.php') ?>">🎪 Tents</a>
    <a href="artist-uploads.php" class="<?= nav_active('artist-uploads.php') ?>">📥 Uploads</a>
  </div>
  <div class="nav-right">
    <a href="../index.html" target="_blank">View Site ↗</a>
    <a href="logout.php">Log Out</a>
  </div>
</nav>
