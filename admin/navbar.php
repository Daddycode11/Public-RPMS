<?php
require_once __DIR__ . '/../includes/protected.php';
if (session_status() == PHP_SESSION_NONE) if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../includes/lang.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$admin_name  = isset($_SESSION['first_name'], $_SESSION['last_name'])
    ? trim($_SESSION['first_name'] . ' ' . $_SESSION['last_name'])
    : ($_SESSION['username'] ?? 'Admin');
$admin_init  = strtoupper(substr($admin_name, 0, 1));
$current_page = basename($_SERVER['PHP_SELF']);

$nav = [
    ['label' => 'Dashboard',  'icon' => 'dashboard',  'href' => 'dashboard.php', 'pages' => ['dashboard.php']],
    ['label' => 'Vendors',    'icon' => 'vendors',  'href' => '#', 'pages' => ['vendors.php','vendor_accounts.php','overdue_vendors.php','vendor_payment_history.php','vendor_documents.php'],
     'children' => [
        ['label' => 'Vendor List',       'href' => 'vendors.php'],
        ['label' => 'Vendor Accounts',   'href' => 'vendor_accounts.php'],
        ['label' => 'Documents',         'href' => 'vendor_documents.php'],
     ]
    ],
    ['label' => 'Payments',   'icon' => 'payments',  'href' => '#', 'pages' => ['payments.php','partial_payments.php','verify_receipt.php','bulk_import.php','payment_calendar.php'],
     'children' => [
        ['label' => 'All Payments',      'href' => 'payments.php'],
        ['label' => 'Partial Payments',  'href' => 'partial_payments.php'],
        ['label' => 'Bulk Import',       'href' => 'bulk_import.php'],
        ['label' => 'Payment Calendar',  'href' => 'payment_calendar.php'],
        ['label' => 'QR Verification',   'href' => 'verify_receipt.php'],
     ]
    ],
    ['label' => 'Sections',   'icon' => 'sections',  'href' => '#', 'pages' => ['sections.php','stall_map.php'],
     'children' => [
        ['label' => 'Manage Sections',   'href' => 'sections.php'],
        ['label' => 'Stall Map',         'href' => 'stall_map.php'],
     ]
    ],
    ['label' => 'Reports',    'icon' => 'reports',  'href' => '#', 'pages' => ['reports.php','export.php','collector_performance.php','overdue_vendors.php','vendor_payment_history.php','late_payments.php','penalty_settings.php'],
     'children' => [
        ['label' => 'View Reports', 'href' => 'reports.php'],
        ['label' => 'Vendor Payment History', 'href' => 'vendor_payment_history.php'],
        ['label' => 'Overdue Vendors', 'href' => 'overdue_vendors.php'],
        ['label' => 'Collector Performance', 'href' => 'collector_performance.php'],
        ['label' => 'Late Payments', 'href' => 'late_payments.php'],
        ['label' => 'Payment Rules', 'href' => 'penalty_settings.php'],
        ['label' => 'Export Data',        'href' => 'export.php'],
     ]
    ],
    ['label' => 'Monitoring', 'icon' => 'monitoring',  'href' => '#', 'pages' => ['collector_performance.php','late_payments.php','audit_logs.php','collector_assignments.php','penalty_settings.php','collector_approvals.php'],
     'children' => [
        ['label' => 'Account Approvals',   'href' => 'collector_approvals.php'],
        ['label' => 'Collector Routes',      'href' => 'collector_assignments.php'],
     ]
    ],
    ['label' => 'Communication', 'icon' => 'communication', 'href' => '#', 'pages' => ['announcements.php','maintenance_requests.php'],
     'children' => [
        ['label' => 'Announcements',         'href' => 'announcements.php'],
        ['label' => 'Maintenance Requests',  'href' => 'maintenance_requests.php'],
     ]
    ],
    ['label' => 'Settings',   'icon' => 'settings',  'href' => 'settings.php',  'pages' => ['settings.php']],
];

