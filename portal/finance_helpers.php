<?php
/**
 * portal/finance_helpers.php
 *
 * Small shared helpers for the business finance/fee-management feature,
 * used by both admin/finance*.php and bizsite/admin/finance*.php. Kept
 * separate from portal/supabase.php since these are finance-domain logic,
 * not generic Supabase plumbing.
 */

/** Reads one finance_settings value (service_role), or $default if unset. */
function finance_get_setting($key, $default = null) {
    $resp = supabase_table_select('finance_settings', 'key=eq.' . urlencode($key) . '&select=value', null, true);
    if ($resp['status'] === 200 && !empty($resp['data'][0]['value'])) {
        return $resp['data'][0]['value'];
    }
    return $default;
}

/** Upserts one finance_settings value. */
function finance_set_setting($key, $value) {
    $existing = supabase_table_select('finance_settings', 'key=eq.' . urlencode($key) . '&select=key', null, true);
    if ($existing['status'] === 200 && !empty($existing['data'])) {
        return supabase_table_update('finance_settings', 'key=eq.' . urlencode($key), [
            'value'      => (string)$value,
            'updated_at' => date('c'),
        ], null, true);
    }
    return supabase_table_insert('finance_settings', ['key' => $key, 'value' => (string)$value], null, true);
}

/** Converts an EC$ (XCD) amount in cents to US$ cents at the given rate (XCD per 1 USD). */
function finance_xcd_cents_to_usd_cents($xcdCents, $rate) {
    $rate = (float)$rate;
    if ($rate <= 0) $rate = 2.7169;
    return (int) round($xcdCents / $rate);
}

/** Formats cents as a display string, e.g. finance_money(2500, 'XCD') -> "EC$25.00". */
function finance_money($cents, $currency) {
    $symbol = ($currency === 'USD') ? 'US$' : 'EC$';
    return $symbol . number_format($cents / 100, 2);
}

/** Absolute URL for a site-root-relative path, e.g. finance_absolute_url('/bizsite/admin/finance.php?paid=1'). Needed for Stripe's success_url/cancel_url, which must be fully qualified. */
function finance_absolute_url($path) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'exit473.com';
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}
