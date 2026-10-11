<?php
/**
 * scripts/migrate-bizsite-users.php
 *
 * One-off CLI to migrate existing bizsite/ businesses (created under the old
 * shared-per-business-password system — one folder per slug under
 * bizsite/data/, guarded by a data/<slug>/.adminpass file) into real
 * Supabase Auth accounts + biz_profiles rows. See
 * bizsite/admin/schema_01_biz_profiles.sql — run that in the Supabase SQL
 * Editor before this script.
 *
 * Every bizsite/data/<slug> folder without a biz_profiles row yet gets an
 * account:
 *   - Email: the business's info.csv `email` field if it's a usable
 *     address, otherwise a Gmail plus-address fallback,
 *     exit473.gd+<slug>@gmail.com — a distinct, real address (Supabase
 *     requires unique emails per account) that still lands in
 *     exit473.gd@gmail.com's inbox. Swap it for the business's real email
 *     later from admin/users.php's Businesses tab (Edit > Email) as soon as
 *     you have it — that updates their login too.
 *   - Password: a random 12-character default, printed once at the end of
 *     this run so you can hand it to the business owner. No password-reset
 *     email is sent (unlike admin/users.php's normal invite flow) — these
 *     are pre-set, not self-registered.
 *
 * The `demo` folder (this repo's sample business, not a real customer) is
 * always skipped.
 *
 * This only sees whatever is physically in bizsite/data/ wherever you run
 * it — if your real businesses only exist on the live server (not in this
 * local checkout), deploy this script there first and run it over SSH.
 *
 * Run once, from the repo root:
 *
 *   php scripts/migrate-bizsite-users.php
 *
 * Safe to re-run: slugs that already have a biz_profiles row are skipped
 * automatically (no new account, no new password is generated for them).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("This script is CLI-only.\n");
}

require_once __DIR__ . '/../portal/supabase.php';

define('BIZ_DATA_ROOT', realpath(__DIR__ . '/../bizsite/data') . DIRECTORY_SEPARATOR);
const SKIP_SLUGS      = ['demo'];
const FALLBACK_EMAIL  = 'exit473.gd@gmail.com';

function read_info_csv($file) {
    $map = [];
    if (!file_exists($file)) return $map;
    $fh = fopen($file, 'r');
    if (!$fh) return $map;
    while (($row = fgetcsv($fh)) !== false) {
        $k = trim(ltrim($row[0] ?? '', "\xEF\xBB\xBF"));
        if ($k !== '') $map[$k] = $row[1] ?? '';
    }
    fclose($fh);
    return $map;
}

/** exit473.gd@gmail.com -> exit473.gd+<slug>@gmail.com (still delivers to the same inbox). */
function fallback_email_for_slug($slug) {
    [$local, $domain] = explode('@', FALLBACK_EMAIL, 2);
    return $local . '+' . $slug . '@' . $domain;
}

/** A readable random password — excludes 0/O/1/l/I to avoid transcription mistakes when handed out. */
function generate_password($length = 12) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $pw = '';
    for ($i = 0; $i < $length; $i++) $pw .= $chars[random_int(0, strlen($chars) - 1)];
    return $pw;
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

$existing = supabase_table_select('biz_profiles', 'select=slug', null, true);
$existingSlugs = [];
if ($existing['status'] === 200) {
    foreach ($existing['data'] as $row) $existingSlugs[] = $row['slug'];
}

$migrated = []; // slug => [email, password, usedFallback]
$skippedExisting = [];
$failed = [];

foreach ($slugs as $slug) {
    if (in_array($slug, $existingSlugs, true)) {
        $skippedExisting[] = $slug;
        continue;
    }

    $info       = read_info_csv(BIZ_DATA_ROOT . $slug . DIRECTORY_SEPARATOR . 'info.csv');
    $realEmail  = trim($info['email'] ?? '');
    $usedFallback = !filter_var($realEmail, FILTER_VALIDATE_EMAIL);
    $email = $usedFallback ? fallback_email_for_slug($slug) : $realEmail;

    $existingEmail = supabase_table_select('biz_profiles', 'email=eq.' . urlencode($email) . '&select=slug', null, true);
    if ($existingEmail['status'] === 200 && !empty($existingEmail['data'])) {
        echo "Skipping {$slug}: {$email} already manages \"{$existingEmail['data'][0]['slug']}\".\n";
        $failed[] = $slug;
        continue;
    }

    $password = generate_password();
    $created  = supabase_auth_admin_create_user($email, ['slug' => $slug], true, $password);
    $uid = null;
    if (in_array($created['status'], [200, 201], true) && !empty($created['data']['id'])) {
        $uid = $created['data']['id'];
    } elseif (($created['data']['error_code'] ?? '') === 'email_exists') {
        echo "Skipping {$slug}: {$email} is already a Supabase Auth user (e.g. an admin or event organizer) — invite manually with a distinct email.\n";
        $failed[] = $slug;
        continue;
    }

    if (!$uid) {
        echo "Failed to create an auth user for {$slug} ({$email}): " . ($created['raw'] ?? 'unknown error') . "\n";
        $failed[] = $slug;
        continue;
    }

    $inserted = supabase_table_insert('biz_profiles', [
        'id'    => $uid,
        'email' => $email,
        'slug'  => $slug,
    ], null, true);
    if ($inserted['status'] < 200 || $inserted['status'] >= 300) {
        echo "Created the auth user for {$slug} but failed to insert biz_profiles: " . ($inserted['raw'] ?? 'unknown error') . "\n";
        $failed[] = $slug;
        continue;
    }

    $migrated[$slug] = [$email, $password, $usedFallback];
}

echo "\nCreated " . count($migrated) . " account(s):\n";
echo str_pad('Business ID', 26) . str_pad('Login Email', 38) . "Password\n";
echo str_repeat('-', 76) . "\n";
foreach ($migrated as $slug => [$email, $password, $usedFallback]) {
    echo str_pad($slug, 26) . str_pad($email, 38) . $password . ($usedFallback ? '  (placeholder email)' : '') . "\n";
}

if ($skippedExisting) {
    echo "\nAlready had an account, skipped:\n";
    foreach ($skippedExisting as $s) echo "  - {$s}\n";
}

if ($failed) {
    echo "\nFailed:\n";
    foreach ($failed as $s) echo "  - {$s}\n";
}

echo "\nSave the table above somewhere safe and hand each password to its business owner.\n";
echo "For any \"(placeholder email)\" row, update the real email as soon as you have it from\n";
echo "admin/users.php's Businesses tab (Edit > Email) — that updates their login too.\n";
