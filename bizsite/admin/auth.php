<?php
if (session_status() === PHP_SESSION_NONE) session_start();

define('BIZ_DATA_ROOT', realpath(__DIR__ . '/../data') . DIRECTORY_SEPARATOR);
define('BIZ_IMG_ROOT',  dirname(__DIR__) . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR);

/* ---- Business helpers ---- */
function biz_exists($slug) {
    return $slug !== '' && is_dir(BIZ_DATA_ROOT . $slug);
}

function current_slug() { return $_SESSION['admin_slug'] ?? ''; }

function get_hash_file($slug) {
    return BIZ_DATA_ROOT . $slug . DIRECTORY_SEPARATOR . '.adminpass';
}

function verify_pw($slug, $pw) {
    $f = get_hash_file($slug);
    if (!file_exists($f)) return false;
    return password_verify($pw, trim(file_get_contents($f)));
}

function change_pw($slug, $new) {
    return file_put_contents(get_hash_file($slug), password_hash($new, PASSWORD_DEFAULT)) !== false;
}

/* ---- Auth ---- */
function require_auth() {
    if (empty($_SESSION['admin_ok']) || empty($_SESSION['admin_slug'])) {
        header('Location: index.php'); exit;
    }
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
