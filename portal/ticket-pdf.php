<?php
/**
 * portal/ticket-pdf.php — buyer re-download of their ticket PDF.
 * Same lookup as order-success.php (?checkout=<Stripe session id> or ?order=<id> for free orders).
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/tickets.php';

$order = tickets_order_from_request();
$issued = ($order && $order['status'] === 'paid') ? tickets_issued($order) : ['ready' => false, 'tickets' => []];
if (!$issued['tickets']) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Tickets not available for this order yet.';
    exit;
}
$ref = strtoupper(substr(str_replace('-', '', $order['id']), 0, 8));
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Exit473-tickets-' . $ref . '.pdf"');
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex');
echo tickets_build_pdf($order, $issued['tickets']);