// SVG icon library for the sidebar
$sb_icons = [
    'dashboard'     => '<path d="M3.75 3.75h6v6h-6v-6zM14.25 3.75h6v6h-6v-6zM3.75 14.25h6v6h-6v-6zM14.25 14.25h6v6h-6v-6z"/>',
    'vendors'       => '<path d="M2.25 21h19.5m-18-18v18m16.5-18v18M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h8.25c.621 0 1.125.504 1.125 1.125V21"/>',
    'payments'      => '<path d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/>',
    'sections'      => '<path d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.804a1.125 1.125 0 00-1.006 0L3.622 6.24C3.24 6.43 3 6.82 3 7.246v11.998c0 .836.88 1.381 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z"/>',
    'reports'       => '<path d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>',
    'monitoring'    => '<path d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
    'communication' => '<path d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.512l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46"/>',
    'settings'      => '<path d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.216.456a1.125 1.125 0 01-1.37-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.213-1.28z"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
];
function sb_icon($icons, $key) {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' . ($icons[$key] ?? '') . '</svg>';
}
?>

<!-- ===== SIDEBAR STYLES ===== -->
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300;0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700;0,14..32,800;1,14..32,400&display=swap');

:root {
  --sidebar-w:     240px;
  --sidebar-bg:    #2b0d05;
  --sidebar-hover: rgba(234,88,12,.14);
  --sidebar-active-bg: rgba(234,88,12,.2);
  --sidebar-active-text: #ffb37a;
  --brand:         #ea580c;
  --brand-dark:    #b3260c;
  --brand-light:   #ffe4d1;
  --ink:           #2b0d05;
  --cream:         #f5f9f6;
  --border:        rgba(234,88,12,.12);
  --topbar-h:      60px;
}

/* Base reset for sidebar context */
body {
  font-family: 'Inter', sans-serif;
  margin: 0;
  background: #f0f4f1;
}

/* ---- SIDEBAR ---- */
.rpms-sidebar {
  position: fixed;
  top: 0; left: 0; bottom: 0;
  width: var(--sidebar-w);
  background: var(--sidebar-bg);
  display: flex;
  flex-direction: column;
  z-index: 200;
  overflow-y: auto;
  scrollbar-width: none;
  transition: transform .3s ease;
}
.rpms-sidebar::-webkit-scrollbar { display: none; }

/* Brand */
.sb-brand {
  padding: 22px 20px 18px;
  border-bottom: 1px solid rgba(255,255,255,.06);
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
}
.sb-brand img { height: 36px; filter: brightness(0) invert(1); opacity: .85; }
.sb-brand-text { line-height: 1.1; }
.sb-brand-name {
  font-family: 'Inter', sans-serif;
  font-size: .95rem; font-weight: 700;
  color: #fff;
}
.sb-brand-sub { font-size: .68rem; color: rgba(255,255,255,.4); letter-spacing: .04em; }

/* Nav section label */
.sb-section-label {
  font-size: .65rem; font-weight: 700;
  letter-spacing: .14em; text-transform: uppercase;
  color: rgba(255,255,255,.25);
  padding: 18px 20px 8px;
}

/* Nav items */
.sb-nav { flex: 1; padding: 4px 0; }
.sb-item { list-style: none; }

