<?php
require_once __DIR__ . '/admin/auth.php';

$slug = clean_slug($_GET['biz'] ?? 'demo');
$info = read_info($slug);

if (empty($info['name'])) {
    http_response_code(404);
    header('Location: index.php?biz=' . urlencode($slug));
    exit;
}

if (!has_menu($info)) {
    header('Location: index.php?biz=' . urlencode($slug));
    exit;
}

$accent    = $info['accent_color'] ?? '#2D6A4F';
$menu_rows = read_csv(menu_path($slug));
$home_url  = 'index.php?biz=' . urlencode($slug);

// Group by category, preserve insertion order
$grouped = [];
foreach ($menu_rows as $row) {
    $cat = $row['category'] ?: 'Other';
    $grouped[$cat][] = $row;
}

// Detect if any item has an image (use card view) or none (use list view)
$has_any_image = !empty(array_filter(array_column($menu_rows, 'image')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Menu — <?= h($info['name']) ?></title>
  <meta name="description" content="Menu at <?= h($info['name']) ?> — <?= h($info['tagline'] ?? '') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css?v=3">
  <style>:root{--accent:<?= h($accent) ?>;--accent-dark:color-mix(in srgb,<?= h($accent) ?> 70%,black)}</style>
</head>
<body>

  <!-- NAV -->
  <nav class="site-nav">
    <div class="nav-inner">
      <a href="../index.html" class="nav-exit473" aria-label="Exit 473 home">
        <img src="../images/logo.png" alt="Exit 473 logo" class="nav-exit473-img" onerror="this.style.display='none'">
        <span class="nav-exit473-text">
          <span class="nav-exit473-main">Exit 473</span>
          <span class="nav-exit473-sub">Food & Lifestyle Park · Grenada</span>
        </span>
      </a>
      <a href="<?= h($home_url) ?>" class="nav-logo">
        <?php if (!empty($info['logo_image'])): ?>
          <img src="<?= h($info['logo_image']) ?>" alt="<?= h($info['name']) ?> logo">
        <?php endif; ?>
        <span class="nav-logo-name"><?= h($info['name']) ?></span>
      </a>
      <ul class="nav-links">
        <li><a href="<?= h($home_url) ?>">Home</a></li>
        <li><a href="menu.php?biz=<?= h($slug) ?>" class="active">Menu</a></li>
        <?php if (!empty($info['phone'])): ?>
          <li class="hide-mobile"><a href="tel:<?= h(preg_replace('/\s+/','',$info['phone'])) ?>"><?= h($info['phone']) ?></a></li>
        <?php endif; ?>
      </ul>
    </div>
  </nav>

  <!-- MENU HERO -->
  <div class="menu-hero">
    <div class="container">
      <h1>Our Menu</h1>
      <p><?= h($info['name']) ?><?= !empty($info['tagline']) ? ' — ' . h($info['tagline']) : '' ?></p>
    </div>
  </div>

  <!-- MENU CONTENT -->
  <main class="section">
    <div class="container">

      <?php if (empty($menu_rows)): ?>
        <div style="text-align:center;padding:4rem 0;color:#718096">
          <div style="font-size:3rem;margin-bottom:1rem">🍽️</div>
          <p>Menu coming soon. Check back shortly.</p>
          <a href="<?= h($home_url) ?>" style="margin-top:1rem;display:inline-block">← Back to home</a>
        </div>

      <?php elseif ($has_any_image): ?>
        <!-- Card view — at least some items have images -->
        <?php foreach ($grouped as $category => $items): ?>
        <div class="menu-category-section">
          <h2 class="menu-category-title">🍴 <?= h($category) ?></h2>
          <div class="menu-grid">
            <?php foreach ($items as $item): ?>
            <div class="menu-item">
              <?php if (!empty($item['image'])): ?>
                <img src="<?= h($item['image']) ?>" alt="<?= h($item['name']) ?>" class="menu-item-img">
              <?php else: ?>
                <div style="width:100%;height:120px;background:linear-gradient(135deg,<?= h($accent) ?>22,<?= h($accent) ?>44);display:flex;align-items:center;justify-content:center;font-size:2.5rem">🍽️</div>
              <?php endif; ?>
              <div class="menu-item-body">
                <div class="menu-item-header">
                  <span class="menu-item-name"><?= h($item['name']) ?></span>
                  <?php if ($item['price']): ?>
                    <span class="menu-item-price">$<?= h($item['price']) ?></span>
                  <?php endif; ?>
                </div>
                <?php if ($item['description']): ?>
                  <p class="menu-item-desc"><?= h($item['description']) ?></p>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>

      <?php else: ?>
        <!-- List view — no images -->
        <?php foreach ($grouped as $category => $items): ?>
        <div class="menu-category-section">
          <h2 class="menu-category-title">🍴 <?= h($category) ?></h2>
          <div class="menu-list">
            <?php foreach ($items as $item): ?>
            <div class="menu-list-item">
              <div class="menu-list-item-info">
                <div class="menu-list-item-name"><?= h($item['name']) ?></div>
                <?php if ($item['description']): ?>
                  <div class="menu-list-item-desc"><?= h($item['description']) ?></div>
                <?php endif; ?>
              </div>
              <?php if ($item['price']): ?>
                <div class="menu-list-item-price">$<?= h($item['price']) ?></div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>

    </div>
  </main>

  <!-- FOOTER -->
  <footer class="site-footer">
    <div class="container">
      <div class="footer-inner">
        <div>
          <div class="footer-brand-name"><?= h($info['name']) ?></div>
          <?php if (!empty($info['tagline'])): ?>
            <div class="footer-tagline"><?= h($info['tagline']) ?></div>
          <?php endif; ?>
        </div>
        <div class="footer-col">
          <h4>Contact</h4>
          <ul>
            <?php if (!empty($info['phone'])): ?>
              <li><a href="tel:<?= h(preg_replace('/\s+/','',$info['phone'])) ?>"><?= h($info['phone']) ?></a></li>
            <?php endif; ?>
            <?php if (!empty($info['email'])): ?>
              <li><a href="mailto:<?= h($info['email']) ?>"><?= h($info['email']) ?></a></li>
            <?php endif; ?>
          </ul>
        </div>
        <div class="footer-col">
          <h4>Links</h4>
          <ul>
            <li><a href="<?= h($home_url) ?>">Home</a></li>
            <li><a href="menu.php?biz=<?= h($slug) ?>">Menu</a></li>
            <li><a href="../index.html">Exit 473</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">&copy; <?= date('Y') ?> <?= h($info['name']) ?>. Part of Exit 473 Food &amp; Lifestyle Park, Grenada.</div>
    </div>
  </footer>

</body>
</html>
