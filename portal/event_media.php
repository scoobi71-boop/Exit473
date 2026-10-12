<?php
/**
 * portal/event_media.php
 *
 * Banner + poster uploads for portal events, shared by the organizer editor
 * (portal/event-edit.php) and the admin media page (portal/admin/event-media.php).
 *
 * Files are stored on this server under /images/portal-events/<event id>/,
 * like the existing admin event photos in /images/events/. That folder gets
 * an .htaccess that disables script execution. Only real JPEG/PNG/WebP images
 * up to 5 MB are accepted: checked by content (finfo + getimagesize), never by
 * the browser-supplied type or name, and saved under a random name with an
 * extension chosen by us.
 *
 * Columns (schema_04_event_media.sql): events.banner_url, events.poster_urls.
 */
require_once __DIR__ . '/supabase.php';

define('MEDIA_MAX_BYTES', 5 * 1024 * 1024);
define('MEDIA_MAX_POSTERS', 12);
define('MEDIA_URL_BASE', '/images/portal-events/');

function media_root_dir() { return dirname(__DIR__) . '/images/portal-events/'; }

/** True once schema_04 has been run (the banner_url column exists). */
function media_columns_ready() {
    static $ready = null;
    if ($ready !== null) return $ready;
    $r = supabase_table_select('events', 'select=banner_url,poster_urls&limit=1', null, true);
    return $ready = ($r['status'] === 200);
}

function media_posters($event) {
    $p = $event['poster_urls'] ?? [];
    if (is_string($p)) $p = json_decode($p, true);
    return is_array($p) ? array_values(array_filter($p, 'is_string')) : [];
}

function media_ensure_dir($eventId) {
    $root = media_root_dir();
    if (!is_dir($root)) @mkdir($root, 0755, true);
    $ht = $root . '.htaccess';
    if (!file_exists($ht)) {
        @file_put_contents($ht, "# Uploaded images only: never execute anything here.\n"
            . "Options -Indexes -ExecCGI\n"
            . "RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .phar .pl .py .cgi .sh\n"
            . "RemoveType .php .phtml .php3 .php4 .php5 .php7 .phar\n"
            . "<IfModule mod_php.c>\n    php_flag engine off\n</IfModule>\n"
            . "<IfModule mod_php7.c>\n    php_flag engine off\n</IfModule>\n"
            . "<FilesMatch \"(?i)\\.(php\\d?|phtml|phar|pl|py|cgi|sh|html?|svg|js)$|^\\.\">\n"
            . "    <IfModule mod_authz_core.c>\n        Require all denied\n    </IfModule>\n"
            . "    <IfModule !mod_authz_core.c>\n        Order allow,deny\n        Deny from all\n    </IfModule>\n"
            . "</FilesMatch>\n"
            . "<IfModule mod_headers.c>\n    Header set X-Content-Type-Options nosniff\n</IfModule>\n");
    }
    $dir = $root . $eventId . '/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return is_dir($dir) && is_writable($dir) ? $dir : null;
}

/** Normalises $_FILES['x'] (single or multiple) into a list of file arrays. */
function media_files_list($f) {
    if (!$f || !isset($f['name'])) return [];
    if (!is_array($f['name'])) return [$f];
    $out = [];
    foreach ($f['name'] as $i => $_) {
        $out[] = ['name' => $f['name'][$i], 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

/**
 * Validates and stores one uploaded image. Returns ['url' => ...] or ['error' => ...].
 * $kind is 'banner' or 'poster' (used in the file name only).
 */
function media_store_upload($file, $eventId, $kind) {
    if (!preg_match('/^[0-9a-f-]{36}$/i', (string)$eventId)) return ['error' => 'Invalid event.'];
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['error' => 'Choose an image to upload.'];
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) return ['error' => 'That image is larger than 5 MB.'];
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) return ['error' => 'Upload failed. Please try again.'];
    if ($file['size'] <= 0 || $file['size'] > MEDIA_MAX_BYTES) return ['error' => 'Images must be 5 MB or smaller.'];

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $exts = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($exts[$mime])) return ['error' => 'Only JPG, PNG or WebP images are allowed.'];
    $info = @getimagesize($file['tmp_name']);
    if (!$info || ($info['mime'] ?? '') !== $mime || $info[0] < 1 || $info[1] < 1) return ['error' => 'That file isn\'t a valid image.'];

    $dir = media_ensure_dir($eventId);
    if (!$dir) return ['error' => 'The server image folder isn\'t writable.'];
    $name = $kind . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $exts[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) return ['error' => 'Could not save the image.'];
    @chmod($dir . $name, 0644);
    return ['url' => MEDIA_URL_BASE . $eventId . '/' . $name];
}

