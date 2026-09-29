<?php
require_once __DIR__ . '/../includes/protected.php';
if (session_status() === PHP_SESSION_NONE) if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../includes/lang.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'vendor') {
    header("Location: ../auth/login.php");
    exit;
}

$vendor_name = isset($_SESSION['first_name'], $_SESSION['last_name'])
    ? trim($_SESSION['first_name'] . ' ' . $_SESSION['last_name'])
    : ($_SESSION['username'] ?? 'Vendor');
$vendor_init = strtoupper(substr($vendor_name, 0, 1));
$current_page = basename($_SERVER['PHP_SELF']);

$icon_dashboard = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3.75 3.75h6v6h-6v-6zM14.25 3.75h6v6h-6v-6zM3.75 14.25h6v6h-6v-6zM14.25 14.25h6v6h-6v-6z"/></svg>';
$icon_receipt   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>';
$icon_megaphone = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 010-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c2.328.184 4.612.652 6.75 1.365a.75.75 0 001.24-.62V4.965a.75.75 0 00-1.24-.62c-2.138.713-4.422 1.181-6.75 1.365m0 9.18v-9.18"/></svg>';
$icon_wrench    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z"/></svg>';
$icon_user      = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>';
$icon_logout    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l3 3m0 0l-3 3m3-3H3"/></svg>';

$nav = [
    ['label'=>'My Documents','icon'=>$icon_user,'href'=>'documents.php','pages'=>['documents.php']],
    ['label' => 'Dashboard',       'icon' => $icon_dashboard,  'href' => 'dashboard.php',              'pages' => ['dashboard.php']],
    ['label' => 'Payment History', 'icon' => $icon_receipt,    'href' => 'vendor_payment_history.php', 'pages' => ['vendor_payment_history.php']],
    ['label' => 'Announcements',   'icon' => $icon_megaphone,  'href' => 'vendor_announcements.php',   'pages' => ['vendor_announcements.php']],
    ['label' => 'Maintenance',     'icon' => $icon_wrench,     'href' => 'vendor_maintenance.php',     'pages' => ['vendor_maintenance.php']],
    ['label' => 'My Account',      'icon' => $icon_user,       'href' => 'vendor_account.php',          'pages' => ['vendor_account.php']],
];
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300;0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700;0,14..32,800;1,14..32,400&display=swap');

:root {
  --sidebar-w:     240px;
  --topbar-h:      60px;
  --sidebar-bg:    #2b0d05;
  --sidebar-hover: rgba(234,88,12,.12);
  --sidebar-active-bg:   rgba(234,88,12,.18);
  --sidebar-active-text: #ffb37a;
  --brand:         #ea580c;
  --brand-dark:    #b3260c;
  --brand-light:   #ffe4d1;
  --ink:           #2b0d05;
  --cream:         #f0f4f1;
  --white:         #ffffff;
  --border:        rgba(234,88,12,.12);
}

body {
  font-family: 'Inter', sans-serif;
  margin: 0;
  background: #f0f4f1;
}

/* ---- SIDEBAR ---- */
.rpms-sidebar {
  position: fixed; top: 0; left: 0; bottom: 0;
  width: var(--sidebar-w);
  background: var(--sidebar-bg);
  display: flex; flex-direction: column;
  z-index: 200; overflow-y: auto; scrollbar-width: none;
  transition: transform .3s ease;
}
.rpms-sidebar::-webkit-scrollbar { display: none; }

