<?php
$current = basename($_SERVER['PHP_SELF']);
$slug    = current_slug();
$info    = read_info($slug);
function nav_a($file, $label) {
    global $current;
    $cls = $current === $file ? ' active' : '';
    echo "<a href=\"{$file}\" class=\"{$cls}\">{$label}</a>";
}
?>
<nav class="admin-nav">
  <div class="brand">
    <?= h($info['name'] ?? 'Business Admin') ?>
    <span>Admin Portal</span>
  </div>
  <div class="nav-links-admin">
    <?php nav_a('dashboard.php', 'Dashboard'); ?>
    <?php nav_a('edit_info.php', 'Business Info'); ?>
    <?php if (has_menu($info)): ?>
      <?php nav_a('menu_items.php', 'Menu'); ?>
    <?php endif; ?>
    <?php nav_a('gallery.php', 'Gallery'); ?>
  </div>
  <div class="nav-right">
    <a href="../index.php?biz=<?= h($slug) ?>" target="_blank">View Site ↗</a>
    <a href="logout.php">Log Out</a>
  </div>
</nav>
