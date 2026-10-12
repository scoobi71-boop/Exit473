<?php
/**
 * /shop-checkout.php: POST from the cart. Reserves stock atomically
 * (reserve_store_order), then redirects to Stripe Checkout. Payment is
 * confirmed only by the webhook (portal/stripe_webhook_tickets.php, kind=store).
 */
require_once __DIR__ . '/portal/auth.php';
require_once __DIR__ . '/portal/stripe.php';
require_once __DIR__ . '/portal/store_lib.php';

function shop_fail($msg) { $_SESSION['shop_error'] = $msg; header('Location: /shop-cart.php', true, 303); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /shop-cart.php'); exit; }
portal_check_csrf();
if (!store_ready()) shop_fail('The shop isn\'t open yet.');

$email = trim($_POST['email'] ?? '');
$name  = store_substr(trim($_POST['name'] ?? ''), 0, 120);
$phone = store_substr(trim($_POST['phone'] ?? ''), 0, 40);
$ful   = ($_POST['fulfillment'] ?? '') === 'delivery' ? 'delivery' : 'pickup';
$addr  = store_substr(trim($_POST['address'] ?? ''), 0, 500);
$_SESSION['shop_form'] = ['email' => $email, 'name' => $name, 'phone' => $phone, 'fulfillment' => $ful, 'address' => $addr];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) shop_fail('Please enter a valid email address.');
if ($name === '') shop_fail('Please enter your name.');
$items = [];
foreach (store_cart() as $pid => $q) if ((int)$q > 0) $items[] = ['product_id' => $pid, 'quantity' => (int)$q];
if (!$items) shop_fail('Your cart is empty.');

$res = supabase_rpc('reserve_store_order', [
    'p_items' => $items, 'p_buyer_email' => $email, 'p_buyer_name' => $name, 'p_buyer_phone' => $phone,
    'p_fulfillment' => $ful, 'p_delivery_address' => $ful === 'delivery' ? $addr : null, 'p_max_items' => STORE_MAX_ITEMS,
], null, true);
if ($res['status'] !== 200 || empty($res['data']['order_id'])) {
    if ($res['status'] !== 200) error_log('[exit473 store] reserve failed: HTTP ' . $res['status'] . ' ' . ($res['raw'] ?? ''));
    shop_fail(store_error_message($res['data']['error'] ?? ''));
}
$order = $res['data'];
$orderId = $order['order_id'];

$lineItems = [];
foreach ($order['items'] as $it) $lineItems[] = ['name' => $it['name'], 'amount_cents' => (int)$it['unit_price_cents'], 'quantity' => (int)$it['quantity']];
if ((int)$order['delivery_fee_cents'] > 0) $lineItems[] = ['name' => 'Delivery', 'amount_cents' => (int)$order['delivery_fee_cents'], 'quantity' => 1];

$session = stripe_create_checkout_session(
    $lineItems,
    'https://' . ($_SERVER['HTTP_HOST'] ?? 'exit473.com') . '/shop-success.php?checkout={CHECKOUT_SESSION_ID}',
    'https://' . ($_SERVER['HTTP_HOST'] ?? 'exit473.com') . '/shop-cart.php?cancelled=1',
    ['kind' => 'store', 'store_order_id' => $orderId],
    [
        'customer_email'      => $email,
        'expires_at'          => time() + STORE_SESSION_MINUTES * 60 + 60,
        'payment_intent_data' => ['metadata' => ['kind' => 'store', 'store_order_id' => $orderId]],
    ]
);
if ($session['status'] !== 200 || empty($session['data']['url'])) {
    supabase_rpc('release_store_order', ['p_session_id' => null, 'p_order_id' => $orderId], null, true);
    error_log('[exit473 store] Stripe session failed: ' . ($session['data']['error']['message'] ?? $session['error'] ?? 'unknown'));
    shop_fail('Payment couldn\'t be started. Please try again in a moment.');
}
supabase_rpc('attach_store_checkout_session', ['p_order_id' => $orderId, 'p_session_id' => $session['data']['id']], null, true);
header('Location: ' . $session['data']['url'], true, 303);
exit;