/** Deletes a stored file, but only if the URL points inside this event's media folder. */
function media_delete_url($url, $eventId) {
    $prefix = MEDIA_URL_BASE . $eventId . '/';
    if (!is_string($url) || strpos($url, $prefix) !== 0) return;
    $name = basename(substr($url, strlen($prefix)));
    if (!preg_match('/^(banner|poster|product|logo)_\d+_[0-9a-f]{12}\.(jpg|png|webp)$/', $name)) return;
    $path = media_root_dir() . $eventId . '/' . $name;
    if (is_file($path)) @unlink($path);
}

/**
 * Handles the media POST actions for one event. $token = organizer access
 * token (RLS-scoped) or null with $serviceRole = true for admins. $extraPatch
 * is merged into every update (organizers: ['status' => 'pending'], because
 * their RLS policy only allows edits that send the event back for review).
 * Returns ['ok' => msg] or ['error' => msg], or null if $action isn't a media action.
 */
function media_handle_action($action, $event, $token = null, $serviceRole = false, array $extraPatch = []) {
    $actions = ['upload_banner', 'remove_banner', 'add_posters', 'remove_poster', 'move_poster'];
    if (!in_array($action, $actions, true)) return null;
    if (!$event) return ['error' => 'Event not found.'];
    if (!media_columns_ready()) return ['error' => 'Banner and poster uploads aren\'t enabled yet: run portal/schema_04_event_media.sql in Supabase first.'];

    $id = $event['id'];
    $posters = media_posters($event);
    $patch = null; $toDelete = []; $ok = '';

    if ($action === 'upload_banner') {
        $r = media_store_upload($_FILES['banner'] ?? null, $id, 'banner');
        if (isset($r['error'])) return $r;
        if (!empty($event['banner_url'])) $toDelete[] = $event['banner_url'];
        $patch = ['banner_url' => $r['url']];
        $ok = 'Banner image saved.';
    } elseif ($action === 'remove_banner') {
        if (!empty($event['banner_url'])) $toDelete[] = $event['banner_url'];
        $patch = ['banner_url' => null];
        $ok = 'Banner removed.';
    } elseif ($action === 'add_posters') {
        $files = array_filter(media_files_list($_FILES['posters'] ?? null), fn($f) => ($f['error'] ?? 4) !== UPLOAD_ERR_NO_FILE);
        if (!$files) return ['error' => 'Choose one or more poster images.'];
        if (count($posters) + count($files) > MEDIA_MAX_POSTERS) return ['error' => 'You can have at most ' . MEDIA_MAX_POSTERS . ' posters per event.'];
        $errors = [];
        foreach ($files as $f) {
            $r = media_store_upload($f, $id, 'poster');
            if (isset($r['url'])) $posters[] = $r['url']; else $errors[] = ($f['name'] ?? 'file') . ': ' . $r['error'];
        }
        if (count($posters) === count(media_posters($event))) return ['error' => implode(' ', $errors)];
        $patch = ['poster_urls' => $posters];
        $ok = 'Poster(s) added.' . ($errors ? ' Skipped: ' . implode(' ', $errors) : '');
    } elseif ($action === 'remove_poster' || $action === 'move_poster') {
        $i = (int)($_POST['poster_index'] ?? -1);
        if (!isset($posters[$i])) return ['error' => 'Poster not found.'];
        if ($action === 'remove_poster') {
            $toDelete[] = $posters[$i];
            array_splice($posters, $i, 1);
            $ok = 'Poster removed.';
        } else {
            $j = $i + ((($_POST['dir'] ?? '') === 'up') ? -1 : 1);
            if (!isset($posters[$j])) return ['ok' => ''];
            [$posters[$i], $posters[$j]] = [$posters[$j], $posters[$i]];
            $ok = 'Poster order updated.';
        }
        $patch = ['poster_urls' => array_values($posters)];
    }

    $resp = supabase_table_update('events', 'id=eq.' . urlencode($id), $patch + $extraPatch, $token, $serviceRole);
    if ($resp['status'] !== 200 || empty($resp['data'][0])) {
        // Don't leave orphaned new files behind if the DB update failed.
        if (isset($patch['banner_url']) && $patch['banner_url']) media_delete_url($patch['banner_url'], $id);
        if ($action === 'add_posters') foreach (array_diff($patch['poster_urls'], media_posters($event)) as $u) media_delete_url($u, $id);
        return ['error' => 'Could not save the change.'];
    }
    foreach ($toDelete as $u) media_delete_url($u, $id);
    return ['ok' => $ok];
}

