<?php
require_once 'auth.php';
require_auth();

$upload_dir = realpath(__DIR__ . '/../uploads/artist-media');
$files      = [];

if ($upload_dir && is_dir($upload_dir)) {
    foreach (new DirectoryIterator($upload_dir) as $f) {
        if ($f->isDot() || $f->isDir() || $f->getFilename() === '.htaccess') continue;
        $files[] = [
            'name'     => $f->getFilename(),
            'size'     => $f->getSize(),
            'modified' => $f->getMTime(),
            'path'     => $upload_dir . DIRECTORY_SEPARATOR . $f->getFilename(),
        ];
    }
    usort($files, fn($a, $b) => $b['modified'] - $a['modified']);
}

/* ---- Handle delete ---- */
$del_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    check_csrf();
    $target = basename($_POST['delete_file']);
    $full   = $upload_dir . DIRECTORY_SEPARATOR . $target;
    if ($upload_dir && file_exists($full) && strpos(realpath($full), $upload_dir) === 0) {
        unlink($full)
            ? ($del_msg = 'ok:' . htmlspecialchars($target, ENT_QUOTES) . ' deleted.')
            : ($del_msg = 'error:Could not delete ' . htmlspecialchars($target, ENT_QUOTES) . '.');
        // Reload list
        $files = [];
        foreach (new DirectoryIterator($upload_dir) as $f) {
            if ($f->isDot() || $f->isDir() || $f->getFilename() === '.htaccess') continue;
            $files[] = ['name' => $f->getFilename(), 'size' => $f->getSize(), 'modified' => $f->getMTime(), 'path' => ''];
        }
        usort($files, fn($a, $b) => $b['modified'] - $a['modified']);
    } else {
        $del_msg = 'error:File not found.';
    }
}
[$del_type, $del_text] = $del_msg ? explode(':', $del_msg, 2) : ['', ''];

function fmt_size(int $b): string {
    if ($b >= 1073741824) return round($b / 1073741824, 2) . ' GB';
    if ($b >= 1048576)    return round($b / 1048576, 1) . ' MB';
    if ($b >= 1024)       return round($b / 1024) . ' KB';
    return $b . ' B';
}

function media_icon(string $name): string {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (in_array($ext, ['mp4','mov','avi','mkv','webm','3gp','m4v']))          return '🎬';
    if (in_array($ext, ['jpg','jpeg','png','gif','webp','heic','heif','tiff'])) return '📷';
    return '🎵';
}

// Group by artist name (first segment before first underscore-date pattern)
function artist_from_filename(string $name): string {
    // Files are named: {artist}_{YYYYMMDD_HHmmss}_{idx}_{original}
    if (preg_match('/^(.+?)_\d{8}_\d{6}_/', $name, $m)) {
        return str_replace('_', ' ', $m[1]);
    }
    return 'Unknown';
}

