<?php
/* ----------------------------------------------------------------
   artist-upload.php — Public media upload for calypso tent artists.
   No login required. Artist enters their name and uploads files.
   ---------------------------------------------------------------- */

$upload_base = __DIR__ . '/uploads/artist-media/';
$max_video   = 1024 * 1024 * 500; // 500 MB
$max_audio   = 1024 * 1024 *  50; //  50 MB
$max_photo   = 1024 * 1024 *  20; //  20 MB

$video_exts  = ['mp4','mov','avi','mkv','webm','3gp','m4v'];
$audio_exts  = ['mp3','wav','ogg','aac','m4a','flac','wma'];
$photo_exts  = ['jpg','jpeg','png','gif','webp','heic','heif','tiff','tif'];
$all_exts    = array_merge($video_exts, $audio_exts, $photo_exts);

$video_mimes = [
    'video/mp4','video/quicktime','video/x-msvideo','video/x-ms-wmv',
    'video/avi','video/x-matroska','video/webm','video/3gpp','video/x-m4v',
];
$audio_mimes = [
    'audio/mpeg','audio/mp3','audio/wav','audio/x-wav','audio/ogg',
    'audio/aac','audio/mp4','audio/x-m4a','audio/flac','audio/x-flac',
    'audio/x-ms-wma','audio/webm',
];
$photo_mimes = [
    'image/jpeg','image/png','image/gif','image/webp',
    'image/heic','image/heif','image/tiff',
];

