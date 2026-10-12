<?php
/**
 * portal/ticket_qr.php
 *
 * QR-coded tickets: loads issued_tickets (schema_05), makes QR matrices with
 * the vendored phpqrcode (portal/lib, LGPL-3.0), draws them as PNG (pure PHP
 * + zlib, no GD) for the email and as vector squares in the PDF.
 *
 * Everything degrades: if schema_05 hasn't been run, tickets_issued() reports
 * ready=false and callers simply send/show the order without QR codes.
 */
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/simple_pdf.php';

const TICKET_CODE_RE = '/^[0-9a-f]{40}$/';

const TICKET_CHECKIN_BASE = 'https://exit473.com/portal/checkin.php?t=';
function ticket_checkin_url($code) { return TICKET_CHECKIN_BASE . rawurlencode($code); }

/** QR matrix (array of '0'/'1' strings) for $text, ECC level M. */
function ticket_qr_matrix($text) {
    static $loaded = false;
    if (!$loaded) {
        $old = error_reporting(error_reporting() & ~(E_DEPRECATED | E_WARNING | E_NOTICE));
        require_once __DIR__ . '/lib/phpqrcode/phpqrcode.php';
        error_reporting($old);
        $loaded = true;
    }
    $old = error_reporting(error_reporting() & ~(E_DEPRECATED | E_NOTICE));
    $m = QRcode::text($text, false, QR_ECLEVEL_M, 1, 0);
    error_reporting($old);
    return is_array($m) ? $m : [];
}

/** PNG bytes for a QR matrix: $scale px per module, 4-module white quiet zone, 8-bit grayscale. */
function ticket_qr_png(array $matrix, $scale = 8) {
    $n = count($matrix); $q = 4; $size = ($n + 2 * $q) * $scale;
    $raw = '';
    $white = str_repeat("\xFF", $size);
    for ($y = 0; $y < $size; $y++) {
        $r = intdiv($y, $scale) - $q;
        if ($r < 0 || $r >= $n) { $raw .= "\x00" . $white; continue; }
        $line = str_repeat("\xFF", $q * $scale);
        $row = $matrix[$r];
        for ($c = 0; $c < $n; $c++) $line .= str_repeat($row[$c] === '1' ? "\x00" : "\xFF", $scale);
        $line .= str_repeat("\xFF", $q * $scale);
        $raw .= "\x00" . $line;
    }
    $chunk = fn($type, $data) => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    return "\x89PNG\r\n\x1a\n"
        . $chunk('IHDR', pack('NNCCCCC', $size, $size, 8, 0, 0, 0, 0))
        . $chunk('IDAT', gzcompress($raw, 9))
        . $chunk('IEND', '');
}

/**
 * Issued tickets for an order, joined to their item names:
 * ['ready' => bool (schema_05 present), 'tickets' => [ ['id','seq','code','status','name'], ... ]].
 * If the order is paid but has no tickets yet (e.g. paid before schema_05),
 * asks the database to issue them (idempotent).
 */
function tickets_issued($order) {
    if (!$order) return ['ready' => false, 'tickets' => []];
    $q = 'order_id=eq.' . urlencode($order['id']) . '&select=id,seq,code,status,ticket_type_id,order_item_id&order=seq.asc';
    $r = supabase_table_select('issued_tickets', $q, null, true);
    if ($r['status'] !== 200) {
        error_log('[exit473 tickets] issued_tickets unavailable (run portal/schema_05_issued_tickets.sql?): HTTP ' . $r['status']);
        return ['ready' => false, 'tickets' => []];
    }
    $rows = $r['data'];
    if (!$rows && ($order['status'] ?? '') === 'paid') {
        supabase_rpc('issue_tickets_for_order', ['p_order_id' => $order['id']], null, true);
        $r = supabase_table_select('issued_tickets', $q, null, true);
        $rows = $r['status'] === 200 ? $r['data'] : [];
    }
    $names = [];
    foreach ($order['ticket_order_items'] ?? [] as $it) $names[$it['id']] = $it['name'];
    foreach ($rows as &$t) $t['name'] = $names[$t['order_item_id'] ?? ''] ?? 'Admission';
    unset($t);
    return ['ready' => true, 'tickets' => $rows];
}

function ticket_short_code($code) { return strtoupper(substr($code, 0, 4) . '-' . substr($code, 4, 4) . '-' . substr($code, 8, 4)); }

