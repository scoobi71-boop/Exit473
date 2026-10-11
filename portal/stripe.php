<?php
/**
 * portal/stripe.php
 *
 * Thin REST helpers for the Stripe API, matching portal/supabase.php's
 * style: plain cURL, no SDK/Composer. Used by the business finance/fee
 * payment feature (bizsite/admin/finance_pay.php,
 * portal/stripe_webhook_finance.php) to create Checkout Sessions and
 * verify webhook signatures by hand.
 */

function stripe_config() {
    static $key = null;
    if ($key !== null) return $key;
    $cfg = portal_config();
    $key = $cfg['stripe_secret_key'] ?? '';
    if ($key === '') {
        http_response_code(500);
        die('Stripe is not configured. Set stripe_secret_key in portal/config.php.');
    }
    return $key;
}

/**
 * Low-level request against the Stripe API. Stripe takes
 * application/x-www-form-urlencoded bodies (not JSON) with PHP-style
 * bracket-nested field names, which http_build_query() already produces
 * from a nested array — e.g. ['line_items' => [['price_data' => [...]]]]
 * encodes to line_items[0][price_data][...]=... automatically.
 */
function stripe_request($method, $path, array $formFields = []) {
    $url = 'https://api.stripe.com' . $path;
    $headers = ['Authorization: Bearer ' . stripe_config()];

    $ch = curl_init($url);
    $opts = [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ];
    if ($formFields) {
        $opts[CURLOPT_POSTFIELDS] = http_build_query($formFields);
    }
    curl_setopt_array($ch, $opts);
    $raw    = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['status' => 0, 'data' => null, 'error' => $err];
    }
    $data = json_decode($raw, true);
    return ['status' => $status, 'data' => $data, 'raw' => $raw];
}

/**
 * Creates a Stripe Checkout Session with one line item per fee charge —
 * this is what lets a business pay several fees in a single transaction.
 * $lineItems: [['name' => string, 'amount_cents' => int, 'quantity' => int], ...]
 * (amount_cents is the per-unit price in the smallest currency unit — USD
 * cents here, since finance charges are always converted to USD before
 * this is called).
 */
function stripe_create_checkout_session(array $lineItems, $successUrl, $cancelUrl, array $metadata = []) {
    $fields = [
        'mode'        => 'payment',
        'success_url' => $successUrl,
        'cancel_url'  => $cancelUrl,
    ];
    foreach ($lineItems as $i => $item) {
        $fields['line_items'][$i]['quantity'] = $item['quantity'] ?? 1;
        $fields['line_items'][$i]['price_data']['currency'] = 'usd';
        $fields['line_items'][$i]['price_data']['unit_amount'] = (int)$item['amount_cents'];
        $fields['line_items'][$i]['price_data']['product_data']['name'] = $item['name'];
    }
    foreach ($metadata as $k => $v) {
        $fields['metadata'][$k] = $v;
    }
    return stripe_request('POST', '/v1/checkout/sessions', $fields);
}

/** Retrieves a Checkout Session by id — used for manual reconciliation, not the critical path. */
function stripe_retrieve_checkout_session($id) {
    return stripe_request('GET', '/v1/checkout/sessions/' . urlencode($id));
}

/**
 * Verifies a Stripe webhook's Stripe-Signature header by hand (no SDK).
 * Header format: "t=<unix ts>,v1=<hex hmac>[,v0=...]". Signed payload is
 * "<ts>.<raw body>", HMAC-SHA256 with the endpoint's webhook secret.
 * Rejects timestamps older than 5 minutes to guard against replay.
 * Returns true/false.
 */
function stripe_verify_webhook_signature($payload, $sigHeader, $secret) {
    if (!$sigHeader || !$secret) return false;

    $timestamp = null;
    $signatures = [];
    foreach (explode(',', $sigHeader) as $part) {
        [$k, $v] = array_pad(explode('=', $part, 2), 2, null);
        if ($k === 't') $timestamp = $v;
        if ($k === 'v1' && $v) $signatures[] = $v;
    }
    if (!$timestamp || !$signatures) return false;
    if (abs(time() - (int)$timestamp) > 300) return false;

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) return true;
    }
    return false;
}
