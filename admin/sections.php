<?php
require_once __DIR__ . '/../includes/protected.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once '../config/database.php';

// --- ROLE CHECK: Only Admin ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// --- IMAGE UPLOAD HELPER ---
function uploadSectionImage($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;

    $maxSize = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $maxSize) return 'SIZE_ERROR';

    $allowed = ['jpg','jpeg','png','webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) return null;

    $newName = uniqid('section_', true) . '.' . $ext;
    $uploadDir = dirname(__DIR__) . '/uploads/sections/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) return $newName;
    return null;
}

// --- HANDLE ADD SECTION ---
if (isset($_POST['add_section'])) {
    $section_name = $_POST['section_name'];
    $description = $_POST['description'] ?? '';
    $image = null;
    if (!empty($_FILES['image']['name'])) {
        $upload = uploadSectionImage($_FILES['image']);
        if ($upload !== 'SIZE_ERROR') $image = $upload;
    }
    $stmt = $pdo->prepare("INSERT INTO sections (section_name, description, image, deleted_at) VALUES (?, ?, ?, NULL)");
    $stmt->execute([$section_name, $description, $image]);
    exit(json_encode(['success' => true]));
}

// --- HANDLE EDIT SECTION ---
if (isset($_POST['edit_section'])) {
    $id = $_POST['section_id'];
    $section_name = $_POST['section_name'];
    $description = $_POST['description'] ?? '';

    $imageSql = '';
    $params = [$section_name, $description];

    if (!empty($_FILES['image']['name'])) {
        $upload = uploadSectionImage($_FILES['image']);
        if ($upload && $upload !== 'SIZE_ERROR') {
            $imageSql = ', image=?';
            $params[] = $upload;
        }
    }
    $params[] = $id;
    $stmt = $pdo->prepare("UPDATE sections SET section_name=?, description=? $imageSql WHERE id=?");
    $stmt->execute($params);
    exit(json_encode(['success' => true]));
}

// --- SOFT DELETE (ARCHIVE) ---
if (isset($_POST['delete'])) {
    $id = $_POST['delete'];
    $stmt = $pdo->prepare("UPDATE sections SET deleted_at=NOW() WHERE id=?");
    $stmt->execute([$id]);
    exit(json_encode(['success' => true]));
}

// --- RESTORE SECTION ---
if (isset($_POST['restore'])) {
    $id = $_POST['restore'];
    $stmt = $pdo->prepare("UPDATE sections SET deleted_at=NULL WHERE id=?");
    $stmt->execute([$id]);
    exit(json_encode(['success' => true]));
}