/** Max width (px) of the image embedded in the ticket PDF: ~2x the 532pt print width. */
const TICKET_PDF_IMAGE_MAX_W = 1100;

/**
 * Raw bytes for an event image URL. Files on our own domain (relative URLs
 * or exit473.com) are read from disk under /images/; other URLs (an external
 * cover image) are fetched with a short timeout and a 6 MB cap.
 */
function ticket_image_bytes($url) {
    $url = trim((string)$url);
    if ($url === '') return null;
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    $path = (string)parse_url($url, PHP_URL_PATH);
    if ($host === '' || in_array($host, ['exit473.com', 'www.exit473.com'], true)) {
        if (!preg_match('#^/images/[A-Za-z0-9/._-]+\.(jpe?g|png|webp|gif)$#i', $path) || strpos($path, '..') !== false) return null;
        $file = dirname(__DIR__) . $path;
        return (is_file($file) && filesize($file) <= 6 * 1024 * 1024) ? (file_get_contents($file) ?: null) : null;
    }
    if (!preg_match('#^https://#i', $url) || !function_exists('curl_init')) return null;
    $ch = curl_init($url);
    $buf = '';
    curl_setopt_array($ch, [CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_WRITEFUNCTION => function ($c, $d) use (&$buf) { $buf .= $d; return strlen($buf) > 6 * 1024 * 1024 ? 0 : strlen($d); }]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code === 200 && $buf !== '') ? $buf : null;
}

/**
 * Converts image bytes to a PDF-friendly JPEG. With GD: JPEG/PNG/WebP/GIF in,
 * resized to TICKET_PDF_IMAGE_MAX_W, transparency flattened onto white,
 * re-encoded at quality 82. Without GD: JPEGs pass through unchanged, other
 * formats are skipped (returns null).
 */
function ticket_image_to_jpeg($bytes) {
    $info = @getimagesizefromstring($bytes);
    if (!$info) return null;
    $type = $info[2];
    if (!extension_loaded('gd')) {
        static $warned = false;
        if (!$warned) { $warned = true; error_log('[exit473 tickets] GD not available: only JPEG banners can be used in ticket PDFs'); }
        return $type === IMAGETYPE_JPEG ? $bytes : null;
    }
    if ($type === IMAGETYPE_WEBP && !function_exists('imagecreatefromwebp')) return null;
    if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) return null;
    // Small JPEGs are already fine; skip the re-encode.
    if ($type === IMAGETYPE_JPEG && $info[0] <= TICKET_PDF_IMAGE_MAX_W && ($info['channels'] ?? 3) !== 4) return $bytes;
    $src = @imagecreatefromstring($bytes);
    if (!$src) return null;
    $w = imagesx($src); $h = imagesy($src);
    $nw = min($w, TICKET_PDF_IMAGE_MAX_W); $nh = max(1, (int)round($h * $nw / $w));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    ob_start(); imagejpeg($dst, null, 82); $out = ob_get_clean();
    imagedestroy($src); imagedestroy($dst);
    return $out ?: null;
}

/**
 * Image for the ticket PDF header, as JPEG bytes: the event's banner_url
 * (schema_04 Banner & Posters) if set and usable, else cover_image_url, else
 * null (plain branded header). Falls back to cover-only when schema_04's
 * banner_url column doesn't exist.
 */
function ticket_event_image_jpeg($eventId) {
    $r = supabase_table_select('events', 'id=eq.' . urlencode($eventId) . '&select=banner_url,cover_image_url', null, true);
    if ($r['status'] !== 200) $r = supabase_table_select('events', 'id=eq.' . urlencode($eventId) . '&select=cover_image_url', null, true);
    $ev = ($r['status'] === 200) ? ($r['data'][0] ?? []) : [];
    foreach (['banner_url', 'cover_image_url'] as $col) {
        if (empty($ev[$col])) continue;
        $raw = ticket_image_bytes($ev[$col]);
        $jpeg = $raw ? ticket_image_to_jpeg($raw) : null;
        if ($jpeg) return $jpeg;
        error_log('[exit473 tickets] ticket PDF: could not use ' . $col . ' for event ' . $eventId);
    }
    return null;
}