/** Renders the banner + posters management UI. $csrfField is the hidden CSRF <input>. */
function media_render_manager($event, $csrfField, $formAction = '') {
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $ready = media_columns_ready();
    $posters = media_posters($event);
    $id = $e($event['id']);
    ob_start(); ?>
    <div class="portal-card media-card" style="margin-bottom:1.25rem">
      <h2 style="margin-bottom:.25rem">Banner &amp; Posters</h2>
      <p class="portal-sub">JPG, PNG or WebP, up to 5 MB each. Banner: a wide image (about 1600×600) shown at the top of the event page. Posters: flyers shown in a gallery.</p>
      <?php if (!$ready): ?>
        <div class="portal-alert portal-alert-error">Uploads aren't enabled yet: the site owner needs to run <code>schema_04_event_media.sql</code> in Supabase.</div>
      <?php else: ?>
        <h3 style="font-size:1rem;margin:1rem 0 .5rem">Banner</h3>
        <?php if (!empty($event['banner_url'])): ?>
          <img src="<?= $e($event['banner_url']) ?>" alt="Current banner" style="width:100%;max-height:220px;object-fit:cover;border-radius:10px;margin-bottom:.6rem">
        <?php else: ?>
          <p style="color:var(--text-light)">No banner yet.</p>
        <?php endif; ?>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
          <form method="post" enctype="multipart/form-data" action="<?= $e($formAction) ?>" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
            <?= $csrfField ?>
            <input type="hidden" name="action" value="upload_banner">
            <input type="hidden" name="event_id" value="<?= $id ?>">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= MEDIA_MAX_BYTES ?>">
            <input type="file" name="banner" accept="image/jpeg,image/png,image/webp" required>
            <button class="btn btn-sm btn-primary" type="submit"><?= !empty($event['banner_url']) ? 'Replace Banner' : 'Upload Banner' ?></button>
          </form>
          <?php if (!empty($event['banner_url'])): ?>
          <form method="post" action="<?= $e($formAction) ?>" onsubmit="return confirm('Remove the banner?')">
            <?= $csrfField ?>
            <input type="hidden" name="action" value="remove_banner">
            <input type="hidden" name="event_id" value="<?= $id ?>">
            <button class="btn btn-sm btn-outline" type="submit">Remove</button>
          </form>
          <?php endif; ?>
        </div>

        <h3 style="font-size:1rem;margin:1.5rem 0 .5rem">Posters <small style="font-weight:400;color:var(--text-light)">(<?= count($posters) ?>/<?= MEDIA_MAX_POSTERS ?>)</small></h3>
        <?php if ($posters): ?>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:.75rem;margin-bottom:.75rem">
            <?php foreach ($posters as $i => $p): ?>
              <div style="border:1px solid var(--gray-300);border-radius:8px;padding:.4rem;text-align:center">
                <img src="<?= $e($p) ?>" alt="Poster <?= $i + 1 ?>" style="width:100%;height:150px;object-fit:cover;border-radius:6px">
                <div style="display:flex;gap:.25rem;justify-content:center;margin-top:.35rem">
                  <?php foreach ([['up', '←', $i > 0], ['down', '→', $i < count($posters) - 1]] as [$dir, $label, $show]): if (!$show) continue; ?>
                  <form method="post" action="<?= $e($formAction) ?>"><?= $csrfField ?>
                    <input type="hidden" name="action" value="move_poster"><input type="hidden" name="event_id" value="<?= $id ?>">
                    <input type="hidden" name="poster_index" value="<?= $i ?>"><input type="hidden" name="dir" value="<?= $dir ?>">
                    <button class="btn btn-sm btn-outline" type="submit" title="Move <?= $dir === 'up' ? 'earlier' : 'later' ?>"><?= $label ?></button>
                  </form>
                  <?php endforeach; ?>
                  <form method="post" action="<?= $e($formAction) ?>" onsubmit="return confirm('Remove this poster?')"><?= $csrfField ?>
                    <input type="hidden" name="action" value="remove_poster"><input type="hidden" name="event_id" value="<?= $id ?>">
                    <input type="hidden" name="poster_index" value="<?= $i ?>">
                    <button class="btn btn-sm btn-outline" type="submit">Remove</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p style="color:var(--text-light)">No posters yet.</p>
        <?php endif; ?>
        <?php if (count($posters) < MEDIA_MAX_POSTERS): ?>
        <form method="post" enctype="multipart/form-data" action="<?= $e($formAction) ?>" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
          <?= $csrfField ?>
          <input type="hidden" name="action" value="add_posters">
          <input type="hidden" name="event_id" value="<?= $id ?>">
          <input type="hidden" name="MAX_FILE_SIZE" value="<?= MEDIA_MAX_BYTES ?>">
          <input type="file" name="posters[]" accept="image/jpeg,image/png,image/webp" multiple required>
          <button class="btn btn-sm btn-primary" type="submit">Add Poster(s)</button>
        </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php return ob_get_clean();
}
