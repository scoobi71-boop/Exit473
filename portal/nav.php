<?php
$current = basename($_SERVER['PHP_SELF']);
function portal_nav_active($file) {
    global $current;
    return $current === $file ? ' active' : '';
}
?>
<nav class="portal-nav">
  <div class="brand">Exit 473 <span>Event Portal</span></div>
  <div class="portal-nav-links">
    <a href="dashboard.php" class="<?= portal_nav_active('dashboard.php') ?>">My Events</a>
    <a href="events.php" class="<?= portal_nav_active('events.php') ?>">Upcoming Events</a>
  </div>
  <div class="portal-nav-right">
    <a href="../index.html">Exit 473 ↗</a>
    &nbsp;·&nbsp;
    <a href="logout.php">Log Out</a>
  </div>
</nav>
