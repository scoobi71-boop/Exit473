<?php
/**
 * portal/checkout.php
 *
 * POST target of the ticket form on e.php. Holds the seats with
 * reserve_tickets() (atomic, can't oversell), then sends the buyer to a
 * Stripe Checkout session. Payment is confirmed by
 * stripe_webhook_tickets.php, never by this page.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/stripe.php';
require_once __DIR__ . '/tickets.php';

function checkout_fail($slug, $message) {
    $_SESSION['ticket_error'] = $message;
    header('Location: e.php?slug=' . urlencode($slug) . '#tickets', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: events.php'); exit; }
portal_check_csrf();

$slug  = trim($_POST['slug'] ?? '');
$email = trim($_POST['email'] ?? '');
$name  = trim($_POST['name'] ?? '');
$qtys  = is_array($_POST['qty'] ?? null) ? $_POST['qty'] : [];
$_SESSION['ticket_form'] = ['email' => $email, 'name' => $name, 'qty' => $qtys];

// Public read (anon key): only published events are visible.
$evResp = supabase_table_select('events', 'slug=eq.' . urlencode($slug) . '&status=eq.published&select=id,title,slug');
$event = ($evResp['status'] === 200 && !empty($evResp['data'][0])) ? $evResp['data'][0] : null;
if (!$event) checkout_fail($slug, 'Tickets for this event aren\'t on sale.');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) checkout_fail($slug, 'Please enter a valid email address.');

$items = [];
$total = 0;
foreach ($qtys as $ttId => $q) {
    if (!preg_match('/^[0-9a-f-]{36}$/i', (string)$ttId)) continue;
    $q = (int)$q;
    if ($q < 0 || $q > TICKETS_MAX_PER_ORDER) checkout_fail($slug, 'You can buy at most ' . TICKETS_MAX_PER_ORDER . ' tickets per order.');
    if ($q > 0) { $items[] = ['ticket_type_id' => $ttId, 'quantity' => $q]; $total += $q; }
}
if ($total < 1) checkout_fail($slug, 'Please choose at least one ticket.');
if ($total > TICKETS_MAX_PER_ORDER) checkout_fail($slug, 'You can buy at most ' . TICKETS_MAX_PER_ORDER . ' tickets per order.');

$res = supabase_rpc('reserve_tickets', [
    'p_event_id'      => $event['id'],
    'p_items'         => $items,
    'p_buyer_email'   => $email,
    'p_buyer_name'    => $name,
    'p_max_per_order' => TICKETS_MAX_PER_ORDER,
], null, true);
if ($res['status'] !== 200 || empty($res['data']['order_id'])) checkout_fail($slug, tickets_error_message($res));

$order = $res['data'];
$orderId = $order['order_id'];

if ((int)$order['total_cents'] === 0) {
    // All free tickets: nothing to charge, confirm immediately.
    supabase_rpc('complete_ticket_order', ['p_session_id' => 'free_' . $orderId, 'p_payment_intent_id' => null, 'p_order_id' => $orderId], null, true);
    $full = tickets_load_order($orderId);
    if (tickets_send_confirmation($full)) supabase_rpc('mark_ticket_email_sent', ['p_order_id' => $orderId], null, true);
    unset($_SESSION['ticket_form']);
    header('Location: order-success.php?order=' . urlencode($orderId), true, 303);
    exit;
}

$lineItems = [];
foreach ($order['items'] as $it) {
    $lineItems[] = [
        'name'         => $event['title'] . ' — ' . $it['name'],
        'amount_cents' => (int)$it['unit_price_cents'],
        'quantity'     => (int)$it['quantity'],
    ];
}

// Not "session_id": Network Solutions' ModSecurity returns 406 for a
// session_id/PHPSESSID-style param when the Referer is another site
// (checkout.stripe.com), i.e. its session-fixation rule.
$session = stripe_create_checkout_session(
    $lineItems,
    portal_url('order-success.php?checkout={CHECKOUT_SESSION_ID}'),
    portal_url('e.php?slug=' . urlencode($event['slug']) . '&cancelled=1#tickets'),
    ['kind' => 'tickets', 'ticket_order_id' => $orderId, 'event_id' => $event['id']],
    [
        'customer_email'      => $email,
        'expires_at'          => time() + TICKETS_SESSION_MINUTES * 60 + 60,
        'payment_intent_data' => ['metadata' => ['kind' => 'tickets', 'ticket_order_id' => $orderId]],
    ]
);

if ($session['status'] !== 200 || empty($session['data']['url'])) {
    supabase_rpc('release_ticket_order_by_id', ['p_order_id' => $orderId], null, true);
    error_log('Exit473 tickets: Stripe session failed: ' . ($session['data']['error']['message'] ?? $session['error'] ?? 'unknown'));
    checkout_fail($event['slug'], 'Payment couldn\'t be started. Please try again in a moment.');
}

supabase_rpc('attach_ticket_checkout_session', ['p_order_id' => $orderId, 'p_session_id' => $session['data']['id']], null, true);
unset($_SESSION['ticket_form']);
header('Location: ' . $session['data']['url'], true, 303);
exit;
