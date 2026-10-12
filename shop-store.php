<?php
/** /shop-store.php?slug=...: a store / merchant page with its products. */
require_once __DIR__ . '/portal/shop_chrome.php';

$slug = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['slug'] ?? ''));
$s = null; $products = [];
if (store_ready() && $slug !== '') {
    $r = supabase_table_select('stores', 'slug=eq.' . urlencode($slug) . '&active=eq.true&select=*');
    $s = ($r['status'] === 200 && !empty($r['data'][0])) ? $r['data'][0] : null;
    if ($s) {
        $pr = supabase_table_select('products', 'store_id=eq.' . urlencode($s['id']) . '&active=eq.true&select=*,stores(name,slug)&order=created_at.desc');
        $products = $pr['status'] === 200 ? $pr['data'] : [];
    }
}
if (!$s) http_response_code(404);
shop_head($s ? $s['name'] : 'Shop not found');
site_page_hero($s && $s['type'] === 'consignment' ? 'Guest Artisan' : 'Exit 473 Shop', $s ? $s['name'] : 'Shop not found', $s ? store_substr((string)$s['description'], 0, 180) : '');
?>
<main>
  <section class="shop-wrap"><div class="container">
    <?php shop_sign(); ?>
    <div class="shop-bar"><a href="/shop.php">← All products</a><?php shop_cart_link(); ?></div>
    <?php if ($s): ?>
      <?php if (!empty($s['logo_url']) || store_strlen((string)$s['description']) > 180): ?>
      <div class="shop-panel" style="display:flex;gap:1.25rem;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap">
        <?php if (!empty($s['logo_url'])): ?><img src="<?= h($s['logo_url']) ?>" alt="" style="width:90px;height:90px;object-fit:cover;border-radius:50%"><?php endif; ?>
        <div style="flex:1;min-width:220px;white-space:pre-line;color:#4A3A3A"><?= h($s['description']) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($products): ?><div class="p-grid"><?php foreach ($products as $p) shop_card($p); ?></div>
      <?php else: ?><p style="color:#7A6565;text-align:center;padding:2rem 0">No products from this shop yet.</p><?php endif; ?>
    <?php else: ?>
      <div class="shop-panel" style="text-align:center"><p><a href="/shop.php">Browse the shop</a></p></div>
    <?php endif; ?>
  </div></section>
</main>
<?php shop_foot();