$messages       = [];
$upload_success = false;
$artist_name_ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $artist_name = trim($_POST['artist_name'] ?? '');

    // --- Validate name ---
    if ($artist_name === '') {
        $messages[] = ['type' => 'error', 'text' => 'Please enter your artist name before uploading.'];
    } elseif (strlen($artist_name) > 100) {
        $messages[] = ['type' => 'error', 'text' => 'Artist name must be 100 characters or fewer.'];
    } else {
        $artist_name_ok = $artist_name;
    }

    // --- Process files only if name is valid ---
    if ($artist_name_ok !== '') {

        $files = $_FILES['media_files'] ?? [];
        $has_files = !empty($files['name'][0]);

        if (!$has_files) {
            $messages[] = ['type' => 'error', 'text' => 'Please choose at least one file to upload.'];
        } else {
            // Ensure upload directory exists
            if (!is_dir($upload_base)) {
                mkdir($upload_base, 0755, true);
            }

            $safe_artist = preg_replace('/[^a-zA-Z0-9\-]/', '_', $artist_name_ok);
            $safe_artist = substr($safe_artist, 0, 60);
            $timestamp   = date('Ymd_His');
            $ok_count    = 0;
            $fail_count  = 0;

            foreach ($files['name'] as $idx => $orig_name) {
                if ($files['error'][$idx] === UPLOAD_ERR_NO_FILE) continue;

                // PHP upload error codes
                if ($files['error'][$idx] !== UPLOAD_ERR_OK) {
                    $err_map = [
                        UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload limit.',
                        UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form size limit.',
                        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded — please try again.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error (no tmp dir).',
                        UPLOAD_ERR_CANT_WRITE => 'Server could not write the file to disk.',
                    ];
                    $err_text = $err_map[$files['error'][$idx]] ?? 'Upload error (code ' . $files['error'][$idx] . ').';
                    $messages[] = ['type' => 'error', 'text' => htmlspecialchars($orig_name, ENT_QUOTES) . ': ' . $err_text];
                    $fail_count++;
                    continue;
                }

                $ext  = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                $mime = mime_content_type($files['tmp_name'][$idx]) ?: '';
                $size = (int) $files['size'][$idx];

                // Extension allow-list
                if (!in_array($ext, $all_exts, true)) {
                    $messages[] = ['type' => 'error', 'text' => htmlspecialchars($orig_name, ENT_QUOTES)
                        . ': File type <strong>.' . htmlspecialchars($ext, ENT_QUOTES) . '</strong> is not allowed. '
                        . 'Please upload video (MP4, MOV, AVI), audio (MP3, WAV, M4A), or photo (JPG, PNG, WEBP) files.'];
                    $fail_count++;
                    continue;
                }

                // Determine media type; fall back to extension if MIME is generic
                $is_video = in_array($mime, $video_mimes, true) || in_array($ext, $video_exts, true);
                $is_audio = in_array($mime, $audio_mimes, true) || in_array($ext, $audio_exts, true);
                $is_photo = in_array($mime, $photo_mimes, true) || in_array($ext, $photo_exts, true);

                if (!$is_video && !$is_audio && !$is_photo) {
                    $messages[] = ['type' => 'error', 'text' => htmlspecialchars($orig_name, ENT_QUOTES)
                        . ': Could not verify file type. Only video, audio, and photo files are accepted.'];
                    $fail_count++;
                    continue;
                }

                // Size limit
                if ($is_video)      { $max_size = $max_video; $max_label = '500 MB'; $type_label = 'Video'; }
                elseif ($is_audio)  { $max_size = $max_audio; $max_label = '50 MB';  $type_label = 'Audio'; }
                else                { $max_size = $max_photo; $max_label = '20 MB';  $type_label = 'Photo'; }

                if ($size > $max_size) {
                    $messages[] = ['type' => 'error', 'text' => htmlspecialchars($orig_name, ENT_QUOTES)
                        . ': File is too large (' . round($size / 1048576) . ' MB). '
                        . $type_label . ' files must be under ' . $max_label . '.'];
                    $fail_count++;
                    continue;
                }

                // Build a safe destination filename
                $clean_orig = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($orig_name));
                $dest_name  = "{$safe_artist}_{$timestamp}_{$idx}_{$clean_orig}";
                $dest_path  = $upload_base . $dest_name;

                if (move_uploaded_file($files['tmp_name'][$idx], $dest_path)) {
                    $ok_count++;
                } else {
                    $messages[] = ['type' => 'error', 'text' => htmlspecialchars($orig_name, ENT_QUOTES)
                        . ': Could not save file. Please try again or contact the site administrator.'];
                    $fail_count++;
                }
            }

            if ($ok_count > 0) {
                $upload_success = true;
                $fw = $ok_count === 1 ? 'file' : 'files';
                array_unshift($messages, [
                    'type' => 'success',
                    'text' => "Thank you, <strong>" . htmlspecialchars($artist_name_ok, ENT_QUOTES) . "</strong>! "
                            . "{$ok_count} {$fw} received successfully."
                            . ($fail_count > 0 ? " ({$fail_count} file(s) could not be uploaded — see below.)" : ''),
                ]);
            } elseif (empty($messages)) {
                $messages[] = ['type' => 'error', 'text' => 'No files were uploaded. Please select at least one file.'];
            }
        }
    }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Artist Media Upload — Exit 473 · Grenada Carnival</title>
  <meta name="description" content="Upload your performance video and audio files for the Grenada Carnival calypso tent.">
  <meta name="robots" content="noindex, nofollow">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Nunito:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <style>
    /* Upload page extras */
    .upload-hero {
      background: linear-gradient(135deg, var(--dark) 0%, var(--dark-2) 60%, #300000 100%);
      color: var(--white);
      padding: calc(var(--nav-h) + 3.5rem) 0 3.5rem;
      text-align: center;
    }
    .upload-hero h1 { color: var(--white); margin-bottom: .75rem; }
    .upload-hero p  { color: rgba(255,255,255,.72); max-width: 560px; margin: 0 auto; font-size: 1.05rem; }
    .upload-hero .section-label { justify-content: center; }

    .upload-wrap {
      max-width: 680px;
      margin: 0 auto;
      background: var(--white);
      border: 1px solid var(--gray-200);
      border-radius: var(--r-xl);
      padding: 2.5rem;
      box-shadow: var(--sh-lg);
    }

    /* Message banners */
    .msg-list { list-style: none; margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: .6rem; }
    .msg {
      padding: .85rem 1.1rem;
      border-radius: var(--r-md);
      font-size: .92rem;
      line-height: 1.5;
      border-left: 4px solid transparent;
    }
    .msg-success { background: #efffef; border-left-color: #2D9A27; color: #1a5c17; }
    .msg-error   { background: #fff2f2; border-left-color: var(--red); color: #7a0000; }

    /* Drop zone */
    .drop-zone {
      border: 2px dashed var(--gray-300);
      border-radius: var(--r-lg);
      padding: 2.5rem 1.5rem;
      text-align: center;
      cursor: pointer;
      transition: border-color var(--ease), background var(--ease);
      background: var(--sand);
      position: relative;
    }
    .drop-zone:hover,
    .drop-zone.drag-over {
      border-color: var(--red);
      background: #fff6f6;
    }
    .drop-zone input[type="file"] {
      position: absolute;
      inset: 0;
      opacity: 0;
      cursor: pointer;
      width: 100%;
      height: 100%;
    }
    .drop-icon { font-size: 2.8rem; margin-bottom: .75rem; display: block; }
    .drop-zone h3 { font-size: 1.05rem; color: var(--text); margin-bottom: .4rem; }
    .drop-zone p  { font-size: .82rem; color: var(--text-light); margin-bottom: 0; }

    /* File list preview */
    #file-list {
      margin-top: 1rem;
      display: flex;
      flex-direction: column;
      gap: .45rem;
    }
    .file-chip {
      display: flex;
      align-items: center;
      gap: .6rem;
      background: var(--sand);
      border: 1px solid var(--gray-200);
      border-radius: var(--r-md);
      padding: .5rem .85rem;
      font-size: .83rem;
      color: var(--text);
    }
    .file-chip-icon { font-size: 1.1rem; flex-shrink: 0; }
    .file-chip-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600; }
    .file-chip-size { color: var(--text-light); flex-shrink: 0; }

    /* Size note */
    .size-note {
      display: flex;
      gap: 1.5rem;
      margin-top: 1rem;
      flex-wrap: wrap;
    }
    .size-pill {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      background: var(--gray-100);
      border-radius: var(--r-full);
      padding: .3rem .85rem;
      font-size: .78rem;
      font-weight: 700;
      color: var(--text-light);
    }

    /* Submit button full-width */
    .upload-submit { width: 100%; justify-content: center; margin-top: 1.5rem; font-size: 1rem; }

    /* Success state */
    .success-block {
      text-align: center;
      padding: 2rem 1.5rem;
    }
    .success-icon { font-size: 3.5rem; display: block; margin-bottom: 1rem; }
    .success-block h2 { color: var(--text); margin-bottom: .6rem; }
    .success-block p  { color: var(--text-light); margin-bottom: 1.5rem; }

    /* Progress bar (client-side) */
    #upload-progress { display: none; margin-top: 1rem; }
    #upload-progress progress {
      width: 100%;
      height: 8px;
      border-radius: 4px;
      overflow: hidden;
      appearance: none;
    }
    #upload-progress progress::-webkit-progress-bar  { background: var(--gray-200); border-radius: 4px; }
    #upload-progress progress::-webkit-progress-value { background: var(--red); border-radius: 4px; }
    #upload-progress progress::-moz-progress-bar { background: var(--red); border-radius: 4px; }
    #progress-label { font-size: .8rem; color: var(--text-light); margin-top: .4rem; text-align: center; }

    @media (max-width: 720px) {
      .upload-wrap { padding: 1.5rem 1.2rem; border-radius: var(--r-lg); }
    }
  </style>
