<?php
/**
 * bizsite/admin/auth.php
 *
 * Session + auth helpers for the bizsite/ business admin. Real per-user
 * accounts via Supabase Auth (same project as portal/ and admin/, see
 * portal/config.php), replacing the old shared-per-business password file
 * (data/<slug>/.adminpass). Each Supabase user is linked to exactly one
 * business slug via the biz_profiles table — see
 * bizsite/admin/schema_01_biz_profiles.sql.
 *
 * Session keys are deliberately different from admin/auth.php's admin_*
 * keys and portal/auth.php's portal_* keys so a visitor could in principle
 * hold more than one of those sessions at once.
 *
 * bizsite/index.php and bizsite/menu.php (the public-facing site) also
 * require this file, but only for the plain utility functions below
 * (clean_slug, read_info, has_menu, h, ...) — they never call require_auth().
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../portal/supabase.php';

define('BIZ_DATA_ROOT', realpath(__DIR__ . '/../data') . DIRECTORY_SEPARATOR);
define('BIZ_IMG_ROOT',  dirname(__DIR__) . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR);

/* ---- Business helpers ---- */
function biz_exists($slug) {
    return $slug !== '' && is_dir(BIZ_DATA_ROOT . $slug);
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function check_csrf() {
    if (empty($_POST['_csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf'])) {
        http_response_code(403); die('Invalid request.');
    }
}

/* ---- Supabase-backed session ---- */

/** Store a Supabase auth session (access/refresh tokens + user) after login/reset. */
function biz_store_session($authData) {
    $_SESSION['biz_access_token']  = $authData['access_token']  ?? null;
    $_SESSION['biz_refresh_token'] = $authData['refresh_token'] ?? null;
    $_SESSION['biz_user_id']       = $authData['user']['id']    ?? null;
    $_SESSION['biz_user_email']    = $authData['user']['email'] ?? null;
}

function biz_logout() {
    unset(
        $_SESSION['biz_access_token'],
        $_SESSION['biz_refresh_token'],
        $_SESSION['biz_user_id'],
        $_SESSION['biz_user_email']
    );
}

function biz_base64url_decode($data) {
    $pad = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    return base64_decode(strtr($data, '-_', '+/'));
}

/** Reads the `exp` claim out of a JWT without needing a signing-key/library. */
function biz_jwt_expiry($jwt) {
    $parts = explode('.', $jwt);
    if (count($parts) < 2) return 0;
    $payload = json_decode(biz_base64url_decode($parts[1]), true);
    return (int)($payload['exp'] ?? 0);
}

/**
 * Returns a valid access token for the current session, transparently
 * refreshing it first if it's expired (or about to). Returns null if the
 * visitor isn't logged in or the refresh token is no longer valid.
 */
function biz_access_token() {
    if (empty($_SESSION['biz_access_token'])) return null;

    if (biz_jwt_expiry($_SESSION['biz_access_token']) > time() + 30) {
        return $_SESSION['biz_access_token'];
    }

    if (!empty($_SESSION['biz_refresh_token'])) {
        $refreshed = supabase_auth_refresh($_SESSION['biz_refresh_token']);
        if ($refreshed['status'] === 200 && !empty($refreshed['data']['access_token'])) {
            biz_store_session($refreshed['data']);
            return $_SESSION['biz_access_token'];
        }
    }

    biz_logout();
    return null;
}

/**
 * The logged-in business user's own biz_profiles row (id, email, slug,
 * first/last name, phone), or null. Reads via the service_role key rather
 * than the user's own token — every caller here is already-trusted
 * server-side PHP, and it sidesteps needing the row's RLS policy to cover
 * every access shape.
 */
function biz_current_profile() {
    if (!biz_access_token() || empty($_SESSION['biz_user_id'])) return null;
    static $profile = null;
    if ($profile !== null) return $profile;
    $resp = supabase_table_select(
        'biz_profiles',
        'id=eq.' . urlencode($_SESSION['biz_user_id']) . '&select=*',
        null,
        true
    );
    $profile = ($resp['status'] === 200 && !empty($resp['data'][0])) ? $resp['data'][0] : false;
    return $profile ?: null;
}

/** Records a login for the Users page's last-login/count columns. Best-effort. */
function biz_record_login($uid) {
    supabase_rpc('record_biz_login', ['uid' => $uid], null, true);
}

/** The current business user's slug, or '' if not logged in / no profile. */
function current_slug() {
    $profile = biz_current_profile();
    return $profile['slug'] ?? '';
}

function require_auth() {
    if (!biz_access_token() || !biz_current_profile()) {
        biz_logout();
        header('Location: index.php'); exit;
    }
}

/* ---- Info CSV (key-value, no header row) ---- */
function read_info($slug) {
    $file = BIZ_DATA_ROOT . $slug . DIRECTORY_SEPARATOR . 'info.csv';
    $map  = [];
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

function write_info($slug, array $map) {
    $dir = BIZ_DATA_ROOT . $slug . DIRECTORY_SEPARATOR;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $file = $dir . 'info.csv';
    $tmp  = $file . '.tmp';
    $fh   = fopen($tmp, 'w');
    if (!$fh) return false;
    fprintf($fh, "\xEF\xBB\xBF");
    foreach ($map as $k => $v) fputcsv($fh, [$k, $v]);
    fclose($fh);
    return rename($tmp, $file);
}

/* ---- Menu CSV (tabular) ---- */
function menu_path($slug) {
    return BIZ_DATA_ROOT . $slug . DIRECTORY_SEPARATOR . 'menu.csv';
}

function read_csv($file) {
    $rows = [];
    if (!file_exists($file)) return $rows;
    $fh = fopen($file, 'r');
    if (!$fh) return $rows;
    $headers = array_map('trim', fgetcsv($fh) ?: []);
    if ($headers) $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
    while (($row = fgetcsv($fh)) !== false) {
        if (count($row) >= count($headers))
            $rows[] = array_combine($headers, array_slice($row, 0, count($headers)));
    }
    fclose($fh);
    return $rows;
}

function write_csv($file, array $rows, array $headers) {
    $tmp = $file . '.tmp';
    $fh  = fopen($tmp, 'w');
    if (!$fh) return false;
    fprintf($fh, "\xEF\xBB\xBF");
    fputcsv($fh, $headers);
    foreach ($rows as $row) {
        $line = [];
        foreach ($headers as $h) $line[] = $row[$h] ?? '';
        fputcsv($fh, $line);
    }
    fclose($fh);
    return rename($tmp, $file);
}

function next_id(array $rows) {
    $ids = array_filter(array_column($rows, 'id'), 'is_numeric');
    return $ids ? (max($ids) + 1) : 1;
}

/* ---- Image upload ---- */
function handle_upload($input_name, $slug) {
    if (empty($_FILES[$input_name]) || $_FILES[$input_name]['error'] !== UPLOAD_ERR_OK) return null;
    $file = $_FILES[$input_name];
    $allowed_types = ['image/jpeg','image/png','image/gif','image/webp'];
    if (!in_array($file['type'], $allowed_types)) return null;
    if ($file['size'] > 5 * 1024 * 1024) return null;
    $dir = BIZ_IMG_ROOT . $slug . DIRECTORY_SEPARATOR;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $name = $input_name . '_' . time() . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir . $name)) {
        return 'images/' . $slug . '/' . $name;
    }
    return null;
}

/* ---- Gallery CSV ---- */
function gallery_path($slug) {
    return BIZ_DATA_ROOT . $slug . DIRECTORY_SEPARATOR . 'gallery.csv';
}

function read_gallery($slug) {
    return read_csv(gallery_path($slug));
}

function write_gallery($slug, array $rows) {
    return write_csv(gallery_path($slug), $rows, ['id','filename','caption','sort_order']);
}

function handle_gallery_upload($slug) {
    if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) return null;
    $file = $_FILES['photo'];
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (!in_array($file['type'], $allowed)) return null;
    if ($file['size'] > 8 * 1024 * 1024) return null;
    $dir = BIZ_IMG_ROOT . $slug . DIRECTORY_SEPARATOR . 'gallery' . DIRECTORY_SEPARATOR;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $name = 'g_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir . $name)) {
        return 'images/' . $slug . '/gallery/' . $name;
    }
    return null;
}

/* ---- Misc ---- */
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function clean_slug($s) {
    return preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($s)));
}

function menu_categories() {
    return ['restaurant','bar','cafe','food','snack-bar','tea-shop'];
}

function has_menu($info) {
    return in_array(strtolower($info['category'] ?? ''), menu_categories());
}