/** One-page-per-ticket PDF (US Letter). Returns bytes. */
function tickets_build_pdf($order, array $tickets) {
    require_once __DIR__ . '/tickets.php';
    $ev = $order['events'] ?? [];
    $ref = strtoupper(substr(str_replace('-', '', $order['id']), 0, 8));
    $pdf = new SimplePdf();
    $W = $pdf->pageWidth();
    $banner = null;
    $bannerBytes = !empty($order['event_id']) ? ticket_event_image_jpeg($order['event_id']) : null;
    if ($bannerBytes) $banner = $pdf->addJpeg($bannerBytes);
    $total = count($tickets);
    foreach ($tickets as $i => $t) {
        $pdf->addPage();
        // Header bar (site colours)
        $pdf->setColor(26, 5, 5);   $pdf->rect(0, 0, $W, 70, true);
        $pdf->setColor(204, 0, 0);  $pdf->rect(0, 70, $W, 5, true);
        $pdf->setColor(245, 200, 64); $pdf->text(40, 22, 'EXIT 473', 'F2', 22);
        $pdf->setColor(255, 255, 255); $pdf->text(40, 48, 'Food & Lifestyle Park  ·  Grand Anse, Grenada', 'F1', 10);
        $pdf->textRight($W - 40, 30, 'ADMIT ONE', 'F2', 16);
        $y = 100;
        if ($banner) {
            [$bw, $bh] = $pdf->imageSize($banner);
            $dw = $W - 80; $dh = $dw * $bh / max(1, $bw);
            if ($dh > 170) { $dw = $dw * 170 / $dh; $dh = 170; } // keep aspect ratio
            $pdf->image($banner, ($W - $dw) / 2, $y, $dw, $dh);
            $y += $dh + 20;
        }
        $pdf->setColor(26, 5, 5);
        $title = (string)($ev['title'] ?? 'Exit 473 Event');
        $size = $pdf->textWidth($title, 22) > $W - 80 ? 16 : 22;
        $pdf->text(40, $y, $title, 'F2', $size); $y += $size + 14;
        $pdf->setColor(90, 70, 70);
        $pdf->text(40, $y, tickets_fmt_time($ev['start_at'] ?? '') . ' (Grenada time)', 'F1', 12); $y += 18;
        if (!empty($ev['venue'])) { $pdf->text(40, $y, (string)$ev['venue'], 'F1', 12); $y += 18; }
        $y += 12;
        // Details (left) + QR (right)
        $qrSize = 210; $qrX = $W - 40 - $qrSize; $qrY = $y;
        $rows = [
            ['TICKET', $t['name'] ?? 'Admission'],
            ['ATTENDEE', ($order['buyer_name'] ?: $order['buyer_email'])],
            ['ORDER', $ref],
            ['NUMBER', ($i + 1) . ' of ' . $total],
            ['CODE', ticket_short_code($t['code'])],
        ];
        foreach ($rows as [$k, $v]) {
            $pdf->setColor(140, 120, 120); $pdf->text(40, $y, $k, 'F2', 9);
            $pdf->setColor(26, 5, 5);      $pdf->text(40, $y + 13, (string)$v, 'F2', 14);
            $y += 40;
        }
        $pdf->setColor(234, 226, 226); $pdf->rect($qrX - 6, $qrY - 6, $qrSize + 12, $qrSize + 12, false);
        $pdf->qr($qrX, $qrY, $qrSize, ticket_qr_matrix(ticket_checkin_url($t['code'])));
        $pdf->setColor(140, 120, 120);
        $pdf->text($qrX + 30, $qrY + $qrSize + 12, 'Scan at the entrance', 'F1', 10);
        if (($t['status'] ?? '') === 'void') {
            $pdf->setColor(204, 0, 0); $pdf->text(40, $y + 10, 'VOID - this ticket was refunded', 'F2', 16);
        }
        // Footer
        $fy = $pdf->pageHeight() - 70;
        $pdf->setColor(234, 226, 226); $pdf->line(40, $fy, $W - 40, $fy);
        $pdf->setColor(122, 101, 101);
        $pdf->text(40, $fy + 12, 'One QR code admits one person and can be scanned once. Do not share it.', 'F1', 9);
        $pdf->text(40, $fy + 26, 'exit473.com  ·  +1 (473) 403-7377  ·  exit473.gd@gmail.com', 'F1', 9);
    }
    return $pdf->output();
}
