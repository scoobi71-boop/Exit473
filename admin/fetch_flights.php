<?php
/**
 * fetch_flights.php
 *
 * Fetch arrivals and departures for GND (Maurice Bishop Intl) from a free API
 * and store a cached JSON at ../data/flights.json. Intended to be run from
 * CLI (cron) or via a protected web request. Configure API key in
 * ../data/flight_config.php or via environment variable AVIATIONSTACK_API_KEY.
 */

$ROOT = dirname(__DIR__);
$dataDir = $ROOT . '/data';
$outFile = $dataDir . '/flights.json';

// Load config
$configFile = $dataDir . '/flight_config.php';
$apiKey = null;
if (file_exists($configFile)) {
    $cfg = include $configFile;
    if (!empty($cfg['aviationstack_api_key'])) $apiKey = $cfg['aviationstack_api_key'];
}
if (empty($apiKey) && getenv('AVIATIONSTACK_API_KEY')) {
    $apiKey = getenv('AVIATIONSTACK_API_KEY');
}

if (empty($apiKey)) {
    // Do not abort — write an empty file with error info so site still serves something.
    $payload = [
        'fetched_at' => gmdate('c'),
        'error' => 'No API key configured. Create data/flight_config.php or set AVIATIONSTACK_API_KEY.',
        'arrivals' => [],
        'departures' => []
    ];
    @file_put_contents($outFile, json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX);
    echo "No API key configured. Wrote empty payload to $outFile\n";
    exit(0);
}

function fetchUrl($url) {
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Exit473-FlightFetcher/1.0\r\n",
            'timeout' => 15,
        ]
    ];
    $context = stream_context_create($opts);
    $json = @file_get_contents($url, false, $context);
    if ($json === false) return null;
    $data = json_decode($json, true);
    return $data ?: null;
}

function normalizeFlights($items, $mode = 'arrival') {
    $out = [];
    if (!is_array($items)) return $out;
    foreach ($items as $it) {
        $flightCode = $it['flight']['iata'] ?? ($it['flight']['number'] ?? ($it['flight']['icao'] ?? ''));
        $airline = $it['airline']['name'] ?? '';
        $aircraft = $it['aircraft']['registration'] ?? ($it['aircraft']['icao'] ?? ($it['airline']['iata'] ?? ''));
        $status = $it['flight_status'] ?? '';
        $time = '';
        $from = '';
        if ($mode === 'arrival') {
            $time = $it['arrival']['scheduled'] ?? $it['arrival']['estimated'] ?? $it['arrival']['actual'] ?? '';
            $from = $it['departure']['airport'] ?? ($it['departure']['iata'] ?? '');
        } else {
            $time = $it['departure']['scheduled'] ?? $it['departure']['estimated'] ?? $it['departure']['actual'] ?? '';
            $from = $it['arrival']['airport'] ?? ($it['arrival']['iata'] ?? '');
        }
        $out[] = [
            'time' => $time,
            'flight' => $flightCode,
            'from' => $from,
            'airline' => $airline,
            'aircraft' => $aircraft,
            'status' => $status,
        ];
    }
    return $out;
}

$base = 'http://api.aviationstack.com/v1/flights';
$limit = 100; // reasonable

$arrUrl = $base . '?access_key=' . urlencode($apiKey) . '&arr_iata=GND&limit=' . $limit;
$depUrl = $base . '?access_key=' . urlencode($apiKey) . '&dep_iata=GND&limit=' . $limit;

$arrResp = fetchUrl($arrUrl);
$depResp = fetchUrl($depUrl);

$arrItems = $arrResp['data'] ?? [];
$depItems = $depResp['data'] ?? [];

$arrivals = normalizeFlights($arrItems, 'arrival');
$departures = normalizeFlights($depItems, 'departure');

$payload = [
    'fetched_at' => gmdate('c'),
    'source' => 'aviationstack',
    'arrivals' => $arrivals,
    'departures' => $departures,
    'meta' => [
        'arr_count' => count($arrivals),
        'dep_count' => count($departures),
    ]
];

@file_put_contents($outFile, json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX);

echo "Wrote flights to $outFile (arrivals: " . count($arrivals) . ", departures: " . count($departures) . ")\n";

// Exit with HTTP 200 text when run via web
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    echo json_encode($payload);
}

?>