.sb-link {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 20px;
  color: rgba(255,255,255,.6);
  text-decoration: none;
  font-size: .875rem; font-weight: 500;
  border-radius: 0;
  transition: background .2s, color .2s;
  cursor: pointer;
  position: relative;
  user-select: none;
}
.sb-link:hover { background: var(--sidebar-hover); color: rgba(255,255,255,.9); }
.sb-link.active {
  background: var(--sidebar-active-bg);
  color: var(--sidebar-active-text);
  font-weight: 600;
}
.sb-link.active::before {
  content: "";
  position: absolute; left: 0; top: 0; bottom: 0;
  width: 3px; background: var(--brand);
  border-radius: 0 2px 2px 0;
}
.sb-icon { width: 20px; height: 20px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.sb-icon svg { width: 17px; height: 17px; }
.sb-label { flex: 1; }
.sb-chevron {
  color: rgba(255,255,255,.3);
  transition: transform .25s;
  display: flex; align-items: center;
}
.sb-chevron svg { width: 12px; height: 12px; }
.sb-item.open > .sb-link .sb-chevron { transform: rotate(90deg); }

/* Submenu */
.sb-sub {
  list-style: none;
  padding: 2px 0 4px;
  display: none;
  background: rgba(0,0,0,.15);
}
.sb-item.open > .sb-sub { display: block; }
.sb-sub a {
  display: block;
  padding: 7px 20px 7px 50px;
  font-size: .83rem;
  color: rgba(255,255,255,.5);
  text-decoration: none;
  transition: color .2s, background .2s;
}
.sb-sub a:hover { color: rgba(255,255,255,.85); background: var(--sidebar-hover); }
.sb-sub a.active { color: var(--sidebar-active-text); font-weight: 600; }
.sb-sub a.danger { color: rgba(255,120,120,.7); }
.sb-sub a.danger:hover { color: #fca5a5; }

/* Sidebar bottom */
.sb-bottom {
  border-top: 1px solid rgba(255,255,255,.06);
  padding: 16px;
}
.sb-user {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px;
  border-radius: 10px;
  cursor: pointer;
  transition: background .2s;
  position: relative;
}
.sb-user:hover { background: var(--sidebar-hover); }
.sb-avatar {
  width: 36px; height: 36px; flex-shrink: 0;
  border-radius: 10px;
  background: linear-gradient(135deg, var(--brand), var(--brand-dark));
  color: #fff; font-weight: 700; font-size: .9rem;
  display: flex; align-items: center; justify-content: center;
}
.sb-user-info { flex: 1; min-width: 0; }
.sb-user-name { font-size: .83rem; font-weight: 600; color: rgba(255,255,255,.85); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sb-user-role { font-size: .7rem; color: rgba(255,255,255,.35); }
.sb-user-chevron { color: rgba(255,255,255,.3); display: flex; align-items: center; }
.sb-user-chevron svg { width: 12px; height: 12px; }

/* User dropdown */
.sb-user-menu {
  position: absolute; bottom: calc(100% + 6px); left: 0; right: 0;
  background: #3a140a;
  border: 1px solid rgba(255,255,255,.08);
  border-radius: 10px;
  overflow: hidden;
  display: none;
  z-index: 99;
}
.sb-user.open .sb-user-menu { display: block; }
.sb-user-menu a {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 16px;
  font-size: .85rem; color: rgba(255,255,255,.65);
  text-decoration: none; transition: background .2s, color .2s;
}
.sb-user-menu a svg { width: 14px; height: 14px; flex-shrink: 0; }
.sb-user-menu a:hover { background: var(--sidebar-hover); color: #fff; }
.sb-user-menu a.logout { color: rgba(255,100,100,.7); }
.sb-user-menu a.logout:hover { color: #fca5a5; }

/* ---- TOP BAR (mobile + page header) ---- */
.rpms-topbar {
  position: fixed;
  top: 0; left: var(--sidebar-w); right: 0;
  height: var(--topbar-h);
  background: #fff;
  border-bottom: 1px solid #e8f0eb;
  display: flex; align-items: center;
  padding: 0 28px;
  z-index: 100;
  gap: 14px;
}
.topbar-hamburger {
  display: none;
  background: none; border: none; cursor: pointer;
  flex-direction: column; gap: 4px; padding: 4px;
}
.topbar-hamburger span { display: block; width: 20px; height: 2px; background: var(--ink); border-radius: 2px; }
.topbar-title {
  font-family: 'Inter', sans-serif;
  font-size: 1.1rem; font-weight: 700;
  color: var(--ink);
}
.topbar-right { margin-left: auto; display: flex; align-items: center; gap: 16px; }
.topbar-date { font-size: .8rem; color: #888; }

/* Notification bell */
.notif-btn {
  position: relative;
  background: var(--cream); border: 1px solid var(--border);
  border-radius: 10px; padding: 7px 10px;
  cursor: pointer; color: var(--ink);
  display: flex; align-items: center; justify-content: center;
  transition: background .2s;
}
.notif-btn svg { width: 17px; height: 17px; }
.notif-btn:hover { background: var(--brand-light); }
.notif-dot {
  position: absolute; top: 5px; right: 5px;
  width: 8px; height: 8px; border-radius: 50%;
  background: var(--brand); border: 2px solid #fff;
}

/* ---- MAIN CONTENT OFFSET ---- */
.rpms-main {
  margin-left: var(--sidebar-w);
  margin-top: var(--topbar-h);
  min-height: calc(100vh - var(--topbar-h));
  padding: 28px;
}

/* Sidebar overlay on mobile */
.sb-overlay {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,.5);
  z-index: 199;
}

/* ---- RESPONSIVE ---- */
@media (max-width: 900px) {
  .rpms-sidebar { transform: translateX(-100%); }
  .rpms-sidebar.open { transform: translateX(0); }
  .sb-overlay.show { display: block; }
  .rpms-topbar { left: 0; }
  .rpms-main { margin-left: 0; }
  .topbar-hamburger { display: flex; }
}
</style>

<!-- ===== SIDEBAR MARKUP ===== -->
<div class="sb-overlay" id="sbOverlay" onclick="closeSidebar()"></div>

<aside class="rpms-sidebar" id="rpmsSidebar">

  <!-- Brand -->
  <a class="sb-brand" href="dashboard.php">
    <span class="market-logo" role="img" aria-label="San Jose Public Market"></span>
    <div class="sb-brand-text">
      <div class="sb-brand-name">RPMS</div>
      <div class="sb-brand-sub">Admin Panel</div>
    </div>
  </a>

  <!-- Navigation -->
  <nav class="sb-nav">
    <div class="sb-section-label">Main Menu</div>
    <ul style="list-style:none;padding:0;margin:0;">
    <?php foreach ($nav as $item):
      $hasChildren = !empty($item['children']);
      $isActive = in_array($current_page, $item['pages']);
      $isOpen   = $hasChildren && $isActive;
    ?>
      <li class="sb-item <?= $isOpen ? 'open' : '' ?>" <?= $hasChildren ? 'data-dropdown' : '' ?>>
        <?php if ($hasChildren): ?>
          <div class="sb-link <?= $isActive ? 'active' : '' ?>" onclick="toggleSbItem(this.parentElement)">
            <span class="sb-icon"><?= sb_icon($sb_icons, $item['icon']) ?></span>
            <span class="sb-label"><?= $item['label'] ?></span>
            <span class="sb-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg></span>
          </div>
          <ul class="sb-sub">
            <?php foreach ($item['children'] as $child): ?>
              <li><a href="<?= $child['href'] ?>"
                class="<?= $current_page === $child['href'] ? 'active' : '' ?> <?= !empty($child['danger']) ? 'danger' : '' ?>">
                <?= $child['label'] ?>
              </a></li>
            <?php endforeach ?>
          </ul>
        <?php else: ?>
          <a class="sb-link <?= $isActive ? 'active' : '' ?>" href="<?= $item['href'] ?>">
            <span class="sb-icon"><?= sb_icon($sb_icons, $item['icon']) ?></span>
            <span class="sb-label"><?= $item['label'] ?></span>
          </a>
        <?php endif ?>
      </li>
    <?php endforeach ?>
    </ul>
  </nav>

  <!-- Bottom user area -->
  <div class="sb-bottom">
    <div class="sb-user" id="sbUser" onclick="toggleUserMenu()">
      <div class="sb-user-menu" id="sbUserMenu">
        <a href="profile.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>Profile</a>
        <a href="../auth/logout.php" class="logout"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8.25 9V5.25A2.25 2.25 0 0110.5 3h6a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0116.5 21h-6a2.25 2.25 0 01-2.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>Logout</a>
      </div>
      <div class="sb-avatar"><?= $admin_init ?></div>
      <div class="sb-user-info">
        <div class="sb-user-name"><?= htmlspecialchars($admin_name) ?></div>
        <div class="sb-user-role">Administrator</div>
      </div>
      <span class="sb-user-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 15l-6-6-6 6"/></svg></span>
    </div>
  </div>
</aside>

<!-- TOP BAR -->
<div class="rpms-topbar" id="rpmsTopbar">
  <button class="topbar-hamburger" onclick="openSidebar()">
    <span></span><span></span><span></span>
  </button>
  <div class="topbar-title" id="topbarTitle">Dashboard</div>
  <div class="topbar-right">

    <span class="topbar-date" id="topbarDate"></span>
    <div class="notif-btn" title="Notifications">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
      <div class="notif-dot"></div>
    </div>
  </div>
</div>

<script>
// Set topbar title from active nav
(function() {
  const active = document.querySelector('.sb-link.active .sb-label, .sb-sub a.active');
  if (active) document.getElementById('topbarTitle').textContent = active.textContent.trim();
  // Date
  document.getElementById('topbarDate').textContent = new Date().toLocaleDateString('en-PH', {
    weekday:'short', year:'numeric', month:'short', day:'numeric'
  });
})();

// Dropdown toggle
function toggleSbItem(el) {
  el.classList.toggle('open');
}

// User menu
function toggleUserMenu() {
  document.getElementById('sbUser').classList.toggle('open');
}
document.addEventListener('click', e => {
  if (!document.getElementById('sbUser').contains(e.target)) {
    document.getElementById('sbUser').classList.remove('open');
  }
});

// Mobile
function openSidebar()  { document.getElementById('rpmsSidebar').classList.add('open'); document.getElementById('sbOverlay').classList.add('show'); }
function closeSidebar() { document.getElementById('rpmsSidebar').classList.remove('open'); document.getElementById('sbOverlay').classList.remove('show'); }
</script>