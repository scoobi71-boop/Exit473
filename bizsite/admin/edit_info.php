<?php
require_once 'auth.php';
require_auth();
$slug = current_slug();
$info = read_info($slug);
$msg  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $map = [
        'name'         => trim($_POST['name']         ?? ''),
        'tagline'      => trim($_POST['tagline']       ?? ''),
        'description'  => trim($_POST['description']   ?? ''),
        'category'     => trim($_POST['category']      ?? 'other'),
        'phone'        => trim($_POST['phone']         ?? ''),
        'email'        => trim($_POST['email']         ?? ''),
        'website'      => trim($_POST['website']       ?? ''),
        'address'      => trim($_POST['address']       ?? ''),
        'hours'        => trim($_POST['hours']         ?? ''),
        'accent_color' => trim($_POST['accent_color']  ?? '#2D6A4F'),
        'facebook'     => trim($_POST['facebook']      ?? ''),
        'instagram'    => trim($_POST['instagram']     ?? ''),
        'hero_image'   => $info['hero_image']  ?? '',
        'logo_image'   => $info['logo_image']  ?? '',
    ];

    // Handle image uploads
    $hero_upload = handle_upload('hero_upload', $slug);
    if ($hero_upload) $map['hero_image'] = $hero_upload;

    $logo_upload = handle_upload('logo_upload', $slug);
    if ($logo_upload) $map['logo_image'] = $logo_upload;

    if (!$map['name']) {
        $msg = 'error:Business name is required.';
    } else {
        write_info($slug, $map);
        $info = $map;
        $msg  = 'ok:Business info saved.';
    }
}

$cats = ['bar','barbershop','cafe','cultural','entertainment','local-craft','other','restaurant','retail','service','shop','snack-bar','tea-shop','wellness'];
[$msg_type, $msg_text] = $msg ? explode(':', $msg, 2) : ['',''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Business Info — <?= h($info['name'] ?? 'Admin') ?></title>
<?php include 'head_styles.php'; ?>
</head>
<body>
<?php include 'nav.php'; ?>
<main class="wrap">
  <h1 class="page-title">Business Info</h1>

  <?php if ($msg_text): ?>
    <div class="alert alert-<?= $msg_type ?>"><?= h($msg_text) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

    <div class="panel">
      <h2 class="panel-title">Basic Details</h2>
      <div class="form-grid">
        <div class="form-row" style="grid-column:1/-1">
          <label>Business Name *</label>
          <input type="text" name="name" required value="<?= h($info['name'] ?? '') ?>" placeholder="Your business name">
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label>Tagline</label>
          <input type="text" name="tagline" value="<?= h($info['tagline'] ?? '') ?>" placeholder="Short catchy phrase">
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label>Description *</label>
          <textarea name="description" rows="5" required><?= h($info['description'] ?? '') ?></textarea>
        </div>
        <div class="form-row">
          <label>Category *</label>
          <select name="category" required>
            <?php foreach ($cats as $c): ?>
              <option value="<?= $c ?>" <?= ($info['category'] ?? '') === $c ? 'selected' : '' ?>><?= ucwords(str_replace('-', ' ', $c)) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="hint">Restaurant / Bar / Cafe / Food categories show a Menu page</span>
        </div>
        <div class="form-row">
          <label>Accent Colour</label>
          <div style="display:flex;align-items:center;gap:.6rem">
            <input type="color" name="accent_color" value="<?= h($info['accent_color'] ?? '#2D6A4F') ?>" style="width:44px;height:36px;padding:2px;border:1.5px solid #DDE3EA;border-radius:6px;cursor:pointer" oninput="document.getElementById('hex_val').value=this.value">
            <input type="text" id="hex_val" value="<?= h($info['accent_color'] ?? '#2D6A4F') ?>" style="width:100px" oninput="document.querySelector('[name=accent_color]').value=this.value">
          </div>
        </div>
      </div>
    </div>

    <div class="panel">
      <h2 class="panel-title">Contact & Hours</h2>
      <div class="form-grid">
        <div class="form-row">
          <label>Phone</label>
          <input type="text" name="phone" value="<?= h($info['phone'] ?? '') ?>" placeholder="+1-473-403-7377">
        </div>
        <div class="form-row">
          <label>Email</label>
          <input type="email" name="email" value="<?= h($info['email'] ?? '') ?>" placeholder="info@example.com">
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label>Address</label>
          <input type="text" name="address" value="<?= h($info['address'] ?? '') ?>" placeholder="Unit 1, Exit 473...">
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label>Hours</label>
          <input type="text" name="hours" value="<?= h($info['hours'] ?? '') ?>" placeholder="Mon–Fri 9am–6pm | Sat 10am–4pm">
          <span class="hint">Use | to separate different day ranges</span>
        </div>
        <div class="form-row">
          <label>Website</label>
          <input type="text" name="website" value="<?= h($info['website'] ?? '') ?>" placeholder="https://...">
        </div>
      </div>
    </div>

    <div class="panel">
      <h2 class="panel-title">Social Media</h2>
      <div class="form-grid">
        <div class="form-row">
          <label>Facebook URL</label>
          <input type="text" name="facebook" value="<?= h($info['facebook'] ?? '') ?>" placeholder="https://facebook.com/...">
        </div>
        <div class="form-row">
          <label>Instagram URL</label>
          <input type="text" name="instagram" value="<?= h($info['instagram'] ?? '') ?>" placeholder="https://instagram.com/...">
        </div>
      </div>
    </div>

    <div class="panel">
      <h2 class="panel-title">Images</h2>
      <div class="form-grid">
        <div class="form-row">
          <label>Hero Image</label>
          <?php if (!empty($info['hero_image'])): ?>
            <img src="../<?= h($info['hero_image']) ?>" alt="Hero" class="img-preview" style="margin-bottom:.5rem">
          <?php endif; ?>
          <input type="file" name="hero_upload" accept="image/*">
          <span class="hint">Recommended: 1400×600px. Appears at the top of your home page.</span>
        </div>
        <div class="form-row">
          <label>Logo</label>
          <?php if (!empty($info['logo_image'])): ?>
            <img src="../<?= h($info['logo_image']) ?>" alt="Logo" class="img-preview" style="margin-bottom:.5rem">
          <?php endif; ?>
          <input type="file" name="logo_upload" accept="image/*">
          <span class="hint">Square image recommended. Shown in navigation.</span>
        </div>
      </div>
    </div>

    <div style="display:flex;gap:.75rem;flex-wrap:wrap">
      <button class="btn btn-primary" type="submit">💾 Save Changes</button>
      <a href="dashboard.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</main>
</body>
</html>
