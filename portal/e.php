<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/tickets.php';

$ticketError = $_SESSION['ticket_error'] ?? '';
$ticketForm  = $_SESSION['ticket_form'] ?? [];
unset($_SESSION['ticket_error']);

$slug = trim($_GET['slug'] ?? '');
$event = null;
$organizer = null;
$ticketTypes = [];
$isPreview = false;

if ($slug) {
    // Public visitors only ever see published events (RLS enforces this for
    // the anon key). If it's not publicly visible, check whether the viewer
    // is logged in as the owning organizer, previewing before approval.
    $resp = supabase_table_select('events', 'slug=eq.' . urlencode($slug) . '&select=*');
    if ($resp['status'] === 200 && !empty($resp['data'][0])) {
        $event = $resp['data'][0];
    } else {
        $token = portal_access_token();
        if ($token) {
            $resp = supabase_table_select('events', 'slug=eq.' . urlencode($slug) . '&select=*', $token);
            if ($resp['status'] === 200 && !empty($resp['data'][0])) {
                $event = $resp['data'][0];
                $isPreview = true;
            }
        }
    }
}

if ($event) {
    $orgResp = supabase_table_select('profiles', 'id=eq.' . urlencode($event['organizer_id']) . '&select=username,display_name');
    if ($orgResp['status'] === 200 && !empty($orgResp['data'][0])) $organizer = $orgResp['data'][0];

    $ttResp = supabase_table_select('ticket_types', 'event_id=eq.' . urlencode($event['id']) . '&select=*&order=price_cents.asc');
    if ($ttResp['status'] === 200) $ticketTypes = $ttResp['data'];
}

