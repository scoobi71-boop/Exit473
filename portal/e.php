<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/site_chrome.php';
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
<?php site_head_extra(); ?>
<style>
.ev-hero { position:relative; max-width:900px; margin:0 auto 1.25rem; min-height:340px; border-radius:14px; overflow:hidden; background:#222 center/cover no-repeat; display:flex; align-items:flex-end; box-shadow:0 10px 30px rgba(0,0,0,.12); }
.ev-hero-overlay { width:100%; padding:2rem 2rem 1.75rem; color:#fff; background:linear-gradient(to top, rgba(0,0,0,.82), rgba(0,0,0,.45) 60%, rgba(0,0,0,0)); }
.ev-hero-overlay h1 { color:#fff; font-size:2.2rem; line-height:1.15; margin:0 0 .5rem; text-shadow:0 2px 8px rgba(0,0,0,.4); }
.ev-hero-overlay p { margin:.15rem 0; font-size:1.02rem; opacity:.95; }
.ev-hero-cta { margin-top:.9rem; display:inline-block; }
.ev-posters { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:.85rem; }
.ev-poster { padding:0; border:0; background:none; cursor:zoom-in; border-radius:10px; overflow:hidden; box-shadow:0 4px 14px rgba(0,0,0,.1); }
.ev-poster img { display:block; width:100%; aspect-ratio:3/4; object-fit:cover; transition:transform .2s; }
.ev-poster:hover img, .ev-poster:focus img { transform:scale(1.03); }
.ev-lightbox { position:fixed; inset:0; z-index:1000; background:rgba(0,0,0,.9); display:flex; align-items:center; justify-content:center; padding:1rem; }
.ev-lightbox[hidden] { display:none; }
.ev-lightbox img { max-width:100%; max-height:92vh; object-fit:contain; border-radius:6px; }
.ev-lb-close { position:absolute; top:.6rem; right:.9rem; font-size:2.4rem; color:#fff; background:none; border:0; cursor:pointer; line-height:1; }
.ev-lb-nav { position:absolute; top:50%; transform:translateY(-50%); font-size:3rem; color:#fff; background:rgba(0,0,0,.35); border:0; border-radius:50%; width:3rem; height:3rem; line-height:2.6rem; cursor:pointer; }
.ev-lb-prev { left:.6rem; } .ev-lb-next { right:.6rem; }
@media (max-width:640px) {
  .ev-hero { min-height:240px; border-radius:10px; }
  .ev-hero-overlay { padding:1.25rem 1.1rem 1.1rem; }
  .ev-hero-overlay h1 { font-size:1.55rem; }
  .ev-hero-overlay p { font-size:.92rem; }
  .ev-posters { grid-template-columns:repeat(2,1fr); gap:.6rem; }
}
</style>
</head>
<body data-portal>
<?php site_nav('events'); ?>
  <main class="portal-main">
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

      <?php
        $heroImg = ($event['banner_url'] ?? '') ?: ($event['cover_image_url'] ?? '');
        $posters = array_values(array_filter((array)(is_string($event['poster_urls'] ?? null) ? json_decode($event['poster_urls'], true) : ($event['poster_urls'] ?? [])), 'is_string'));
      ?>
      <?php if ($heroImg): ?>
        <header class="ev-hero" style="background-image:url('<?= h($heroImg) ?>')">
          <div class="ev-hero-overlay">
            <h1><?= h($event['title']) ?></h1>
            <p>📅 <?= h(fmt_event_time($event['start_at'])) ?></p>
            <?php if (!empty($event['venue'])): ?><p>📍 <?= h($event['venue']) ?></p><?php endif; ?>
            <?php if ($ticketTypes): ?><a class="btn btn-primary ev-hero-cta" href="#tickets">Get Tickets</a><?php endif; ?>
          </div>
        </header>
      <?php endif; ?>

      <div class="portal-card">
        <?php if (!$heroImg): ?>
        <h1><?= h($event['title']) ?></h1>
        <p class="portal-sub" style="margin-bottom:.25rem"><?= h(fmt_event_time($event['start_at'])) ?></p>
        <?php if (!empty($event['venue'])): ?><p class="portal-sub" style="margin-bottom:.25rem">📍 <?= h($event['venue']) ?></p><?php endif; ?>
        <?php endif; ?>
        <?php if ($organizer): ?><p class="portal-sub">Hosted by <?= h($organizer['display_name'] ?: $organizer['username']) ?></p><?php endif; ?>

        <?php if (!empty($event['description'])): ?>
          <p style="white-space:pre-line;color:var(--text);margin:1.5rem 0"><?= h($event['description']) ?></p>
        <?php endif; ?>

        <?php if ($posters): ?>
          <h2 style="font-size:1.1rem;margin:2rem 0 1rem">Posters</h2>
          <div class="ev-posters">
            <?php foreach ($posters as $i => $p): ?>
              <button type="button" class="ev-poster" data-full="<?= h($p) ?>" aria-label="Enlarge poster <?= $i + 1 ?>">
                <img src="<?= h($p) ?>" alt="Event poster <?= $i + 1 ?>" loading="lazy">
              </button>
            <?php endforeach; ?>
          </div>
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
  </main>
<?php site_footer(); ?>
  <div class="ev-lightbox" id="evLightbox" hidden role="dialog" aria-modal="true" aria-label="Poster">
    <button type="button" class="ev-lb-close" aria-label="Close">×</button>
    <button type="button" class="ev-lb-nav ev-lb-prev" aria-label="Previous">‹</button>
    <img alt="">
    <button type="button" class="ev-lb-nav ev-lb-next" aria-label="Next">›</button>
  </div>
  <script>
  (function () {
    var thumbs = Array.prototype.slice.call(document.querySelectorAll('.ev-poster'));
    var lb = document.getElementById('evLightbox');
    if (!thumbs.length || !lb) return;
    var img = lb.querySelector('img'), idx = 0;
    function show(i) { idx = (i + thumbs.length) % thumbs.length; img.src = thumbs[idx].getAttribute('data-full'); lb.hidden = false; document.body.style.overflow = 'hidden'; }
    function close() { lb.hidden = true; img.src = ''; document.body.style.overflow = ''; thumbs[idx].focus(); }
    thumbs.forEach(function (t, i) { t.addEventListener('click', function () { show(i); }); });
    lb.querySelector('.ev-lb-close').addEventListener('click', close);
    lb.querySelector('.ev-lb-prev').addEventListener('click', function (e) { e.stopPropagation(); show(idx - 1); });
    lb.querySelector('.ev-lb-next').addEventListener('click', function (e) { e.stopPropagation(); show(idx + 1); });
    lb.addEventListener('click', function (e) { if (e.target === lb) close(); });
    document.addEventListener('keydown', function (e) {
      if (lb.hidden) return;
      if (e.key === 'Escape') close(); else if (e.key === 'ArrowLeft') show(idx - 1); else if (e.key === 'ArrowRight') show(idx + 1);
    });
    if (thumbs.length < 2) lb.querySelectorAll('.ev-lb-nav').forEach(function (b) { b.hidden = true; });
  })();
  </script>
</body>
</html>
