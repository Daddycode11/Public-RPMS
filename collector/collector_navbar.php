<?php
require_once __DIR__ . '/../includes/protected.php';
if (session_status() === PHP_SESSION_NONE) if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../includes/lang.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'collector') {
    header("Location: ../auth/login.php");
    exit;
}

$collector_name = isset($_SESSION['first_name'], $_SESSION['last_name'])
    ? trim($_SESSION['first_name'] . ' ' . $_SESSION['last_name'])
    : ($_SESSION['username'] ?? 'Collector');
$collector_init = strtoupper(substr($collector_name, 0, 1));
$current_page   = basename($_SERVER['PHP_SELF']);

$icon_dashboard = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>';
$icon_pos       = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>';
$icon_receipt   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>';
$icon_storefront = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.25 21h19.5m-18-18v18m16.5-18v18M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h8.25c.621 0 1.125.504 1.125 1.125V21"/></svg>';
$icon_megaphone = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 010-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c2.328.184 4.612.652 6.75 1.365a.75.75 0 001.24-.62V4.965a.75.75 0 00-1.24-.62c-2.138.713-4.422 1.181-6.75 1.365m0 9.18v-9.18"/></svg>';
$icon_printer   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6.72 13.829c-.24.03-.48.062-.72.096V9.75A2.25 2.25 0 019 7.5h6a2.25 2.25 0 012.25 2.25v4.075M6.72 13.829a48.822 48.822 0 011.472 4.377c.16.554.596.983 1.155 1.108.408.093.822.174 1.24.243m0 0a48.716 48.716 0 007.986 0m0 0c.418-.069.831-.15 1.24-.243a1.128 1.128 0 001.154-1.107 48.807 48.807 0 001.472-4.378M15.75 13.829V9.75A2.25 2.25 0 0013.5 7.5H9M15.75 13.829a48.729 48.729 0 00-7.5 0"/></svg>';
$icon_user      = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>';
$icon_logout    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l3 3m0 0l-3 3m3-3H3"/></svg>';

