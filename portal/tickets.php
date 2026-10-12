<?php
/**
 * portal/tickets.php
 *
 * Shared helpers for event ticket sales (Stripe Checkout). See
 * schema_03_ticket_orders.sql for the tables and the reserve/complete
 * functions, portal/checkout.php for starting a purchase and
 * portal/stripe_webhook_tickets.php for confirming it.
 */
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/resend.php';
require_once __DIR__ . '/ticket_qr.php';

define('TICKETS_MAX_PER_ORDER', 10);
define('TICKETS_SESSION_MINUTES', 30); // Stripe's minimum Checkout expiry

function tickets_money($cents) { return 'US$' . number_format(((int)$cents) / 100, 2); }

function tickets_fmt_time($iso) {
    if (!$iso) return '';
    try {
        return (new DateTime($iso))->setTimezone(new DateTimeZone('America/Grenada'))->format('l, M j, Y \a\t g:i A');
    } catch (Exception $e) { return ''; }
}

/** Maps a reserve_tickets() error to a buyer-facing message. */
function tickets_error_message($resp) {
    $msg = $resp['data']['message'] ?? '';
    if (strpos($msg, 'SOLD_OUT:') === 0) return 'Sorry, there aren\'t enough "' . substr($msg, 9) . '" tickets left. Please choose fewer.';
    switch ($msg) {
        case 'TOO_MANY_TICKETS':    return 'You can buy at most ' . TICKETS_MAX_PER_ORDER . ' tickets per order.';
        case 'NO_TICKETS':          return 'Please choose at least one ticket.';
        case 'BAD_EMAIL':           return 'Please enter a valid email address.';
        case 'EVENT_ENDED':         return 'This event has already ended.';
        case 'EVENT_NOT_AVAILABLE': return 'Tickets for this event aren\'t on sale.';
        case 'BAD_TICKET_TYPE':     return 'That ticket type isn\'t available.';
    }
    return 'Something went wrong reserving your tickets. Please try again.';
}

/**
 * Order for a buyer-facing request: ?checkout=<Stripe session id> (or legacy
 * ?session_id=) for paid orders, ?order=<uuid> for free orders only.
 */
function tickets_order_from_request() {
    $sessionId = $_GET['checkout'] ?? ($_GET['session_id'] ?? '');
    $orderParam = $_GET['order'] ?? '';
    if (preg_match('/^cs_(test|live)_[A-Za-z0-9]+$/', $sessionId)) {
        $r = supabase_table_select('ticket_orders', 'stripe_session_id=eq.' . urlencode($sessionId) . '&select=id', null, true);
        if ($r['status'] === 200 && !empty($r['data'][0])) return tickets_load_order($r['data'][0]['id']);
    } elseif (preg_match('/^[0-9a-f-]{36}$/i', $orderParam)) {
        $o = tickets_load_order($orderParam);
        if ($o && (int)$o['total_cents'] === 0) return $o;
    }
    return null;
}

/** Loads an order with its items and event (service role). */
function tickets_load_order($orderId) {
    $o = supabase_table_select('ticket_orders', 'id=eq.' . urlencode($orderId) . '&select=*,ticket_order_items(*),events(title,slug,venue,start_at)', null, true);
    return ($o['status'] === 200 && !empty($o['data'][0])) ? $o['data'][0] : null;
}