// --- FETCH SECTIONS ---
$sections = $pdo->query("SELECT * FROM sections WHERE deleted_at IS NULL ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$archivedSections = $pdo->query("SELECT * FROM sections WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Count vendors per section
$vendorCounts = [];
$vcStmt = $pdo->query("SELECT section_id, COUNT(*) as cnt FROM vendors GROUP BY section_id");
while ($row = $vcStmt->fetch(PDO::FETCH_ASSOC)) {
    $vendorCounts[$row['section_id']] = $row['cnt'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sections | Admin - RPMS</title>
<?php include __DIR__ . '/../includes/favicon.php'; ?>

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
.page-header { display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
.page-header h1 { font-family: 'Inter', sans-serif; font-size: 1.7rem; font-weight: 700; line-height: 1; }
.page-header .sub { font-size: .88rem; color: var(--ink-3); margin-top: 4px; }

/* Buttons */
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 9px; font-family: inherit; font-size: .87rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: .2s; }
.btn-primary { background: var(--brand); color: #fff; }
.btn-primary:hover { background: var(--brand-dark); transform: translateY(-1px); }
.btn-outline { background: var(--white); color: var(--ink-2); border: 1px solid var(--border); }
.btn-outline:hover { background: var(--brand-light); color: var(--brand-dark); }
.btn-sm { padding: 6px 12px; font-size: .8rem; border-radius: 7px; }
.btn-edit { background: #fff7ed; color: #b45309; border: 1px solid #fde68a; }
.btn-edit:hover { background: #fde68a; }
.btn-del { background: var(--error-bg); color: var(--error); border: 1px solid #fecaca; }
.btn-del:hover { background: #fecaca; }
.btn-restore { background: var(--brand-light); color: var(--brand-dark); border: 1px solid var(--brand); }
.btn-restore:hover { background: var(--brand); color: #fff; }

/* Summary cards */
.summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.sum-card { background: var(--white); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px 22px; display: flex; align-items: center; gap: 14px; transition: .25s; }
.sum-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px var(--brand-glow); }
.sum-icon { width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.sum-icon svg { width: 21px; height: 21px; }
.sum-icon.a { background: var(--brand-light); color: var(--brand-dark); }
.sum-icon.b { background: #eff6ff; color: #1e40af; }
.sum-icon.c { background: #fff7ed; color: #b45309; }
.sum-label { font-size: .73rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--ink-3); margin-bottom: 3px; }
.sum-val { font-family: 'Inter', sans-serif; font-size: 1.5rem; font-weight: 700; color: var(--ink); }

/* Card */
.card { background: var(--white); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
.card-header { padding: 18px 22px 14px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #edf2ee; flex-wrap: wrap; gap: 10px; }
.card-title { font-size: .95rem; font-weight: 700; color: var(--ink); }

/* Filter bar */
.filter-bar { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; padding: 14px 22px; border-bottom: 1px solid #edf2ee; background: var(--cream); }
.search-wrap { position: relative; flex: 1; min-width: 200px; }
.search-wrap input { width: 100%; padding: 8px 14px 8px 36px; border: 1.5px solid var(--border); border-radius: 8px; font-family: inherit; font-size: .875rem; color: var(--ink); background: var(--white); outline: none; transition: .2s; }
.search-wrap input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-glow); }
.search-icon { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: var(--ink-3); pointer-events: none; display: flex; align-items: center; }
.search-icon svg { width: 15px; height: 15px; }
.filter-select { padding: 8px 12px; border: 1.5px solid var(--border); border-radius: 8px; font-family: inherit; font-size: .875rem; color: var(--ink-2); background: var(--white); outline: none; cursor: pointer; }
.filter-select:focus { border-color: var(--brand); }

/* Tab pills */
.tab-pills { display: flex; gap: 4px; }
.tab-pill { padding: 6px 16px; border-radius: 50px; font-size: .82rem; font-weight: 600; cursor: pointer; border: none; background: transparent; color: var(--ink-3); transition: .2s; font-family: inherit; }
.tab-pill.active { background: var(--brand); color: #fff; }
.tab-pill:not(.active):hover { background: var(--brand-light); color: var(--brand-dark); }
.tab-pill .count { display: inline-flex; align-items: center; justify-content: center; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 50px; font-size: .7rem; font-weight: 700; margin-left: 4px; }
.tab-pill.active .count { background: rgba(255,255,255,.25); color: #fff; }
.tab-pill:not(.active) .count { background: var(--cream); color: var(--ink-3); }

/* Section cards grid */
.sections-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; padding: 22px; }
.section-card { background: var(--white); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; transition: .25s; }
.section-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px var(--brand-glow); }
.section-card.archived { opacity: .7; }
.section-card.archived:hover { opacity: 1; }

.section-img { width: 100%; height: 160px; object-fit: cover; display: block; }
.section-img-placeholder {
  width: 100%; height: 160px; display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, var(--cream) 0%, #f3e5db 100%);
  color: var(--ink-3); font-size: .85rem;
}
.section-body { padding: 16px 18px; }
.section-name { font-family: 'Inter', sans-serif; font-size: 1.1rem; font-weight: 700; color: var(--ink); margin-bottom: 4px; }
.section-desc { font-size: .82rem; color: var(--ink-3); line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.4em; }
.section-meta { display: flex; align-items: center; gap: 8px; margin-top: 10px; }
.section-vendor-count { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 50px; font-size: .73rem; font-weight: 700; background: var(--brand-light); color: var(--brand-dark); }
.section-vendor-count svg { width: 12px; height: 12px; }
.section-footer { padding: 12px 18px; border-top: 1px solid #edf2ee; display: flex; gap: 8px; }

/* Toast */
#alertToast { position: fixed; top: 80px; right: 24px; z-index: 9999; min-width: 300px; display: none; }
.toast-inner { padding: 14px 18px; border-radius: 10px; font-size: .88rem; font-weight: 500; box-shadow: 0 8px 24px rgba(0,0,0,.1); display: flex; align-items: center; gap: 10px; }
.toast-inner svg { width: 16px; height: 16px; flex-shrink: 0; }
.toast-success { background: var(--brand-light); color: var(--brand-dark); border: 1px solid var(--brand); }
.toast-error   { background: var(--error-bg); color: var(--error); border: 1px solid #fecaca; }

/* ---- MODAL ---- */
.modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(43,13,5,.5); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; }
.modal-backdrop.show { display: flex; }
.modal-box { background: var(--white); border-radius: 18px; width: 100%; max-width: 520px; max-height: 90vh; overflow-y: auto; box-shadow: 0 24px 60px rgba(0,0,0,.2); animation: modalPop .3s cubic-bezier(.34,1.56,.64,1) both; }
@keyframes modalPop { from{transform:scale(.9);opacity:0} to{transform:scale(1);opacity:1} }
.modal-head { padding: 22px 26px 18px; border-bottom: 1px solid #edf2ee; display: flex; align-items: center; justify-content: space-between; }
.modal-head h3 { font-family: 'Inter', sans-serif; font-size: 1.25rem; font-weight: 700; color: var(--ink); }
.modal-close { background: var(--cream); border: none; border-radius: 8px; width: 32px; height: 32px; cursor: pointer; color: var(--ink-3); transition: .2s; display: flex; align-items: center; justify-content: center; }
.modal-close svg { width: 15px; height: 15px; }
.modal-close:hover { background: var(--error-bg); color: var(--error); }
.modal-body { padding: 22px 26px; }
.modal-foot { padding: 16px 26px; border-top: 1px solid #edf2ee; display: flex; justify-content: flex-end; gap: 10px; }

/* Form fields */
.field { margin-bottom: 16px; }
.field label { display: block; font-size: .82rem; font-weight: 600; color: var(--ink-2); margin-bottom: 5px; }
.field input, .field select, .field textarea { width: 100%; padding: 10px 13px; border: 1.5px solid #e0e8e3; border-radius: 9px; font-family: inherit; font-size: .9rem; color: var(--ink); outline: none; transition: .2s; background: var(--white); resize: vertical; }
.field input:focus, .field select:focus, .field textarea:focus { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-glow); }
.field input::placeholder, .field textarea::placeholder { color: #b0c4b8; }

/* Image upload area */
.img-upload-area { border: 2px dashed var(--border); border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: .2s; background: var(--cream); margin-bottom: 16px; }
.img-upload-area:hover, .img-upload-area.drag-over { border-color: var(--brand); background: var(--brand-light); }
.img-preview { max-height: 120px; border-radius: 10px; object-fit: cover; margin-bottom: 8px; display: none; }
.img-upload-text { font-size: .82rem; color: var(--ink-3); }

/* Delete confirm */
.delete-confirm { text-align: center; padding: 10px 0; }
.delete-confirm .del-icon { width: 52px; height: 52px; margin: 0 auto 12px; background: #fff7ed; color: #b45309; border-radius: 14px; display: flex; align-items: center; justify-content: center; }
.delete-confirm .del-icon svg { width: 26px; height: 26px; }
.delete-confirm p { font-size: .92rem; color: var(--ink-3); line-height: 1.6; }
.delete-confirm strong { color: var(--ink); }

/* Empty state */
.empty-state { text-align: center; padding: 60px 20px; }
.empty-state .empty-icon { width: 52px; height: 52px; margin: 0 auto 12px; opacity: .4; display: flex; align-items: center; justify-content: center; }
.empty-state .empty-icon svg { width: 30px; height: 30px; }
.empty-state p { font-size: .92rem; color: var(--ink-3); }

@media (max-width: 900px) { .summary-grid { grid-template-columns: 1fr 1fr; } .sections-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 600px) { .summary-grid { grid-template-columns: 1fr; } .sections-grid { grid-template-columns: 1fr; } }
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
    <div>
      <h1>Sections</h1>
      <p class="sub">Manage market sections and stall groupings.</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Section</button>
  </div>

  <!-- SUMMARY -->
  <div class="summary-grid">
    <div class="sum-card">
      <div class="sum-icon a"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3.75 3.75h6v6h-6v-6zM14.25 3.75h6v6h-6v-6zM3.75 14.25h6v6h-6v-6zM14.25 14.25h6v6h-6v-6z"/></svg></div>
      <div><div class="sum-label">Active Sections</div><div class="sum-val"><?= count($sections) ?></div></div>
    </div>
    <div class="sum-card">
      <div class="sum-icon b"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.25 21h19.5m-18-18v18m16.5-18v18M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h8.25c.621 0 1.125.504 1.125 1.125V21"/></svg></div>
      <div><div class="sum-label">Total Vendors</div><div class="sum-val"><?= array_sum($vendorCounts) ?></div></div>
    </div>
    <div class="sum-card">
      <div class="sum-icon c"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg></div>
      <div><div class="sum-label">Archived</div><div class="sum-val"><?= count($archivedSections) ?></div></div>
    </div>
  </div>

  <!-- SECTIONS CARD -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">All Sections</span>
      <div class="tab-pills">
        <button class="tab-pill active" data-tab="active">Active <span class="count"><?= count($sections) ?></span></button>
        <button class="tab-pill" data-tab="archived">Archived <span class="count"><?= count($archivedSections) ?></span></button>
      </div>
    </div>

    <div class="filter-bar">
      <div class="search-wrap">
        <span class="search-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg></span>
        <input type="text" id="searchInput" placeholder="Search sections...">
      </div>
      <select class="filter-select" id="sortSelect">
        <option value="id_desc">Newest First</option>
        <option value="id_asc">Oldest First</option>
        <option value="name_asc">Name A-Z</option>
        <option value="name_desc">Name Z-A</option>
      </select>
    </div>

    <!-- Active Sections Grid -->
    <div class="sections-grid" id="activeSections">
      <?php if ($sections): ?>
        <?php foreach ($sections as $s): ?>
        <div class="section-card" data-id="<?= $s['id'] ?>" data-name="<?= htmlspecialchars(strtolower($s['section_name'])) ?>" data-date="<?= $s['id'] ?>">
          <?php if ($s['image']): ?>
            <img src="../uploads/sections/<?= $s['image'] ?>" class="section-img" alt="">
          <?php else: ?>
            <div class="section-img-placeholder">No Image</div>
          <?php endif; ?>
          <div class="section-body">
            <div class="section-name"><?= htmlspecialchars($s['section_name']) ?></div>
            <div class="section-desc"><?= htmlspecialchars($s['description']) ?></div>
            <div class="section-meta">
              <span class="section-vendor-count"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg> <?= $vendorCounts[$s['id']] ?? 0 ?> Vendor<?= ($vendorCounts[$s['id']] ?? 0) !== 1 ? 's' : '' ?></span>
            </div>
          </div>
          <div class="section-footer">
            <button class="btn btn-edit btn-sm editBtn"
              data-id="<?= $s['id'] ?>"
              data-name="<?= htmlspecialchars($s['section_name']) ?>"
              data-desc="<?= htmlspecialchars($s['description']) ?>"
              data-img="<?= $s['image'] ? '../uploads/sections/'.$s['image'] : '' ?>">Edit</button>
            <button class="btn btn-del btn-sm archiveBtn" data-id="<?= $s['id'] ?>" data-name="<?= htmlspecialchars($s['section_name']) ?>">Archive</button>
          </div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state" style="grid-column:1/-1;">
          <div class="empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3.75 3.75h6v6h-6v-6zM14.25 3.75h6v6h-6v-6zM3.75 14.25h6v6h-6v-6zM14.25 14.25h6v6h-6v-6z"/></svg></div>
          <p>No active sections found. Add your first section above.</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Archived Sections Grid (hidden by default) -->
    <div class="sections-grid" id="archivedSections" style="display:none;">
      <?php if ($archivedSections): ?>
        <?php foreach ($archivedSections as $s): ?>
        <div class="section-card archived" data-id="<?= $s['id'] ?>" data-name="<?= htmlspecialchars(strtolower($s['section_name'])) ?>" data-date="<?= $s['id'] ?>">
          <?php if ($s['image']): ?>
            <img src="../uploads/sections/<?= $s['image'] ?>" class="section-img" alt="">
          <?php else: ?>
            <div class="section-img-placeholder">No Image</div>
          <?php endif; ?>
          <div class="section-body">
            <div class="section-name"><?= htmlspecialchars($s['section_name']) ?></div>
            <div class="section-desc"><?= htmlspecialchars($s['description']) ?></div>
          </div>
          <div class="section-footer">
            <button class="btn btn-restore btn-sm restoreBtn" data-id="<?= $s['id'] ?>" data-name="<?= htmlspecialchars($s['section_name']) ?>">Restore</button>
          </div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state" style="grid-column:1/-1;">
          <div class="empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg></div>
          <p>No archived sections.</p>
        </div>
      <?php endif; ?>
    </div>

  </div>

</div>
</main>

<!-- ADD SECTION MODAL -->
<div class="modal-backdrop" id="addModal">
  <div class="modal-box">
    <div class="modal-head">
      <h3>Add Section</h3>
      <button class="modal-close" onclick="closeModal('addModal')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    <form id="addSectionForm" enctype="multipart/form-data">
      <div class="modal-body">
        <div class="img-upload-area" id="dropArea">
          <img class="img-preview" id="addPreview">
          <div class="img-upload-text">Drag & drop an image or click to browse</div>
          <input type="file" name="image" accept="image/*" id="addImageInput" style="display:none;">
        </div>
        <div class="field">
          <label>Section Name</label>
          <input type="text" name="section_name" placeholder="e.g. Section A" required>
        </div>
        <div class="field">
          <label>Description</label>
          <textarea name="description" rows="3" placeholder="Brief description of this section"></textarea>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Section</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT SECTION MODAL -->
<div class="modal-backdrop" id="editModal">
  <div class="modal-box">
    <div class="modal-head">
      <h3>Edit Section</h3>
      <button class="modal-close" onclick="closeModal('editModal')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    <form id="editSectionForm" enctype="multipart/form-data">
      <input type="hidden" name="section_id" id="editSectionId">
      <div class="modal-body">
        <div class="img-upload-area" id="editDropArea">
          <img class="img-preview" id="editPreview">
          <div class="img-upload-text">Drag & drop an image or click to browse</div>
          <input type="file" name="image" accept="image/*" id="editImageInput" style="display:none;">
        </div>
        <div class="field">
          <label>Section Name</label>
          <input type="text" name="section_name" id="editName" required>
        </div>
        <div class="field">
          <label>Description</label>
          <textarea name="description" id="editDesc" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ARCHIVE CONFIRM MODAL -->
<div class="modal-backdrop" id="archiveModal">
  <div class="modal-box" style="max-width:400px;">
    <div class="modal-head">
      <h3>Archive Section</h3>
      <button class="modal-close" onclick="closeModal('archiveModal')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    <div class="modal-body">
      <div class="delete-confirm">
        <div class="del-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg></div>
        <p>Are you sure you want to archive<br><strong id="archiveName"></strong>?</p>
        <p style="font-size:.8rem;margin-top:8px;">You can restore it later from the Archived tab.</p>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-outline" onclick="closeModal('archiveModal')">Cancel</button>
      <button class="btn btn-del" id="confirmArchiveBtn">Archive</button>
    </div>
  </div>
</div>

<script>
// ---- MODAL ----
function openModal(id) { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
document.querySelectorAll('.modal-backdrop').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) closeModal(m.id); });
});

// ---- TOAST ----
const toastCheckSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.75l6 6 9-13.5"/></svg>';
const toastErrorSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>';
function showToast(type, msg) {
  const t = document.getElementById('alertToast');
  const inner = document.getElementById('toastInner');
  inner.className = 'toast-inner toast-' + type;
  inner.innerHTML = (type === 'success' ? toastCheckSvg : toastErrorSvg) + msg;
  t.style.display = 'block';
  setTimeout(() => { t.style.display = 'none'; }, 3500);
}

// ---- IMAGE PREVIEW ----
function previewImage(input, previewId) {
  const img = document.getElementById(previewId);
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => { img.src = e.target.result; img.style.display = 'block'; };
    reader.readAsDataURL(input.files[0]);
  }
}

// ---- DRAG & DROP (Add) ----
const dropArea = document.getElementById('dropArea');
const addFileInput = document.getElementById('addImageInput');
dropArea.addEventListener('click', () => addFileInput.click());
addFileInput.addEventListener('change', () => previewImage(addFileInput, 'addPreview'));
dropArea.addEventListener('dragover', e => { e.preventDefault(); dropArea.classList.add('drag-over'); });
dropArea.addEventListener('dragleave', e => { e.preventDefault(); dropArea.classList.remove('drag-over'); });
dropArea.addEventListener('drop', e => {
  e.preventDefault();
  dropArea.classList.remove('drag-over');
  addFileInput.files = e.dataTransfer.files;
  previewImage(addFileInput, 'addPreview');
});

// ---- DRAG & DROP (Edit) ----
const editDropArea = document.getElementById('editDropArea');
const editFileInput = document.getElementById('editImageInput');
editDropArea.addEventListener('click', () => editFileInput.click());
editFileInput.addEventListener('change', () => previewImage(editFileInput, 'editPreview'));
editDropArea.addEventListener('dragover', e => { e.preventDefault(); editDropArea.classList.add('drag-over'); });
editDropArea.addEventListener('dragleave', e => { e.preventDefault(); editDropArea.classList.remove('drag-over'); });
editDropArea.addEventListener('drop', e => {
  e.preventDefault();
  editDropArea.classList.remove('drag-over');
  editFileInput.files = e.dataTransfer.files;
  previewImage(editFileInput, 'editPreview');
});

// ---- ADD SECTION ----
$('#addSectionForm').on('submit', function(e) {
  e.preventDefault();
  const fd = new FormData(this);
  fd.append('add_section', true);
  $.ajax({
    url: 'sections.php', type: 'POST', data: fd,
    contentType: false, processData: false,
    success: function(res) {
      showToast('success', 'Section added successfully.');
      setTimeout(() => location.reload(), 800);
    },
    error: function() { showToast('error', 'Failed to add section.'); }
  });
});

// ---- EDIT SECTION ----
$(document).on('click', '.editBtn', function() {
  const btn = $(this);
  $('#editSectionId').val(btn.data('id'));
  $('#editName').val(btn.data('name'));
  $('#editDesc').val(btn.data('desc'));
  const img = btn.data('img');
  if (img) {
    $('#editPreview').attr('src', img).show();
  } else {
    $('#editPreview').hide();
  }
  openModal('editModal');
});

$('#editSectionForm').on('submit', function(e) {
  e.preventDefault();
  const fd = new FormData(this);
  fd.append('edit_section', true);
  $.ajax({
    url: 'sections.php', type: 'POST', data: fd,
    contentType: false, processData: false,
    success: function() {
      showToast('success', 'Section updated successfully.');
      setTimeout(() => location.reload(), 800);
    },
    error: function() { showToast('error', 'Failed to update section.'); }
  });
});

// ---- ARCHIVE ----
let archiveId = null;
$(document).on('click', '.archiveBtn', function() {
  archiveId = $(this).data('id');
  $('#archiveName').text($(this).data('name'));
  openModal('archiveModal');
});

$('#confirmArchiveBtn').on('click', function() {
  if (!archiveId) return;
  $.post('sections.php', { delete: archiveId }, function() {
    showToast('success', 'Section archived.');
    setTimeout(() => location.reload(), 800);
  });
  closeModal('archiveModal');
});

// ---- RESTORE ----
$(document).on('click', '.restoreBtn', function() {
  const id = $(this).data('id');
  $.post('sections.php', { restore: id }, function() {
    showToast('success', 'Section restored.');
    setTimeout(() => location.reload(), 800);
  });
});

// ---- TAB SWITCHING ----
$('.tab-pill').on('click', function() {
  $('.tab-pill').removeClass('active');
  $(this).addClass('active');
  const tab = $(this).data('tab');
  if (tab === 'active') {
    $('#activeSections').show();
    $('#archivedSections').hide();
  } else {
    $('#activeSections').hide();
    $('#archivedSections').show();
  }
});

// ---- SEARCH ----
$('#searchInput').on('input', function() {
  const q = this.value.toLowerCase();
  const visibleGrid = $('.tab-pill.active').data('tab') === 'active' ? '#activeSections' : '#archivedSections';
  $(visibleGrid + ' .section-card').each(function() {
    const name = $(this).data('name') || '';
    $(this).toggle(name.includes(q));
  });
});

// ---- SORT ----
$('#sortSelect').on('change', function() {
  const val = this.value;
  ['#activeSections', '#archivedSections'].forEach(function(container) {
    const cards = $(container).children('.section-card').get();
    cards.sort(function(a, b) {
      if (val === 'id_desc') return $(b).data('date') - $(a).data('date');
      if (val === 'id_asc') return $(a).data('date') - $(b).data('date');
      if (val === 'name_asc') return ($(a).data('name') || '').localeCompare($(b).data('name') || '');
      if (val === 'name_desc') return ($(b).data('name') || '').localeCompare($(a).data('name') || '');
    });
    $.each(cards, function(i, card) { $(container).append(card); });
  });
});
</script>
</body>
</html>