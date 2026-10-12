<?php
/**
 * portal/store_lib.php
 *
 * Shared helpers for the online store (schema_06_store.sql): settings,
 * session cart, money, order loading, emails. Public pages: /shop*.php.
 * Admin: portal/admin/store*.php. Webhook: portal/stripe_webhook_tickets.php
 * (handles metadata.kind = 'store' too, so no second Stripe endpoint).
 */
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/resend.php';

// COMING-SOON SIGN for the shop: set to false when the store goes live.
const SHOP_SHOW_COMING_SOON_SIGN = true;

const STORE_CATEGORIES   = ['spices' => 'Spices & Sauces', 'goods' => 'Local Goods', 'merch' => 'Exit 473 Merch', 'art' => 'Art & Crafts', 'food' => 'Food & Drink'];
const STORE_MAX_ITEMS    = 50;   // per order (all quantities)
const STORE_MAX_PER_LINE = 20;
const STORE_SESSION_MINUTES = 31; // Stripe minimum is 30

/** mbstring may be missing on the host: UTF-8-safe fallbacks. */
function store_substr($s, $start, $len) {
    $s = (string)$s;
    if (function_exists('mb_substr')) return mb_substr($s, $start, $len, 'UTF-8');
    return preg_match_all('/./us', $s, $m) ? implode('', array_slice($m[0], $start, $len)) : substr($s, $start, $len);
}
function store_strlen($s) {
    $s = (string)$s;
    if (function_exists('mb_strlen')) return mb_strlen($s, 'UTF-8');
    $n = preg_match_all('/./us', $s); return $n === false ? strlen($s) : $n;
}

function store_money($cents) { return 'US$' . number_format(((int)$cents) / 100, 2); }
function store_pct($v) { return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.') . '%'; }
/** Timestamps in Grenada time (UTC-4), like tickets.php. */
function store_time($iso, $fmt = 'M j, Y g:i A') {
    try { return (new DateTime((string)$iso))->setTimezone(new DateTimeZone('America/Grenada'))->format($fmt); } catch (Exception $e) { return ''; }
}
function store_ref($id) { return strtoupper(substr(str_replace('-', '', (string)$id), 0, 8)); }

/** True once schema_06 has been run (stores table readable). Cached per request. */
function store_ready() {
    static $ready = null;
    if ($ready === null) {
        $r = supabase_table_select('store_settings', 'select=id&limit=1');
        $ready = ($r['status'] === 200);
        if (!$ready) error_log('[exit473 store] store tables unavailable (run portal/schema_06_store.sql?): HTTP ' . $r['status']);
    }
    return $ready;
}

function store_settings() {
    static $s = null;
    if ($s === null) {
        $r = supabase_table_select('store_settings', 'id=eq.1&select=*');
        $s = ($r['status'] === 200 && !empty($r['data'][0])) ? $r['data'][0]
           : ['delivery_enabled' => true, 'delivery_fee_cents' => 1000, 'pickup_enabled' => true, 'pickup_instructions' => '', 'delivery_note' => ''];
    }
    return $s;
}

function store_product_image($p) {
    $imgs = $p['images'] ?? [];
    if (is_string($imgs)) $imgs = json_decode($imgs, true) ?: [];
    return $imgs[0] ?? '';
}
function store_product_images($p) {
    $imgs = $p['images'] ?? [];
    if (is_string($imgs)) $imgs = json_decode($imgs, true) ?: [];
    return array_values(array_filter($imgs, 'is_string'));
}

/* ---------------- cart (session: product_id => qty) ---------------- */
function store_cart() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $c = $_SESSION['store_cart'] ?? [];
    return is_array($c) ? $c : [];
}
function store_cart_set($productId, $qty) {
    if (!preg_match('/^[0-9a-f-]{36}$/i', (string)$productId)) return;
    $c = store_cart();
    $qty = max(0, min(STORE_MAX_PER_LINE, (int)$qty));
    if ($qty === 0) unset($c[$productId]); else $c[$productId] = $qty;
    $_SESSION['store_cart'] = $c;
}
function store_cart_clear() { $_SESSION['store_cart'] = []; }
function store_cart_count() { return array_sum(store_cart()); }

/** Cart lines joined to current product data (public read). Drops products that vanished. */
function store_cart_lines() {
    $c = store_cart();
    if (!$c) return [];
    $ids = implode(',', array_map('rawurlencode', array_keys($c)));
    $r = supabase_table_select('products', 'id=in.(' . $ids . ')&select=*,stores(name,slug)');
    $lines = [];
    foreach (($r['status'] === 200 ? $r['data'] : []) as $p) {
        $q = (int)$c[$p['id']];
        $lines[] = ['product' => $p, 'qty' => $q, 'line_cents' => $q * (int)$p['price_cents'], 'short' => $q > (int)$p['stock_qty']];
    }
    return $lines;
}

function store_error_message($err) {
    $err = (string)$err;
    if (str_starts_with($err, 'insufficient_stock:')) return 'Sorry, there isn\'t enough stock left of "' . substr($err, 19) . '". Please lower the quantity.';
    if (str_starts_with($err, 'not_available:')) return 'One of the items in your cart is no longer available.';
    return [
        'empty' => 'Your cart is empty.',
        'too_many' => 'That\'s more than ' . STORE_MAX_ITEMS . ' items in one order.',
        'invalid_fulfillment' => 'Please choose pickup or delivery.',
        'address_required' => 'Please enter a delivery address.',
    ][$err] ?? 'Something went wrong. Please try again.';
}

/* ---------------- orders ---------------- */
function store_load_order($id) {
    $r = supabase_table_select('store_orders', 'id=eq.' . urlencode($id) . '&select=*,store_order_items(*)', null, true);
    return ($r['status'] === 200 && !empty($r['data'][0])) ? $r['data'][0] : null;
}

