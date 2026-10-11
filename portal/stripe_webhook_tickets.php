<?php
/**
 * portal/stripe_webhook_tickets.php
 *
 * Public endpoint for Stripe (server-to-server, no session). Register its
 * URL as a SEPARATE webhook endpoint in the Stripe Dashboard, subscribed to:
 *   checkout.session.completed
 *   checkout.session.async_payment_succeeded
 *   checkout.session.expired
 *   charge.refunded
 * and put that endpoint's signing secret in portal/config.php as
 * stripe_webhook_secret_tickets.
 *
 * Only sessions with metadata.kind = "tickets" are handled; business-fee
 * sessions belong to stripe_webhook_finance.php. All DB calls are idempotent,
 * so Stripe retries are safe.
 */
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/stripe.php';
require_once __DIR__ . '/tickets.php';

$rawBody   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$cfg       = portal_config();
$secret    = $cfg['stripe_webhook_secret_tickets'] ?? '';

if (!stripe_verify_webhook_signature($rawBody, $sigHeader, $secret)) {
    http_response_code(400);
    die('Invalid signature.');
}

$event = json_decode($rawBody, true);
$type  = $event['type'] ?? '';
$obj   = $event['data']['object'] ?? [];

function is_ticket_obj($obj) { return ($obj['metadata']['kind'] ?? '') === 'tickets'; }

if (($type === 'checkout.session.completed' || $type === 'checkout.session.async_payment_succeeded') && is_ticket_obj($obj)) {
    // "completed" can arrive with payment_status=unpaid for delayed methods;
    // those are confirmed later by async_payment_succeeded.
    if (($obj['payment_status'] ?? '') === 'paid') {
        $r = supabase_rpc('complete_ticket_order', [
            'p_session_id'        => $obj['id'],
            'p_payment_intent_id' => $obj['payment_intent'] ?? null,
            'p_order_id'          => $obj['metadata']['ticket_order_id'] ?? null,
        ], null, true);
        if ($r['status'] !== 200) { http_response_code(500); die('db error'); } // Stripe will retry
        $orderId = $r['data']['order_id'] ?? null;
        if ($orderId) {
            $order = tickets_load_order($orderId);
            if ($order && empty($order['email_sent_at']) && tickets_send_confirmation($order)) {
                supabase_rpc('mark_ticket_email_sent', ['p_order_id' => $orderId], null, true);
            }
        }
    }
} elseif ($type === 'checkout.session.expired' && is_ticket_obj($obj)) {
    supabase_rpc('release_ticket_order', ['p_session_id' => $obj['id']], null, true);
} elseif ($type === 'charge.refunded') {
    // Full refunds only return the seats; partial refunds are left as paid.
    $pi = $obj['payment_intent'] ?? '';
    if ($pi && !empty($obj['refunded'])) {
        supabase_rpc('refund_ticket_order', ['p_payment_intent_id' => $pi], null, true);
    }
}

http_response_code(200);
echo 'ok';
