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
$order = tickets_order_from_request();
$ev = $order['events'] ?? null;
$ref = $order ? strtoupper(substr(str_replace('-', '', $order['id']), 0, 8)) : '';
$issued = ($order && $order['status'] === 'paid') ? tickets_issued($order) : ['ready' => false, 'tickets' => []];
$pdfQuery = http_build_query(array_intersect_key($_GET, array_flip(['checkout', 'session_id', 'order'])));
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
        <?php if ($issued['tickets']): ?>
          <p class="portal-sub">Your tickets (one QR code per person) were emailed to <?= h($order['buyer_email']) ?>. You can also download them here:</p>
          <p><a class="btn btn-primary" href="ticket-pdf.php?<?= h($pdfQuery) ?>">⬇ Download tickets (PDF)</a></p>
        <?php else: ?>
          <p class="portal-sub">A confirmation was sent to <?= h($order['buyer_email']) ?>. Show it at the entrance.</p>
        <?php endif; ?>
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