$nav = [
    ['label' => 'Dashboard',        'icon' => $icon_dashboard,  'href' => 'dashboard.php',  'pages' => ['dashboard.php']],
    ['label' => 'Submit Payment',   'icon' => $icon_pos,        'href' => 'collector_payments.php', 'pages' => ['collector_payments.php']],
    ['label' => 'My Collections',   'icon' => $icon_receipt,    'href' => 'collector_history.php',   'pages' => ['collector_history.php']],
    ['label' => 'Vendor List',      'icon' => $icon_storefront, 'href' => 'vendors_list.php',   'pages' => ['vendors_list.php']],
    ['label' => 'Announcements',    'icon' => $icon_megaphone,  'href' => 'collector_announcements.php', 'pages' => ['collector_announcements.php']],
    ['label' => 'Receipts',         'icon' => $icon_printer,    'href' => 'collector_receipt.php',  'pages' => ['collector_receipt.php']],
    ['label' => 'Profile',          'icon' => $icon_user,       'href' => 'collector_profile.php',   'pages' => ['collector_profile.php']],
];
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap');

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
.sb-brand-name { font-family: 'Inter', sans-serif; font-weight: 700; font-size: .95rem; font-weight: 700; color: #fff; line-height: 1.1; }
.sb-brand-sub  { font-size: .68rem; color: rgba(255,255,255,.38); letter-spacing: .04em; }

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
.sb-icon  { width: 20px; height: 20px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; }
.sb-icon svg { width: 17px; height: 17px; }

/* Today's summary box */
.sb-today {
  margin: 8px 14px 4px;
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 10px; padding: 12px 14px;
}
.sb-today-label { font-size: .65rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: rgba(255,255,255,.35); margin-bottom: 8px; }
.sb-today-row   { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
.sb-today-row:last-child { margin-bottom: 0; }
.sb-today-key   { font-size: .77rem; color: rgba(255,255,255,.5); }
.sb-today-val   { font-size: .82rem; font-weight: 700; color: rgba(255,255,255,.85); }
.sb-today-val.green { color: #ffb37a; }

/* Bottom user area */
.sb-bottom { border-top: 1px solid rgba(255,255,255,.06); padding: 14px; }
.sb-user {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px; border-radius: 10px;
  cursor: pointer; transition: background .2s; position: relative;
}
.sb-user:hover { background: var(--sidebar-hover); }
.sb-chevron { display: inline-flex; color: rgba(255,255,255,.3); flex-shrink: 0; }
.sb-avatar {
  width: 36px; height: 36px; flex-shrink: 0; border-radius: 10px;
  background: linear-gradient(135deg, var(--brand), var(--brand-dark));
  color: #fff; font-weight: 700; font-size: .9rem;
  display: flex; align-items: center; justify-content: center;
}
.sb-user-name { font-size: .83rem; font-weight: 600; color: rgba(255,255,255,.85); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sb-user-role { font-size: .7rem; color: rgba(255,255,255,.35); }
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
.topbar-title { font-family: 'Inter', sans-serif; font-weight: 700; font-size: 1.1rem; font-weight: 700; color: var(--ink); }
.topbar-right  { margin-left: auto; display: flex; align-items: center; gap: 12px; }
.topbar-date   { font-size: .8rem; color: #888; }

/* Quick action button in topbar */
.topbar-pos-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 7px 16px; border-radius: 8px;
  background: var(--brand); color: #fff;
  font-family: 'Inter', sans-serif; font-size: .82rem; font-weight: 700;
  border: none; cursor: pointer; text-decoration: none; transition: .2s;
}
.topbar-pos-btn:hover { background: var(--brand-dark); }

/* Online indicator */
.online-dot {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: .78rem; color: var(--brand); font-weight: 600;
}
.online-dot::before { content:""; width:7px; height:7px; border-radius:50%; background:var(--brand); display:block; }
.online-dot.offline { color: #dc2626; }
.online-dot.offline::before { background: #dc2626; }

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
  <a class="sb-brand" href="collector_payments.php">
    <span class="market-logo" role="img" aria-label="San Jose Public Market"></span>
    <div>
      <div class="sb-brand-name">RPMS</div>
      <div class="sb-brand-sub">Collector Portal</div>
    </div>
  </a>

  <!-- Role pill -->
  <div class="sb-role-pill">
    <div class="sb-role-dot"></div>
    <div class="sb-role-text">Fee Collector · Active</div>
  </div>

  <!-- Today's live summary -->
  <div class="sb-today" id="sbTodaySummary">
    <div class="sb-today-label">Today's Summary</div>
    <div class="sb-today-row">
      <span class="sb-today-key">Collected</span>
      <span class="sb-today-val green" id="sbTodayAmount">—</span>
    </div>
    <div class="sb-today-row">
      <span class="sb-today-key">Transactions</span>
      <span class="sb-today-val" id="sbTodayCount">—</span>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="sb-nav">
    <div class="sb-section-label">Navigation</div>
    <ul style="list-style:none;padding:0;margin:0;">
      <?php foreach ($nav as $item):
        $isActive = in_array($current_page, $item['pages']);
        $isPOS    = $item['href'] === '#pos';
      ?>
      <li>
        <?php if ($isPOS): ?>
          <a class="sb-link" href="collector_payments.php#pos" onclick="scrollToPOS(event)">
            <span class="sb-icon"><?= $item['icon'] ?></span>
            <span><?= $item['label'] ?></span>
            <span style="margin-left:auto;background:rgba(234,88,12,.2);color:#ffb37a;font-size:.65rem;font-weight:700;padding:2px 7px;border-radius:50px;">POS</span>
          </a>
        <?php else: ?>
          <a class="sb-link <?= $isActive ? 'active' : '' ?>" href="<?= $item['href'] ?>">
            <span class="sb-icon"><?= $item['icon'] ?></span>
            <span><?= $item['label'] ?></span>
          </a>
        <?php endif ?>
      </li>
      <?php endforeach ?>
    </ul>
  </nav>

  <!-- Keyboard shortcut hint -->
  <div style="padding:10px 18px 4px;font-size:.68rem;color:rgba(255,255,255,.25);line-height:1.6;">
    <kbd style="background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px;font-size:.65rem;">Ctrl+Enter</kbd> to submit payment
  </div>

  <!-- Bottom user -->
  <div class="sb-bottom">
    <div class="sb-user" id="sbUser" onclick="toggleUserMenu()">
      <div class="sb-user-menu" id="sbUserMenu">
        <a href="collector_profile.php"><?= $icon_user ?> My Profile</a>
        <a href="../auth/logout.php" class="logout"><?= $icon_logout ?> Logout</a>
      </div>
      <div class="sb-avatar"><?= $collector_init ?></div>
      <div style="flex:1;min-width:0;">
        <div class="sb-user-name"><?= htmlspecialchars($collector_name) ?></div>
        <div class="sb-user-role">Fee Collector</div>
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
    <span class="online-dot" id="onlineIndicator">Online</span>
    
    <span class="topbar-date" id="topbarDate"></span>
    <a class="topbar-pos-btn" href="collector_payments.php#pos" onclick="scrollToPOS(event)">
      + New Payment
    </a>
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
  function toggleUserMenu() { document.getElementById('sbUser').classList.toggle('open'); }
  window.toggleUserMenu = toggleUserMenu;
  document.addEventListener('click', e => {
    if (!document.getElementById('sbUser').contains(e.target))
      document.getElementById('sbUser').classList.remove('open');
  });

  /* Mobile sidebar */
  window.openSidebar  = () => { document.getElementById('rpmsSidebar').classList.add('open'); document.getElementById('sbOverlay').classList.add('show'); };
  window.closeSidebar = () => { document.getElementById('rpmsSidebar').classList.remove('open'); document.getElementById('sbOverlay').classList.remove('show'); };

  /* Scroll to POS form */
  window.scrollToPOS = function (e) {
    const pos = document.getElementById('paymentForm') || document.querySelector('.pos-card');
    if (pos && window.location.pathname.includes('collector_payments')) {
      e.preventDefault();
      pos.scrollIntoView({ behavior: 'smooth', block: 'start' });
      const input = document.getElementById('stall_number');
      if (input) setTimeout(() => input.focus(), 400);
    }
  };

  /* Online indicator */
  function updateOnline() {
    const el = document.getElementById('onlineIndicator');
    if (!el) return;
    if (navigator.onLine) { el.textContent = 'Online'; el.className = 'online-dot'; }
    else                  { el.textContent = 'Offline'; el.className = 'online-dot offline'; }
  }
  window.addEventListener('online',  updateOnline);
  window.addEventListener('offline', updateOnline);
  updateOnline();

  /* Today's summary — fetch via AJAX if endpoint exists */
  fetch('collector_today_summary.php')
    .then(r => r.ok ? r.json() : null)
    .then(d => {
      if (!d) return;
      const amt = document.getElementById('sbTodayAmount');
      const cnt = document.getElementById('sbTodayCount');
      if (amt) amt.textContent = '₱' + parseFloat(d.total || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
      if (cnt) cnt.textContent = d.count || 0;
    })
    .catch(() => {});
})();
</script>