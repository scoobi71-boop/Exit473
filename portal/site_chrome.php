<?php
/**
 * portal/site_chrome.php
 *
 * Main-site branding for public portal pages (events.php, e.php,
 * order-success.php): the same nav, fonts, Google Analytics tag, page hero
 * and full footer as /events.html, with root-relative links so they work
 * from /portal/. Keep site_footer() in sync with the footer in events.html.
 * (css/styles.css is already loaded by head_styles.php.)
 */

function site_head_extra() { ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Nunito:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="icon" href="/images/logo.png">
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-T6MG63FX84"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-T6MG63FX84');
</script>
<style>
/* Portal pages inside the main-site chrome */
body[data-portal] { background: var(--sand); }
.portal-main { padding-top: var(--nav-h); }
.portal-main .portal-wrap { padding-top: 2.5rem; padding-bottom: 3.5rem; }
.portal-main .portal-card h1 { font-family: var(--font-display); }
@media (max-width: 640px) {
  .portal-main .portal-wrap { padding: 1.5rem .9rem 2.5rem; }
  .portal-card { padding: 1.25rem; }
}
</style>
<?php }

function site_nav($active = 'events') {
    $a = fn($k) => $k === $active ? ' class="active"' : ''; ?>
  <!-- NAVIGATION -->
  <nav class="site-nav solid" aria-label="Main navigation">
    <div class="nav-inner">
      <a href="/index.html" class="nav-logo" aria-label="Exit 473 home">
        <img src="/images/logo.png" alt="Exit 473 logo" class="logo-img" onerror="this.style.display='none'">
        <span class="logo-text">
          <span class="logo-main">Exit 473</span>
          <span class="logo-sub">Food & Lifestyle Park · Grenada</span>
        </span>
      </a>
      <ul class="nav-links" role="list">
        <li><a href="/index.html">Home</a></li>
        <li><a href="/directory.html">Directory</a></li>
        <li><a href="/exit473_aerial_map.php">Site Map</a></li>
        <li><a href="/events.html"<?= $a('events') ?>>Events</a></li>
        <li><a href="/carnival/carnival.html">Carnival</a></li>
        <li><a href="/about.html">About</a></li>
        <li><a href="/contact.html">Contact</a></li>
        <li><a href="/members.php"<?= $a('members') ?>>Members</a></li>
        <li><a href="/portal/events.php" class="btn btn-primary">🎟️ Tickets</a></li>
      </ul>
      <button class="nav-hamburger" aria-label="Toggle menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </nav>
<?php }

function site_page_hero($label, $title, $sub = '') { ?>
    <!-- PAGE HERO -->
    <div class="page-hero" aria-labelledby="page-heading">
      <div class="container">
        <span class="section-label"><?= htmlspecialchars($label) ?></span>
        <h1 id="page-heading"><?= htmlspecialchars($title) ?></h1>
        <?php if ($sub !== ''): ?><p><?= htmlspecialchars($sub) ?></p><?php endif; ?>
      </div>
    </div>

    <!-- WAVE -->
    <div class="wave-divider" aria-hidden="true">
      <svg viewBox="0 0 1440 50" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
        <path d="M0,25 C480,50 960,0 1440,25 L1440,50 L0,50 Z" fill="#FFF9F7"/>
      </svg>
    </div>
<?php }

/** Full main-site footer (same as events.html) + the main site JS (nav menu, footer year). */
function site_footer() { ?>
  <!-- FOOTER -->
  <footer class="site-footer" aria-label="Site footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-brand">
          <a href="/index.html" class="nav-logo">
            <img src="/images/logo.png" alt="Exit 473 logo" class="logo-img" onerror="this.style.display='none'">
            <span class="logo-text">
              <span class="logo-main">Exit 473</span>
              <span class="logo-sub">Food & Lifestyle Park · Grenada</span>
            </span>
          </a>
          <p>St. George's most vibrant destination — 10 businesses celebrating the food, culture, and spirit of beautiful Grenada, West Indies.</p>
          <div class="social-links">
            <a href="#" class="social-link" aria-label="Facebook">f</a>
            <a href="#" class="social-link" aria-label="Instagram">📸</a>
            <a href="#" class="social-link" aria-label="WhatsApp">💬</a>
          </div>
        </div>
        <div class="footer-col">
          <h4>Navigate</h4>
          <ul>
            <li><a href="/index.html">Home</a></li>
            <li><a href="/directory.html">Business Directory</a></li>
            <li><a href="/exit473_aerial_map.php">Site Map</a></li>
            <li><a href="/events.html">Events Calendar</a></li>
            <li><a href="/about.html">About the Park</a></li>
            <li><a href="/contact.html">Contact &amp; Location</a></li>
          </ul>
        </div>
        <div class="footer-col">
          <h4>Categories</h4>
          <ul>
            <li><a href="/directory.html?cat=restaurant">Restaurants</a></li>
            <li><a href="/directory.html?cat=barbershop">Barbershop</a></li>
            <li><a href="/directory.html?cat=cultural">Cultural Arts</a></li>
          </ul>
        </div>
        <div class="footer-col">
          <h4>Contact</h4>
          <div class="footer-contact-item"><span>📍</span><span>Maurice Bishop Highway, Grand Anse, St George's, Grenada, W.I.</span></div>
          <div class="footer-contact-item"><span>📞</span><span><a href="tel:+14734037377">+1 (473) 403-7377</a></span></div>
          <div class="footer-contact-item"><span>✉️</span><span><a href="mailto:exit473.gd@gmail.com">exit473.gd@gmail.com</a></span></div>
          <div class="footer-contact-item"><span>🕐</span><span>Mon–Sun · 7am – Midnight</span></div>
        </div>
      </div>
      <div class="footer-bottom">
        <p>&copy; <span id="footer-year">2024</span> Exit 473 · Grenada, West Indies. All rights reserved.</p>
        <p>Built with 🌿 and island pride.</p>
      </div>
    </div>
  </footer>

  <script src="/js/app.js"></script>
<?php }
