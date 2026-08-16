<?php
require_once 'auth.php';
require_auth();

$HEADS   = ['id','title','date','time','end_time','venue','description','category','website','free','recurring','recur_count','images'];
$CSV     = DATA_PATH . 'events.csv';
$JS_FILE = realpath(__DIR__ . '/../data') . DIRECTORY_SEPARATOR . 'events.js';
$SITE_ROOT = realpath(__DIR__ . '/..');

$event_id = trim($_GET['id'] ?? '');
if (!preg_match('/^\d+$/', $event_id)) { header('Location: events.php'); exit; }

$rows  = read_csv($CSV);
$event = null;
foreach ($rows as $r) { if ((string)$r['id'] === $event_id) { $event = $r; break; } }
if (!$event) { header('Location: events.php'); exit; }

$images = array_values(array_filter(explode('|', $event['images'] ?? ''), fn($s) => trim($s) !== ''));
$msg    = '';

/* ---- Helpers ---- */
function event_img_dir($site_root, $event_id) {
    return $site_root . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'events' . DIRECTORY_SEPARATOR . $event_id . DIRECTORY_SEPARATOR;
}

function update_event_images($CSV, $HEADS, $JS_FILE, $event_id, array $images) {
    global $rows;
    $img_str = implode('|', $images);
    foreach ($rows as &$r) {
        if ((string)$r['id'] === (string)$event_id) { $r['images'] = $img_str; break; }
    }
    unset($r);
    usort($rows, fn($a,$b) => strcmp($a['date'], $b['date']));
    write_csv($CSV, $rows, $HEADS);
    write_js_datafile($JS_FILE, 'EXIT473_EVENTS_CSV', $CSV);
}

