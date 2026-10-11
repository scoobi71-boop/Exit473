<?php
require_once __DIR__ . '/auth.php';

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

        <h2 style="font-size:1.1rem;margin:2rem 0 1rem">Tickets</h2>
        <?php if (!$ticketTypes): ?>
          <p style="color:var(--text-light)">No ticket types have been added yet.</p>
        <?php else: ?>
          <?php foreach ($ticketTypes as $tt):
            $soldOut = (int)$tt['quantity_sold'] >= (int)$tt['quantity_total'];
          ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:.85rem 0;border-bottom:1px solid var(--gray-300)">
              <div>
                <div style="font-weight:700"><?= h($tt['name']) ?></div>
                <div style="color:var(--text-light);font-size:.85rem"><?= $soldOut ? 'Sold out' : ((int)$tt['quantity_total'] - (int)$tt['quantity_sold']) . ' left' ?></div>
              </div>
              <div style="display:flex;align-items:center;gap:1rem">
                <div style="font-weight:700">$<?= number_format($tt['price_cents'] / 100, 2) ?></div>
                <button class="btn btn-outline btn-sm" disabled title="Ticket sales are coming soon">Buy — Coming Soon</button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
