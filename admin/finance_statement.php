<?php
require_once 'auth.php';
require_role(['site_owner', 'finance']);
require_once __DIR__ . '/../portal/finance_helpers.php';
require_once __DIR__ . '/../portal/simple_pdf.php';

$slug = $_GET['slug'] ?? '';
$period = $_GET['period'] ?? date('Y-m');
$download = isset($_GET['download']);

if (!$slug || !preg_match('/^\d{4}-\d{2}$/', $period)) {
    http_response_code(400);
    die('Missing or invalid slug/period.');
}

$bizResp = supabase_table_select('finance_businesses', 'slug=eq.' . urlencode($slug) . '&select=*', null, true);
$biz = $bizResp['data'][0] ?? null;
if (!$biz) {
    http_response_code(404);
    die('Business not found.');
}

$chargesResp = supabase_table_select('business_fee_charges', 'business_slug=eq.' . urlencode($slug) . '&select=*,fee_types(name)&order=due_date.asc', null, true);
$allCharges = $chargesResp['data'] ?? [];

$periodStart = new DateTime($period . '-01');
$periodEnd = (clone $periodStart)->modify('+1 month');

// Show every charge from this calendar month, plus any charge from an
// earlier month that's still outstanding (so the statement reflects the
// true current balance, not just this month's activity).
$statementCharges = array_values(array_filter($allCharges, function ($c) use ($periodStart, $periodEnd) {
    if (in_array($c['status'], ['unpaid', 'pending'], true)) return true;
    $created = new DateTime($c['created_at']);
    return $created >= $periodStart && $created < $periodEnd;
}));

$outstandingTotal = 0;
foreach ($allCharges as $c) {
    if (in_array($c['status'], ['unpaid', 'pending'], true)) $outstandingTotal += $c['amount_cents'];
}

$pdf = new SimplePdf();
$marginX = 50;
$rightX = 562;
$pageBottom = 730;

function statement_draw_header($pdf, $biz, $period, $marginX, $rightX) {
    $pdf->text($marginX, 50, 'Exit 473 Business Complex', 'F2', 18);
    $pdf->text($marginX, 72, 'Fee Statement', 'F1', 11);
    $pdf->textRight($rightX, 50, 'Generated ' . date('M j, Y'), 'F1', 9);
    $pdf->text($marginX, 100, $biz['display_name'] . ' (' . $biz['slug'] . ')', 'F2', 13);
    $pdf->text($marginX, 118, 'Period: ' . date('F Y', strtotime($period . '-01')), 'F1', 10);
    $pdf->line($marginX, 132, $rightX, 132);

    $y = 150;
    $pdf->text($marginX, $y, 'Description', 'F2', 9);
    $pdf->text(370, $y, 'Due Date', 'F2', 9);
    $pdf->textRight(480, $y, 'Amount', 'F2', 9);
    $pdf->text(495, $y, 'Status', 'F2', 9);
    $pdf->line($marginX, $y + 6, $rightX, $y + 6);
    return $y + 24;
}

$pdf->addPage();
$y = statement_draw_header($pdf, $biz, $period, $marginX, $rightX);

foreach ($statementCharges as $c) {
    if ($y > $pageBottom) {
        $pdf->addPage();
        $y = statement_draw_header($pdf, $biz, $period, $marginX, $rightX);
    }
    $desc = $c['description'] ?: ($c['fee_types']['name'] ?? 'Fee');
    if (strlen($desc) > 55) $desc = substr($desc, 0, 52) . '...';
    $pdf->text($marginX, $y, $desc, 'F1', 9);
    $pdf->text(370, $y, date('M j, Y', strtotime($c['due_date'])), 'F1', 9);
    $pdf->textRight(480, $y, finance_money($c['amount_cents'], $c['currency']), 'F1', 9);
    $pdf->text(495, $y, ucfirst($c['status']), 'F1', 9);
    $y += 18;
}

if (!$statementCharges) {
    $pdf->text($marginX, $y, 'No charges for this period.', 'F1', 9);
    $y += 18;
}

$y += 6;
$pdf->line($marginX, $y, $rightX, $y);
$y += 20;
$pdf->textRight($rightX, $y, 'Total Outstanding (all periods): ' . finance_money($outstandingTotal, 'XCD'), 'F2', 11);

$bytes = $pdf->output(); // finalizes the last page's content automatically

header('Content-Type: application/pdf');
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="statement-' . $slug . '-' . $period . '.pdf"');
header('Content-Length: ' . strlen($bytes));
echo $bytes;
