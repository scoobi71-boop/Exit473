<?php
/**
 * portal/stripe_webhook_finance.php
 *
 * Public endpoint — Stripe calls this server-to-server, no session/auth.
 * Verifies the signature by hand (see stripe_verify_webhook_signature() in
 * portal/stripe.php), then on checkout.session.completed marks the linked
 * business_fee_payments/business_fee_charges rows paid via the
 * mark_stripe_payment_completed RPC, which is idempotent — a Stripe retry
 * of the same event is a safe no-op.
 *
 * Register this file's URL as a webhook endpoint in the Stripe Dashboard
 * (subscribed to checkout.session.completed) and put the signing secret
 * it gives you into portal/config.php's stripe_webhook_secret_finance.
 */
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/stripe.php';

$rawBody = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$cfg = portal_config();
$secret = $cfg['stripe_webhook_secret_finance'] ?? '';

if (!stripe_verify_webhook_signature($rawBody, $sigHeader, $secret)) {
    http_response_code(400);
    die('Invalid signature.');
}

$event = json_decode($rawBody, true);

if (($event['type'] ?? '') === 'checkout.session.completed') {
    $session = $event['data']['object'] ?? [];
    $sessionId = $session['id'] ?? '';
    $paymentIntentId = $session['payment_intent'] ?? '';

    if ($sessionId) {
        supabase_rpc('mark_stripe_payment_completed', [
            'p_checkout_session_id' => $sessionId,
            'p_payment_intent_id'   => $paymentIntentId,
        ], null, true);
    }
}

http_response_code(200);
echo 'ok';
