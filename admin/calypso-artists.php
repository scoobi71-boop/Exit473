<?php
require_once 'auth.php';
require_auth();

$JSON_FILE = DATA_PATH . 'calypso-artists.json';
$JS_FILE   = realpath(__DIR__ . '/../data') . DIRECTORY_SEPARATOR . 'calypso-artists.js';
$PHOTO_DIR = __DIR__ . '/../images/carnival/calypso-artists/';
$PHOTO_URL_BASE = '/images/carnival/calypso-artists/';
$MAX_PHOTO_SIZE = 10 * 1024 * 1024; // 10 MB
$PHOTO_TYPES = ['image/jpeg','image/png','image/gif','image/webp'];

function save_artist_upload(array $file, string $upload_dir, string $url_base, int $max_size, array $allowed_types, string &$error = null): ?string {
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => 'The uploaded image exceeds the server limit.',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded image exceeds the form limit.',
            UPLOAD_ERR_PARTIAL    => 'The uploaded image was only partially uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server upload directory missing.',
            UPLOAD_ERR_CANT_WRITE => 'Server failed to save the uploaded image.',
        ];
        $error = $errors[$file['error']] ?? 'Unknown upload error.';
        return null;
    }

    $mime = mime_content_type($file['tmp_name']) ?: '';
    if (!in_array($mime, $allowed_types, true)) {
        $error = 'Only JPEG, PNG, GIF, and WEBP images are allowed.';
        return null;
    }

    if ($file['size'] > $max_size) {
        $error = 'Image must be 10 MB or smaller.';
        return null;
    }

    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
        $error = 'Could not create image upload directory.';
        return null;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext === '') {
        $error = 'File name must include an extension.';
        return null;
    }

    $dest_name = 'artist_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $dest_path = $upload_dir . DIRECTORY_SEPARATOR . $dest_name;

    if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
        $error = 'Could not save uploaded image.';
        return null;
    }

    return $url_base . $dest_name;
}

$PARISHES = ["St. George's", "St. David's", "St. Andrew's", "St. Patrick's", "St. Mark's", "St. John's", "Carriacou", "Petite Martinique"];

function extract_youtube_id(string $url): ?string {
    $url = trim($url);
    if ($url === '') return null;
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url)) return $url;
    if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})#i', $url, $m)) {
        return $m[1];
    }
    return null;
}

$TENTS_JSON = DATA_PATH . 'calypso-tents.json';
$tents_list = file_exists($TENTS_JSON) ? (json_decode(file_get_contents($TENTS_JSON), true) ?: []) : [];

$QUESTIONS = [
    1 => 'Tell us who you are and where you\'re from. How did growing up in Grenada shape the artist you\'re becoming?',
    2 => 'When did you first fall in love with calypso, and who were the artists or sounds that made you say, "That\'s what I want to do"?',
    3 => 'Calypso has always been the voice of the people. What topics, stories, or social issues do you feel called to sing about?',
    4 => 'Describe your sound. How would you introduce your music to someone who has never heard Grenadian calypso before?',
    5 => 'What has been your most memorable performance or career moment so far, and what did it teach you about yourself as an artist?',
    6 => 'How do you see Grenadian calypso evolving, and what role do you want to play in keeping the tradition alive for the next generation?',
    7 => 'What\'s next for you? Tell us about what you\'re working on and what fans can look forward to.',
];