</head>
<body data-page="upload">

  <!-- NAV -->
  <nav class="site-nav solid" aria-label="Main navigation">
    <div class="nav-inner">
      <a href="index.html" class="nav-logo" aria-label="Exit 473 home">
        <img src="images/logo.png" alt="Exit 473 logo" class="logo-img" onerror="this.style.display='none'">
        <span class="logo-text">
          <span class="logo-main">Exit 473</span>
          <span class="logo-sub">Grenada</span>
        </span>
      </a>
      <ul class="nav-links" role="list">
        <li><a href="index.html">Home</a></li>
        <li><a href="carnival/carnival.html">Carnival</a></li>
        <li><a href="carnival/calypso-tents.html">Calypso Tents</a></li>
        <li><a href="carnival/calypso-artists.html">Artists</a></li>
      </ul>
      <button class="nav-hamburger" aria-label="Toggle menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>
  </nav>

  <!-- HERO -->
  <div class="upload-hero">
    <div class="container">
      <span class="section-label">Grenada Carnival</span>
      <h1>Artist Media Upload</h1>
      <p>Share your audio and video recordings with us. Just enter your name and select your files — no account needed.</p>
    </div>
  </div>

  <!-- MAIN -->
  <main>
    <section class="section-sm">
      <div class="container">
        <div class="upload-wrap">

          <?php if ($upload_success && empty(array_filter($messages, fn($m) => $m['type'] === 'error'))): ?>
            <!-- Pure success — show a thank-you card -->
            <div class="success-block">
              <span class="success-icon">🎉</span>
              <h2>Files Received!</h2>
              <?php foreach ($messages as $msg): ?>
                <p><?= $msg['text'] ?></p>
              <?php endforeach; ?>
              <a href="artist-upload.php" class="btn btn-gold btn-lg">Upload More Files</a>
            </div>

          <?php else: ?>

            <?php if (!empty($messages)): ?>
              <ul class="msg-list" role="alert">
                <?php foreach ($messages as $msg): ?>
                  <li class="msg msg-<?= h($msg['type']) ?>"><?= $msg['text'] ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" id="upload-form" novalidate>
              <!-- Hidden field helps PHP handle large file sizes -->
              <input type="hidden" name="MAX_FILE_SIZE" value="<?= $max_video ?>">

              <!-- Artist Name -->
              <div class="form-group">
                <label for="artist_name">Your Artist Name <span style="color:var(--red)">*</span></label>
                <input
                  type="text"
                  id="artist_name"
                  name="artist_name"
                  placeholder="e.g. Lady Spice"
                  maxlength="100"
                  required
                  autocomplete="name"
                  value="<?= h($_POST['artist_name'] ?? '') ?>"
                >
                <div class="form-note">Enter the name you perform under.</div>
              </div>

              <!-- File Upload Drop Zone -->
              <div class="form-group">
                <label>Audio &amp; Video Files <span style="color:var(--red)">*</span></label>
                <div class="drop-zone" id="drop-zone">
                  <input
                    type="file"
                    name="media_files[]"
                    id="media_files"
                    accept="video/mp4,video/quicktime,video/x-msvideo,video/webm,audio/mpeg,audio/wav,audio/ogg,audio/aac,audio/mp4,image/jpeg,image/png,image/gif,image/webp,image/heic,.mp4,.mov,.avi,.mkv,.webm,.3gp,.m4v,.mp3,.wav,.ogg,.aac,.m4a,.flac,.jpg,.jpeg,.png,.gif,.webp,.heic,.heif,.tiff"
                    multiple
                    required
                  >
                  <span class="drop-icon">🎵</span>
                  <h3>Drag &amp; drop files here, or click to browse</h3>
                  <p>Select one or more audio, video, or photo files</p>
                </div>

                <!-- Accepted formats & limits -->
                <div class="size-note">
                  <span class="size-pill">🎬 Video — up to 500 MB per file</span>
                  <span class="size-pill">🎙 Audio — up to 50 MB per file</span>
                  <span class="size-pill">📷 Photo — up to 20 MB per file</span>
                </div>
                <div class="form-note" style="margin-top:.5rem">
                  Accepted: MP4, MOV, AVI, MKV, WEBM &nbsp;·&nbsp; MP3, WAV, M4A, OGG, AAC, FLAC &nbsp;·&nbsp; JPG, PNG, WEBP, HEIC, GIF
                </div>

                <!-- Client-side file preview list -->
                <div id="file-list"></div>
              </div>

              <!-- Upload progress bar (shown via JS during submit) -->
              <div id="upload-progress">
                <progress id="progress-bar" value="0" max="100"></progress>
                <p id="progress-label">Uploading…</p>
              </div>

              <button type="submit" class="btn btn-gold btn-lg upload-submit" id="submit-btn">
                Upload Files
              </button>
            </form>

          <?php endif; ?>

        </div><!-- /.upload-wrap -->
      </div>
    </section>
  </main>

  <!-- FOOTER -->
  <footer class="site-footer">
    <div class="container">
      <div class="footer-bottom" style="border-top:1px solid rgba(255,255,255,.1);padding-top:1.5rem">
        <p>&copy; <?= date('Y') ?> Exit 473 &middot; Grenada</p>
        <p><a href="index.html">← Back to Exit 473</a></p>
      </div>
    </div>
  </footer>

  <script src="js/app.js"></script>
  <script>
    /* ---- Drop zone drag-and-drop highlight ---- */
    const zone   = document.getElementById('drop-zone');
    const input  = document.getElementById('media_files');
    const list   = document.getElementById('file-list');
    const form   = document.getElementById('upload-form');
    const btn    = document.getElementById('submit-btn');
    const progWrap = document.getElementById('upload-progress');
    const progBar  = document.getElementById('progress-bar');
    const progLbl  = document.getElementById('progress-label');

    if (zone) {
      ['dragenter','dragover'].forEach(evt =>
        zone.addEventListener(evt, e => { e.preventDefault(); zone.classList.add('drag-over'); })
      );
      ['dragleave','drop'].forEach(evt =>
        zone.addEventListener(evt, e => { e.preventDefault(); zone.classList.remove('drag-over'); })
      );
    }

    /* ---- File list preview ---- */
    function formatSize(bytes) {
      if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
      if (bytes >= 1024)    return (bytes / 1024).toFixed(0) + ' KB';
      return bytes + ' B';
    }

    function iconFor(name) {
      const ext = name.split('.').pop().toLowerCase();
      const vids  = ['mp4','mov','avi','mkv','webm','3gp','m4v'];
      const imgs  = ['jpg','jpeg','png','gif','webp','heic','heif','tiff','tif'];
      return vids.includes(ext) ? '🎬' : imgs.includes(ext) ? '📷' : '🎵';
    }

    function renderFileList(files) {
      if (!list) return;
      list.innerHTML = '';
      Array.from(files).forEach(f => {
        const chip = document.createElement('div');
        chip.className = 'file-chip';
        chip.innerHTML = `
          <span class="file-chip-icon">${iconFor(f.name)}</span>
          <span class="file-chip-name" title="${f.name.replace(/"/g,'&quot;')}">${f.name}</span>
          <span class="file-chip-size">${formatSize(f.size)}</span>
        `;
        list.appendChild(chip);
      });
    }

    if (input) {
      input.addEventListener('change', () => renderFileList(input.files));
    }

    /* ---- Show progress bar on submit (XHR for progress feedback) ---- */
    if (form) {
      form.addEventListener('submit', function (e) {
        const name  = document.getElementById('artist_name');
        const files = input ? input.files : null;

        if (name && !name.value.trim()) {
          name.focus(); return; // let HTML5 validation handle it
        }
        if (!files || files.length === 0) return;

        e.preventDefault();

        const fd = new FormData(form);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', window.location.href, true);

        xhr.upload.addEventListener('progress', function (ev) {
          if (!ev.lengthComputable) return;
          const pct = Math.round((ev.loaded / ev.total) * 100);
          if (progWrap) progWrap.style.display = 'block';
          if (progBar)  progBar.value = pct;
          if (progLbl)  progLbl.textContent = 'Uploading… ' + pct + '%';
        });

        xhr.addEventListener('load', function () {
          // Replace page content with server response
          document.open();
          document.write(xhr.responseText);
          document.close();
        });

        xhr.addEventListener('error', function () {
          if (progLbl) progLbl.textContent = 'Upload failed. Please try again.';
          if (btn) btn.disabled = false;
        });

        if (btn) { btn.disabled = true; btn.textContent = 'Uploading…'; }
        xhr.send(fd);
      });
    }
  </script>
</body>
</html>