/* Brand */
.sb-brand {
  padding: 22px 20px 18px;
  border-bottom: 1px solid rgba(255,255,255,.06);
  display: flex; align-items: center; gap: 12px;
  text-decoration: none;
}
.sb-brand img { height: 36px; filter: brightness(0) invert(1); opacity: .85; }
.sb-brand-name { font-family: 'Inter', sans-serif; font-size: .95rem; font-weight: 700; color: #fff; line-height: 1.1; }
.sb-brand-sub  { font-size: .68rem; color: rgba(255,255,255,.4); letter-spacing: .04em; }

/* Role pill */
.sb-role-pill {
  margin: 12px 16px 4px;
  background: rgba(234,88,12,.18);
  border: 1px solid rgba(234,88,12,.25);
  border-radius: 8px;
  padding: 8px 14px;
  display: flex; align-items: center; gap: 8px;
}
.sb-role-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--brand); flex-shrink: 0; box-shadow: 0 0 0 3px rgba(234,88,12,.25); }
.sb-role-text { font-size: .78rem; font-weight: 600; color: #ffb37a; }

/* Section label */
.sb-section-label {
  font-size: .65rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase;
  color: rgba(255,255,255,.25); padding: 16px 20px 6px;
}

/* Nav */
.sb-nav { flex: 1; padding: 4px 0; }
.sb-link {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 20px; color: rgba(255,255,255,.62);
  text-decoration: none; font-size: .875rem; font-weight: 500;
  transition: background .2s, color .2s; position: relative;
  user-select: none; cursor: pointer;
}
.sb-link:hover  { background: var(--sidebar-hover); color: rgba(255,255,255,.92); }
.sb-link.active {
  background: var(--sidebar-active-bg);
  color: var(--sidebar-active-text); font-weight: 600;
}
.sb-link.active::before {
  content: ""; position: absolute; left: 0; top: 0; bottom: 0;
  width: 3px; background: var(--brand); border-radius: 0 2px 2px 0;
}
.sb-icon { width: 20px; height: 20px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; }
.sb-icon svg { width: 17px; height: 17px; }

/* Bottom user area */
.sb-bottom { border-top: 1px solid rgba(255,255,255,.06); padding: 14px; margin-top: auto; }
.sb-user {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px; border-radius: 10px;
  cursor: pointer; transition: background .2s; position: relative;
}
.sb-user:hover { background: var(--sidebar-hover); }
.sb-avatar {
  width: 36px; height: 36px; flex-shrink: 0; border-radius: 10px;
  background: linear-gradient(135deg, var(--brand), var(--brand-dark));
  color: #fff; font-weight: 700; font-size: .9rem;
  display: flex; align-items: center; justify-content: center;
}
.sb-user-name { font-size: .83rem; font-weight: 600; color: rgba(255,255,255,.85); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sb-user-role { font-size: .7rem; color: rgba(255,255,255,.35); }
.sb-chevron { display: inline-flex; color: rgba(255,255,255,.3); flex-shrink: 0; }
.sb-user-menu {
  position: absolute; bottom: calc(100% + 6px); left: 0; right: 0;
  background: #3a140a; border: 1px solid rgba(255,255,255,.1);
  border-radius: 10px; overflow: hidden; display: none; z-index: 99;
}
.sb-user.open .sb-user-menu { display: block; }
.sb-user-menu a { display: flex; align-items: center; gap: 8px; padding: 10px 16px; font-size: .85rem; color: rgba(255,255,255,.65); text-decoration: none; transition: background .2s, color .2s; }
.sb-user-menu a svg { width: 15px; height: 15px; flex-shrink: 0; }
.sb-user-menu a:hover  { background: var(--sidebar-hover); color: #fff; }
.sb-user-menu a.logout { color: rgba(255,100,100,.7); }
.sb-user-menu a.logout:hover { color: #fca5a5; }

/* ---- TOP BAR ---- */
.rpms-topbar {
  position: fixed; top: 0; left: var(--sidebar-w); right: 0;
  height: var(--topbar-h); background: #fff;
  border-bottom: 1px solid #e8f0eb;
  display: flex; align-items: center; padding: 0 28px;
  z-index: 100; gap: 14px;
}
.topbar-hamburger { display: none; background: none; border: none; cursor: pointer; flex-direction: column; gap: 4px; padding: 4px; }
.topbar-hamburger span { display: block; width: 20px; height: 2px; background: var(--ink); border-radius: 2px; }
.topbar-title { font-family: 'Inter', sans-serif; font-size: 1.1rem; font-weight: 700; color: var(--ink); }
.topbar-right  { margin-left: auto; display: flex; align-items: center; gap: 12px; }
.topbar-date   { font-size: .8rem; color: #888; }

/* Main content offset */
.rpms-main { margin-left: var(--sidebar-w); margin-top: var(--topbar-h); min-height: calc(100vh - var(--topbar-h)); padding: 28px; }

/* Sidebar overlay mobile */
.sb-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 199; }

/* Responsive */
@media (max-width: 900px) {
  .rpms-sidebar { transform: translateX(-100%); }
  .rpms-sidebar.open { transform: translateX(0); }
  .sb-overlay.show { display: block; }
  .rpms-topbar { left: 0; }
  .rpms-main { margin-left: 0; }
  .topbar-hamburger { display: flex; }
  .topbar-date { display: none; }
}
</style>

<div class="sb-overlay" id="sbOverlay" onclick="closeSidebar()"></div>

<aside class="rpms-sidebar" id="rpmsSidebar">

  <!-- Brand -->
  <a class="sb-brand" href="dashboard.php">
    <span class="market-logo" role="img" aria-label="San Jose Public Market"></span>
    <div>
      <div class="sb-brand-name">RPMS</div>
      <div class="sb-brand-sub">Vendor Portal</div>
    </div>
  </a>

  <!-- Role pill -->
  <div class="sb-role-pill">
    <div class="sb-role-dot"></div>
    <div class="sb-role-text">Stall Vendor · Active</div>
  </div>

  <!-- Navigation -->
  <nav class="sb-nav">
    <div class="sb-section-label">Navigation</div>
    <ul style="list-style:none;padding:0;margin:0;">
      <?php foreach ($nav as $item):
        $isActive = in_array($current_page, $item['pages']);
      ?>
      <li>
        <a class="sb-link <?= $isActive ? 'active' : '' ?>" href="<?= $item['href'] ?>">
          <span class="sb-icon"><?= $item['icon'] ?></span>
          <span><?= $item['label'] ?></span>
        </a>
      </li>
      <?php endforeach ?>
    </ul>
  </nav>

  <!-- Bottom user -->
  <div class="sb-bottom">
    <div class="sb-user" id="sbUser" onclick="toggleUserMenu()">
      <div class="sb-user-menu" id="sbUserMenu">
        <a href="vendor_account.php"><?= $icon_user ?> My Account</a>
        <a href="../auth/logout.php" class="logout"><?= $icon_logout ?> Logout</a>
      </div>
      <div class="sb-avatar"><?= $vendor_init ?></div>
      <div style="flex:1;min-width:0;">
        <div class="sb-user-name"><?= htmlspecialchars($vendor_name) ?></div>
        <div class="sb-user-role">Stall Vendor</div>
      </div>
      <span class="sb-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="12" height="12"><path d="M4.5 15.75l7.5-7.5 7.5 7.5"/></svg></span>
    </div>
  </div>
</aside>

<!-- TOP BAR -->
<div class="rpms-topbar">
  <button class="topbar-hamburger" onclick="openSidebar()">
    <span></span><span></span><span></span>
  </button>
  <div class="topbar-title" id="topbarTitle">Dashboard</div>
  <div class="topbar-right">

    <span class="topbar-date" id="topbarDate"></span>
  </div>
</div>

<script>
(function () {
  /* Topbar date */
  document.getElementById('topbarDate').textContent = new Date().toLocaleDateString('en-PH', {
    weekday: 'short', month: 'short', day: 'numeric'
  });

  /* Topbar title from active nav */
  const active = document.querySelector('.sb-link.active');
  if (active) document.getElementById('topbarTitle').textContent = active.textContent.trim();

  /* User menu */
  window.toggleUserMenu = function() { document.getElementById('sbUser').classList.toggle('open'); };
  document.addEventListener('click', e => {
    if (!document.getElementById('sbUser').contains(e.target))
      document.getElementById('sbUser').classList.remove('open');
  });

  /* Mobile sidebar */
  window.openSidebar  = () => { document.getElementById('rpmsSidebar').classList.add('open'); document.getElementById('sbOverlay').classList.add('show'); };
  window.closeSidebar = () => { document.getElementById('rpmsSidebar').classList.remove('open'); document.getElementById('sbOverlay').classList.remove('show'); };
})();
</script>
