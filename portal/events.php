<?php
/**
 * portal/events.php
 *
 * Public list of published, upcoming portal events. Uses only anon-key
 * reads (RLS shows published events and their ticket types), the same
 * way e.php does. Each event links to e.php?slug=...
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/site_chrome.php';
require_once __DIR__ . '/tickets.php';

// COMING-SOON SIGN: set to false to remove the "NOT A REAL EVENT" banner above the event cards.
const SHOW_COMING_SOON_SIGN = true;

$nowIso = gmdate('Y-m-d\TH:i:s\Z');
// Upcoming = not yet ended (end_at in future, or no end_at and start_at in future).
$query = 'status=eq.published'
       . '&or=' . rawurlencode('(end_at.gte.' . $nowIso . ',and(end_at.is.null,start_at.gte.' . $nowIso . '))')
       . '&select=*' // * so this keeps working before/after schema_04 (banner_url)
       . '&order=start_at.asc&limit=100';
$resp = supabase_table_select('events', $query);
$loadError = $resp['status'] !== 200;
$events = $loadError ? [] : (array)$resp['data'];

$ticketsByEvent = [];
if ($events) {
    $ids = implode(',', array_map('rawurlencode', array_column($events, 'id')));
    $tt = supabase_table_select('ticket_types', 'event_id=in.(' . $ids . ')&select=event_id,price_cents,quantity_total,quantity_sold');
    if ($tt['status'] === 200) {
        foreach ($tt['data'] as $t) $ticketsByEvent[$t['event_id']][] = $t;
    }
}

function event_price_info($types) {
    if (!$types) return ['label' => '', 'soldOut' => false];
    $avail = array_filter($types, fn($t) => (int)$t['quantity_sold'] < (int)$t['quantity_total']);
    if (!$avail) return ['label' => '', 'soldOut' => true];
    $min = min(array_map(fn($t) => (int)$t['price_cents'], $avail));
    return ['label' => $min === 0 ? 'Free' : 'From ' . tickets_money($min), 'soldOut' => false];
}

function fmt_list_time($iso) {
    if (!$iso) return '';
    try {
        return (new DateTime($iso))->setTimezone(new DateTimeZone('America/Grenada'))->format('D, M j, Y · g:i A');
    } catch (Exception $e) { return ''; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Upcoming Events — Exit 473</title>
<?php include 'head_styles.php'; ?>
<?php site_head_extra(); ?>
<style>
.ev-grid { margin-bottom:.5rem; }
.ev-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:1.25rem; }
.ev-card { display:flex; flex-direction:column; background:#fff; border:1px solid var(--gray-300); border-radius:12px; overflow:hidden; text-decoration:none; color:inherit; transition:box-shadow .15s; }
.ev-card:hover { box-shadow:0 6px 18px rgba(0,0,0,.08); }
.ev-img { width:100%; height:160px; object-fit:cover; background:var(--gray-300); }
.ev-img-ph { display:flex; align-items:center; justify-content:center; font-size:2.5rem; }
.ev-body { padding:1rem 1.1rem 1.2rem; display:flex; flex-direction:column; gap:.3rem; flex:1; }
.ev-title { font-weight:700; font-size:1.05rem; }
.ev-meta { color:var(--text-light); font-size:.88rem; }
.ev-soldout { background:#FFF0F0; color:#990000; border:1px solid #FFAAAA; border-radius:999px; padding:.15rem .6rem; font-size:.8rem; font-weight:700; }
.ev-foot { margin-top:auto; padding-top:.6rem; display:flex; justify-content:space-between; align-items:center; }
</style>
</head>
<body data-portal>
<?php site_nav('events'); ?>
  <main>
<?php site_page_hero("What's On", 'Upcoming Events', 'Live music, cultural showcases, workshops and pop-up dining at Exit 473. Get your tickets online.'); ?>
    <section class="section-sm" aria-labelledby="upcoming-heading">
      <div class="container">
        <div class="section-header" style="margin-bottom:2rem">
          <span class="section-label">Coming Up</span>
          <h2 id="upcoming-heading">Tickets on Sale</h2>
        </div>

      <?php if (SHOW_COMING_SOON_SIGN): /* COMING-SOON SIGN (see flag at top of file) */ ?>
        <div class="coming-soon-sign" role="alert">
          <span class="cs-icon" aria-hidden="true">🚧</span>
          <strong>NOT A REAL EVENT - FEATURE COMING SOON!!!!</strong>
          <span class="cs-icon" aria-hidden="true">🚧</span>
        </div>
        <style>
        .coming-soon-sign { display:flex; align-items:center; justify-content:center; gap:.75rem; text-align:center;
          background:repeating-linear-gradient(135deg,#CC0000 0 22px,#A80000 22px 44px); color:#fff;
          border:4px solid #F5C840; border-radius:14px; padding:1.1rem 1.25rem; margin:0 0 2rem;
          box-shadow:0 10px 30px rgba(204,0,0,.35); }
        .coming-soon-sign strong { font-family:'Playfair Display',Georgia,serif; font-weight:800; letter-spacing:.03em;
          font-size:clamp(1.15rem,3.2vw,2rem); line-height:1.2; text-shadow:0 2px 6px rgba(0,0,0,.35); }
        .coming-soon-sign .cs-icon { font-size:clamp(1.4rem,3.5vw,2.2rem); }
        @media (max-width:520px) { .coming-soon-sign .cs-icon { display:none; } }
        </style>
      <?php endif; ?>

      <?php if ($loadError): ?>
        <div class="portal-alert portal-alert-error">Events couldn't be loaded right now. Please try again shortly.</div>
      <?php elseif (!$events): ?>
        <p style="color:var(--text-light);padding:1.5rem 0">No upcoming events right now. Check back soon!</p>
      <?php else: ?>
        <div class="ev-grid">
          <?php foreach ($events as $ev):
            $info = event_price_info($ticketsByEvent[$ev['id']] ?? []);
          ?>
            <a class="ev-card" href="e.php?slug=<?= h(rawurlencode($ev['slug'])) ?>">
              <?php $cardImg = ($ev['cover_image_url'] ?? '') ?: ($ev['banner_url'] ?? ''); ?>
              <?php if ($cardImg): ?>
                <img class="ev-img" src="<?= h($cardImg) ?>" alt="" loading="lazy">
              <?php else: ?>
                <div class="ev-img ev-img-ph" aria-hidden="true">🎶</div>
              <?php endif; ?>
              <div class="ev-body">
                <div class="ev-title"><?= h($ev['title']) ?></div>
                <div class="ev-meta"><?= h(fmt_list_time($ev['start_at'])) ?></div>
                <?php if (!empty($ev['venue'])): ?><div class="ev-meta">📍 <?= h($ev['venue']) ?></div><?php endif; ?>
                <div class="ev-foot">
                  <?php if ($info['soldOut']): ?>
                    <span class="ev-soldout">Sold out</span>
                  <?php else: ?>
                    <span style="font-weight:700"><?= h($info['label']) ?></span>
                  <?php endif; ?>
                  <span class="btn btn-sm btn-outline"><?= $info['soldOut'] ? 'Details' : 'Get Tickets' ?></span>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      </div>
    </section>

    <!-- CTA -->
    <section class="section cta-section" aria-labelledby="events-cta-heading">
      <div class="container">
        <h2 id="events-cta-heading">Hosting an Event at Exit 473?</h2>
        <p>Create your event page and sell tickets online through the Exit 473 Event Portal.</p>
        <div class="cta-actions">
          <a href="/portal/register.php" class="btn btn-primary btn-lg">🎤 Become an Organizer</a>
          <a href="/events.html" class="btn btn-outline-white btn-lg">📅 Full Events Calendar</a>
        </div>
      </div>
    </section>
  </main>
<?php site_footer(); ?>
</body>
</html>
