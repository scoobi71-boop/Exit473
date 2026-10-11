<?php
if (session_status() === PHP_SESSION_NONE) session_start();

define('DATA_PATH', realpath(__DIR__ . '/../data') . DIRECTORY_SEPARATOR);
/*
 * The admin password hash lives OUTSIDE the web root so it can never be
 * downloaded. Default location: <parent of DOCUMENT_ROOT>/exit473-private/.adminpass
 * Override by defining EXIT473_PASS_FILE as an environment variable (SetEnv).
 * Generate a hash with:  php -r "echo password_hash('NEWPASS', PASSWORD_DEFAULT);"
 */
function pass_file_path() {
    $env = getenv('EXIT473_PASS_FILE');
    if ($env) return $env;
    $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
    $base = $docroot !== '' ? dirname($docroot) : dirname(__DIR__, 2);
    return $base . DIRECTORY_SEPARATOR . 'exit473-private' . DIRECTORY_SEPARATOR . '.adminpass';
}
define('PASS_FILE', pass_file_path());

function get_stored_hash() {
    if (!is_readable(PASS_FILE)) {
        http_response_code(500);
        error_log('Exit473 admin: password hash file missing or unreadable: ' . PASS_FILE);
        die('Admin login is not configured. Create the password hash file outside the web root '
          . '(exit473-private/.adminpass next to your public web folder). See README "Admin password".');
    }
    $hash = trim(file_get_contents(PASS_FILE));
    if ($hash === '') {
        http_response_code(500);
        die('Admin password hash file is empty. See README "Admin password".');
    }
    return $hash;
}

function require_auth() {
    if (empty($_SESSION['admin_ok'])) {
        header('Location: index.php'); exit;
    }
}

function verify_pw($pw)  { return password_verify($pw, get_stored_hash()); }
function change_pw($new) { return file_put_contents(PASS_FILE, password_hash($new, PASSWORD_DEFAULT)) !== false; }

function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function check_csrf() {
    if (empty($_POST['_csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf'])) {
        http_response_code(403); die('Invalid request.');
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

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

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
