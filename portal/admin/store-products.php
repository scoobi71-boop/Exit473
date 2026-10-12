<?php
/** Store admin: products per store (create, edit, images, stock, active). */
require_once __DIR__ . '/../../admin/auth.php';
require_once __DIR__ . '/../supabase.php';
require_once __DIR__ . '/../store_lib.php';
require_once __DIR__ . '/../event_media.php';
require_once __DIR__ . '/_chrome.php';
padmin_require(['site_owner', 'content_manager']);

const PRODUCT_MAX_IMAGES = 8;
$ready = store_ready();
$msg = ''; $err = '';
$storeFilter = preg_match('/^[0-9a-f-]{36}$/i', $_GET['store'] ?? '') ? $_GET['store'] : '';

function load_product($id) {
    $r = supabase_table_select('products', 'id=eq.' . urlencode($id) . '&select=*', null, true);
    return ($r['status'] === 200 && !empty($r['data'][0])) ? $r['data'][0] : null;
}

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? '';
    if ($action === 'save') {
        $name = trim($_POST['name'] ?? '');
        $storeId = $_POST['store_id'] ?? '';
        $price = round((float)($_POST['price'] ?? -1) * 100);
        $stock = (int)($_POST['stock_qty'] ?? -1);
        $cat = $_POST['category'] ?? 'goods';
        if ($name === '' || !preg_match('/^[0-9a-f-]{36}$/i', $storeId)) $err = 'Name and store are required.';
        elseif ($price < 0) $err = 'Enter a price of 0 or more.';
        elseif ($stock < 0) $err = 'Stock can\'t be negative.';
        elseif (!isset(STORE_CATEGORIES[$cat])) $err = 'Choose a category.';
        else {
            $row = ['store_id' => $storeId, 'name' => $name, 'description' => trim($_POST['description'] ?? '') ?: null, 'category' => $cat,
                    'price_cents' => (int)$price, 'stock_qty' => $stock, 'active' => !empty($_POST['active']), 'updated_at' => gmdate('c')];
            $origStock = (int)($_POST['orig_stock'] ?? -1);
            if ($id) {
                // Stock is saved only if no sale changed it since the form was opened, so an edit can't undo a sale.
                unset($row['stock_qty']);
                $r = supabase_table_update('products', 'id=eq.' . urlencode($id), $row, null, true);
                if ($stock !== $origStock && in_array($r['status'], [200, 201], true)) {
                    $sr = supabase_table_update('products', 'id=eq.' . urlencode($id) . '&stock_qty=eq.' . $origStock, ['stock_qty' => $stock], null, true);
                    if ($sr['status'] === 200 && empty($sr['data'])) $err = 'Saved, but stock was NOT changed: an order changed it while you were editing. Check the new number and try again.';
                }
            }
            else {
                $base = portal_slugify($name) ?: 'product'; $slug = $base;
                for ($i = 2; $i < 50; $i++) { $c = supabase_table_select('products', 'slug=eq.' . urlencode($slug) . '&select=id', null, true); if (empty($c['data'])) break; $slug = $base . '-' . $i; }
                $row['slug'] = $slug;
                $r = supabase_table_insert('products', $row, null, true);
            }
            if (in_array($r['status'], [200, 201], true) && !empty($r['data'][0])) {
                $p = $r['data'][0];
                $imgs = store_product_images($p);
                foreach (media_files_list($_FILES['images'] ?? null) as $file) {
                    if (count($imgs) >= PRODUCT_MAX_IMAGES) { $err = 'Saved. Only ' . PRODUCT_MAX_IMAGES . ' images are allowed per product.'; break; }
                    $u = media_store_upload($file, $p['id'], 'product');
                    if (isset($u['error'])) { $err = 'Saved, but an image failed: ' . $u['error']; break; }
                    $imgs[] = $u['url'];
                }
                if ($imgs !== store_product_images($p)) supabase_table_update('products', 'id=eq.' . urlencode($p['id']), ['images' => $imgs], null, true);
                if (!$err) { header('Location: store-products.php?saved=1&store=' . urlencode($storeId) . '&edit=' . urlencode($p['id']) . '#product-form'); exit; }
            } else $err = 'Could not save the product (HTTP ' . $r['status'] . ').';
        }
    } elseif ($action === 'remove_image' || $action === 'first_image') {
        $p = load_product($id);
        $url = (string)($_POST['url'] ?? '');
        if ($p) {
            $imgs = store_product_images($p);
            $k = array_search($url, $imgs, true);
            if ($k !== false) {
                array_splice($imgs, $k, 1);
                if ($action === 'remove_image') media_delete_url($url, $p['id']); else array_unshift($imgs, $url);
                supabase_table_update('products', 'id=eq.' . urlencode($p['id']), ['images' => $imgs], null, true);
            }
            header('Location: store-products.php?store=' . urlencode($p['store_id']) . '&edit=' . urlencode($p['id']) . '#product-form'); exit;
        }
    } elseif ($action === 'toggle') {
        $p = load_product($id);
        if ($p) supabase_table_update('products', 'id=eq.' . urlencode($id), ['active' => !$p['active']], null, true);
        header('Location: store-products.php?store=' . urlencode($storeFilter)); exit;
    }
}
if (isset($_GET['saved'])) $msg = 'Product saved.';