function fmt_event_time($iso) {
    if (!$iso) return '';
    try {
        return (new DateTime($iso))->setTimezone(new DateTimeZone('America/Grenada'))->format('l, M j, Y \a\t g:i A');
    } catch (Exception $e) { return ''; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $event ? h($event['title']) . ' — Exit 473 Events' : 'Event Not Found — Exit 473 Events' ?></title>
<?php include 'head_styles.php'; ?>
</head>
<body data-portal>
  <nav class="portal-nav">
    <div class="brand">Exit 473 <span>Event Portal</span></div>
    <div class="portal-nav-links"><a href="events.php">Upcoming Events</a></div>
    <div class="portal-nav-right"><a href="../index.html">Exit 473 ↗</a></div>
  </nav>
  <div class="portal-wrap wide">
    <?php if (!$event): ?>
      <div class="portal-card" style="text-align:center">
        <h1>Event Not Found</h1>
        <p class="portal-sub">This event doesn't exist, or isn't public yet.</p>
      </div>
    <?php else: ?>
      <?php if ($isPreview): ?>
        <div class="portal-alert portal-alert-ok" style="max-width:900px;margin:0 auto 1rem">
          Preview mode — this event is <strong><?= h($event['status']) ?></strong> and only visible to you until it's approved.
        </div>
      <?php endif; ?>

      <div class="portal-card">
        <?php if (!empty($event['cover_image_url'])): ?>
          <img src="<?= h($event['cover_image_url']) ?>" alt="" style="width:100%;max-height:360px;object-fit:cover;border-radius:10px;margin-bottom:1.5rem">
        <?php endif; ?>

        <h1><?= h($event['title']) ?></h1>
        <p class="portal-sub" style="margin-bottom:.25rem"><?= h(fmt_event_time($event['start_at'])) ?></p>
        <?php if (!empty($event['venue'])): ?><p class="portal-sub" style="margin-bottom:.25rem">📍 <?= h($event['venue']) ?></p><?php endif; ?>
        <?php if ($organizer): ?><p class="portal-sub">Hosted by <?= h($organizer['display_name'] ?: $organizer['username']) ?></p><?php endif; ?>

        <?php if (!empty($event['description'])): ?>
          <p style="white-space:pre-line;color:var(--text);margin:1.5rem 0"><?= h($event['description']) ?></p>
        <?php endif; ?>

        <h2 id="tickets" style="font-size:1.1rem;margin:2rem 0 1rem">Tickets</h2>
        <?php
          $eventOver = strtotime($event['end_at'] ?: $event['start_at']) < time();
          $onSale = !$isPreview && $event['status'] === 'published' && !$eventOver;
          $anyLeft = false;
          foreach ($ticketTypes as $tt) if ((int)$tt['quantity_sold'] < (int)$tt['quantity_total']) $anyLeft = true;
        ?>
        <?php if ($ticketError): ?><div class="portal-alert portal-alert-error"><?= h($ticketError) ?></div><?php endif; ?>
        <?php if (isset($_GET['cancelled'])): ?><div class="portal-alert portal-alert-ok">Checkout cancelled, so you haven't been charged. Your seats are released.</div><?php endif; ?>
        <?php if (!$ticketTypes): ?>
          <p style="color:var(--text-light)">No ticket types have been added yet.</p>
        <?php else: ?>
          <form method="post" action="checkout.php">
            <input type="hidden" name="_csrf" value="<?= h(portal_csrf_token()) ?>">
            <input type="hidden" name="slug" value="<?= h($event['slug']) ?>">
          <?php foreach ($ticketTypes as $tt):
            $left = max(0, (int)$tt['quantity_total'] - (int)$tt['quantity_sold']);
            $soldOut = $left === 0;
            $maxPick = min($left, TICKETS_MAX_PER_ORDER);
            $picked = (int)($ticketForm['qty'][$tt['id']] ?? 0);
          ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:.85rem 0;border-bottom:1px solid var(--gray-300)">
              <div>
                <div style="font-weight:700"><?= h($tt['name']) ?></div>
                <div style="color:var(--text-light);font-size:.85rem"><?= $soldOut ? 'Sold out' : ($left <= 20 ? $left . ' left' : 'Available') ?></div>
              </div>
              <div style="display:flex;align-items:center;gap:1rem">
                <div style="font-weight:700"><?= (int)$tt['price_cents'] === 0 ? 'Free' : tickets_money($tt['price_cents']) ?></div>
                <?php if ($soldOut): ?>
                  <span class="status-pill status-cancelled">Sold out</span>
                <?php elseif ($onSale): ?>
                  <select id="qty_<?= h($tt['id']) ?>" name="qty[<?= h($tt['id']) ?>]" aria-label="Quantity of <?= h($tt['name']) ?>" style="padding:.35rem .5rem">
                    <?php for ($i = 0; $i <= $maxPick; $i++): ?><option value="<?= $i ?>"<?= $i === $picked ? ' selected' : '' ?>><?= $i ?></option><?php endfor; ?>
                  </select>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if ($onSale && $anyLeft): ?>
            <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1.25rem;align-items:flex-end">
              <div class="portal-form-row" style="margin-bottom:0;flex:1;min-width:180px">
                <label for="buyer_name">Your name</label>
                <input type="text" id="buyer_name" name="name" maxlength="100" value="<?= h($ticketForm['name'] ?? '') ?>">
              </div>
              <div class="portal-form-row" style="margin-bottom:0;flex:1;min-width:200px">
                <label for="buyer_email">Email (tickets are sent here)</label>
                <input type="email" id="buyer_email" name="email" required maxlength="200" value="<?= h($ticketForm['email'] ?? '') ?>">
              </div>
              <button class="btn btn-primary" type="submit" style="height:42px">Buy Tickets →</button>
            </div>
            <p style="color:var(--text-light);font-size:.8rem;margin-top:.5rem">Up to <?= TICKETS_MAX_PER_ORDER ?> tickets per order. Secure payment by Stripe, in US dollars.</p>
          <?php elseif (!$anyLeft): ?>
            <p style="font-weight:700;margin-top:1rem">This event is sold out.</p>
          <?php elseif ($isPreview): ?>
            <p style="color:var(--text-light);margin-top:1rem">Ticket sales open once this event is approved.</p>
          <?php elseif ($eventOver): ?>
            <p style="color:var(--text-light);margin-top:1rem">This event has ended.</p>
          <?php endif; ?>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
