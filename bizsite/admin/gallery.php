<?php
require_once 'auth.php';
require_auth();
$slug   = current_slug();
$info   = read_info($slug);
$photos = read_gallery($slug);
usort($photos, fn($a,$b) => (int)$a['sort_order'] - (int)$b['sort_order']);

$msg = '';

/* ---- Handle POST actions ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $path = handle_gallery_upload($slug);
        if ($path) {
            $photos[] = [
                'id'         => next_id($photos),
                'filename'   => $path,
                'caption'    => trim($_POST['caption'] ?? ''),
                'sort_order' => count($photos) + 1,
            ];
            write_gallery($slug, $photos);
            $msg = 'ok:Photo uploaded successfully.';
        } else {
            $msg = 'error:Upload failed. Use JPEG, PNG, GIF, or WEBP under 8 MB.';
        }
    }

    if ($action === 'save_caption') {
        $edit_id = (int)($_POST['photo_id'] ?? 0);
        foreach ($photos as &$p) {
            if ((int)$p['id'] === $edit_id) {
                $p['caption'] = trim($_POST['caption'] ?? '');
                break;
            }
        }
        unset($p);
        write_gallery($slug, $photos);
        $msg = 'ok:Caption saved.';
    }

    if ($action === 'delete') {
        $del_id = (int)($_POST['photo_id'] ?? 0);
        foreach ($photos as $p) {
            if ((int)$p['id'] === $del_id) {
                $abs = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $p['filename']);
                if (file_exists($abs)) @unlink($abs);
                break;
            }
        }
        $photos = array_values(array_filter($photos, fn($p) => (int)$p['id'] !== $del_id));
        write_gallery($slug, $photos);
        $msg = 'ok:Photo deleted.';
    }

    if ($action === 'reorder') {
        $order = array_map('intval', array_filter(explode(',', $_POST['order'] ?? ''), 'is_numeric'));
        $indexed = [];
        foreach ($photos as $p) $indexed[(int)$p['id']] = $p;
        $new = [];
        foreach ($order as $i => $id) {
            if (isset($indexed[$id])) {
                $indexed[$id]['sort_order'] = $i + 1;
                $new[] = $indexed[$id];
            }
        }
        $photos = $new;
        write_gallery($slug, $photos);
        $msg = 'ok:Order saved.';
    }

    $photos = read_gallery($slug);
    usort($photos, fn($a,$b) => (int)$a['sort_order'] - (int)$b['sort_order']);
}

[$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['',''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gallery — <?= h($info['name'] ?? 'Admin') ?></title>
<?php include 'head_styles.php'; ?>
<style>
.gallery-admin-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1rem;
  margin-top: 1rem;
}
.ga-item {
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 1px 8px rgba(0,0,0,.06);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  cursor: grab;
}
.ga-item.dragging { opacity: .4; }
.ga-item a img { width: 100%; height: 150px; object-fit: cover; display: block; transition: opacity .15s; }
.ga-item a:hover img { opacity: .85; }
.ga-item-body { padding: .75rem; display: flex; flex-direction: column; gap: .5rem; flex: 1; }
.ga-sort-handle {
  font-size: .72rem;
  color: #999;
  text-align: center;
  padding-top: .25rem;
  cursor: grab;
  user-select: none;
}

.upload-zone {
  border: 2px dashed #DDE3EA;
  border-radius: 10px;
  padding: 2rem 1.5rem;
  text-align: center;
  background: #F9FBFC;
  transition: border-color .2s, background .2s;
}
.upload-zone:hover, .upload-zone.drag-over { border-color: #2D6A4F; background: #EDF7F2; }
.upload-zone input[type=file] { display: block; margin: .85rem auto 0; }

.empty-state { color: #718096; font-size: .92rem; padding: 2rem 0; text-align: center; }
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<main class="wrap">
  <h1 class="page-title">Photo Gallery</h1>

  <?php if ($msg_text): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= h($msg_text) ?></div>
  <?php endif; ?>

  <!-- Upload panel -->
  <div class="panel">
    <h2 class="panel-title">Add Photo</h2>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="upload">
      <div class="upload-zone" id="upload-zone">
        <div style="font-size:2.2rem;margin-bottom:.4rem">📷</div>
        <div style="font-size:.88rem;color:#718096">JPEG, PNG, GIF, or WEBP — max 8 MB</div>
        <input type="file" name="photo" id="photo-input" accept="image/jpeg,image/png,image/gif,image/webp" required>
        <div id="file-name-display" style="margin-top:.5rem;font-size:.82rem;color:#2D6A4F;font-weight:600"></div>
      </div>
      <div class="form-grid" style="margin-top:1rem;grid-template-columns:1fr auto">
        <div class="form-row">
          <label>Caption <span style="font-weight:400;color:#9AA5B1">(optional)</span></label>
          <input type="text" name="caption" placeholder="e.g. Our rooftop dining area">
        </div>
        <div style="display:flex;align-items:flex-end">
          <button class="btn btn-primary" type="submit">Upload</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Gallery grid -->
  <div class="panel">
    <h2 class="panel-title">
      Gallery Photos
      <span style="font-weight:400;color:#718096;font-size:.85rem">(<?= count($photos) ?>)</span>
      <?php if (count($photos) > 1): ?>
        <span style="font-weight:400;color:#9AA5B1;font-size:.78rem;margin-left:.75rem">Drag to reorder</span>
      <?php endif; ?>
    </h2>

    <?php if (empty($photos)): ?>
      <div class="empty-state">No photos yet — upload one above to get started.</div>
    <?php else: ?>
      <div class="gallery-admin-grid" id="gallery-grid">
        <?php foreach ($photos as $p): ?>
        <div class="ga-item" data-id="<?= (int)$p['id'] ?>">
          <div class="ga-sort-handle">⠿ drag to reorder</div>
          <a href="../<?= h($p['filename']) ?>" target="_blank" title="View full size">
            <img src="../<?= h($p['filename']) ?>" alt="<?= h($p['caption'] ?: 'Gallery photo') ?>" loading="lazy">
          </a>
          <div class="ga-item-body">
            <!-- Inline caption edit -->
            <form method="post">
              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="save_caption">
              <input type="hidden" name="photo_id" value="<?= (int)$p['id'] ?>">
              <div class="form-row" style="margin-bottom:.4rem">
                <label>Caption</label>
                <input type="text" name="caption" value="<?= h($p['caption']) ?>" placeholder="Add caption…" style="font-size:.82rem;padding:.3rem .55rem">
              </div>
              <button class="btn btn-outline btn-sm" type="submit">Save</button>
            </form>
            <!-- Delete -->
            <form method="post" onsubmit="return confirm('Delete this photo permanently?')">
              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="photo_id" value="<?= (int)$p['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit" style="margin-top:.25rem">Delete</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Hidden reorder form -->
      <form method="post" id="reorder-form" style="display:none">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="reorder">
        <input type="hidden" name="order" id="reorder-input">
      </form>
    <?php endif; ?>
  </div>
</main>

<script>
/* File name preview */
document.getElementById('photo-input').addEventListener('change', function() {
  const d = document.getElementById('file-name-display');
  d.textContent = this.files[0] ? '✓ ' + this.files[0].name : '';
});

/* Drag-and-drop reorder */
(function() {
  const grid = document.getElementById('gallery-grid');
  if (!grid) return;
  let dragged = null;

  grid.addEventListener('dragstart', e => {
    dragged = e.target.closest('.ga-item');
    if (dragged) { dragged.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; }
  });
  grid.addEventListener('dragend', e => {
    const el = e.target.closest('.ga-item');
    if (el) el.classList.remove('dragging');
    dragged = null;
    saveOrder();
  });
  grid.addEventListener('dragover', e => {
    e.preventDefault();
    const target = e.target.closest('.ga-item');
    if (target && target !== dragged) {
      const rect = target.getBoundingClientRect();
      const after = e.clientX > rect.left + rect.width / 2;
      grid.insertBefore(dragged, after ? target.nextSibling : target);
    }
  });

  /* Make items draggable */
  Array.from(grid.querySelectorAll('.ga-item')).forEach(el => el.setAttribute('draggable', 'true'));

  function saveOrder() {
    const ids = Array.from(grid.querySelectorAll('.ga-item')).map(el => el.dataset.id);
    document.getElementById('reorder-input').value = ids.join(',');
    document.getElementById('reorder-form').submit();
  }
})();
</script>
</body>
</html>