function read_artists(string $file): array {
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function write_artists(string $json_file, string $js_file, array $artists): void {
    $artists = array_values($artists);
    $json    = json_encode($artists, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $tmp     = $json_file . '.tmp';
    file_put_contents($tmp, $json);
    rename($tmp, $json_file);
    $js  = "window.CALYPSO_ARTISTS_DATA = " . json_encode($artists, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";\n";
    $tmp = $js_file . '.tmp';
    file_put_contents($tmp, $js);
    rename($tmp, $js_file);
}

function next_artist_id(array $artists): string {
    $ids = array_filter(array_column($artists, 'id'), 'is_numeric');
    return $ids ? (string)((int)max($ids) + 1) : '1';
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action  = $_POST['action'] ?? '';
    $artists = read_artists($JSON_FILE);

    if ($action === 'delete') {
        $del_id  = (string)($_POST['del_id'] ?? '');
        $artists = array_values(array_filter($artists, fn($a) => (string)$a['id'] !== $del_id));
        write_artists($JSON_FILE, $JS_FILE, $artists);
        $msg = 'ok:Artist deleted.';

    } elseif ($action === 'save') {
        $id     = trim($_POST['id'] ?? '');
        $is_new = ($id === '');

        $existing_artist = null;
        if (!$is_new) {
            foreach ($artists as $a) {
                if ((string)$a['id'] === $id) {
                    $existing_artist = $a;
                    break;
                }
            }
        }

        $upload_error = '';
        $uploaded_photo = null;
        if (!empty($_FILES['photo_upload']['name']) && ($_FILES['photo_upload']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $uploaded_photo = save_artist_upload($_FILES['photo_upload'], $PHOTO_DIR, $PHOTO_URL_BASE, $MAX_PHOTO_SIZE, $PHOTO_TYPES, $upload_error);
            if ($uploaded_photo === null) {
                $msg = 'error:' . $upload_error;
            }
        }

        if ($msg === '') {
            $photo_path = trim($_POST['photo'] ?? '');
            if ($photo_path !== '' && !str_starts_with($photo_path, '/') && str_starts_with($photo_path, 'images/')) {
                $photo_path = '/' . $photo_path;
            }
            if ($uploaded_photo !== null) {
                $photo_path = $uploaded_photo;
                $old_photo = $existing_artist['photo'] ?? '';
                if ($old_photo && str_starts_with($old_photo, $PHOTO_URL_BASE)) {
                    $old_path = __DIR__ . '/../' . str_replace('/', DIRECTORY_SEPARATOR, $old_photo);
                    if (file_exists($old_path)) {
                        @unlink($old_path);
                    }
                }
            }

            $videos = [];
            foreach (preg_split('/\r\n|\r|\n/', $_POST['videos'] ?? '') as $video_line) {
                $vid = extract_youtube_id($video_line);
                if ($vid && !in_array($vid, $videos, true)) {
                    $videos[] = $vid;
                }
            }

            $entry = [
                'id'              => $is_new ? next_artist_id($artists) : $id,
                'stageName'       => trim($_POST['stageName']       ?? ''),
                'realName'        => trim($_POST['realName']        ?? ''),
                'parish'          => trim($_POST['parish']          ?? ''),
                'dateOfInterview' => trim($_POST['dateOfInterview'] ?? ''),
                'tentId'          => trim($_POST['tentId']          ?? '') ?: null,
                'photo'           => $photo_path,
                'videos'          => $videos,
                'shortBio'        => trim($_POST['shortBio']        ?? ''),
                'q1'              => trim($_POST['q1']              ?? ''),
                'q2'              => trim($_POST['q2']              ?? ''),
                'q3'              => trim($_POST['q3']              ?? ''),
                'q4'              => trim($_POST['q4']              ?? ''),
                'q5'              => trim($_POST['q5']              ?? ''),
                'q6'              => trim($_POST['q6']              ?? ''),
                'q7'              => trim($_POST['q7']              ?? ''),
            ];

            if ($is_new) {
                $artists[] = $entry;
            } else {
                foreach ($artists as &$a) {
                    if ((string)$a['id'] === $id) { $a = $entry; break; }
                }
                unset($a);
            }
            write_artists($JSON_FILE, $JS_FILE, $artists);
            $msg = 'ok:Artist ' . ($is_new ? 'added' : 'updated') . ' successfully.';
        }
    }
}

$artists   = read_artists($JSON_FILE);
$tents_map = array_column($tents_list, null, 'id');
$edit_id   = $_GET['edit'] ?? '';
$editing = null;
foreach ($artists as $a) { if ((string)$a['id'] === $edit_id) { $editing = $a; break; } }

[$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['', ''];

function qval(array $a, int $n): string {
    return $a['q' . $n] ?? '';
}

function videos_textarea_value(array $a): string {
    $ids = $a['videos'] ?? [];
    if (!$ids) return '';
    return implode("\n", array_map(fn($id) => "https://www.youtube.com/watch?v={$id}", $ids));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Calypso Artists — Exit 473 Admin</title>
<?php include 'head_styles.php'; ?>
<style>
.artist-table{width:100%;border-collapse:collapse;font-size:.88rem}
.artist-table th{background:#1A0505;color:#F5C840;text-align:left;padding:.6rem .75rem;font-weight:600;font-size:.78rem;text-transform:uppercase;letter-spacing:.06em}
.artist-table td{padding:.6rem .75rem;border-bottom:1px solid #EAE2E2;vertical-align:top}
.artist-table tr:hover td{background:#FFF9F7}
.table-wrap{overflow-x:auto}
.parish-badge{display:inline-block;padding:.2rem .55rem;border-radius:99px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;background:#FFF0DC;color:#7A4A00}
.q-hint{font-size:.78rem;color:#7A6565;font-style:italic;margin-bottom:.4rem;line-height:1.4}
.section-divider{font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#CC0000;padding:.6rem 0 .4rem;border-bottom:2px solid #F0E8E8;margin:1.5rem 0 1rem}
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<main class="wrap">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
    <h1 class="page-title" style="margin:0">Calypso Artists</h1>
    <a href="?new=1" class="btn btn-primary">+ Add New Artist</a>
  </div>

  <?php if ($msg_text): ?>
    <div class="alert alert-<?= $msg_type ?>" style="margin-bottom:1.5rem"><?= h($msg_text) ?></div>
  <?php endif; ?>

  <?php if ($editing || isset($_GET['new'])): ?>
  <!-- EDIT / ADD FORM -->
  <div class="panel" id="artist-form">
    <h2 class="panel-title"><?= $editing ? 'Edit Artist — ' . h($editing['stageName']) : 'Add New Artist' ?></h2>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="_csrf"   value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action"  value="save">
      <input type="hidden" name="id"      value="<?= h($editing['id'] ?? '') ?>">

      <div class="section-divider">🎤 Artist Information</div>
      <div class="form-grid">
        <div class="form-row">
          <label>Stage Name *</label>
          <input type="text" name="stageName" required value="<?= h($editing['stageName'] ?? '') ?>" placeholder="e.g. Lady Spice">
        </div>
        <div class="form-row">
          <label>Real Name *</label>
          <input type="text" name="realName" required value="<?= h($editing['realName'] ?? '') ?>" placeholder="e.g. Sandra Joseph">
        </div>
        <div class="form-row">
          <label>Parish / Hometown *</label>
          <select name="parish" required>
            <option value="">Select parish…</option>
            <?php foreach ($PARISHES as $p): ?>
              <option value="<?= h($p) ?>" <?= ($editing['parish'] ?? '') === $p ? 'selected' : '' ?>><?= h($p) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <label>Date of Interview</label>
          <input type="date" name="dateOfInterview" value="<?= h($editing['dateOfInterview'] ?? '') ?>">
        </div>
        <div class="form-row">
          <label>Calypso Tent</label>
          <select name="tentId">
            <option value="">— Independent / No tent —</option>
            <?php foreach ($tents_list as $t): ?>
              <option value="<?= h($t['id']) ?>"
                <?= ($editing['tentId'] ?? '') === $t['id'] ? 'selected' : '' ?>>
                <?= $t['shortName'] ? h($t['shortName']) . ' — ' : '' ?><?= h($t['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php if (!empty($editing['photo'])): ?>
        <div class="form-row" style="grid-column:1/-1">
          <label>Current Photo</label>
          <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
            <img src="../<?= h($editing['photo']) ?>" alt="<?= h($editing['stageName']) ?>" style="max-width:140px;border-radius:12px;border:1px solid #E8D7C0">
            <span style="color:#7A6565;max-width:calc(100% - 160px)">Existing profile image. Uploading a new file below will replace this path automatically.</span>
          </div>
        </div>
        <?php endif; ?>
        <div class="form-row" style="grid-column:1/-1">
          <label>Upload Profile Image <small style="font-weight:400;text-transform:none;color:#7A6565">(optional — JPEG, PNG, GIF, WEBP; max 10 MB)</small></label>
          <input type="file" name="photo_upload" accept="image/jpeg,image/png,image/gif,image/webp">
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label>Photo Path <small style="font-weight:400;text-transform:none;color:#7A6565">(optional — root-relative path begins with <code>/</code>; used when no image is uploaded)</small></label>
          <input type="text" name="photo" value="<?= h($editing['photo'] ?? '') ?>" placeholder="e.g. /images/carnival/artist-name.png">
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label>Short Bio <small style="font-weight:400;text-transform:none;color:#7A6565">(1–2 sentences shown on the artist listing card)</small></label>
          <textarea name="shortBio" rows="3" placeholder="A brief introduction to the artist…"><?= h($editing['shortBio'] ?? '') ?></textarea>
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label>YouTube Videos <small style="font-weight:400;text-transform:none;color:#7A6565">(one URL per line — each plays embedded on the artist's profile page)</small></label>
          <textarea name="videos" rows="4" placeholder="https://www.youtube.com/watch?v=…&#10;https://youtu.be/…"><?= h(videos_textarea_value($editing ?? [])) ?></textarea>
        </div>
      </div>

      <div class="section-divider">🗣 Interview Answers</div>

      <?php foreach ($QUESTIONS as $n => $q): ?>
      <div class="form-row" style="margin-bottom:1rem">
        <label>Q<?= $n ?> — <?= h(explode('.', $q)[0]) ?></label>
        <div class="q-hint"><?= h($q) ?></div>
        <textarea name="q<?= $n ?>" rows="5"><?= h(qval($editing ?? [], $n)) ?></textarea>
      </div>
      <?php endforeach; ?>

      <div style="display:flex;gap:.75rem;margin-top:1.5rem;flex-wrap:wrap">
        <button class="btn btn-primary" type="submit">💾 Save Artist</button>
        <a href="calypso-artists.php" class="btn btn-outline">Cancel</a>
        <?php if ($editing): ?>
          <a href="../calypso-artist-detail.html?id=<?= h($editing['id']) ?>" target="_blank" class="btn btn-outline" style="margin-left:auto">👁 View Profile ↗</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- ARTISTS TABLE -->
  <div class="panel">
    <h2 class="panel-title"><?= count($artists) ?> Artist<?= count($artists) !== 1 ? 's' : '' ?></h2>
    <?php if (empty($artists)): ?>
      <p style="color:#7A6565;text-align:center;padding:2rem">No artists yet. Add your first artist above.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="artist-table">
      <thead>
        <tr>
          <th>Artist</th>
          <th>Parish</th>
          <th>Tent</th>
          <th>Interview Date</th>
          <th>Answers</th>
          <th>Videos</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($artists as $a): ?>
        <?php
          $answered = count(array_filter(range(1,7), fn($n) => !empty($a['q'.$n])));
        ?>
        <tr>
          <td>
            <strong><?= h($a['stageName']) ?></strong><br>
            <small style="color:#7A6565"><?= h($a['realName']) ?></small>
            <?php if (!empty($a['shortBio'])): ?>
              <div style="font-size:.78rem;color:#7A6565;margin-top:.25rem;line-height:1.4"><?= h(mb_strimwidth($a['shortBio'], 0, 90, '…')) ?></div>
            <?php endif; ?>
          </td>
          <td><span class="parish-badge"><?= h($a['parish']) ?></span></td>
          <td style="font-size:.82rem">
            <?php
              $tid  = $a['tentId'] ?? null;
              $tent = $tid ? ($tents_map[$tid] ?? null) : null;
            ?>
            <?php if ($tent): ?>
              <a href="calypso-tents.php?edit=<?= h($tid) ?>" style="color:#CC0000;font-weight:600;text-decoration:none">
                <?= h($tent['shortName'] ?: $tent['name']) ?>
              </a>
            <?php else: ?>
              <span style="color:#CCC">—</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.82rem;white-space:nowrap"><?= $a['dateOfInterview'] ? h(date('j M Y', strtotime($a['dateOfInterview']))) : '<span style="color:#CCC">—</span>' ?></td>
          <td style="font-size:.82rem">
            <span style="color:<?= $answered === 7 ? '#1B5E20' : ($answered > 0 ? '#7A4A00' : '#9E9E9E') ?>;font-weight:700">
              <?= $answered ?>/7
            </span>
          </td>
          <td style="font-size:.82rem">
            <?php $vcount = count($a['videos'] ?? []); ?>
            <span style="color:<?= $vcount ? '#1B5E20' : '#9E9E9E' ?>;font-weight:700">🎬 <?= $vcount ?></span>
          </td>
          <td style="white-space:nowrap">
            <a href="?edit=<?= h($a['id']) ?>#artist-form" class="btn btn-sm btn-outline">Edit</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete <?= h(addslashes($a['stageName'])) ?>?')">
              <input type="hidden" name="_csrf"   value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action"  value="delete">
              <input type="hidden" name="del_id"  value="<?= h($a['id']) ?>">
              <button class="btn btn-sm btn-danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </div>

  <div style="margin-top:.5rem">
    <a href="../calypso-artists.html" target="_blank" class="btn btn-outline">👁 View Artist Listing Page ↗</a>
  </div>
</main>
</body>
</html>
