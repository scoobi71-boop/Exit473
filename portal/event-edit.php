<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/resend.php';
require_once __DIR__ . '/tickets.php';
require_once __DIR__ . '/event_media.php';
require_once __DIR__ . '/admin_look.php';
portal_require_auth();

$token = portal_access_token();
$eventId = $_GET['id'] ?? '';
$event = null;
$ticketTypes = [];
$error = '';
$msg = '';

function load_event($id, $token) {
    if (!$id) return null;
    $resp = supabase_table_select('events', 'id=eq.' . urlencode($id) . '&select=*', $token);
    return ($resp['status'] === 200 && !empty($resp['data'][0])) ? $resp['data'][0] : null;
}

function load_ticket_types($eventId, $token) {
    $resp = supabase_table_select('ticket_types', 'event_id=eq.' . urlencode($eventId) . '&select=*&order=created_at.asc', $token);
    return ($resp['status'] === 200) ? $resp['data'] : [];
}

/** Ensures a slug is unique by appending -2, -3, ... as needed. */
function unique_slug($base, $excludeId = null) {
    $slug = $base;
    for ($i = 2; $i < 50; $i++) {
        $query = 'slug=eq.' . urlencode($slug) . '&select=id';
        $resp = supabase_table_select('events', $query, null, true);
        $rows = ($resp['status'] === 200) ? $resp['data'] : [];
        $conflict = false;
        foreach ($rows as $r) {
            if ($r['id'] !== $excludeId) { $conflict = true; break; }
        }
        if (!$conflict) return $slug;
        $slug = $base . '-' . $i;
    }
    return $base . '-' . bin2hex(random_bytes(3));
}