/** Sends the order confirmation via Resend. Never throws. Returns true on success. */
function tickets_send_confirmation($order) {
    if (!$order || empty($order['buyer_email'])) return false;
    $ev = $order['events'] ?? [];
    $rows = '';
    foreach ($order['ticket_order_items'] ?? [] as $it) {
        $rows .= '<tr><td style="padding:4px 12px 4px 0">' . htmlspecialchars($it['name']) . ' × ' . (int)$it['quantity']
              . '</td><td style="padding:4px 0;text-align:right">' . tickets_money($it['unit_price_cents'] * $it['quantity']) . '</td></tr>';
    }
    $ref = strtoupper(substr(str_replace('-', '', $order['id']), 0, 8));

    // QR tickets (schema_05). Without it the email still goes out, just without QR codes.
    $qrHtml = ''; $attachments = [];
    try {
        $issued = tickets_issued($order);
        if (!$issued['ready']) {
            error_log('[exit473 tickets] sending order ' . $ref . ' without QR codes: schema_05 not applied');
        } elseif ($issued['tickets']) {
            $attachments[] = ['filename' => 'Exit473-tickets-' . $ref . '.pdf', 'content' => tickets_build_pdf($order, $issued['tickets']), 'content_type' => 'application/pdf'];
            $cells = '';
            foreach ($issued['tickets'] as $i => $t) {
                $cid = 'qr-' . ($i + 1) . '-' . substr($t['code'], 0, 8);
                $attachments[] = ['filename' => 'ticket-' . ($i + 1) . '-qr.png', 'content' => ticket_qr_png(ticket_qr_matrix(ticket_checkin_url($t['code'])), 6), 'content_type' => 'image/png', 'content_id' => $cid];
                $cells .= '<td style="padding:8px;text-align:center;vertical-align:top;width:50%">'
                        . '<img src="cid:' . $cid . '" width="180" height="180" alt="QR code for ticket ' . ($i + 1) . '" style="display:block;margin:0 auto;border:1px solid #eee">'
                        . '<div style="font-size:13px;margin-top:6px"><strong>' . htmlspecialchars($t['name']) . '</strong><br>Ticket ' . ($i + 1) . ' of ' . count($issued['tickets'])
                        . '<br><span style="font-family:monospace;color:#666">' . ticket_short_code($t['code']) . '</span></div></td>';
                if ($i % 2 === 1) $cells .= '</tr><tr>';
            }
            $qrHtml = '<h3 style="margin:20px 0 6px">Your tickets</h3><table style="border-collapse:collapse;width:100%"><tr>' . $cells . '</tr></table>';
        }
    } catch (Throwable $e) {
        error_log('[exit473 tickets] QR/PDF generation failed for ' . $ref . ': ' . $e->getMessage());
        $qrHtml = ''; $attachments = [];
    }

    $name = $order['buyer_name'] ? htmlspecialchars($order['buyer_name']) : 'there';
    $html = '<div style="font-family:Arial,sans-serif;max-width:560px">'
          . '<h2>You\'re going to ' . htmlspecialchars($ev['title'] ?? 'the event') . '!</h2>'
          . '<p>Hi ' . $name . ', thanks for your order. ' . ($qrHtml ? 'Your tickets are attached as a PDF, and the QR codes are below. Show one QR code per person at the entrance.' : 'Show this email (or your order number) at the entrance.') . '</p>'
          . '<p><strong>When:</strong> ' . htmlspecialchars(tickets_fmt_time($ev['start_at'] ?? '')) . ' (Grenada time)<br>'
          . (!empty($ev['venue']) ? '<strong>Where:</strong> ' . htmlspecialchars($ev['venue']) . '<br>' : '')
          . '<strong>Order number:</strong> ' . $ref . '</p>'
          . '<table style="border-collapse:collapse;width:100%">' . $rows
          . '<tr><td style="padding:8px 12px 4px 0;border-top:1px solid #ddd"><strong>Total paid</strong></td>'
          . '<td style="padding:8px 0 4px;border-top:1px solid #ddd;text-align:right"><strong>' . tickets_money($order['total_cents']) . '</strong></td></tr></table>'
          . $qrHtml
          . '<p style="color:#666;font-size:13px">Event page: ' . htmlspecialchars(portal_url('e.php?slug=' . urlencode($ev['slug'] ?? ''))) . '<br>Questions? Just reply to this email.</p>'
          . '</div>';
    $r = resend_send_email($order['buyer_email'], 'Your tickets: ' . ($ev['title'] ?? 'Exit 473 event') . ' (Order ' . $ref . ')', $html, $attachments);
    return ($r['status'] ?? 0) >= 200 && ($r['status'] ?? 0) < 300;
}

/**
 * Paid-sales summary per event: [event_id => ['orders'=>n,'tickets'=>n,'gross_cents'=>n]].
 * Pass $token for an organizer (RLS-scoped) or null + $serviceRole for admin.
 */
function tickets_sales_summary(array $eventIds, $token = null, $serviceRole = false) {
    $out = [];
    if (!$eventIds) return $out;
    $q = 'status=eq.paid&event_id=in.(' . implode(',', array_map('urlencode', $eventIds)) . ')&select=event_id,ticket_count,total_cents';
    $r = supabase_table_select('ticket_orders', $q, $token, $serviceRole);
    if ($r['status'] !== 200) return $out;
    foreach ($r['data'] as $o) {
        $e = $o['event_id'];
        if (!isset($out[$e])) $out[$e] = ['orders' => 0, 'tickets' => 0, 'gross_cents' => 0];
        $out[$e]['orders']++;
        $out[$e]['tickets'] += (int)$o['ticket_count'];
        $out[$e]['gross_cents'] += (int)$o['total_cents'];
    }
    return $out;
}