/* ---- Handle POST actions ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $file = $_FILES['photo'] ?? null;
        $ok   = false;
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
            if (in_array($file['type'], $allowed) && $file['size'] <= 10 * 1024 * 1024) {
                $dir = event_img_dir($SITE_ROOT, $event_id);
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $name = 'ev_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $dir . $name)) {
                    $images[] = 'images/events/' . $event_id . '/' . $name;
                    update_event_images($CSV, $HEADS, $JS_FILE, $event_id, $images);
                    $msg = 'ok:Photo uploaded.';
                    $ok  = true;
                }
            }
        }
        if (!$ok && $msg === '') $msg = 'error:Upload failed. Use JPEG, PNG, GIF, or WEBP under 10 MB.';
    }

    if ($action === 'delete') {
        $del = trim($_POST['img'] ?? '');
        if ($del && in_array($del, $images)) {
            $abs = $SITE_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $del);
            if (file_exists($abs)) @unlink($abs);
            $images = array_values(array_filter($images, fn($p) => $p !== $del));
            update_event_images($CSV, $HEADS, $JS_FILE, $event_id, $images);
            $msg = 'ok:Photo deleted.';
        }
    }

    if ($action === 'reorder') {
        $new_order = array_filter(explode('|', $_POST['order'] ?? ''), fn($s) => trim($s) !== '');
        $valid = array_filter($new_order, fn($p) => in_array($p, $images));
        if (count($valid) === count($images)) {
            $images = array_values($valid);
            update_event_images($CSV, $HEADS, $JS_FILE, $event_id, $images);
            $msg = 'ok:Order saved.';
        }
    }

    // Reload
    $rows  = read_csv($CSV);
    foreach ($rows as $r) { if ((string)$r['id'] === $event_id) { $event = $r; break; } }
    $images = array_values(array_filter(explode('|', $event['images'] ?? ''), fn($s) => trim($s) !== ''));
}

[$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['',''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Event Photos — <?= h($event['title']) ?></title>
<?php include 'head_styles.php'; ?>
<style>
.event-info-bar {
  background: var(--dark, #1A0505);
  color: #fff;
  border-radius: 10px;
  padding: 1rem 1.25rem;
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: .75rem;
}
.event-info-bar h2 { font-size: 1.05rem; margin: 0; color: #F5C840; }
.event-info-bar p  { font-size: .84rem; color: rgba(255,255,255,.7); margin: .2rem 0 0; }

.photo-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1rem;
}
.photo-item {
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 1px 8px rgba(26,5,5,.07);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  cursor: grab;
}
.photo-item.dragging { opacity: .4; }
.photo-item a img { width: 100%; height: 150px; object-fit: cover; display: block; }
.photo-item-body { padding: .65rem; display: flex; gap: .4rem; flex-direction: column; }
.drag-hint { font-size: .72rem; color: #999; text-align: center; padding: .2rem 0; cursor: grab; user-select: none; }

.upload-zone {
  border: 2px dashed #D0C4C4;
  border-radius: 10px;
  padding: 2rem;
  text-align: center;
  background: #FFF9F7;
  transition: border-color .2s, background .2s;
}
.upload-zone:hover { border-color: #CC0000; background: #FFF0F0; }
.upload-zone input[type=file] { display: block; margin: .85rem auto 0; }
.upload-zone .fname { margin-top: .5rem; font-size: .82rem; color: #CC0000; font-weight: 600; }
.empty-msg { color: #7A6565; text-align: center; padding: 2rem 0; font-size: .9rem; }
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<main class="wrap">

  <div style="margin-bottom:1rem">
    <a href="events.php" class="btn btn-sm btn-outline">← Back to Events</a>
  </div>

  <div class="event-info-bar">
    <div>
      <h2><?= h($event['title']) ?></h2>
      <p><?= h(date('D, d M Y', strtotime($event['date']))) ?> · <?= h($event['time']) ?> · <?= h($event['venue']) ?></p>
    </div>
    <span style="font-size:.82rem;color:rgba(255,255,255,.5)"><?= count($images) ?> photo<?= count($images) !== 1 ? 's' : '' ?></span>
  </div>

  <?php if ($msg_text): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= h($msg_text) ?></div>
  <?php endif; ?>

  <!-- Upload -->
  <div class="panel">
    <h2 class="panel-title">Upload Photo / Poster</h2>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="upload">
      <div class="upload-zone" id="upload-zone">
        <div style="font-size:2.2rem;margin-bottom:.4rem">🖼️</div>
        <div style="font-size:.88rem;color:#7A6565">Event posters, photos — JPEG, PNG, GIF, WEBP — max 10 MB</div>
        <input type="file" name="photo" id="photo-input" accept="image/jpeg,image/png,image/gif,image/webp" required>
        <div class="fname" id="fname-display"></div>
      </div>
      <button class="btn btn-primary" type="submit" style="margin-top:1rem">Upload</button>
    </form>
  </div>

  <!-- Photo grid -->
  <div class="panel">
    <h2 class="panel-title">
      Photos
      <span style="font-weight:400;color:#7A6565;font-size:.85rem">(<?= count($images) ?>)</span>
      <?php if (count($images) > 1): ?>
        <span style="font-weight:400;color:#B0A0A0;font-size:.78rem;margin-left:.75rem">Drag to reorder — first image is the thumbnail</span>
      <?php endif; ?>
    </h2>

    <?php if (empty($images)): ?>
      <div class="empty-msg">No photos yet. Upload event posters or photos above.</div>
    <?php else: ?>
      <div class="photo-grid" id="photo-grid">
        <?php foreach ($images as $img): ?>
        <div class="photo-item" data-img="<?= h($img) ?>" draggable="true">
          <div class="drag-hint">⠿ drag to reorder</div>
          <a href="../<?= h($img) ?>" target="_blank">
            <img src="../<?= h($img) ?>" alt="Event photo" loading="lazy">
          </a>
          <div class="photo-item-body">
            <form method="post" onsubmit="return confirm('Delete this photo?')">
              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="img" value="<?= h($img) ?>">
              <button class="btn btn-danger btn-sm" type="submit" style="width:100%">Delete</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <form method="post" id="reorder-form" style="display:none">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="reorder">
        <input type="hidden" name="order" id="reorder-input">
      </form>
    <?php endif; ?>
  </div>

</main>
<script>
document.getElementById('photo-input').addEventListener('change', function() {
  const d = document.getElementById('fname-display');
  d.textContent = this.files[0] ? '✓ ' + this.files[0].name : '';
});

(function() {
  const grid = document.getElementById('photo-grid');
  if (!grid) return;
  let dragged = null;
  grid.addEventListener('dragstart', e => {
    dragged = e.target.closest('.photo-item');
    if (dragged) { dragged.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; }
  });
  grid.addEventListener('dragend', e => {
    const el = e.target.closest('.photo-item');
    if (el) el.classList.remove('dragging');
    dragged = null;
    saveOrder();
  });
  grid.addEventListener('dragover', e => {
    e.preventDefault();
    const target = e.target.closest('.photo-item');
    if (target && target !== dragged) {
      const rect = target.getBoundingClientRect();
      grid.insertBefore(dragged, e.clientX > rect.left + rect.width / 2 ? target.nextSibling : target);
    }
  });
  function saveOrder() {
    const paths = Array.from(grid.querySelectorAll('.photo-item')).map(el => el.dataset.img);
    document.getElementById('reorder-input').value = paths.join('|');
    document.getElementById('reorder-form').submit();
  }
})();
</script>
</body>
</html>
