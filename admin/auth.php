<?php
/**
 * admin/auth.php
 *
 * Session + auth helpers for the core site admin (/admin). Real per-user
 * accounts via Supabase Auth (same project as portal/, see
 * portal/config.php), replacing the old single shared admin/.adminpass
 * password. Roles (site_owner / content_manager) live in the admin_profiles
 * table — see admin/schema_01_admin_profiles.sql.
 *
 * Session keys are deliberately different from portal/auth.php's
 * portal_* keys so a visitor can (in principle) hold both an admin session
 * and an organizer session at once.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../portal/supabase.php';

define('DATA_PATH', realpath(__DIR__ . '/../data') . DIRECTORY_SEPARATOR);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

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
function admin_store_session($authData) {
    $_SESSION['admin_access_token']  = $authData['access_token']  ?? null;
    $_SESSION['admin_refresh_token'] = $authData['refresh_token'] ?? null;
    $_SESSION['admin_user_id']       = $authData['user']['id']    ?? null;
    $_SESSION['admin_user_email']    = $authData['user']['email'] ?? null;
}

function admin_logout() {
    unset(
        $_SESSION['admin_access_token'],
        $_SESSION['admin_refresh_token'],
        $_SESSION['admin_user_id'],
        $_SESSION['admin_user_email']
    );
}

function admin_base64url_decode($data) {
    $pad = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    return base64_decode(strtr($data, '-_', '+/'));
}

/** Reads the `exp` claim out of a JWT without needing a signing-key/library. */
function admin_jwt_expiry($jwt) {
    $parts = explode('.', $jwt);
    if (count($parts) < 2) return 0;
    $payload = json_decode(admin_base64url_decode($parts[1]), true);
    return (int)($payload['exp'] ?? 0);
}

/**
 * Returns a valid access token for the current session, transparently
 * refreshing it first if it's expired (or about to). Returns null if the
 * visitor isn't logged in or the refresh token is no longer valid.
 */
function admin_access_token() {
    if (empty($_SESSION['admin_access_token'])) return null;

    if (admin_jwt_expiry($_SESSION['admin_access_token']) > time() + 30) {
        return $_SESSION['admin_access_token'];
    }

    if (!empty($_SESSION['admin_refresh_token'])) {
        $refreshed = supabase_auth_refresh($_SESSION['admin_refresh_token']);
        if ($refreshed['status'] === 200 && !empty($refreshed['data']['access_token'])) {
            admin_store_session($refreshed['data']);
            return $_SESSION['admin_access_token'];
        }
    }

    admin_logout();
    return null;
}

/**
 * The logged-in admin's own admin_profiles row (id, email, role,
 * display_name), or null. Reads via the service_role key rather than the
 * user's own token — every caller here is already-trusted server-side PHP,
 * and it sidesteps needing the row's RLS policy to cover every access shape.
 */
function admin_current_profile() {
    if (!admin_access_token() || empty($_SESSION['admin_user_id'])) return null;
    static $profile = null;
    if ($profile !== null) return $profile;
    $resp = supabase_table_select(
        'admin_profiles',
        'id=eq.' . urlencode($_SESSION['admin_user_id']) . '&select=*',
        null,
        true
    );
    $profile = ($resp['status'] === 200 && !empty($resp['data'][0])) ? $resp['data'][0] : false;
    return $profile ?: null;
}

/** Records a login for the Users page's last-login/count columns. Best-effort. */
function admin_record_login($uid) {
    supabase_rpc('record_admin_login', ['uid' => $uid], null, true);
}

function require_auth() {
    if (!admin_access_token()) {
        header('Location: index.php'); exit;
    }
}

/** Gate a page to one or more roles, e.g. require_role(['site_owner']). */
function require_role(array $roles) {
    require_auth();
    $profile = admin_current_profile();
    if (!$profile || !in_array($profile['role'], $roles, true)) {
        http_response_code(403);
        die('You do not have permission to view this page.');
    }
}

/* ---- CSV helpers ---- */
function read_csv($file) {
    $rows = [];
    if (!file_exists($file)) return $rows;
    $fh = fopen($file, 'r');
    if (!$fh) return $rows;
    $headers = array_map('trim', fgetcsv($fh) ?: []);
    if ($headers) $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF"); // strip UTF-8 BOM
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
    fprintf($fh, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens it correctly
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

function write_js_datafile($js_file, $var_name, $csv_file) {
    $csv = file_get_contents($csv_file);
    if ($csv === false) return false;
    // Remove UTF-8 BOM if present
    $csv = ltrim($csv, "\xEF\xBB\xBF");
    // Escape backticks so they don't break the JS template literal
    $csv = str_replace('`', '\`', $csv);
    $js  = "window.{$var_name} = `" . $csv . "`;\n";
    return file_put_contents($js_file, $js) !== false;
}
