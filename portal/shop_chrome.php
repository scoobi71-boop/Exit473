<?php
/** Shared page shell for the public shop pages (/shop*.php): main-site chrome + shop styles. */
require_once __DIR__ . '/auth.php';          // session, h(), CSRF
require_once __DIR__ . '/site_chrome.php';
require_once __DIR__ . '/store_lib.php';

function shop_head($title, $desc = 'Shop local spices, goods and Exit 473 merch from Grand Anse, Grenada.', $extraHead = '') { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?> — Exit 473 Shop</title>
<meta name="description" content="<?= h($desc) ?>">
<link rel="stylesheet" href="/css/styles.css?v=3">
<?php site_head_extra(); ?>
<?= $extraHead ?>
<style>
.shop-wrap { padding:2.5rem 0 4rem; }
.shop-wrap.shop-top { padding-top:calc(var(--nav-h, 72px) + 2.5rem); }
.shop-bar { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; justify-content:space-between; margin-bottom:1.5rem; }
.shop-chips { display:flex; flex-wrap:wrap; gap:.4rem; }
.shop-chip { padding:.4rem .9rem; border-radius:999px; border:1.5px solid #E6D8D8; background:#fff; color:#3D2E2E; font-size:.88rem; font-weight:600; text-decoration:none; }
.shop-chip:hover { border-color:#CC0000; color:#CC0000; }
.shop-chip.active { background:#CC0000; border-color:#CC0000; color:#fff; }
.shop-cartlink { font-weight:700; color:#CC0000; text-decoration:none; white-space:nowrap; }
.p-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:1.25rem; }
.p-card { display:flex; flex-direction:column; background:#fff; border:1px solid #EFE6E6; border-radius:14px; overflow:hidden; text-decoration:none; color:inherit; transition:box-shadow .15s, transform .15s; }
.p-card:hover { box-shadow:0 10px 26px rgba(26,5,5,.10); transform:translateY(-2px); }
.p-img { width:100%; aspect-ratio:1/1; object-fit:cover; background:#F5EEEE; display:flex; align-items:center; justify-content:center; font-size:3rem; }
.p-body { padding:.9rem 1rem 1.1rem; display:flex; flex-direction:column; gap:.25rem; flex:1; }
.p-name { font-weight:700; font-size:1rem; }
.p-store { color:#8A7474; font-size:.82rem; }
.p-foot { margin-top:auto; padding-top:.5rem; display:flex; justify-content:space-between; align-items:center; }
.p-price { font-weight:800; color:#1A0505; }
.p-out { background:#FFF0F0; color:#990000; border:1px solid #FFAAAA; border-radius:999px; padding:.1rem .55rem; font-size:.75rem; font-weight:700; }
.shop-panel { background:#fff; border:1px solid #EFE6E6; border-radius:14px; padding:1.5rem; }
.shop-alert { padding:.75rem 1rem; border-radius:10px; margin-bottom:1rem; font-size:.92rem; }
.shop-alert-error { background:#FFF0F0; border:1px solid #FFAAAA; color:#990000; }
.shop-alert-ok { background:#E8F5E9; border:1px solid #A5D6A7; color:#1B5E20; }
.shop-form label { display:block; font-size:.8rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#5A4545; margin:.8rem 0 .3rem; }
.shop-form input[type=text], .shop-form input[type=email], .shop-form input[type=tel], .shop-form textarea, .shop-form select, .shop-qty { width:100%; padding:.65rem .8rem; border:1.5px solid #E6D8D8; border-radius:8px; font-size:.95rem; font-family:inherit; }
.shop-qty { width:80px; }
.coming-soon-sign { display:flex; align-items:center; justify-content:center; gap:.75rem; text-align:center; background:repeating-linear-gradient(135deg,#CC0000 0 22px,#A80000 22px 44px); color:#fff; border:4px solid #F5C840; border-radius:14px; padding:1.1rem 1.25rem; margin:0 0 2rem; box-shadow:0 10px 30px rgba(204,0,0,.35); }
.coming-soon-sign strong { font-family:'Playfair Display',Georgia,serif; font-weight:800; letter-spacing:.03em; font-size:clamp(1.15rem,3.2vw,2rem); line-height:1.2; text-shadow:0 2px 6px rgba(0,0,0,.35); }
.coming-soon-sign .cs-icon { font-size:clamp(1.4rem,3.5vw,2.2rem); }
@media (max-width:520px) { .coming-soon-sign .cs-icon { display:none; } }
</style>
</head>
<body>
<?php site_nav('shop'); }

/** The "not real yet" banner, controlled by SHOP_SHOW_COMING_SOON_SIGN in portal/store_lib.php. */
function shop_sign() {
    if (!SHOP_SHOW_COMING_SOON_SIGN) return; ?>
    <div class="coming-soon-sign" role="alert"><span class="cs-icon" aria-hidden="true">🚧</span><strong>NOT REAL - SHOP COMING SOON!!!!</strong><span class="cs-icon" aria-hidden="true">🚧</span></div>
<?php }

function shop_cart_link() {
    $n = store_cart_count(); ?>
    <a class="shop-cartlink" href="/shop-cart.php">🛒 Cart<?= $n ? ' (' . (int)$n . ')' : '' ?></a>
<?php }

function shop_card($p) {
    $img = store_product_image($p); $out = (int)$p['stock_qty'] <= 0; ?>
    <a class="p-card" href="/shop-product.php?slug=<?= h(rawurlencode($p['slug'])) ?>">
      <?php if ($img): ?><img class="p-img" src="<?= h($img) ?>" alt="" loading="lazy"><?php else: ?><div class="p-img" aria-hidden="true">🛍️</div><?php endif; ?>
      <div class="p-body">
        <div class="p-name"><?= h($p['name']) ?></div>
        <?php if (!empty($p['stores']['name'])): ?><div class="p-store">by <?= h($p['stores']['name']) ?></div><?php endif; ?>
        <div class="p-foot"><span class="p-price"><?= store_money($p['price_cents']) ?></span><?php if ($out): ?><span class="p-out">Sold out</span><?php endif; ?></div>
      </div>
    </a>
<?php }

function shop_unavailable() { ?>
    <div class="shop-panel" style="text-align:center;padding:3rem 1.5rem"><h2>The shop is opening soon</h2><p style="color:#7A6565">Check back shortly for local spices, goods and Exit 473 merch.</p></div>
<?php }

function shop_foot() { site_footer(); echo "</body>\n</html>\n"; }
