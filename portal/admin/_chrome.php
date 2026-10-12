<?php
/**
 * portal/admin/_chrome.php
 *
 * Main-admin header for the portal admin pages: same markup and links as
 * admin/nav.php, but with absolute /admin/ URLs (admin/nav.php uses relative
 * links that only work from /admin/). Keep the two in sync.
 */
require_once dirname(__DIR__) . '/admin_look.php';

function padmin_nav($active = 'ticketing') {
    $p = function_exists('admin_current_profile') ? admin_current_profile() : null;
    $role = $p['role'] ?? '';
    $t = $active === 'ticketing' ? ' class="active"' : '';
    $st = $active === 'store' ? ' class="active"' : ''; ?>
<nav class="admin-nav">
  <div class="brand">
    Exit 473
    <span>Admin Portal</span>
  </div>
  <div class="nav-links-admin">
    <a href="/admin/dashboard.php">Dashboard</a>
    <?php if (in_array($role, ['site_owner', 'content_manager'], true)): ?>
      <a href="/admin/events.php">Events</a>
      <a href="/portal/admin/events.php"<?= $t ?>>🎟️ Ticketing</a>
      <a href="/portal/admin/store.php"<?= $st ?>>🛍️ Store</a>
      <a href="/admin/businesses.php">Businesses</a>
      <a href="/admin/spotlight.php">✨ Spotlight</a>
      <a href="/admin/calypso-artists.php">Artists</a>
      <a href="/admin/calypso-tents.php">🎪 Tents</a>
      <a href="/admin/artist-uploads.php">📥 Uploads</a>
    <?php else: ?>
      <a href="/portal/admin/events.php"<?= $t ?>>🎟️ Ticketing</a>
    <?php endif; ?>
    <?php if ($role === 'finance'): ?>
      <a href="/portal/admin/store-payouts.php"<?= $st ?>>🛍️ Store Payouts</a>
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

/** Gate a portal admin page to the core admin session and (optionally) roles. */
function padmin_require(array $roles = []) {
    if (!admin_access_token()) { header('Location: /admin/index.php'); exit; }
    if ($roles) {
        $p = admin_current_profile();
        if (!$p || !in_array($p['role'], $roles, true)) { http_response_code(403); die('You do not have permission to view this page.'); }
    }
}

/** Store section tabs (shown under the page title on every store admin page). */
function padmin_store_tabs($current) {
    $tabs = ['store.php' => 'Stores & Merchants', 'store-products.php' => 'Products', 'store-orders.php' => 'Orders', 'store-payouts.php' => 'Payouts', 'store-settings.php' => 'Settings']; ?>
  <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin:-.5rem 0 1.5rem">
    <?php foreach ($tabs as $f => $label): ?>
      <a href="<?= $f ?>" class="btn btn-sm <?= $current === $f ? 'btn-primary' : 'btn-outline' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
<?php }

function padmin_store_not_ready() { ?>
  <div class="panel"><h2 class="panel-title">Store database not set up yet</h2>
  <p>Run <code>portal/schema_06_store.sql</code> in the Supabase SQL editor, then reload this page.</p></div>
<?php }
