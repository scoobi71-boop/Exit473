<?php
/**
 * bizsite/admin/finance_pay.php
 *
 * POST-only. Takes the fee charges a business selected on finance.php,
 * re-validates everything server-side (never trusts a client-supplied
 * total), reserves them via the create_stripe_pending_payment RPC, and
 * redirects to a Stripe Checkout Session covering all of them in one
 * transaction.
 */
require_once 'auth.php';
require_auth();
require_once __DIR__ . '/../../portal/finance_helpers.php';
require_once __DIR__ . '/../../portal/stripe.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: finance.php'); exit;
}
check_csrf();

$slug = current_slug();

$chargeIds = array_values(array_filter((array)($_POST['charge_ids'] ?? []), function ($id) {
    return is_string($id) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id);
}));

if (!$chargeIds) {
    header('Location: finance.php?error=1'); exit;
}

// Re-fetch the charges server-side — the authoritative amount is whatever
// is actually stored, never a client-supplied total.
$idList = implode(',', $chargeIds);
$chargesResp = supabase_table_select(
    'business_fee_charges',
    'id=in.(' . $idList . ')&business_slug=eq.' . urlencode($slug) . '&status=eq.unpaid&select=*,fee_types(name)',
    null, true
);
$charges = $chargesResp['data'] ?? [];

if (count($charges) !== count($chargeIds)) {
    // One or more selected charges no longer exist / aren't unpaid / don't
    // belong to this business (e.g. paid in another tab in the meantime).
    header('Location: finance.php?error=1'); exit;
}

$xcdRate = (float) finance_get_setting('xcd_to_usd_rate', 2.7169);

$lineItems = [];
$totalUsdCents = 0;
foreach ($charges as $c) {
    $usdCents = finance_xcd_cents_to_usd_cents($c['amount_cents'], $xcdRate);
    $totalUsdCents += $usdCents;
    $lineItems[] = [
        'name'         => $c['description'] ?: ($c['fee_types']['name'] ?? 'Fee'),
        'amount_cents' => $usdCents,
        'quantity'     => 1,
    ];
}

$paymentResp = supabase_rpc('create_stripe_pending_payment', [
    'p_business_slug'     => $slug,
    'p_charge_ids'        => $chargeIds,
    'p_amount_usd_cents'  => $totalUsdCents,
    'p_exchange_rate'     => $xcdRate,
], null, true);

if ($paymentResp['status'] !== 200 || empty($paymentResp['data'])) {
    header('Location: finance.php?error=1'); exit;
}
$paymentId = $paymentResp['data'];

$successUrl = finance_absolute_url('/bizsite/admin/finance.php?paid=1');
$cancelUrl  = finance_absolute_url('/bizsite/admin/finance.php?cancelled=1');

$session = stripe_create_checkout_session($lineItems, $successUrl, $cancelUrl, [
    'payment_id'    => $paymentId,
    'business_slug' => $slug,
]);

if ($session['status'] !== 200 || empty($session['data']['url'])) {
    // Stripe failed — release the reserved charges back to unpaid so the business can retry immediately.
    supabase_rpc('cancel_pending_payment', ['p_payment_id' => $paymentId, 'p_business_slug' => $slug], null, true);
    header('Location: finance.php?error=1'); exit;
}

supabase_table_update('business_fee_payments', 'id=eq.' . urlencode($paymentId), [
    'stripe_checkout_session_id' => $session['data']['id'],
], null, true);

header('Location: ' . $session['data']['url']);
exit;