function to_utc_iso($localDatetime) {
    if (!$localDatetime) return null;
    try {
        $dt = new DateTime($localDatetime, new DateTimeZone('America/Grenada'));
        return $dt->format(DateTime::ATOM);
    } catch (Exception $e) {
        return null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    portal_check_csrf();
    $action = $_POST['action'] ?? '';

    // Banner / poster actions (see event_media.php). Organizer RLS only
    // allows updates that send the event back to 'pending' for review.
    if (in_array($action, ['upload_banner', 'remove_banner', 'add_posters', 'remove_poster', 'move_poster'], true)) {
        $eventId = $_POST['event_id'] ?? '';
        $mediaEvent = load_event($eventId, $token);
        $wasPublished = $mediaEvent && $mediaEvent['status'] === 'published';
        $mr = media_handle_action($action, $mediaEvent, $token, false, ['status' => 'pending']);
        if (isset($mr['error'])) $error = $mr['error'];
        elseif (!empty($mr['ok'])) $msg = $mr['ok'] . ($wasPublished ? ' The event goes back to admin review before it\'s public again.' : '');
    }

    if ($action === 'save_event') {
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $venue       = trim($_POST['venue'] ?? '');
        $startAt     = to_utc_iso($_POST['start_at'] ?? '');
        $endAt       = to_utc_iso($_POST['end_at'] ?? '');
        $coverUrl    = trim($_POST['cover_image_url'] ?? '');
        $postedId    = $_POST['id'] ?? '';

        if ($title === '' || !$startAt) {
            $error = 'Title and start date/time are required.';
        } else {
            $payload = [
                'title'           => $title,
                'description'     => $description ?: null,
                'venue'           => $venue ?: null,
                'start_at'        => $startAt,
                'end_at'          => $endAt,
                'cover_image_url' => $coverUrl ?: null,
                'status'          => 'pending',
            ];

            if ($postedId) {
                $prev = load_event($postedId, $token);
                $resp = supabase_table_update('events', 'id=eq.' . urlencode($postedId), $payload, $token);
                if ($resp['status'] === 200 && !empty($resp['data'][0])) {
                    header('Location: event-edit.php?id=' . urlencode($postedId) . '&saved=1&was=' . urlencode($prev['status'] ?? '')); exit;
                }
                $error = 'Could not save changes.';
            } else {
                $payload['organizer_id'] = $_SESSION['portal_user_id'];
                $payload['slug'] = unique_slug(portal_slugify($title));
                $resp = supabase_table_insert('events', $payload, $token);
                if ($resp['status'] === 201 && !empty($resp['data'][0])) {
                    $organizer = portal_current_profile();
                    $organizerLabel = $organizer ? ($organizer['display_name'] ?: $organizer['username']) : ($_SESSION['portal_user_email'] ?? 'an organizer');
                    notify_admin_new_event($title, $organizerLabel);
                    header('Location: event-edit.php?id=' . urlencode($resp['data'][0]['id']) . '&created=1'); exit;
                }
                $error = 'Could not create the event.';
            }
        }
        $eventId = $postedId ?: '';

    } elseif ($action === 'cancel_event') {
        $postedId = $_POST['id'] ?? '';
        supabase_table_update('events', 'id=eq.' . urlencode($postedId), ['status' => 'cancelled'], $token);
        header('Location: dashboard.php?cancelled=1'); exit;

    } elseif ($action === 'add_ticket') {
        $eventId = $_POST['event_id'] ?? '';
        $name  = trim($_POST['ticket_name'] ?? '');
        $price = $_POST['ticket_price'] ?? '';
        $qty   = $_POST['ticket_qty'] ?? '';

        if ($name === '' || !is_numeric($price) || $price < 0 || !ctype_digit((string)$qty) || (int)$qty < 1) {
            $error = 'Enter a valid ticket name, price, and quantity (at least 1).';
        } else {
            $resp = supabase_table_insert('ticket_types', [
                'event_id'       => $eventId,
                'name'           => $name,
                'price_cents'    => (int)round(((float)$price) * 100),
                'quantity_total' => (int)$qty,
            ], $token);
            if ($resp['status'] !== 201) $error = 'Could not add that ticket type.';
        }

    } elseif ($action === 'update_ticket') {
        $eventId  = $_POST['event_id'] ?? '';
        $ticketId = $_POST['ticket_id'] ?? '';
        $price = $_POST['ticket_price'] ?? '';
        $qty   = $_POST['ticket_qty'] ?? '';
        if (!is_numeric($price) || $price < 0 || !ctype_digit((string)$qty) || (int)$qty < 1) {
            $error = 'Enter a valid price and quantity (at least 1).';
        } else {
            // quantity_sold can't be changed here: the database only lets
            // organizers update name, price and total quantity, and rejects
            // a total below what's already sold.
            $resp = supabase_table_update('ticket_types', 'id=eq.' . urlencode($ticketId), [
                'price_cents'    => (int)round(((float)$price) * 100),
                'quantity_total' => (int)$qty,
            ], $token);
            if ($resp['status'] !== 200 || empty($resp['data'][0])) {
                $error = 'Could not update that ticket type. The quantity can\'t be lower than tickets already sold.';
            } else {
                $msg = 'Ticket type updated.';
            }
        }

    } elseif ($action === 'delete_ticket') {
        $ticketId = $_POST['ticket_id'] ?? '';
        $eventId  = $_POST['event_id'] ?? '';
        supabase_table_delete('ticket_types', 'id=eq.' . urlencode($ticketId), $token);
    }
}

if ($eventId) {
    $event = load_event($eventId, $token);
    if ($event) $ticketTypes = load_ticket_types($eventId, $token);
}

$orders = [];
$salesSummary = ['orders' => 0, 'tickets' => 0, 'gross_cents' => 0];
if ($event) {
    $or = supabase_table_select('ticket_orders', 'event_id=eq.' . urlencode($event['id']) . '&status=in.(paid,refunded)&select=*,ticket_order_items(name,quantity)&order=paid_at.desc', $token);
    if ($or['status'] === 200) $orders = $or['data'];
    $salesSummary = tickets_sales_summary([$event['id']], $token)[$event['id']] ?? $salesSummary;
}

if ($event && isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tickets-' . preg_replace('/[^a-z0-9-]/', '', $event['slug']) . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['order', 'status', 'paid_at', 'name', 'email', 'tickets', 'items', 'total_usd']);
    foreach ($orders as $o) {
        $items = implode('; ', array_map(fn($i) => $i['name'] . ' x' . $i['quantity'], $o['ticket_order_items'] ?? []));
        fputcsv($out, [strtoupper(substr(str_replace('-', '', $o['id']), 0, 8)), $o['status'], $o['paid_at'], $o['buyer_name'], $o['buyer_email'], $o['ticket_count'], $items, number_format($o['total_cents'] / 100, 2, '.', '')]);
    }
    fclose($out);
    exit;
}

if (isset($_GET['saved'])) {
    $msg = (($_GET['was'] ?? '') === 'pending' || ($_GET['was'] ?? '') === '')
        ? 'Changes saved. Your event is still waiting for admin approval.'
        : 'Changes saved. Since this event was already reviewed, editing it sends it back for admin approval before it\'s public again.';
}
if (isset($_GET['created'])) $msg = 'Event created — add at least one ticket type below, then it\'ll be queued for admin approval.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $event ? 'Edit Event' : 'Create Event' ?> — Exit 473 Event Portal</title>
<meta name="robots" content="noindex">
<?php admin_look_head(); ?>
<style>
.tt-row { display:flex; gap:.75rem; align-items:center; padding:.6rem 0; border-bottom:1px solid #EAE2E2; flex-wrap:wrap; }
.tt-row:last-child { border-bottom:none; }
.tt-name { flex:1; font-weight:700; min-width:140px; }
.tt-price, .tt-qty { color:#7A6565; font-size:.88rem; }
.review-note { background:#FFF9F0; border:1px solid #F3D9A4; color:#6B4E00; border-radius:8px; padding:.7rem 1rem; font-size:.88rem; margin-bottom:1.25rem; }
</style>
</head>
<body>
<nav class="admin-nav">
  <div class="brand">Exit 473 <span>Event Portal</span></div>
  <div class="nav-links-admin">
    <a href="dashboard.php">My Events</a>
    <a href="event-edit.php"<?= $event ? '' : ' class="active"' ?>>+ New Event</a>
    <a href="events.php" target="_blank">Public Listing</a>
  </div>
  <div class="nav-right">
    <?php if (!empty($_SESSION['portal_user_email'])): ?><span style="color:#7A6565"><?= h($_SESSION['portal_user_email']) ?></span><?php endif; ?>
    <a href="/members.php">Members</a>
    <a href="/index.html" target="_blank">View Site ↗</a>
    <a href="logout.php">Log Out</a>
  </div>
</nav>
<main class="wrap">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
    <h1 class="page-title" style="margin:0"><?= $event ? 'Edit Event' : 'Create Event' ?></h1>
    <div style="display:flex;gap:.5rem;align-items:center">
      <?php if ($event): ?><span class="status-pill status-<?= h($event['status']) ?>"><?= h($event['status']) ?></span>
      <a href="e.php?slug=<?= h($event['slug']) ?>" class="btn btn-outline" target="_blank"><?= $event['status'] === 'published' ? 'View Page ↗' : 'Preview ↗' ?></a><?php endif; ?>
      <a href="dashboard.php" class="btn btn-outline">← My Events</a>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-ok"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

  <?php if ($event): ?>
  <div class="review-note">
    <?php if ($event['status'] === 'pending'): ?>
      ⏳ <strong>Waiting for approval.</strong> You can keep editing this event (details, tickets, banner and posters) until an admin approves it.
    <?php elseif ($event['status'] === 'published'): ?>
      ✅ <strong>Live.</strong> Ticket prices and quantities can be updated directly. Changing the event details, banner or posters sends it back to admin review, and it's hidden until re-approved.
    <?php elseif ($event['status'] === 'rejected'): ?>
      ❌ <strong>Not approved.</strong> Edit and save to resubmit it for review.
    <?php else: ?>
      This event was cancelled.
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="panel" id="event-form">
    <h2 class="panel-title">Event Details</h2>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= h(portal_csrf_token()) ?>">
      <input type="hidden" name="action" value="save_event">
      <input type="hidden" name="id" value="<?= h($event['id'] ?? '') ?>">

      <div class="form-grid">
        <div class="form-row" style="grid-column:1/-1">
          <label for="title">Event Title *</label>
          <input type="text" id="title" name="title" required value="<?= h($event['title'] ?? '') ?>" placeholder="e.g. Spicemas Fete Night">
        </div>
        <div class="form-row">
          <label for="start_at">Starts (Grenada time) *</label>
          <input type="datetime-local" id="start_at" name="start_at" required
                 value="<?= h(!empty($event['start_at']) ? (new DateTime($event['start_at']))->setTimezone(new DateTimeZone('America/Grenada'))->format('Y-m-d\TH:i') : '') ?>">
        </div>
        <div class="form-row">
          <label for="end_at">Ends</label>
          <input type="datetime-local" id="end_at" name="end_at"
                 value="<?= h(!empty($event['end_at']) ? (new DateTime($event['end_at']))->setTimezone(new DateTimeZone('America/Grenada'))->format('Y-m-d\TH:i') : '') ?>">
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label for="venue">Venue</label>
          <input type="text" id="venue" name="venue" value="<?= h($event['venue'] ?? '') ?>" placeholder="e.g. Exit 473, Grand Anse">
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label for="description">Description</label>
          <textarea id="description" name="description" rows="5" placeholder="What's this event about?"><?= h($event['description'] ?? '') ?></textarea>
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label for="cover_image_url">Cover Image URL</label>
          <input type="url" id="cover_image_url" name="cover_image_url" value="<?= h($event['cover_image_url'] ?? '') ?>" placeholder="https://...">
          <span class="hint">Optional. A link to an image hosted elsewhere. To upload images, use Banner &amp; Posters below.</span>
        </div>
      </div>

      <div style="display:flex;gap:.75rem;margin-top:1.25rem;flex-wrap:wrap">
        <button class="btn btn-primary" type="submit">💾 <?= $event ? 'Save Changes' : 'Create Event' ?></button>
        <a href="dashboard.php" class="btn btn-outline">Cancel</a>
      </div>
    </form>
  </div>

    <?php if ($event): ?>
    <?= media_render_manager($event, '<input type="hidden" name="_csrf" value="' . h(portal_csrf_token()) . '">', 'event-edit.php?id=' . rawurlencode($event['id'])) ?>
    <?php endif; ?>

    <?php if ($event): ?>
    <div class="portal-card" style="margin-bottom:1.25rem">
      <h2 style="margin-bottom:.25rem">Ticket Types</h2>
      <p class="portal-sub">At least one ticket type is required before this event can be approved.</p>

      <?php if ($ticketTypes): ?>
        <div style="margin-bottom:1.25rem">
          <?php foreach ($ticketTypes as $tt): ?>
            <div class="tt-row">
              <div class="tt-name"><?= h($tt['name']) ?></div>
              <div class="tt-price">$<?= number_format($tt['price_cents'] / 100, 2) ?> <?= h($tt['currency']) ?></div>
              <div class="tt-qty"><?= (int)$tt['quantity_sold'] ?> / <?= (int)$tt['quantity_total'] ?> sold<?= (int)$tt['quantity_sold'] >= (int)$tt['quantity_total'] ? ' · <strong>Sold out</strong>' : '' ?></div>
              <form method="post" style="display:flex;gap:.35rem;align-items:center">
                <input type="hidden" name="_csrf" value="<?= h(portal_csrf_token()) ?>">
                <input type="hidden" name="action" value="update_ticket">
                <input type="hidden" name="event_id" value="<?= h($event['id']) ?>">
                <input type="hidden" name="ticket_id" value="<?= h($tt['id']) ?>">
                <input type="number" name="ticket_price" aria-label="Price (USD)" value="<?= h(number_format($tt['price_cents'] / 100, 2, '.', '')) ?>" min="0" step="0.01" style="width:90px">
                <input type="number" name="ticket_qty" aria-label="Total quantity" value="<?= (int)$tt['quantity_total'] ?>" min="<?= max(1, (int)$tt['quantity_sold']) ?>" step="1" style="width:80px">
                <button type="submit" class="btn btn-sm btn-outline">Update</button>
              </form>
              <?php if ((int)$tt['quantity_sold'] === 0): ?>
              <form method="post" onsubmit="return confirm('Remove this ticket type?')">
                <input type="hidden" name="_csrf" value="<?= h(portal_csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_ticket">
                <input type="hidden" name="event_id" value="<?= h($event['id']) ?>">
                <input type="hidden" name="ticket_id" value="<?= h($tt['id']) ?>">
                <button type="submit" class="btn btn-sm btn-outline">Remove</button>
              </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p style="color:var(--text-light);margin-bottom:1.25rem">No ticket types yet.</p>
      <?php endif; ?>

      <form method="post" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
        <input type="hidden" name="_csrf" value="<?= h(portal_csrf_token()) ?>">
        <input type="hidden" name="action" value="add_ticket">
        <input type="hidden" name="event_id" value="<?= h($event['id']) ?>">
        <div class="portal-form-row" style="margin-bottom:0;flex:2;min-width:160px">
          <label for="ticket_name">Ticket Name</label>
          <input type="text" id="ticket_name" name="ticket_name" required placeholder="e.g. General Admission">
        </div>
        <div class="portal-form-row" style="margin-bottom:0;flex:1;min-width:100px">
          <label for="ticket_price">Price (USD)</label>
          <input type="number" id="ticket_price" name="ticket_price" required min="0" step="0.01" placeholder="25.00">
        </div>
        <div class="portal-form-row" style="margin-bottom:0;flex:1;min-width:100px">
          <label for="ticket_qty">Quantity</label>
          <input type="number" id="ticket_qty" name="ticket_qty" required min="1" step="1" placeholder="100">
        </div>
        <button class="btn btn-outline" type="submit" style="height:42px">+ Add Ticket Type</button>
      </form>
    </div>

    <div class="portal-card" style="margin-bottom:1.25rem">
      <h2 style="margin-bottom:.25rem">Ticket Sales</h2>
      <p class="portal-sub"><?= (int)$salesSummary['tickets'] ?> tickets sold across <?= (int)$salesSummary['orders'] ?> paid orders · <?= tickets_money($salesSummary['gross_cents']) ?> gross (before Stripe fees)</p>
      <?php if ($orders): ?>
        <p><a class="btn btn-sm btn-outline" href="event-edit.php?id=<?= h($event['id']) ?>&export=csv">Download CSV</a></p>
        <div style="overflow-x:auto">
        <table class="data-table">
          <tr><th>Order</th><th>Buyer</th><th>Tickets</th><th>Total</th><th>Status</th></tr>
          <?php foreach ($orders as $o): ?>
          <tr>
            <td><?= h(strtoupper(substr(str_replace('-', '', $o['id']), 0, 8))) ?></td>
            <td><?= h($o['buyer_name'] ?: '') ?><br><span style="color:var(--text-light)"><?= h($o['buyer_email']) ?></span></td>
            <td><?= h(implode(', ', array_map(fn($i) => $i['name'] . ' × ' . $i['quantity'], $o['ticket_order_items'] ?? []))) ?></td>
            <td><?= tickets_money($o['total_cents']) ?></td>
            <td><?= h($o['status']) ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
        </div>
      <?php else: ?>
        <p style="color:var(--text-light)">No sales yet.</p>
      <?php endif; ?>
    </div>

    <?php if ($event['status'] !== 'cancelled'): ?>
    <div class="portal-card">
      <h2 style="margin-bottom:.5rem">Cancel This Event</h2>
      <p class="portal-sub">This takes the event offline permanently. This can't be undone from here.</p>
      <form method="post" onsubmit="return confirm('Cancel this event? This cannot be undone.')">
        <input type="hidden" name="_csrf" value="<?= h(portal_csrf_token()) ?>">
        <input type="hidden" name="action" value="cancel_event">
        <input type="hidden" name="id" value="<?= h($event['id']) ?>">
        <button type="submit" class="btn btn-danger">Cancel Event</button>
      </form>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</main>
</body>
</html>
