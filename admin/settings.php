<?php
require_once __DIR__ . '/../includes/protected.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once '../config/database.php';

// --- ROLE CHECK: Only Admin ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $settings = [];
}

// --- IMAGE UPLOAD HELPER ---
function uploadImage($file, $prefix) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;

    $maxSize = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $maxSize) return 'SIZE_ERROR';

    $allowed = ['jpg','jpeg','png','webp','ico'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return null;

    $newName = uniqid($prefix.'_'.time().'_', true) . '.' . $ext;
    $uploadDir = dirname(__DIR__) . '/uploads/settings/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) return $newName;
    return null;
}

// --- HANDLE SAVE SETTINGS ---
if (isset($_POST['save_settings'])) {
    $site_name = $_POST['site_name'];
    $description = $_POST['description'];
    $contact_email = $_POST['contact_email'];
    $primary_color = $_POST['primary_color'] ?? '#ea580c';
    $secondary_color = $_POST['secondary_color'] ?? '#6c757d';

    $logoSql = $faviconSql = $homepageSql = '';
    $params = [$site_name, $description, $contact_email, $primary_color, $secondary_color];

    if (!empty($_FILES['logo']['name'])) {
        $upload = uploadImage($_FILES['logo'], 'logo');
        if ($upload && $upload !== 'SIZE_ERROR') {
            $logoSql = ', logo=?';
            $params[] = $upload;
        }
    }
    if (!empty($_FILES['favicon']['name'])) {
        $upload = uploadImage($_FILES['favicon'], 'favicon');
        if ($upload && $upload !== 'SIZE_ERROR') {
            $faviconSql = ', favicon=?';
            $params[] = $upload;
        }
    }
    if (!empty($_FILES['homepage']['name'])) {
        $upload = uploadImage($_FILES['homepage'], 'homepage');
        if ($upload && $upload !== 'SIZE_ERROR') {
            $homepageSql = ', homepage_image=?';
            $params[] = $upload;
        }
    }

    if ($settings) {
        $params[] = $settings['id'];
        $stmt = $pdo->prepare("UPDATE settings SET site_name=?, description=?, contact_email=?, primary_color=?, secondary_color=? $logoSql $faviconSql $homepageSql WHERE id=?");
        $stmt->execute($params);
    } else {
        $stmt = $pdo->prepare("INSERT INTO settings (site_name, description, contact_email, primary_color, secondary_color, logo, favicon, homepage_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([...$params, $params[5] ?? null, $params[6] ?? null, $params[7] ?? null]);
    }

    exit(json_encode(['success' => true]));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings | Admin - RPMS</title>
<?php include __DIR__.'/../includes/favicon.php'; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300;0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700;0,14..32,800;1,14..32,400&display=swap" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --brand:       #ea580c;
  --brand-dark:  #b3260c;
  --brand-light: #ffe4d1;
  --brand-glow:  rgba(234,88,12,.12);
  --ink:         #2b0d05;
  --ink-2:       #3a5042;
  --ink-3:       #6b8878;
  --cream:       #f0f4f1;
  --white:       #ffffff;
  --border:      rgba(234,88,12,.12);
  --radius:      14px;
  --radius-sm:   10px;
  --error:       #dc2626;
  --error-bg:    #fef2f2;
}
body { font-family: 'Inter', sans-serif; background: var(--cream); color: var(--ink); }
.page-wrap { display: flex; flex-direction: column; gap: 24px; }

/* Header */
.page-header h1 { font-family: 'Inter', sans-serif; font-size: 1.7rem; font-weight: 700; line-height: 1; }
.page-header .sub { font-size: .88rem; color: var(--ink-3); margin-top: 4px; }

/* Buttons */
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 11px 24px; border-radius: 9px; font-family: inherit; font-size: .87rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: .2s; }
.btn-primary { background: var(--brand); color: #fff; }
.btn-primary:hover { background: var(--brand-dark); transform: translateY(-1px); }

/* Card */
.card { background: var(--white); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
.card-header { padding: 18px 22px 14px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #edf2ee; }
.card-title { font-size: .95rem; font-weight: 700; color: var(--ink); }
.card-body { padding: 22px; }

/* Grid */
.settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

/* Form fields */
.field { margin-bottom: 18px; }
.field:last-child { margin-bottom: 0; }
.field label { display: block; font-size: .82rem; font-weight: 600; color: var(--ink-2); margin-bottom: 5px; }
.field input, .field select, .field textarea {
  width: 100%; padding: 10px 13px;
  border: 1.5px solid var(--border); border-radius: 9px;
  font-family: inherit; font-size: .9rem; color: var(--ink);
  outline: none; transition: .2s; background: var(--white); resize: vertical;
}
.field input:focus, .field select:focus, .field textarea:focus { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-glow); }
.field input::placeholder, .field textarea::placeholder { color: #c9b3a5; }
.field-hint { font-size: .75rem; color: var(--ink-3); margin-top: 4px; }

/* Color picker */
.color-field { display: flex; align-items: center; gap: 12px; }
.color-swatch {
  width: 44px; height: 44px; border-radius: 10px; border: 2px solid var(--border);
  cursor: pointer; flex-shrink: 0; overflow: hidden; position: relative;
}
.color-swatch input[type="color"] {
  position: absolute; inset: -8px; width: 60px; height: 60px; border: none; cursor: pointer; padding: 0;
}
.color-hex { flex: 1; }

/* Image upload */
.upload-area {
  border: 2px dashed var(--border); border-radius: 12px;
  padding: 16px; text-align: center; cursor: pointer;
  transition: .2s; background: var(--cream); position: relative;
}
.upload-area:hover { border-color: var(--brand); background: var(--brand-light); }
.upload-area input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.upload-preview { max-height: 80px; border-radius: 8px; object-fit: contain; margin-bottom: 8px; }
.upload-text { font-size: .82rem; color: var(--ink-3); }
.upload-text strong { color: var(--brand-dark); }

/* Toast */
#alertToast { position: fixed; top: 80px; right: 24px; z-index: 9999; min-width: 300px; display: none; }
.toast-inner { padding: 14px 18px; border-radius: 10px; font-size: .88rem; font-weight: 500; box-shadow: 0 8px 24px rgba(0,0,0,.1); display: flex; align-items: center; gap: 10px; }
.toast-success { background: var(--brand-light); color: var(--brand-dark); border: 1px solid var(--brand); }
.toast-error   { background: var(--error-bg); color: var(--error); border: 1px solid #fecaca; }

@media (max-width: 900px) { .settings-grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<?php include 'navbar.php'; ?>

<!-- Toast -->
<div id="alertToast"><div class="toast-inner" id="toastInner"></div></div>

<main class="rpms-main">
<div class="page-wrap">

  <!-- HEADER -->
  <div class="page-header">
    <h1>Settings</h1>
    <p class="sub">Configure your site appearance and general preferences.</p>
  </div>

  <form id="settingsForm" enctype="multipart/form-data">
  <div class="settings-grid">

    <!-- GENERAL SETTINGS -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">General Information</span>
      </div>
      <div class="card-body">
        <div class="field">
          <label>Site Name</label>
          <input type="text" name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>" placeholder="e.g. RPMS" required>
        </div>
        <div class="field">
          <label>Description</label>
          <textarea name="description" rows="3" placeholder="Brief description of the system"><?= htmlspecialchars($settings['description'] ?? '') ?></textarea>
        </div>
        <div class="field">
          <label>Contact Email</label>
          <input type="email" name="contact_email" value="<?= htmlspecialchars($settings['contact_email'] ?? '') ?>" placeholder="admin@example.com" required>
        </div>
      </div>
    </div>

    <!-- APPEARANCE -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Appearance</span>
      </div>
      <div class="card-body">
        <div class="field">
          <label>Primary Color</label>
          <div class="color-field">
            <div class="color-swatch">
              <input type="color" name="primary_color" id="primaryColor" value="<?= htmlspecialchars($settings['primary_color'] ?? '#ea580c') ?>">
            </div>
            <input type="text" class="color-hex" id="primaryHex" value="<?= htmlspecialchars($settings['primary_color'] ?? '#ea580c') ?>" readonly>
          </div>
        </div>
        <div class="field">
          <label>Secondary Color</label>
          <div class="color-field">
            <div class="color-swatch">
              <input type="color" name="secondary_color" id="secondaryColor" value="<?= htmlspecialchars($settings['secondary_color'] ?? '#6c757d') ?>">
            </div>
            <input type="text" class="color-hex" id="secondaryHex" value="<?= htmlspecialchars($settings['secondary_color'] ?? '#6c757d') ?>" readonly>
          </div>
          <div class="field-hint">Colors are used across the system&#39;s theme.</div>
        </div>
      </div>
    </div>

    <!-- LOGO -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Logo</span>
      </div>
      <div class="card-body">
        <div class="upload-area">
          <?php if (!empty($settings['logo'])): ?>
            <img src="../uploads/settings/<?= $settings['logo'] ?>" class="upload-preview" id="logoPreview">
          <?php else: ?>
            <img src="" class="upload-preview" id="logoPreview" style="display:none;">
          <?php endif; ?>
          <div class="upload-text">Click or drag to upload <strong>logo</strong></div>
          <input type="file" name="logo" accept="image/*" onchange="previewImage(this,'logoPreview')">
        </div>
        <div class="field-hint" style="margin-top:8px;">Recommended: PNG with transparent background, max 2MB.</div>
      </div>
    </div>

    <!-- FAVICON -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Favicon</span>
      </div>
      <div class="card-body">
        <div class="upload-area">
          <?php if (!empty($settings['favicon'])): ?>
            <img src="../uploads/settings/<?= $settings['favicon'] ?>" class="upload-preview" id="faviconPreview">
          <?php else: ?>
            <img src="" class="upload-preview" id="faviconPreview" style="display:none;">
          <?php endif; ?>
          <div class="upload-text">Click or drag to upload <strong>favicon</strong></div>
          <input type="file" name="favicon" accept="image/*,.ico" onchange="previewImage(this,'faviconPreview')">
        </div>
        <div class="field-hint" style="margin-top:8px;">Recommended: .ico or square PNG, 32x32 or 64x64 pixels.</div>
      </div>
    </div>

    <!-- HOMEPAGE IMAGE -->
    <div class="card" style="grid-column: 1 / -1;">
      <div class="card-header">
        <span class="card-title">Homepage Image</span>
      </div>
      <div class="card-body">
        <div class="upload-area">
          <?php if (!empty($settings['homepage_image'])): ?>
            <img src="../uploads/settings/<?= $settings['homepage_image'] ?>" class="upload-preview" id="homepagePreview" style="max-height:120px;">
          <?php else: ?>
            <img src="" class="upload-preview" id="homepagePreview" style="display:none;">
          <?php endif; ?>
          <div class="upload-text">Click or drag to upload <strong>homepage default image</strong></div>
          <input type="file" name="homepage" accept="image/*" onchange="previewImage(this,'homepagePreview')">
        </div>
        <div class="field-hint" style="margin-top:8px;">Displayed on the public-facing homepage. Recommended: 1200x600 or larger.</div>
      </div>
    </div>

  </div>

  <!-- SAVE BUTTON -->
  <div style="margin-top:20px; display:flex; justify-content:flex-end;">
    <button type="submit" class="btn btn-primary">Save Settings</button>
  </div>
  </form>

</div>
</main>

<script>
// ---- TOAST ----
function showToast(type, msg) {
  const t = document.getElementById('alertToast');
  const inner = document.getElementById('toastInner');
  inner.className = 'toast-inner toast-' + type;
  inner.textContent = (type === 'success' ? '\u2713 ' : '\u2717 ') + msg;
  t.style.display = 'block';
  setTimeout(() => { t.style.display = 'none'; }, 3500);
}

// ---- IMAGE PREVIEW ----
function previewImage(input, id) {
  const img = document.getElementById(id);
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => { img.src = e.target.result; img.style.display = 'block'; };
    reader.readAsDataURL(input.files[0]);
  }
}

// ---- COLOR SYNC ----
document.getElementById('primaryColor').addEventListener('input', function() {
  document.getElementById('primaryHex').value = this.value;
});
document.getElementById('secondaryColor').addEventListener('input', function() {
  document.getElementById('secondaryHex').value = this.value;
});

// ---- AJAX SAVE ----
$('#settingsForm').on('submit', function(e) {
  e.preventDefault();
  const fd = new FormData(this);
  fd.append('save_settings', true);
  $.ajax({
    url: 'settings.php',
    type: 'POST',
    data: fd,
    contentType: false,
    processData: false,
    success: function(res) {
      showToast('success', 'Settings saved successfully.');
      setTimeout(() => location.reload(), 1000);
    },
    error: function() {
      showToast('error', 'Failed to save settings.');
    }
  });
});
</script>
</body>
</html>