<?php
/** /shop-cart.php: session cart (POST add/update/remove) + checkout form. */
require_once __DIR__ . '/portal/shop_chrome.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    portal_check_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pid = (string)($_POST['product_id'] ?? '');
        $add = max(1, (int)($_POST['qty'] ?? 1));
        $cur = store_cart()[$pid] ?? 0;
        store_cart_set($pid, $cur + $add);
        $_SESSION['shop_flash'] = ['ok', 'Added to your cart.'];
        $back = preg_replace('/[^a-z0-9-]/', '', strtolower($_POST['back'] ?? ''));
        header('Location: ' . ($back ? '/shop-product.php?slug=' . rawurlencode($back) : '/shop-cart.php'), true, 303); exit;
    } elseif ($action === 'update') {
        foreach ((array)($_POST['qty'] ?? []) as $pid => $q) store_cart_set((string)$pid, (int)$q);
    } elseif ($action === 'remove') {
        store_cart_set((string)($_POST['product_id'] ?? ''), 0);
    }
    header('Location: /shop-cart.php', true, 303); exit;
}

$ready = store_ready();
$lines = $ready ? store_cart_lines() : [];
$set = $ready ? store_settings() : [];
$sub = array_sum(array_column($lines, 'line_cents'));
$form = $_SESSION['shop_form'] ?? [];
$err = $_SESSION['shop_error'] ?? ''; unset($_SESSION['shop_error']);
$fulfil = $form['fulfillment'] ?? (!empty($set['pickup_enabled']) ? 'pickup' : 'delivery');
$fee = (int)($set['delivery_fee_cents'] ?? 0);
shop_head('Your Cart');
?>
<style>
.cart { display:grid; grid-template-columns:1.4fr 1fr; gap:1.75rem; align-items:start; }
@media (max-width:900px) { .cart { grid-template-columns:1fr; } }
.cl { display:flex; gap:1rem; align-items:center; padding:.9rem 0; border-bottom:1px solid #F0E8E8; }
.cl img, .cl .ph { width:72px; height:72px; border-radius:10px; object-fit:cover; background:#F5EEEE; display:flex; align-items:center; justify-content:center; font-size:1.8rem; flex-shrink:0; }
.cl-name { flex:1; min-width:0; }
.cl-name a { font-weight:700; color:#1A0505; text-decoration:none; }
.cl-sub { color:#8A7474; font-size:.82rem; }
.cl-price { font-weight:700; white-space:nowrap; }
.sum-row { display:flex; justify-content:space-between; padding:.35rem 0; }
.ful { display:flex; gap:.6rem; flex-wrap:wrap; }
.ful label { flex:1; min-width:140px; border:1.5px solid #E6D8D8; border-radius:10px; padding:.7rem .8rem; cursor:pointer; text-transform:none; letter-spacing:0; font-size:.95rem; color:#2D1E1E; margin:0; }
.ful input { margin-right:.4rem; }
.linkbtn { background:none; border:0; color:#CC0000; cursor:pointer; font-size:.82rem; padding:0; }
</style>
<main>
  <section class="shop-wrap shop-top"><div class="container">
    <?php shop_sign(); ?>
    <div class="shop-bar"><h1 style="margin:0;font-size:clamp(1.6rem,3vw,2.2rem)">Your Cart</h1><a href="/shop.php">← Keep shopping</a></div>
    <?php if ($err): ?><div class="shop-alert shop-alert-error"><?= h($err) ?></div><?php endif; ?>
    <?php if (!$ready): shop_unavailable(); elseif (!$lines): ?>
      <div class="shop-panel" style="text-align:center;padding:3rem 1rem"><p style="font-size:1.1rem">Your cart is empty.</p><p><a class="btn btn-primary" href="/shop.php">Browse the shop</a></p></div>
    <?php else: ?>
    <div class="cart">
      <div class="shop-panel">
        <form method="post" id="cart-form">
          <input type="hidden" name="_csrf" value="<?= h(portal_csrf_token()) ?>">
          <input type="hidden" name="action" value="update">
          <?php foreach ($lines as $l): $p = $l['product']; $img = store_product_image($p); ?>
            <div class="cl">
              <?php if ($img): ?><img src="<?= h($img) ?>" alt=""><?php else: ?><div class="ph">🛍️</div><?php endif; ?>
              <div class="cl-name">
                <a href="/shop-product.php?slug=<?= h(rawurlencode($p['slug'])) ?>"><?= h($p['name']) ?></a>
                <div class="cl-sub"><?= h($p['stores']['name'] ?? '') ?> · <?= store_money($p['price_cents']) ?> each</div>
                <?php if ($l['short']): ?><div class="cl-sub" style="color:#990000">Only <?= (int)$p['stock_qty'] ?> left. Please lower the quantity.</div><?php endif; ?>
                <?php if (!$p['active']): ?><div class="cl-sub" style="color:#990000">No longer available.</div><?php endif; ?>
              </div>
              <input class="shop-qty" type="number" name="qty[<?= h($p['id']) ?>]" value="<?= (int)$l['qty'] ?>" min="0" max="<?= STORE_MAX_PER_LINE ?>" aria-label="Quantity" style="width:70px;padding:.45rem;border:1.5px solid #E6D8D8;border-radius:8px">
              <div class="cl-price"><?= store_money($l['line_cents']) ?></div>
              <button class="linkbtn" type="submit" form="rm-<?= h($p['id']) ?>" aria-label="Remove">✕</button>
            </div>
          <?php endforeach; ?>
          <p style="margin-top:1rem"><button class="btn btn-outline btn-sm" type="submit">Update cart</button></p>
        </form>
        <?php foreach ($lines as $l): ?>
          <form method="post" id="rm-<?= h($l['product']['id']) ?>"><input type="hidden" name="_csrf" value="<?= h(portal_csrf_token()) ?>"><input type="hidden" name="action" value="remove"><input type="hidden" name="product_id" value="<?= h($l['product']['id']) ?>"></form>
        <?php endforeach; ?>
      </div>

      <form class="shop-panel shop-form" method="post" action="/shop-checkout.php">
        <input type="hidden" name="_csrf" value="<?= h(portal_csrf_token()) ?>">
        <h2 style="margin:0 0 .5rem;font-size:1.3rem">Checkout</h2>
        <label>How would you like it?</label>
        <div class="ful">
          <?php if (!empty($set['pickup_enabled'])): ?><label><input type="radio" name="fulfillment" value="pickup" <?= $fulfil === 'pickup' ? 'checked' : '' ?>> 🏪 Pickup <span style="color:#1B5E20;font-weight:700">Free</span></label><?php endif; ?>
          <?php if (!empty($set['delivery_enabled'])): ?><label><input type="radio" name="fulfillment" value="delivery" <?= $fulfil === 'delivery' ? 'checked' : '' ?>> 🚚 Delivery <strong><?= store_money($fee) ?></strong></label><?php endif; ?>
        </div>
        <p id="pickup-note" style="color:#7A6565;font-size:.85rem;margin:.5rem 0 0"><?= h($set['pickup_instructions'] ?? '') ?></p>
        <div id="addr-box">
          <label for="address">Delivery address</label>
          <textarea id="address" name="address" rows="3" placeholder="Street, area, parish"><?= h($form['address'] ?? '') ?></textarea>
          <p style="color:#7A6565;font-size:.85rem;margin:.3rem 0 0"><?= h($set['delivery_note'] ?? '') ?></p>
        </div>
        <label for="name">Your name</label><input type="text" id="name" name="name" required value="<?= h($form['name'] ?? '') ?>" autocomplete="name">
        <label for="email">Email (for your receipt)</label><input type="email" id="email" name="email" required value="<?= h($form['email'] ?? '') ?>" autocomplete="email">
        <label for="phone">Phone (optional)</label><input type="tel" id="phone" name="phone" value="<?= h($form['phone'] ?? '') ?>" autocomplete="tel">
        <div style="margin:1.25rem 0 .75rem">
          <div class="sum-row"><span>Subtotal</span><span><?= store_money($sub) ?></span></div>
          <div class="sum-row" id="fee-row"><span>Delivery</span><span><?= store_money($fee) ?></span></div>
          <div class="sum-row" style="font-weight:800;font-size:1.15rem;border-top:1px solid #F0E8E8;padding-top:.6rem"><span>Total</span><span id="total" data-sub="<?= $sub ?>" data-fee="<?= $fee ?>"><?= store_money($sub) ?></span></div>
        </div>
        <button class="btn btn-primary btn-lg" type="submit" style="width:100%">Pay Securely →</button>
        <p style="color:#7A6565;font-size:.8rem;text-align:center;margin-top:.6rem">You'll pay on Stripe's secure checkout page.</p>
      </form>
    </div>
    <script>
    (function(){
      function upd(){
        var r = document.querySelector('input[name=fulfillment]:checked'); var d = r && r.value === 'delivery';
        document.getElementById('addr-box').style.display = d ? '' : 'none';
        document.getElementById('pickup-note').style.display = d ? 'none' : '';
        document.getElementById('fee-row').style.display = d ? '' : 'none';
        document.getElementById('address').required = d;
        var t = document.getElementById('total'); var c = (+t.dataset.sub) + (d ? +t.dataset.fee : 0);
        t.textContent = 'US$' + (c/100).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
      }
      document.querySelectorAll('input[name=fulfillment]').forEach(function(x){ x.addEventListener('change', upd); }); upd();
    })();
    </script>
    <?php endif; ?>
  </div></section>
</main>
<?php shop_foot();
