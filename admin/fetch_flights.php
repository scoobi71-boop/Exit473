<?php
/**
 * fetch_flights.php
 *
 * Fetch arrivals and departures for GND (Maurice Bishop Intl) from the
 * AeroDataBox API (via RapidAPI) and store a cached JSON at ../data/flights.json.
 * Intended to be run from CLI (cron) or via a protected web request. Configure
 * API key in ../data/flight_config.php or via environment variable AERODATABOX_API_KEY.
 */

$ROOT = dirname(__DIR__);
$dataDir = $ROOT . '/data';
$outFile = $dataDir . '/flights.json';

// Load config
$configFile = $dataDir . '/flight_config.php';
$apiKey = null;
if (file_exists($configFile)) {
    $cfg = include $configFile;
    if (!empty($cfg['aerodatabox_api_key'])) $apiKey = $cfg['aerodatabox_api_key'];
}
if (empty($apiKey) && getenv('AERODATABOX_API_KEY')) {
    $apiKey = getenv('AERODATABOX_API_KEY');
}

if (empty($apiKey)) {
    // Do not abort — write an empty file with error info so site still serves something.
    $payload = [
        'fetched_at' => gmdate('c'),
        'error' => 'No API key configured. Create data/flight_config.php with aerodatabox_api_key or set AERODATABOX_API_KEY.',
        'arrivals' => [],
        'departures' => []
    ];
    @file_put_contents($outFile, json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX);
    echo "No API key configured. Wrote empty payload to $outFile\n";
    exit(0);
}

function fetchJson($url, $apiKey) {
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "X-RapidAPI-Key: $apiKey\r\nX-RapidAPI-Host: aerodatabox.p.rapidapi.com\r\n",
            'timeout' => 20,
            'ignore_errors' => true,
        ]
    ];
    $context = stream_context_create($opts);
    $json = @file_get_contents($url, false, $context);
    $status = 0;
    if (isset($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) $status = (int)$m[1];
        }
    }
    if ($json === false || $json === '') return ['status' => $status, 'data' => null];
    $data = json_decode($json, true);
    return ['status' => $status, 'data' => $data];
}

function airportLabel($airport) {
    if (!$airport || empty($airport['name'])) return '';
    return !empty($airport['iata']) ? $airport['name'] . ' (' . $airport['iata'] . ')' : $airport['name'];
}

// AeroDataBox returns UTC times like "2026-08-20 00:25Z" (space, no seconds) —
// not the strict ISO-8601 ("...T...+00:00") format flights.html's `new Date()`
// calls expect, so normalize it here.
function normalizeTime($raw) {
    if (!$raw) return '';
    try {
        return (new DateTime($raw, new DateTimeZone('UTC')))->format(DateTime::ATOM);
    } catch (Exception $e) {
        return $raw;
    }
}

function pickTime($movement) {
    if (!$movement) return '';
    $raw = $movement['scheduledTime']['utc']
        ?? $movement['revisedTime']['utc']
        ?? $movement['runwayTime']['utc']
        ?? '';
    return normalizeTime($raw);
}

function normalizeFlights($items, $mode = 'arrival') {
    $out = [];
    if (!is_array($items)) return $out;
    foreach ($items as $it) {
        $flightCode = $it['number'] ?? '';
        $airline = $it['airline']['name'] ?? '';
        $aircraft = $it['aircraft']['model'] ?? ($it['aircraft']['reg'] ?? '');
        $status = $it['status'] ?? '';
        if ($mode === 'arrival') {
            $movement = $it['arrival'] ?? ($it['movement'] ?? null);
            $other = $it['departure'] ?? ($it['movement'] ?? null);
        } else {
            $movement = $it['departure'] ?? ($it['movement'] ?? null);
            $other = $it['arrival'] ?? ($it['movement'] ?? null);
        }
        $out[] = [
            'time' => pickTime($movement),
            'flight' => $flightCode,
            'from' => airportLabel($other['airport'] ?? null),
            'airline' => $airline,
            'aircraft' => $aircraft,
            'status' => $status,
        ];
    }
    return $out;
}

// AeroDataBox takes a per-request local-time range capped at 12 hours, and
// interprets fromLocal/toLocal as wall-clock time at the airport. flights.html
// filters results client-side to whatever UTC day fetched_at falls on, so we
// build our two 12-hour windows from today's UTC boundaries (converted to
// Grenada local wall-clock) to make sure that filter doesn't drop flights.
$utc = new DateTimeZone('UTC');
$grenada = new DateTimeZone('America/Grenada');
$now = new DateTime('now', $utc);
$utcBounds = [
    (clone $now)->setTime(0, 0),
    (clone $now)->setTime(12, 0),
    (clone $now)->setTime(23, 59),
];
$windows = [
    [(clone $utcBounds[0])->setTimezone($grenada), (clone $utcBounds[1])->setTimezone($grenada)],
    [(clone $utcBounds[1])->setTimezone($grenada), (clone $utcBounds[2])->setTimezone($grenada)],
];

$code = 'GND';
$arrItems = [];
$depItems = [];
$errors = [];

$first = true;
foreach ($windows as [$from, $to]) {
    // AeroDataBox's free tier throttles bursts; a small gap between our two
    // window requests avoids spurious 429s.
    if (!$first) sleep(2);
    $first = false;

    $fromStr = $from->format('Y-m-d\TH:i');
    $toStr = $to->format('Y-m-d\TH:i');
    $url = 'https://aerodatabox.p.rapidapi.com/flights/airports/iata/' . urlencode($code)
        . '/' . urlencode($fromStr) . '/' . urlencode($toStr) . '?withLeg=true';
    $resp = fetchJson($url, $apiKey);
    if ($resp['status'] === 204) {
        continue; // no flights scheduled in this window
    }
    if ($resp['status'] !== 200 || !is_array($resp['data'])) {
        $errors[] = "Request for $fromStr - $toStr failed (HTTP {$resp['status']})";
        continue;
    }
    $arrItems = array_merge($arrItems, $resp['data']['arrivals'] ?? []);
    $depItems = array_merge($depItems, $resp['data']['departures'] ?? []);
}

$arrivals = normalizeFlights($arrItems, 'arrival');
$departures = normalizeFlights($depItems, 'departure');

// newest first
usort($arrivals, fn($a, $b) => strcmp($b['time'], $a['time']));
usort($departures, fn($a, $b) => strcmp($b['time'], $a['time']));

$payload = [
    'fetched_at' => gmdate('c'),
    'source' => 'aerodatabox',
    'arrivals' => $arrivals,
    'departures' => $departures,
    'meta' => [
        'arr_count' => count($arrivals),
        'dep_count' => count($departures),
    ]
];
if (!empty($errors)) $payload['errors'] = $errors;

@file_put_contents($outFile, json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX);

echo "Wrote flights to $outFile (arrivals: " . count($arrivals) . ", departures: " . count($departures) . ")\n";

// Exit with HTTP 200 text when run via web
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    echo json_encode($payload);
}

?>
