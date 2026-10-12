<?php
/** Store admin: stores / merchants (house or consignment), commission, active. */
require_once __DIR__ . '/../../admin/auth.php';
require_once __DIR__ . '/../supabase.php';
require_once __DIR__ . '/../store_lib.php';
require_once __DIR__ . '/../event_media.php';
require_once __DIR__ . '/_chrome.php';
padmin_require(['site_owner', 'content_manager']);

$ready = store_ready();
$msg = ''; $err = '';
if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = $_POST['id'] ?? '';
    if (($_POST['action'] ?? '') === 'save') {
        $name = trim($_POST['name'] ?? '');
        $type = ($_POST['type'] ?? '') === 'house' ? 'house' : 'consignment';
        $pct  = round((float)($_POST['commission_percent'] ?? 20), 2);
        $email = trim($_POST['contact_email'] ?? '');
        if ($name === '') $err = 'Name is required.';
        elseif ($pct < 0 || $pct > 100) $err = 'Commission must be between 0 and 100%.';
        elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Contact email is not valid.';
        else {
            $row = ['name' => $name, 'description' => trim($_POST['description'] ?? '') ?: null, 'contact_email' => $email ?: null,
                    'type' => $type, 'commission_percent' => $pct, 'active' => !empty($_POST['active']), 'updated_at' => gmdate('c')];
            $logoUrl = trim($_POST['logo_url'] ?? '');
            if ($logoUrl === '' || preg_match('#^(https://|/images/)#', $logoUrl)) $row['logo_url'] = $logoUrl ?: null;
            if ($id) {
                $r = supabase_table_update('stores', 'id=eq.' . urlencode($id), $row, null, true);
            } else {
                $base = portal_slugify($name) ?: 'shop'; $slug = $base;
                for ($i = 2; $i < 50; $i++) { $c = supabase_table_select('stores', 'slug=eq.' . urlencode($slug) . '&select=id', null, true); if (empty($c['data'])) break; $slug = $base . '-' . $i; }
                $row['slug'] = $slug;
                $r = supabase_table_insert('stores', $row, null, true);
            }
            if (in_array($r['status'], [200, 201], true) && !empty($r['data'][0])) {
                $sid = $r['data'][0]['id'];
                if (!empty($_FILES['logo']['name'])) {
                    $u = media_store_upload($_FILES['logo'], $sid, 'logo');
                    if (isset($u['error'])) $err = 'Saved, but the logo upload failed: ' . $u['error'];
                    else { if (!empty($r['data'][0]['logo_url'])) media_delete_url($r['data'][0]['logo_url'], $sid); supabase_table_update('stores', 'id=eq.' . urlencode($sid), ['logo_url' => $u['url']], null, true); }
                }
                if (!$err) { header('Location: store.php?saved=1'); exit; }
            } else $err = 'Could not save the store (HTTP ' . $r['status'] . ').';
        }
    }
}
if (isset($_GET['saved'])) $msg = 'Store saved.';
$stores = []; $counts = []; $editing = null;
if ($ready) {
    $r = supabase_table_select('stores', 'select=*&order=type.asc,name.asc', null, true);
    $stores = $r['status'] === 200 ? $r['data'] : [];
    $pr = supabase_table_select('products', 'select=store_id,active,stock_qty', null, true);
    foreach (($pr['status'] === 200 ? $pr['data'] : []) as $p) { $counts[$p['store_id']]['all'] = ($counts[$p['store_id']]['all'] ?? 0) + 1; if ($p['active']) $counts[$p['store_id']]['active'] = ($counts[$p['store_id']]['active'] ?? 0) + 1; }
    foreach ($stores as $s) if ($s['id'] === ($_GET['edit'] ?? '')) $editing = $s;
}
$showForm = $editing || isset($_GET['new']) || ($err && $_SERVER['REQUEST_METHOD'] === 'POST');
$f = $editing ?: ($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : ['type' => 'consignment', 'commission_percent' => 20, 'active' => 1]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Store — Exit 473 Admin</title>
<meta name="robots" content="noindex">
<?php admin_look_head(); ?>
<style>.pill{display:inline-block;padding:.15rem .55rem;border-radius:99px;font-size:.72rem;font-weight:700;text-transform:uppercase}.pill-house{background:#FDF6D8;color:#8D6E00}.pill-consignment{background:#E0EEFF;color:#1565C0}.pill-off{background:#ECEFF1;color:#455A64}</style>
</head>
<body>
<?php padmin_nav('store'); ?>
<main class="wrap">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
    <h1 class="page-title" style="margin:0">🛍️ Store</h1>
    <div style="display:flex;gap:.5rem"><a href="/shop.php" target="_blank" class="btn btn-outline">View shop ↗</a><?php if ($ready): ?><a href="?new=1#store-form" class="btn btn-primary">+ Add Store / Merchant</a><?php endif; ?></div>
  </div>
  <?php padmin_store_tabs('store.php'); ?>
  <?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-error"><?= h($err) ?></div><?php endif; ?>
  <?php if (!$ready): padmin_store_not_ready(); else: ?>

  <?php if ($showForm): ?>
  <div class="panel" id="store-form">
    <h2 class="panel-title"><?= $editing ? 'Edit Store' : 'Add Store / Merchant' ?></h2>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= h($editing['id'] ?? '') ?>">
      <div class="form-grid">
        <div class="form-row"><label>Name *</label><input type="text" name="name" required value="<?= h($f['name'] ?? '') ?>" placeholder="e.g. Grenada Spice Lady"></div>
        <div class="form-row"><label>Type</label>
          <select name="type">
            <option value="consignment" <?= ($f['type'] ?? '') === 'consignment' ? 'selected' : '' ?>>Consignment (guest artisan: Exit 473 keeps commission)</option>
            <option value="house" <?= ($f['type'] ?? '') === 'house' ? 'selected' : '' ?>>House (Exit 473's own goods: 100% retained)</option>
          </select></div>
        <div class="form-row"><label>Commission %</label><input type="number" name="commission_percent" min="0" max="100" step="0.5" value="<?= h($f['commission_percent'] ?? 20) ?>"><span class="hint">Share of each sale Exit 473 keeps (consignment only). Changes apply to new orders.</span></div>
        <div class="form-row"><label>Contact email</label><input type="email" name="contact_email" value="<?= h($f['contact_email'] ?? '') ?>" placeholder="merchant@example.com"><span class="hint">Gets a notice for each sale (consignment).</span></div>
        <div class="form-row" style="grid-column:1/-1"><label>Description</label><textarea name="description" rows="3"><?= h($f['description'] ?? '') ?></textarea></div>
        <div class="form-row"><label>Logo</label><input type="file" name="logo" accept="image/jpeg,image/png,image/webp"><span class="hint">JPG/PNG/WebP, up to 5 MB.</span></div>
        <div class="form-row"><label>…or logo URL</label><input type="text" name="logo_url" value="<?= h($f['logo_url'] ?? '') ?>" placeholder="https://..."></div>
        <div class="form-row" style="grid-column:1/-1"><label class="checkbox-label"><input type="checkbox" name="active" value="1" <?= !empty($f['active']) ? 'checked' : '' ?>> Active (shown in the shop)</label></div>
      </div>
      <div style="display:flex;gap:.75rem;margin-top:1.25rem"><button class="btn btn-primary" type="submit">💾 Save Store</button><a href="store.php" class="btn btn-outline">Cancel</a></div>
    </form>
  </div>
  <?php endif; ?>

  <div class="panel">
    <h2 class="panel-title"><?= count($stores) ?> Store<?= count($stores) === 1 ? '' : 's' ?></h2>
    <?php if (!$stores): ?><p style="color:#7A6565;text-align:center;padding:1.5rem">No stores yet. Add Exit 473's own store (House) and any guest artisans (Consignment).</p><?php else: ?>
    <div class="table-wrap"><table class="data-table">
      <thead><tr><th>Store</th><th>Type</th><th>Commission</th><th>Products</th><th>Contact</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($stores as $s): ?>
        <tr>
          <td><strong><?= h($s['name']) ?></strong><br><small style="color:#7A6565">/shop-store.php?slug=<?= h($s['slug']) ?></small></td>
          <td><span class="pill pill-<?= h($s['type']) ?>"><?= h($s['type']) ?></span></td>
          <td><?= $s['type'] === 'house' ? '100% (house)' : store_pct($s['commission_percent']) ?></td>
          <td><?= (int)($counts[$s['id']]['active'] ?? 0) ?> active / <?= (int)($counts[$s['id']]['all'] ?? 0) ?></td>
          <td><?= h($s['contact_email'] ?? '') ?></td>
          <td><?= $s['active'] ? 'Active' : '<span class="pill pill-off">Inactive</span>' ?></td>
          <td style="white-space:nowrap"><a class="btn btn-sm btn-outline" href="?edit=<?= h($s['id']) ?>#store-form">Edit</a> <a class="btn btn-sm btn-outline" href="store-products.php?store=<?= h($s['id']) ?>">Products</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</main>
</body>
</html>
