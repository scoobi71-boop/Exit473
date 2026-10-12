<?php
/** /shop.php: public store listing, filter by ?category= or ?store=<slug>. */
require_once __DIR__ . '/portal/shop_chrome.php';

$cat = $_GET['category'] ?? '';
if (!isset(STORE_CATEGORIES[$cat])) $cat = '';
$storeSlug = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['store'] ?? ''));
$ready = store_ready();
$products = []; $stores = []; $storeName = '';
if ($ready) {
    $sr = supabase_table_select('stores', 'active=eq.true&select=id,name,slug&order=name.asc');
    $stores = $sr['status'] === 200 ? $sr['data'] : [];
    $q = 'active=eq.true&select=*,stores!inner(name,slug,active)&stores.active=eq.true&order=created_at.desc&limit=200';
    if ($cat) $q .= '&category=eq.' . urlencode($cat);
    if ($storeSlug) $q .= '&stores.slug=eq.' . urlencode($storeSlug);
    $pr = supabase_table_select('products', $q);
    $products = $pr['status'] === 200 ? $pr['data'] : [];
    foreach ($stores as $s) if ($s['slug'] === $storeSlug) $storeName = $s['name'];
}
$qs = fn($k, $v) => '/shop.php' . (($p = http_build_query(array_filter(['category' => $k === 'category' ? $v : $cat, 'store' => $k === 'store' ? $v : $storeSlug]))) ? '?' . $p : '');
shop_head('Shop');
site_page_hero('Shop Local', 'The Exit 473 Shop', 'Grenadian spices, local goods from guest artisans, and Exit 473 merch. Pick up at the park or get it delivered.');
?>
<main>
  <section class="shop-wrap"><div class="container">
    <?php shop_sign(); ?>
    <?php if (!$ready): shop_unavailable(); else: ?>
    <div class="shop-bar">
      <div class="shop-chips" aria-label="Categories">
        <a class="shop-chip<?= $cat === '' ? ' active' : '' ?>" href="<?= h($qs('category', '')) ?>">All</a>
        <?php foreach (STORE_CATEGORIES as $k => $label): ?><a class="shop-chip<?= $cat === $k ? ' active' : '' ?>" href="<?= h($qs('category', $k)) ?>"><?= h($label) ?></a><?php endforeach; ?>
      </div>
      <?php shop_cart_link(); ?>
    </div>
    <?php if ($stores): ?>
    <div class="shop-chips" style="margin-bottom:1.5rem" aria-label="Shops">
      <span style="color:#7A6565;font-size:.88rem;align-self:center">Shops:</span>
      <a class="shop-chip<?= $storeSlug === '' ? ' active' : '' ?>" href="<?= h($qs('store', '')) ?>">All shops</a>
      <?php foreach ($stores as $s): ?><a class="shop-chip<?= $storeSlug === $s['slug'] ? ' active' : '' ?>" href="<?= h($qs('store', $s['slug'])) ?>"><?= h($s['name']) ?></a><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($storeName): ?><p style="margin:-.5rem 0 1rem"><a href="/shop-store.php?slug=<?= h($storeSlug) ?>">About <?= h($storeName) ?> →</a></p><?php endif; ?>
    <?php if (!$products): ?>
      <p style="color:#7A6565;padding:2rem 0;text-align:center">No products here yet. Check back soon!</p>
    <?php else: ?>
      <div class="p-grid"><?php foreach ($products as $p) shop_card($p); ?></div>
    <?php endif; ?>
    <?php endif; ?>
  </div></section>
</main>
<?php shop_foot();
