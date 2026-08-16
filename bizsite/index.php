<?php
require_once __DIR__ . '/admin/auth.php';

$slug = clean_slug($_GET['biz'] ?? 'demo');
$info = read_info($slug);

if (empty($info['name'])) {
    http_response_code(404);
    $page_title = 'Business Not Found';
    $not_found  = true;
} else {
    $not_found  = false;
    $page_title = $info['name'];
    $accent     = $info['accent_color'] ?? '#2D6A4F';
    $hours_list = array_filter(array_map('trim', explode('|', $info['hours'] ?? '')));
    $show_menu  = has_menu($info);
    $menu_url   = 'menu.php?biz=' . urlencode($slug);
    $hero_style = !empty($info['hero_image'])
        ? 'background-image:url(' . h($info['hero_image']) . ');background-size:cover;background-position:center'
        : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h($page_title) ?><?= !$not_found ? ' — ' . h($info['tagline'] ?? 'Grenada') : '' ?></title>
  <?php if (!$not_found): ?>
  <meta name="description" content="<?= h(substr($info['description'] ?? '', 0, 160)) ?>">
  <?php endif; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css?v=3">
  <?php if (!$not_found): ?>
  <style>:root{--accent:<?= h($accent) ?>;--accent-dark:color-mix(in srgb,<?= h($accent) ?> 70%,black)}</style>
  <?php endif; ?>
</head>
<body>

<?php if ($not_found): ?>
  <div class="error-page">
    <div>
      <div style="font-size:3rem;margin-bottom:1rem">🏪</div>
      <h1>Business Not Found</h1>
      <p>We couldn't find a listing for <strong><?= h($slug) ?></strong>.</p>
      <a href="../index.html" class="btn btn-accent">← Back to Exit 473</a>
    </div>
  </div>
<?php else: ?>

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
      <a href="index.php?biz=<?= h($slug) ?>" class="nav-logo">
        <?php if (!empty($info['logo_image'])): ?>
          <img src="<?= h($info['logo_image']) ?>" alt="<?= h($info['name']) ?> logo">
        <?php endif; ?>
        <span class="nav-logo-name"><?= h($info['name']) ?></span>
      </a>
      <ul class="nav-links">
        <li><a href="index.php?biz=<?= h($slug) ?>" class="active">Home</a></li>
        <?php if ($show_menu): ?>
          <li><a href="<?= h($menu_url) ?>">Menu</a></li>
        <?php endif; ?>
        <?php if (!empty($info['phone'])): ?>
          <li class="hide-mobile"><a href="tel:<?= h(preg_replace('/\s+/','',$info['phone'])) ?>"><?= h($info['phone']) ?></a></li>
        <?php endif; ?>
        <?php if ($show_menu): ?>
          <li><a href="<?= h($menu_url) ?>" class="nav-cta">View Menu</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </nav>

  <!-- HERO -->
  <section class="hero" style="<?= $hero_style ?>">
    <div class="container">
      <div class="hero-content">
        <div class="hero-tag"><?= h(ucwords(str_replace('-', ' ', $info['category'] ?? 'Business'))) ?></div>
        <h1><?= h($info['name']) ?></h1>
        <?php if (!empty($info['tagline'])): ?>
          <p class="hero-tagline"><?= h($info['tagline']) ?></p>
        <?php endif; ?>
        <div class="hero-actions">
          <?php if ($show_menu): ?>
            <a href="<?= h($menu_url) ?>" class="btn btn-accent btn-lg">🍽️ View Menu</a>
          <?php endif; ?>
          <?php if (!empty($info['phone'])): ?>
            <a href="tel:<?= h(preg_replace('/\s+/','',$info['phone'])) ?>" class="btn btn-outline-white btn-lg">📞 Call Now</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- ABOUT -->
  <?php if (!empty($info['description'])): ?>
  <section class="section">
    <div class="container">
      <div class="about-grid">
        <div class="about-text">
          <div class="section-label">About Us</div>
          <h2 class="section-title">Welcome to <?= h($info['name']) ?></h2>
          <p><?= nl2br(h($info['description'])) ?></p>
          <?php if ($show_menu): ?>
            <a href="<?= h($menu_url) ?>" class="btn btn-accent" style="margin-top:1.5rem">See Our Menu →</a>
          <?php endif; ?>
        </div>
        <div>
          <?php if (!empty($info['hero_image'])): ?>
            <img src="<?= h($info['hero_image']) ?>" alt="<?= h($info['name']) ?>" style="width:100%;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.15)">
          <?php else: ?>
            <div style="width:100%;aspect-ratio:4/3;background:linear-gradient(135deg,<?= h($accent) ?>33,<?= h($accent) ?>88);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:4rem">
              🏪
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- INFO: HOURS / CONTACT / ADDRESS -->
  <section class="section-sm section-alt">
    <div class="container">
      <div class="section-label" style="text-align:center;margin-bottom:2rem">Find Us</div>
      <div class="info-grid">
        <?php if (!empty($hours_list)): ?>
        <div class="info-card">
          <div class="info-card-icon">🕐</div>
          <div class="info-card-label">Opening Hours</div>
          <div class="info-card-value">
            <?php foreach ($hours_list as $h_line): ?>
              <span class="hours-line"><?= h(trim($h_line)) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($info['phone'])): ?>
        <div class="info-card">
          <div class="info-card-icon">📞</div>
          <div class="info-card-label">Phone</div>
          <div class="info-card-value"><a href="tel:<?= h(preg_replace('/\s+/','',$info['phone'])) ?>"><?= h($info['phone']) ?></a></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($info['email'])): ?>
        <div class="info-card">
          <div class="info-card-icon">✉️</div>
          <div class="info-card-label">Email</div>
          <div class="info-card-value"><a href="mailto:<?= h($info['email']) ?>"><?= h($info['email']) ?></a></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($info['address'])): ?>
        <div class="info-card">
          <div class="info-card-icon">📍</div>
          <div class="info-card-label">Address</div>
          <div class="info-card-value"><?= nl2br(h($info['address'])) ?></div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- GALLERY -->
  <?php
  $gallery = read_gallery($slug);
  usort($gallery, fn($a,$b) => (int)$a['sort_order'] - (int)$b['sort_order']);
  if (!empty($gallery)):
  ?>
  <section class="section gallery-section">
    <div class="container">
      <div class="section-label" style="text-align:center">Gallery</div>
      <h2 class="section-title" style="text-align:center;margin-bottom:2.5rem">Our Photos</h2>
      <div class="gallery-pub-grid">
        <?php foreach ($gallery as $i => $photo): ?>
        <button class="gallery-pub-item" onclick="openLightbox(<?= $i ?>)" aria-label="View photo <?= $i + 1 ?>">
          <img src="<?= h($photo['filename']) ?>" alt="<?= h($photo['caption'] ?: $info['name'] . ' photo') ?>" loading="lazy">
          <?php if (!empty($photo['caption'])): ?>
            <div class="gallery-pub-caption"><?= h($photo['caption']) ?></div>
          <?php endif; ?>
        </button>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- LIGHTBOX -->
  <div id="lightbox" class="lightbox" role="dialog" aria-modal="true" aria-label="Photo viewer">
    <button class="lb-btn lb-close" onclick="closeLightbox()" aria-label="Close">✕</button>
    <button class="lb-btn lb-prev" onclick="moveLightbox(-1)" aria-label="Previous photo">&#8249;</button>
    <button class="lb-btn lb-next" onclick="moveLightbox(1)" aria-label="Next photo">&#8250;</button>
    <div class="lb-backdrop" onclick="closeLightbox()"></div>
    <div class="lb-inner">
      <img id="lb-img" src="" alt="">
      <div id="lb-caption" class="lb-caption"></div>
      <div id="lb-counter" class="lb-counter"></div>
    </div>
  </div>

  <script>
  const _gallery = <?= json_encode(array_values(array_map(fn($p) => [
      'src'     => $p['filename'],
      'caption' => $p['caption'],
  ], $gallery))) ?>;
  let _lbIdx = 0;
  const _lb  = document.getElementById('lightbox');

  function openLightbox(i) {
    _lbIdx = i;
    _updateLightbox();
    _lb.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
  function closeLightbox() {
    _lb.classList.remove('active');
    document.body.style.overflow = '';
  }
  function moveLightbox(dir) {
    _lbIdx = (_lbIdx + dir + _gallery.length) % _gallery.length;
    _updateLightbox();
  }
  function _updateLightbox() {
    const p = _gallery[_lbIdx];
    const img = document.getElementById('lb-img');
    img.src = p.src;
    img.alt = p.caption;
    document.getElementById('lb-caption').textContent = p.caption;
    document.getElementById('lb-counter').textContent = (_lbIdx + 1) + ' / ' + _gallery.length;
  }
  document.addEventListener('keydown', e => {
    if (!_lb.classList.contains('active')) return;
    if (e.key === 'Escape')      closeLightbox();
    if (e.key === 'ArrowLeft')   moveLightbox(-1);
    if (e.key === 'ArrowRight')  moveLightbox(1);
  });
  </script>
  <?php endif; ?>

  <!-- FOOTER -->
  <footer class="site-footer">
    <div class="container">
      <div class="footer-inner">
        <div>
          <div class="footer-brand-name"><?= h($info['name']) ?></div>
          <?php if (!empty($info['tagline'])): ?>
            <div class="footer-tagline"><?= h($info['tagline']) ?></div>
          <?php endif; ?>
          <?php if (!empty($info['facebook']) && $info['facebook'] !== '#' || !empty($info['instagram']) && $info['instagram'] !== '#'): ?>
          <div class="social-links">
            <?php if (!empty($info['facebook']) && $info['facebook'] !== '#'): ?>
              <a href="<?= h($info['facebook']) ?>" class="social-link" target="_blank" rel="noopener" aria-label="Facebook">f</a>
            <?php endif; ?>
            <?php if (!empty($info['instagram']) && $info['instagram'] !== '#'): ?>
              <a href="<?= h($info['instagram']) ?>" class="social-link" target="_blank" rel="noopener" aria-label="Instagram">📷</a>
            <?php endif; ?>
          </div>
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
            <?php if (!empty($info['address'])): ?>
              <li><?= h($info['address']) ?></li>
            <?php endif; ?>
          </ul>
        </div>
        <div class="footer-col">
          <h4>Links</h4>
          <ul>
            <li><a href="index.php?biz=<?= h($slug) ?>">Home</a></li>
            <?php if ($show_menu): ?><li><a href="<?= h($menu_url) ?>">Menu</a></li><?php endif; ?>
            <?php if (!empty($info['website']) && $info['website'] !== '#'): ?>
              <li><a href="<?= h($info['website']) ?>" target="_blank" rel="noopener">Website ↗</a></li>
            <?php endif; ?>
            <li><a href="../index.html">Exit 473</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">&copy; <?= date('Y') ?> <?= h($info['name']) ?>. Part of Exit 473 Food &amp; Lifestyle Park, Grenada.</div>
    </div>
  </footer>

<?php endif; ?>
</body>
</html>