/** Order for the buyer-facing success page: ?checkout=<Stripe session id>. */
function store_order_from_request() {
    $sid = $_GET['checkout'] ?? '';
    if (!preg_match('/^cs_(test|live)_[A-Za-z0-9]+$/', $sid)) return null;
    $r = supabase_table_select('store_orders', 'stripe_session_id=eq.' . urlencode($sid) . '&select=id', null, true);
    return ($r['status'] === 200 && !empty($r['data'][0])) ? store_load_order($r['data'][0]['id']) : null;
}

function store_items_table($items, $showStore = true) {
    $rows = '';
    foreach ($items as $it) {
        $rows .= '<tr><td style="padding:4px 12px 4px 0">' . htmlspecialchars($it['name']) . ' × ' . (int)$it['quantity']
              . ($showStore ? '<br><span style="color:#888;font-size:12px">' . htmlspecialchars($it['store_name']) . '</span>' : '')
              . '</td><td style="padding:4px 0;text-align:right;vertical-align:top">' . store_money($it['line_total_cents']) . '</td></tr>';
    }
    return $rows;
}

/** Buyer confirmation + admin notice + per-merchant notices. Never throws. Returns true if the buyer email went out. */
function store_send_order_emails($order) {
    if (!$order || empty($order['buyer_email'])) return false;
    $cfg = portal_config();
    $set = store_settings();
    $ref = store_ref($order['id']);
    $items = $order['store_order_items'] ?? [];
    $isDelivery = $order['fulfillment'] === 'delivery';
    $totals = '<tr><td style="padding:8px 12px 4px 0;border-top:1px solid #ddd">Subtotal</td><td style="padding:8px 0 4px;border-top:1px solid #ddd;text-align:right">' . store_money($order['subtotal_cents']) . '</td></tr>'
            . ($isDelivery ? '<tr><td style="padding:4px 12px 4px 0">Delivery</td><td style="text-align:right">' . store_money($order['delivery_fee_cents']) . '</td></tr>' : '')
            . '<tr><td style="padding:4px 12px 4px 0"><strong>Total paid</strong></td><td style="text-align:right"><strong>' . store_money($order['total_cents']) . '</strong></td></tr>';
    $how = $isDelivery
        ? '<p><strong>Delivery to:</strong><br>' . nl2br(htmlspecialchars($order['delivery_address'] ?? '')) . '</p><p style="color:#555">' . htmlspecialchars($set['delivery_note'] ?? '') . '</p>'
        : '<p><strong>Pickup:</strong> ' . htmlspecialchars($set['pickup_instructions'] ?? '') . ' We\'ll email you when it\'s ready.</p>';
    $name = $order['buyer_name'] ? htmlspecialchars($order['buyer_name']) : 'there';
    $html = '<div style="font-family:Arial,sans-serif;max-width:560px">'
          . '<h2 style="color:#CC0000">Thanks for your order!</h2>'
          . '<p>Hi ' . $name . ', we\'ve received your Exit 473 Shop order <strong>' . $ref . '</strong>.</p>'
          . '<table style="border-collapse:collapse;width:100%">' . store_items_table($items) . $totals . '</table>' . $how
          . '<p style="color:#666;font-size:13px">Questions? Just reply to this email.</p></div>';
    $r = resend_send_email($order['buyer_email'], 'Your Exit 473 Shop order ' . $ref, $html);
    $ok = ($r['status'] ?? 0) >= 200 && ($r['status'] ?? 0) < 300;

    // Admin notice
    if (!empty($cfg['admin_notify_email'])) {
        $a = '<div style="font-family:Arial,sans-serif;max-width:560px"><h2>New shop order ' . $ref . '</h2>'
           . '<p>' . htmlspecialchars(($order['buyer_name'] ?: '') . ' <' . $order['buyer_email'] . '>') . ($order['buyer_phone'] ? ' · ' . htmlspecialchars($order['buyer_phone']) : '') . '<br>'
           . ($isDelivery ? 'Delivery to: ' . htmlspecialchars($order['delivery_address'] ?? '') : 'Pickup') . '</p>'
           . '<table style="border-collapse:collapse;width:100%">' . store_items_table($items) . $totals . '</table>'
           . '<p><a href="' . htmlspecialchars(portal_url('admin/store-orders.php')) . '">Open store orders</a></p></div>';
        resend_send_email($cfg['admin_notify_email'], 'New shop order ' . $ref . ' (' . store_money($order['total_cents']) . ')', $a);
    }

    // Merchant notices (consignment stores with a contact email)
    $byStore = [];
    foreach ($items as $it) $byStore[$it['store_id']][] = $it;
    if ($byStore) {
        $ids = implode(',', array_map('rawurlencode', array_keys($byStore)));
        $sr = supabase_table_select('stores', 'id=in.(' . $ids . ')&select=id,name,type,contact_email', null, true);
        foreach (($sr['status'] === 200 ? $sr['data'] : []) as $s) {
            if ($s['type'] !== 'consignment' || empty($s['contact_email'])) continue;
            $mine = $byStore[$s['id']];
            $payout = array_sum(array_column($mine, 'payout_cents'));
            $m = '<div style="font-family:Arial,sans-serif;max-width:560px"><h2>You made a sale on the Exit 473 Shop</h2>'
               . '<p>Order ' . $ref . ' includes:</p><table style="border-collapse:collapse;width:100%">' . store_items_table($mine, false) . '</table>'
               . '<p>Your share after commission: <strong>' . store_money($payout) . '</strong>. Payouts are made by Exit 473.</p></div>';
            resend_send_email($s['contact_email'], 'New sale: order ' . $ref . ' (' . $s['name'] . ')', $m);
        }
    }
    return $ok;
}
