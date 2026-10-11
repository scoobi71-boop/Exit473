<?php
/**
 * scripts/finance-sync-businesses.php
 *
 * Populates/updates public.finance_businesses from whatever business
 * folders actually exist under bizsite/data/ — the finance module's own
 * business registry, kept separate from biz_profiles (the login table) so
 * financial history survives a business's login account being removed or
 * reassigned (see admin/schema_05_finance_schema.sql's design note).
 *
 * Same technique as scripts/migrate-bizsite-users.php: scans
 * bizsite/data/*, skips `demo` (this repo's sample business), and reads
 * each folder's info.csv `name` field for the display name. Safe to
 * re-run any time (upserts by slug) — this is also exactly what
 * admin/finance.php's "Sync Businesses" button calls.
 *
 * Run from the repo root:
 *
 *   php scripts/finance-sync-businesses.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("This script is CLI-only.\n");
}

require_once __DIR__ . '/../portal/supabase.php';

define('BIZ_DATA_ROOT', realpath(__DIR__ . '/../bizsite/data') . DIRECTORY_SEPARATOR);
const SKIP_SLUGS = ['demo'];

function finance_read_business_name($slug) {
    $file = BIZ_DATA_ROOT . $slug . DIRECTORY_SEPARATOR . 'info.csv';
    if (!file_exists($file)) return ucwords(str_replace('-', ' ', $slug));
    $fh = fopen($file, 'r');
    if (!$fh) return ucwords(str_replace('-', ' ', $slug));
    while (($row = fgetcsv($fh)) !== false) {
        $k = trim(ltrim($row[0] ?? '', "\xEF\xBB\xBF"));
        if ($k === 'name' && !empty($row[1])) {
            fclose($fh);
            return $row[1];
        }
    }
    fclose($fh);
    return ucwords(str_replace('-', ' ', $slug));
}

$slugs = [];
foreach (glob(BIZ_DATA_ROOT . '*', GLOB_ONLYDIR) as $dir) {
    $slug = basename($dir);
    if (!in_array($slug, SKIP_SLUGS, true)) $slugs[] = $slug;
}

if (!$slugs) {
    echo "No business folders found under bizsite/data/ (besides the skipped ones).\n";
    exit(0);
}

$existing = supabase_table_select('finance_businesses', 'select=slug', null, true);
$existingSlugs = [];
if ($existing['status'] === 200) {
    foreach ($existing['data'] as $row) $existingSlugs[] = $row['slug'];
}

$created = [];
$updated = [];
$failed  = [];

foreach ($slugs as $slug) {
    $name = finance_read_business_name($slug);

    if (in_array($slug, $existingSlugs, true)) {
        $resp = supabase_table_update('finance_businesses', 'slug=eq.' . urlencode($slug), [
            'display_name' => $name,
            'updated_at'   => date('c'),
        ], null, true);
        if ($resp['status'] >= 200 && $resp['status'] < 300) {
            $updated[] = $slug;
        } else {
            echo "Failed to update {$slug}: " . ($resp['raw'] ?? 'unknown error') . "\n";
            $failed[] = $slug;
        }
        continue;
    }

    $resp = supabase_table_insert('finance_businesses', [
        'slug'         => $slug,
        'display_name' => $name,
        'status'       => 'active',
    ], null, true);
    if ($resp['status'] >= 200 && $resp['status'] < 300) {
        $created[] = "{$slug} ({$name})";
    } else {
        echo "Failed to create {$slug}: " . ($resp['raw'] ?? 'unknown error') . "\n";
        $failed[] = $slug;
    }
}

echo "\nCreated " . count($created) . " business(es):\n";
foreach ($created as $c) echo "  - {$c}\n";

echo "\nUpdated " . count($updated) . " existing business(es).\n";

if ($failed) {
    echo "\nFailed:\n";
    foreach ($failed as $s) echo "  - {$s}\n";
}
