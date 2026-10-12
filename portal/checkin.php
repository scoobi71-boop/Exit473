<?php
/**
 * portal/checkin.php?t=<ticket code> — target of every ticket QR code.
 *
 * Placeholder for the future door scanner: logged-in admins, and the
 * organizer who owns the event, see the ticket's status (read-only). Anyone
 * else just sees a generic "valid Exit 473 ticket" notice with no details.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/site_chrome.php';
require_once __DIR__ . '/tickets.php';

$code = strtolower(trim($_GET['t'] ?? ''));
$ticket = null; $order = null; $event = null; $canSee = false;

if (preg_match(TICKET_CODE_RE, $code)) {
    $r = supabase_table_select('issued_tickets', 'code=eq.' . urlencode($code) . '&select=*', null, true);
    if ($r['status'] === 200 && !empty($r['data'][0])) $ticket = $r['data'][0];
}
if ($ticket) {
    $adminTok = $_SESSION['admin_access_token'] ?? '';
    $isAdmin = $adminTok && portal_jwt_expiry($adminTok) > time();
    $isOwner = false;
    if (!$isAdmin && portal_access_token()) {
        $er = supabase_table_select('events', 'id=eq.' . urlencode($ticket['event_id']) . '&select=organizer_id', null, true);
        $isOwner = $er['status'] === 200 && ($er['data'][0]['organizer_id'] ?? null) === ($_SESSION['portal_user_id'] ?? '');
    }
    $canSee = $isAdmin || $isOwner;
    if ($canSee) {
        $order = tickets_load_order($ticket['order_id']);
        $event = $order['events'] ?? null;
        foreach ($order['ticket_order_items'] ?? [] as $it) if ($it['id'] === $ticket['order_item_id']) $ticket['name'] = $it['name'];
    }
}
$labels = ['issued' => ['Valid, not yet checked in', '#1B5E20', '#E8F5E9'], 'checked_in' => ['Already checked in', '#8D6E00', '#FFF8E1'], 'void' => ['VOID (refunded)', '#B71C1C', '#FFEBEE']];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ticket Check-in — Exit 473</title>
<meta name="robots" content="noindex">
<?php include 'head_styles.php'; ?>
<?php site_head_extra(); ?>
</head>
<body data-portal>
<?php site_nav('events'); ?>
  <main class="portal-main">
  <div class="portal-wrap">
    <div class="portal-card" style="text-align:center">
      <?php if (!$ticket): ?>
        <h1>Ticket not found</h1>
        <p class="portal-sub">This QR code isn't a valid Exit 473 ticket.</p>
      <?php elseif (!$canSee): ?>
        <h1>🎟️ Exit 473 Ticket</h1>
        <p class="portal-sub">This is an Exit 473 event ticket. Please show it at the entrance, where staff will scan it.</p>
        <p class="portal-sub" style="font-size:.85rem">Staff: <a href="/members.php">log in</a> to see this ticket's status.</p>
      <?php else: [$lbl, $fg, $bg] = $labels[$ticket['status']] ?? [$ticket['status'], '#333', '#eee']; ?>
        <h1><?= h($event['title'] ?? 'Event') ?></h1>
        <p class="portal-sub"><?= h(tickets_fmt_time($event['start_at'] ?? '')) ?><?= !empty($event['venue']) ? ' · ' . h($event['venue']) : '' ?></p>
        <div style="display:inline-block;padding:.6rem 1.2rem;border-radius:10px;font-weight:800;font-size:1.2rem;color:<?= $fg ?>;background:<?= $bg ?>;margin:.5rem 0 1rem"><?= h($lbl) ?></div>
        <p><strong><?= h($ticket['name'] ?? 'Admission') ?></strong> · ticket <?= (int)$ticket['seq'] ?> of <?= (int)($order['ticket_count'] ?? 0) ?></p>
        <p class="portal-sub"><?= h($order['buyer_name'] ?: $order['buyer_email']) ?> · Order <?= h(strtoupper(substr(str_replace('-', '', $order['id']), 0, 8))) ?> · Code <?= h(ticket_short_code($ticket['code'])) ?></p>
        <?php if (!empty($ticket['checked_in_at'])): ?><p class="portal-sub">Checked in <?= h(tickets_fmt_time($ticket['checked_in_at'])) ?></p><?php endif; ?>
        <p class="portal-sub" style="font-size:.85rem">Check-in scanning is coming soon. This page is read-only for now.</p>
      <?php endif; ?>
    </div>
  </div>
  </main>
<?php site_footer(); ?>
</body>
</html>