$stores = []; $products = []; $editing = null;
if ($ready) {
    $sr = supabase_table_select('stores', 'select=id,name,type,active&order=name.asc', null, true);
    $stores = $sr['status'] === 200 ? $sr['data'] : [];
    $q = 'select=*,stores(name)&order=created_at.desc';
    if ($storeFilter) $q .= '&store_id=eq.' . urlencode($storeFilter);
    $pr = supabase_table_select('products', $q, null, true);
    $products = $pr['status'] === 200 ? $pr['data'] : [];
    if (!empty($_GET['edit'])) $editing = load_product($_GET['edit']);
}
$showForm = $editing || isset($_GET['new']) || $err;
$f = $editing ?: ($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST + ['price_cents' => round((float)($_POST['price'] ?? 0) * 100)] : ['store_id' => $storeFilter, 'active' => 1, 'category' => 'goods', 'stock_qty' => 0]);
$storeName = '';
foreach ($stores as $s) if ($s['id'] === $storeFilter) $storeName = $s['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Products — Exit 473 Admin</title>
<meta name="robots" content="noindex">
<?php admin_look_head(); ?>
<style>
.thumb{width:48px;height:48px;object-fit:cover;border-radius:6px;background:#F5EEEE;display:inline-flex;align-items:center;justify-content:center}
.imgs{display:flex;gap:.75rem;flex-wrap:wrap;margin-top:.5rem}
.imgs figure{width:120px;text-align:center}
.imgs img{width:120px;height:120px;object-fit:cover;border-radius:8px;border:1px solid #EAE2E2}
.imgs form{display:inline}
.low{color:#B71C1C;font-weight:700}
</style>
</head>
<body>
<?php padmin_nav('store'); ?>
<main class="wrap">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
    <h1 class="page-title" style="margin:0">🛍️ Products<?= $storeName ? ': ' . h($storeName) : '' ?></h1>
    <?php if ($ready && $stores): ?><a href="?new=1&store=<?= h($storeFilter) ?>#product-form" class="btn btn-primary">+ Add Product</a><?php endif; ?>
  </div>
  <?php padmin_store_tabs('store-products.php'); ?>
  <?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-error"><?= h($err) ?></div><?php endif; ?>
  <?php if (!$ready): padmin_store_not_ready(); elseif (!$stores): ?>
    <div class="panel"><p>Add a store first on the <a href="store.php">Stores &amp; Merchants</a> tab.</p></div>
  <?php else: ?>

  <form method="get" style="display:flex;gap:.5rem;align-items:center;margin-bottom:1rem;flex-wrap:wrap">
    <label style="font-weight:600;font-size:.85rem">Store:</label>
    <select name="store" onchange="this.form.submit()" style="padding:.45rem .6rem;border:1.5px solid #EAE2E2;border-radius:7px">
      <option value="">All stores</option>
      <?php foreach ($stores as $s): ?><option value="<?= h($s['id']) ?>" <?= $storeFilter === $s['id'] ? 'selected' : '' ?>><?= h($s['name']) ?></option><?php endforeach; ?>
    </select>
  </form>

  <?php if ($showForm): ?>
  <div class="panel" id="product-form">
    <h2 class="panel-title"><?= $editing ? 'Edit Product' : 'Add Product' ?></h2>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= h($editing['id'] ?? '') ?>">
      <input type="hidden" name="orig_stock" value="<?= (int)($editing['stock_qty'] ?? -1) ?>">
      <div class="form-grid">
        <div class="form-row"><label>Name *</label><input type="text" name="name" required value="<?= h($f['name'] ?? '') ?>" placeholder="e.g. Grenada Nutmeg (whole, 100 g)"></div>
        <div class="form-row"><label>Store *</label><select name="store_id" required><?php foreach ($stores as $s): ?><option value="<?= h($s['id']) ?>" <?= ($f['store_id'] ?? '') === $s['id'] ? 'selected' : '' ?>><?= h($s['name']) ?> (<?= h($s['type']) ?>)</option><?php endforeach; ?></select></div>
        <div class="form-row"><label>Category</label><select name="category"><?php foreach (STORE_CATEGORIES as $k => $l): ?><option value="<?= $k ?>" <?= ($f['category'] ?? '') === $k ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></div>
        <div class="form-row"><label>Price (USD) *</label><input type="number" name="price" required min="0" step="0.01" value="<?= isset($f['price_cents']) ? h(number_format($f['price_cents'] / 100, 2, '.', '')) : '' ?>"></div>
        <div class="form-row"><label>Stock quantity *</label><input type="number" name="stock_qty" required min="0" step="1" value="<?= (int)($f['stock_qty'] ?? 0) ?>"><span class="hint">Units available now. Sales reduce it automatically.</span></div>
        <div class="form-row" style="justify-content:flex-end"><label class="checkbox-label"><input type="checkbox" name="active" value="1" <?= !empty($f['active']) ? 'checked' : '' ?>> Active (shown in the shop)</label></div>
        <div class="form-row" style="grid-column:1/-1"><label>Description</label><textarea name="description" rows="4"><?= h($f['description'] ?? '') ?></textarea></div>
        <div class="form-row" style="grid-column:1/-1"><label>Add images</label><input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp"><span class="hint">JPG/PNG/WebP, up to 5 MB each, max <?= PRODUCT_MAX_IMAGES ?>. The first image is the main photo.</span></div>
      </div>
      <div style="display:flex;gap:.75rem;margin-top:1.25rem"><button class="btn btn-primary" type="submit">💾 Save Product</button><a href="store-products.php?store=<?= h($storeFilter) ?>" class="btn btn-outline">Cancel</a>
      <?php if ($editing): ?><a class="btn btn-outline" target="_blank" href="/shop-product.php?slug=<?= h($editing['slug']) ?>">View in shop ↗</a><?php endif; ?></div>
    </form>
    <?php if ($editing && ($imgs = store_product_images($editing))): ?>
      <h3 style="margin-top:1.5rem">Images (<?= count($imgs) ?>/<?= PRODUCT_MAX_IMAGES ?>)</h3>
      <div class="imgs">
        <?php foreach ($imgs as $i => $u): ?>
          <figure><img src="<?= h($u) ?>" alt="">
            <figcaption>
              <?php if ($i > 0): ?><form method="post"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="first_image"><input type="hidden" name="id" value="<?= h($editing['id']) ?>"><input type="hidden" name="url" value="<?= h($u) ?>"><button class="btn btn-sm btn-outline" type="submit">Make main</button></form><?php else: ?><small>Main photo</small><?php endif; ?>
              <form method="post" onsubmit="return confirm('Remove this image?')"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="remove_image"><input type="hidden" name="id" value="<?= h($editing['id']) ?>"><input type="hidden" name="url" value="<?= h($u) ?>"><button class="btn btn-sm btn-danger" type="submit">Remove</button></form>
            </figcaption></figure>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="panel">
    <h2 class="panel-title"><?= count($products) ?> Product<?= count($products) === 1 ? '' : 's' ?></h2>
    <?php if (!$products): ?><p style="color:#7A6565;text-align:center;padding:1.5rem">No products yet.</p><?php else: ?>
    <div class="table-wrap"><table class="data-table">
      <thead><tr><th></th><th>Product</th><th>Store</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($products as $p): $img = store_product_image($p); ?>
        <tr>
          <td><?php if ($img): ?><img class="thumb" src="<?= h($img) ?>" alt=""><?php else: ?><span class="thumb">🛍️</span><?php endif; ?></td>
          <td><strong><?= h($p['name']) ?></strong></td>
          <td><?= h($p['stores']['name'] ?? '') ?></td>
          <td><?= h(STORE_CATEGORIES[$p['category']] ?? $p['category']) ?></td>
          <td><?= store_money($p['price_cents']) ?></td>
          <td class="<?= (int)$p['stock_qty'] <= 3 ? 'low' : '' ?>"><?= (int)$p['stock_qty'] ?></td>
          <td><span class="status-pill <?= $p['active'] ? 'status-published' : 'status-cancelled' ?>"><?= $p['active'] ? 'active' : 'hidden' ?></span></td>
          <td style="white-space:nowrap">
            <a class="btn btn-sm btn-outline" href="?store=<?= h($storeFilter) ?>&edit=<?= h($p['id']) ?>#product-form">Edit</a>
            <form method="post" style="display:inline"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= h($p['id']) ?>"><button class="btn btn-sm btn-outline" type="submit"><?= $p['active'] ? 'Hide' : 'Show' ?></button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</main>
</body>
</html>