$grouped = [];
foreach ($files as $f) {
    $a = artist_from_filename($f['name']);
    $grouped[$a][] = $f;
}
ksort($grouped);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Artist Uploads — Exit 473 Admin</title>
<?php include 'head_styles.php'; ?>
<style>
  .uploads-table { width: 100%; border-collapse: collapse; font-size: .88rem; }
  .uploads-table th {
    text-align: left; padding: .65rem 1rem;
    background: var(--sand); border-bottom: 2px solid var(--gray-200);
    font-size: .75rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase;
    color: var(--text-light);
  }
  .uploads-table td { padding: .7rem 1rem; border-bottom: 1px solid var(--gray-200); vertical-align: middle; }
  .uploads-table tr:last-child td { border-bottom: none; }
  .uploads-table tr:hover td { background: var(--sand); }
  .file-icon { font-size: 1.2rem; }
  .file-name { font-family: 'Courier New', monospace; font-size: .78rem; color: var(--text); word-break: break-all; }
  .artist-group-header {
    background: var(--dark);
    color: var(--white);
    padding: .6rem 1rem;
    font-size: .78rem;
    font-weight: 800;
    letter-spacing: .1em;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: .6rem;
  }
  .artist-group-header .badge {
    background: var(--gold);
    color: var(--dark);
    border-radius: 999px;
    padding: .1rem .55rem;
    font-size: .7rem;
  }
  .upload-table-wrap {
    border: 1px solid var(--gray-200);
    border-radius: var(--r-lg);
    overflow: hidden;
    margin-bottom: 2rem;
    box-shadow: var(--sh-sm);
  }
  .no-uploads {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-light);
  }
  .no-uploads .icon { font-size: 3rem; display: block; margin-bottom: 1rem; }
  .del-btn {
    background: transparent;
    border: 1px solid var(--red);
    color: var(--red);
    border-radius: var(--r-sm);
    padding: .25rem .65rem;
    font-size: .75rem;
    font-weight: 700;
    cursor: pointer;
    transition: all .2s;
  }
  .del-btn:hover { background: var(--red); color: var(--white); }
  .dl-link { color: var(--red); font-weight: 700; font-size: .8rem; text-decoration: underline; }
  .flash { padding: .75rem 1.1rem; border-radius: var(--r-md); margin-bottom: 1.5rem; font-size: .9rem; }
  .flash-ok    { background: #efffef; border-left: 4px solid #2D9A27; color: #1a5c17; }
  .flash-error { background: #fff2f2; border-left: 4px solid #CC0000; color: #7a0000; }
  .summary-bar {
    display: flex; gap: 2rem; align-items: center; flex-wrap: wrap;
    margin-bottom: 1.5rem; padding: 1rem 1.25rem;
    background: var(--sand); border-radius: var(--r-md);
    border: 1px solid var(--gray-200); font-size: .88rem;
  }
  .summary-bar strong { color: var(--text); }
  .summary-bar span   { color: var(--text-light); }
</style>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>
<main class="wrap">
  <h1 class="page-title">Artist Media Uploads</h1>
  <p style="color:var(--text-light);margin-bottom:1.5rem">Files submitted via the public <a href="../artist-upload.php" target="_blank" style="color:var(--red)">artist upload page</a>. Stored in <code>uploads/artist-media/</code>.</p>

  <?php if ($del_type): ?>
    <div class="flash flash-<?= $del_type === 'ok' ? 'ok' : 'error' ?>"><?= $del_text ?></div>
  <?php endif; ?>

  <?php if (empty($files)): ?>
    <div class="upload-table-wrap">
      <div class="no-uploads">
        <span class="icon">📭</span>
        <p>No files have been uploaded yet.</p>
        <p><a href="../artist-upload.php" target="_blank" style="color:var(--red)">View the upload page →</a></p>
      </div>
    </div>

  <?php else: ?>
    <!-- Summary -->
    <?php
      $total_size = array_sum(array_column($files, 'size'));
    ?>
    <div class="summary-bar">
      <span><strong><?= count($files) ?></strong> file<?= count($files) !== 1 ? 's' : '' ?></span>
      <span><strong><?= count($grouped) ?></strong> artist<?= count($grouped) !== 1 ? 's' : '' ?></span>
      <span>Total size: <strong><?= fmt_size($total_size) ?></strong></span>
      <span style="margin-left:auto">
        <a href="../artist-upload.php" target="_blank" class="btn" style="font-size:.8rem;padding:.35rem .9rem;border:1px solid var(--gray-300);border-radius:var(--r-full);color:var(--text-light)">
          View Upload Page ↗
        </a>
      </span>
    </div>

    <!-- Grouped by artist -->
    <?php foreach ($grouped as $artist => $artist_files): ?>
      <div class="upload-table-wrap">
        <div class="artist-group-header">
          🎤 <?= h($artist) ?>
          <span class="badge"><?= count($artist_files) ?> file<?= count($artist_files) !== 1 ? 's' : '' ?></span>
        </div>
        <table class="uploads-table">
          <thead>
            <tr>
              <th style="width:2.5rem"></th>
              <th>Filename</th>
              <th>Size</th>
              <th>Uploaded</th>
              <th style="width:130px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($artist_files as $file): ?>
              <tr>
                <td class="file-icon"><?= media_icon($file['name']) ?></td>
                <td class="file-name"><?= h($file['name']) ?></td>
                <td style="white-space:nowrap;color:var(--text-light)"><?= fmt_size($file['size']) ?></td>
                <td style="white-space:nowrap;color:var(--text-light)"><?= date('M j, Y g:i A', $file['modified']) ?></td>
                <td>
                  <div style="display:flex;gap:.5rem;align-items:center">
                    <a
                      href="../uploads/artist-media/<?= rawurlencode($file['name']) ?>"
                      download="<?= h($file['name']) ?>"
                      class="dl-link"
                    >Download</a>
                    <form method="post" style="margin:0" onsubmit="return confirm('Delete <?= h(addslashes($file['name'])) ?>?')">
                      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="delete_file" value="<?= h($file['name']) ?>">
                      <button type="submit" class="del-btn">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endforeach; ?>

  <?php endif; ?>

</main>
</body>
</html>
