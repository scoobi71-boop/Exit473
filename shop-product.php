<?php
/** /shop-product.php?slug=...: product detail + add to cart. */
require_once __DIR__ . '/portal/shop_chrome.php';

$slug = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['slug'] ?? ''));
$p = null;
if (store_ready() && $slug !== '') {
    $r = supabase_table_select('products', 'slug=eq.' . urlencode($slug) . '&active=eq.true&select=*,stores!inner(name,slug,type,active,description)&stores.active=eq.true');
    $p = ($r['status'] === 200 && !empty($r['data'][0])) ? $r['data'][0] : null;
}
if (!$p) http_response_code(404);
$imgs = $p ? store_product_images($p) : [];
$stock = $p ? (int)$p['stock_qty'] : 0;
$flash = $_SESSION['shop_flash'] ?? null; unset($_SESSION['shop_flash']);
shop_head($p ? $p['name'] : 'Product not found', $p ? store_substr(strip_tags((string)$p['description']), 0, 150) : '');
?>
<style>
.pd { display:grid; grid-template-columns:1.1fr 1fr; gap:2.5rem; align-items:start; }
@media (max-width:820px) { .pd { grid-template-columns:1fr; gap:1.5rem; } }
.pd-main { width:100%; aspect-ratio:1/1; object-fit:cover; border-radius:16px; background:#F5EEEE; display:flex; align-items:center; justify-content:center; font-size:5rem; }
.pd-thumbs { display:flex; gap:.5rem; margin-top:.6rem; flex-wrap:wrap; }
.pd-thumbs img { width:70px; height:70px; object-fit:cover; border-radius:8px; cursor:pointer; border:2px solid transparent; }
.pd-thumbs img.on { border-color:#CC0000; }
.pd h1 { font-size:clamp(1.6rem,3vw,2.3rem); margin:.2rem 0 .4rem; }
.pd-price { font-size:1.6rem; font-weight:800; color:#CC0000; margin:.4rem 0 1rem; }
.pd-desc { color:#4A3A3A; line-height:1.7; white-space:pre-line; margin-bottom:1.25rem; }
</style>
<main>
  <section class="shop-wrap shop-top"><div class="container">
    <?php shop_sign(); ?>
    <div class="shop-bar"><a href="/shop.php">← Back to the shop</a><?php shop_cart_link(); ?></div>
    <?php if ($flash): ?><div class="shop-alert shop-alert-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= h($flash[1]) ?> <?php if ($flash[0] === 'ok'): ?><a href="/shop-cart.php"><strong>View cart →</strong></a><?php endif; ?></div><?php endif; ?>
    <?php if (!$p): ?>
      <div class="shop-panel" style="text-align:center"><h1>Product not found</h1><p><a href="/shop.php">Browse the shop</a></p></div>
    <?php else: ?>
    <div class="pd">
      <div>
        <?php if ($imgs): ?>
          <img class="pd-main" id="pd-main" src="<?= h($imgs[0]) ?>" alt="<?= h($p['name']) ?>">
          <?php if (count($imgs) > 1): ?><div class="pd-thumbs"><?php foreach ($imgs as $i => $u): ?><img src="<?= h($u) ?>" alt="" class="<?= $i === 0 ? 'on' : '' ?>" onclick="document.getElementById('pd-main').src=this.src;document.querySelectorAll('.pd-thumbs img').forEach(function(x){x.classList.remove('on')});this.classList.add('on')"><?php endforeach; ?></div><?php endif; ?>
        <?php else: ?><div class="pd-main" aria-hidden="true">🛍️</div><?php endif; ?>
      </div>
      <div>
        <span class="section-label"><?= h(STORE_CATEGORIES[$p['category']] ?? ucfirst($p['category'])) ?></span>
        <h1><?= h($p['name']) ?></h1>
        <div>by <a href="/shop-store.php?slug=<?= h($p['stores']['slug']) ?>"><?= h($p['stores']['name']) ?></a></div>
        <div class="pd-price"><?= store_money($p['price_cents']) ?></div>
        <?php if ($p['description']): ?><div class="pd-desc"><?= h($p['description']) ?></div><?php endif; ?>
        <?php if ($stock <= 0): ?>
          <p><span class="p-out" style="font-size:.95rem">Sold out</span></p>
        <?php else: ?>
          <form method="post" action="/shop-cart.php" class="shop-form" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap">
            <input type="hidden" name="_csrf" value="<?= h(portal_csrf_token()) ?>">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?= h($p['id']) ?>">
            <input type="hidden" name="back" value="<?= h($p['slug']) ?>">
            <div><label for="qty">Quantity</label><input class="shop-qty" type="number" id="qty" name="qty" value="1" min="1" max="<?= min($stock, STORE_MAX_PER_LINE) ?>"></div>
            <button class="btn btn-primary btn-lg" type="submit">Add to Cart</button>
          </form>
          <p style="color:#7A6565;font-size:.88rem;margin-top:.6rem"><?= $stock <= 5 ? 'Only ' . $stock . ' left' : 'In stock' ?> · Pickup at Exit 473 or delivery</p>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div></section>
</main>
<?php shop_foot();
