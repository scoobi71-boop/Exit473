<?php
/** /shop-success.php?checkout=<Stripe session id>: order status after Stripe. */
require_once __DIR__ . '/portal/shop_chrome.php';

$order = store_ready() ? store_order_from_request() : null;
if ($order && $order['status'] === 'paid') { store_cart_clear(); unset($_SESSION['shop_form']); }
$set = $order ? store_settings() : [];
shop_head('Order', '', ($order && $order['status'] === 'pending') ? '<meta http-equiv="refresh" content="5">' : '');
?>
<main>
  <section class="shop-wrap shop-top"><div class="container" style="max-width:720px">
    <?php shop_sign(); ?>
    <div class="shop-panel" style="text-align:center">
    <?php if (!$order): ?>
      <h1>Order not found</h1><p style="color:#7A6565">If you just paid, your confirmation email is on its way.</p>
    <?php elseif ($order['status'] === 'paid'): ?>
      <h1>Thank you! 🎉</h1>
      <p>Order <strong><?= h(store_ref($order['id'])) ?></strong> · <?= store_money($order['total_cents']) ?></p>
      <table style="width:100%;max-width:460px;margin:1rem auto;text-align:left;border-collapse:collapse">
        <?php foreach ($order['store_order_items'] as $it): ?><tr><td style="padding:.3rem 0"><?= h($it['name']) ?> × <?= (int)$it['quantity'] ?></td><td style="text-align:right"><?= store_money($it['line_total_cents']) ?></td></tr><?php endforeach; ?>
        <?php if ($order['fulfillment'] === 'delivery'): ?><tr><td style="padding:.3rem 0">Delivery</td><td style="text-align:right"><?= store_money($order['delivery_fee_cents']) ?></td></tr><?php endif; ?>
      </table>
      <p style="color:#5A4545"><?= $order['fulfillment'] === 'delivery' ? h($set['delivery_note'] ?? '') : h($set['pickup_instructions'] ?? '') . ' We\'ll email you when it\'s ready.' ?></p>
      <p style="color:#7A6565">A receipt was sent to <?= h($order['buyer_email']) ?>.</p>
    <?php elseif ($order['status'] === 'pending'): ?>
      <h1>Processing your payment…</h1><p style="color:#7A6565">This page refreshes automatically.</p>
    <?php else: ?>
      <h1>This order wasn't completed</h1><p style="color:#7A6565">No payment was taken (or it was refunded).</p>
    <?php endif; ?>
      <p style="margin-top:1.25rem"><a class="btn btn-outline" href="/shop.php">Back to the shop</a></p>
    </div>
  </div></section>
</main>
<?php shop_foot();
