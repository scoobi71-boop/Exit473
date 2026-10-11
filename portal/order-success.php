<?php
/**
 * portal/order-success.php
 *
 * Where Stripe sends the buyer after paying (?checkout=<Stripe session id>), or where
 * checkout.php sends free orders (?order=...). Read-only: the order is
 * marked paid by the webhook, so this may briefly show "processing".
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/site_chrome.php';
require_once __DIR__ . '/tickets.php';

// ?checkout= is current; ?session_id= kept for sessions created before the 406 fix.
$sessionId = $_GET['checkout'] ?? ($_GET['session_id'] ?? '');
$orderParam = $_GET['order'] ?? '';
$order = null;

if (preg_match('/^cs_(test|live)_[A-Za-z0-9]+$/', $sessionId)) {
    $r = supabase_table_select('ticket_orders', 'stripe_session_id=eq.' . urlencode($sessionId) . '&select=id', null, true);
    if ($r['status'] === 200 && !empty($r['data'][0])) $order = tickets_load_order($r['data'][0]['id']);
} elseif (preg_match('/^[0-9a-f-]{36}$/i', $orderParam)) {
    // Free orders only; paid orders are looked up by Stripe session id.
    $o = tickets_load_order($orderParam);
    if ($o && (int)$o['total_cents'] === 0) $order = $o;
}
$ev = $order['events'] ?? null;
$ref = $order ? strtoupper(substr(str_replace('-', '', $order['id']), 0, 8)) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Your Tickets — Exit 473 Events</title>
<?php if ($order && $order['status'] === 'pending'): ?><meta http-equiv="refresh" content="5"><?php endif; ?>
<?php include 'head_styles.php'; ?>
<?php site_head_extra(); ?>
</head>
<body data-portal>
<?php site_nav('events'); ?>
  <main class="portal-main">
  <div class="portal-wrap">
    <div class="portal-card" style="text-align:center">
      <?php if (!$order): ?>
        <h1>Order not found</h1>
        <p class="portal-sub">If you just paid, your confirmation email is on its way. Questions? Contact us via the <a href="../contact.html">contact page</a>.</p>
      <?php elseif ($order['status'] === 'paid'): ?>
        <h1>You're all set! 🎉</h1>
        <p class="portal-sub">Order <strong><?= h($ref) ?></strong>: <?= (int)$order['ticket_count'] ?> ticket<?= (int)$order['ticket_count'] === 1 ? '' : 's' ?> for <strong><?= h($ev['title'] ?? '') ?></strong></p>
        <p><?= h(tickets_fmt_time($ev['start_at'] ?? '')) ?><?= !empty($ev['venue']) ? ' · ' . h($ev['venue']) : '' ?></p>
        <p class="portal-sub">A confirmation was sent to <?= h($order['buyer_email']) ?>. Show it at the entrance.</p>
      <?php elseif ($order['status'] === 'pending'): ?>
        <h1>Processing your payment…</h1>
        <p class="portal-sub">This page refreshes automatically. Your confirmation email will arrive shortly.</p>
      <?php else: ?>
        <h1>This order wasn't completed</h1>
        <p class="portal-sub">No payment was taken (or it was refunded). <a href="e.php?slug=<?= h($ev['slug'] ?? '') ?>">Back to the event</a></p>
      <?php endif; ?>
      <?php if ($ev): ?><p style="margin-top:1.5rem"><a class="btn btn-outline" href="e.php?slug=<?= h($ev['slug']) ?>">Back to event</a></p><?php endif; ?>
    </div>
  </div>
  </main>
<?php site_footer(); ?>
</body>
</html>
