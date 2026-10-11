<?php
/**
 * /members.php — public "Members" hub linked from the main navigation.
 * Points visitors to admin login, organizer (portal) login, registration and
 * password reset; shows dashboard links for whoever is already logged in.
 * Login forms themselves stay on /admin/ and /portal/ (their own CSRF/session code).
 */
require_once __DIR__ . '/portal/auth.php';       // session + h() + portal_access_token()
require_once __DIR__ . '/portal/site_chrome.php';

$portalUser = portal_access_token() ? ($_SESSION['portal_user_email'] ?? 'your account') : null;
$adminUser  = !empty($_SESSION['admin_access_token']) ? ($_SESSION['admin_user_email'] ?? 'admin') : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Members — Exit 473</title>
<meta name="description" content="Log in or register for the Exit 473 member and event-organizer portal.">
<link rel="stylesheet" href="/css/styles.css?v=3">
<?php site_head_extra(); ?>
<style>
.mem-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:1.5rem; margin:0 auto; max-width:1000px; }
.mem-card { background:#fff; border-radius:16px; padding:1.75rem; box-shadow:0 6px 24px rgba(26,5,5,.08); display:flex; flex-direction:column; gap:.75rem; }
.mem-card h2 { font-size:1.3rem; margin:0; }
.mem-card p { color:#6b5a5a; margin:0; font-size:.95rem; }
.mem-card .mem-actions { display:flex; flex-wrap:wrap; gap:.6rem; margin-top:auto; padding-top:.5rem; }
.mem-icon { font-size:2rem; }
.mem-note { background:#FFF9F0; border:1px solid #F3D9A4; color:#6B4E00; border-radius:10px; padding:.7rem .9rem; font-size:.88rem; }
.mem-logged { background:#E8F5E9; border:1px solid #A5D6A7; color:#1B5E20; border-radius:10px; padding:.7rem .9rem; font-size:.9rem; }
.mem-links { font-size:.88rem; }
.mem-links a { color:#CC0000; }
.mem-section { padding:3rem 1.25rem 4rem; }
</style>
</head>
<body>
<?php site_nav('members'); ?>
<?php site_page_hero('Members', 'Members & Organizers', 'Log in to manage your events and tickets, or join as a new member.'); ?>
<main>
  <section class="mem-section">
    <div class="mem-grid">

      <div class="mem-card">
        <div class="mem-icon">🎟️</div>
        <h2>Organizers &amp; Members</h2>
        <?php if ($portalUser): ?>
          <div class="mem-logged">You're logged in as <strong><?= h($portalUser) ?></strong>.</div>
          <div class="mem-actions">
            <a class="btn btn-primary" href="/portal/dashboard.php">My Events Dashboard →</a>
            <a class="btn btn-outline" href="/portal/event-edit.php">+ Create Event</a>
            <a class="btn btn-outline" href="/portal/logout.php">Log Out</a>
          </div>
        <?php else: ?>
          <p>Host an event at Exit 473? Log in to create events, set ticket types and prices, upload a banner and posters, and track your sales.</p>
          <div class="mem-note">⏳ <strong>Events waiting for approval can still be edited.</strong> Make changes any time before an admin approves them. Editing an event that's already live sends it back for a quick re-review.</div>
          <div class="mem-actions">
            <a class="btn btn-primary" href="/portal/login.php">Organizer Log In →</a>
            <a class="btn btn-outline" href="/portal/register.php">Register as a New Member</a>
          </div>
          <div class="mem-links"><a href="/portal/forgot-password.php">Forgot your password?</a></div>
        <?php endif; ?>
      </div>

      <div class="mem-card">
        <div class="mem-icon">🔐</div>
        <h2>Exit 473 Admin</h2>
        <?php if ($adminUser): ?>
          <div class="mem-logged">You're logged in to the admin as <strong><?= h($adminUser) ?></strong>.</div>
          <div class="mem-actions">
            <a class="btn btn-primary" href="/admin/dashboard.php">Admin Dashboard →</a>
            <a class="btn btn-outline" href="/portal/admin/events.php">🎟️ Ticketed Events</a>
            <a class="btn btn-outline" href="/admin/logout.php">Log Out</a>
          </div>
        <?php else: ?>
          <p>Park staff and site managers: log in to manage businesses, events, spotlights, finance and organizer approvals.</p>
          <div class="mem-actions">
            <a class="btn btn-primary" href="/admin/">Admin Log In →</a>
          </div>
          <div class="mem-links"><a href="/admin/forgot-password.php">Forgot your admin password?</a></div>
        <?php endif; ?>
      </div>

      <div class="mem-card">
        <div class="mem-icon">📅</div>
        <h2>Just Browsing?</h2>
        <p>See what's coming up at the park and grab tickets. No account needed to buy tickets.</p>
        <div class="mem-actions">
          <a class="btn btn-outline" href="/portal/events.php">Ticketed Events</a>
          <a class="btn btn-outline" href="/events.html">Events Calendar</a>
        </div>
      </div>

    </div>
  </section>
</main>
<?php site_footer(); ?>
</body>
</html>
