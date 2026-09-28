<?php
require_once __DIR__ . '/../includes/protected.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once '../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'collector') {
    header("Location: ../auth/login.php");
    exit;
}

$collector_id = $_SESSION['user_id'];

// Handle AJAX update request
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'update_profile') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (!$first_name || !$last_name || !$email) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=? WHERE id=?");
        $updated = $stmt->execute([$first_name, $last_name, $email, $phone, $collector_id]);

        if ($updated) {
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update profile.']);
        }
        exit;
    }

    if ($_POST['action'] === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$current || !$new || !$confirm) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
            exit;
        }

        if ($new !== $confirm) {
            echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
        $stmt->execute([$collector_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($current, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
            exit;
        }

        $new_hashed = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->execute([$new_hashed, $collector_id]);

        echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
        exit;
    }
}

// Fetch collector info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$stmt->execute([$collector_id]);
$collector = $stmt->fetch(PDO::FETCH_ASSOC);

$initials = strtoupper(substr($collector['first_name'], 0, 1) . substr($collector['last_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile | RPMS Collector</title>
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
  --brand-glow:  rgba(234,88,12,.15);
  --ink:         #2b0d05;
  --ink-2:       #3a5042;
  --ink-3:       #6b8878;
  --cream:       #f0f4f1;
  --white:       #ffffff;
  --border:      rgba(234,88,12,.12);
  --radius:      14px;
  --radius-sm:   10px;
  --error:       #dc2626;
}
body { font-family: 'Inter', sans-serif; background: var(--cream); color: var(--ink); }
.page-wrap { display: flex; flex-direction: column; gap: 24px; }

/* Header */
.page-header h1 { font-family: 'Inter', sans-serif; font-size: 1.7rem; font-weight: 700; line-height: 1; }
.page-header .sub { font-size: .88rem; color: var(--ink-3); margin-top: 4px; }

/* Profile banner */
.profile-banner {
  background: linear-gradient(135deg, var(--ink) 0%, #5a2415 100%);
  border-radius: var(--radius);
  padding: 28px 32px;
  display: flex; align-items: center; gap: 20px;
}
.profile-avatar {
  width: 72px; height: 72px; border-radius: 16px; flex-shrink: 0;
  background: linear-gradient(135deg, var(--brand), var(--brand-dark));
  color: #fff; font-family: 'Inter', sans-serif; font-size: 1.6rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 16px rgba(234,88,12,.3);
}
.profile-name { font-family: 'Inter', sans-serif; font-size: 1.3rem; font-weight: 700; color: #fff; }
.profile-email { font-size: .85rem; color: rgba(255,255,255,.55); margin-top: 3px; }
.profile-tags { display: flex; gap: 8px; margin-top: 10px; }
.profile-tag {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 12px; border-radius: 6px;
  font-size: .75rem; font-weight: 700;
}
.profile-tag.role { background: rgba(234,88,12,.3); color: #ffb37a; }
.profile-tag.since { background: rgba(255,255,255,.12); color: rgba(255,255,255,.7); }

/* Cards */
.card { background: var(--white); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
.card-header { padding: 18px 22px 14px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #edf2ee; }
.card-title { font-size: .95rem; font-weight: 700; color: var(--ink); }
.card-body { padding: 22px; }

/* Grid */
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

/* Form fields */
.field { margin-bottom: 18px; }
.field label { display: block; font-size: .82rem; font-weight: 600; color: var(--ink-2); margin-bottom: 5px; }
.field input {
  width: 100%; padding: 11px 14px;
  border: 1.5px solid #e0e8e3; border-radius: 9px;
  font-family: inherit; font-size: .9rem; color: var(--ink);
  outline: none; transition: .2s; background: var(--white);
}
.field input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-glow); }
.field input::placeholder { color: #b0c4b8; }
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

/* Buttons */
.btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 11px 24px; border-radius: 9px;
  font-family: inherit; font-size: .87rem; font-weight: 600;
  cursor: pointer; border: none; text-decoration: none; transition: .2s;
  width: 100%; justify-content: center;
}
.btn-primary { background: var(--brand); color: #fff; }
.btn-primary:hover { background: var(--brand-dark); transform: translateY(-1px); }
.btn-warn { background: #fff7ed; color: #b45309; border: 1px solid #fde68a; }
.btn-warn:hover { background: #fde68a; }

/* Toast alert */
.toast-msg {
  padding: 12px 16px; border-radius: 9px;
  font-size: .85rem; font-weight: 500; margin-bottom: 16px;
  display: none; align-items: center; gap: 8px;
}
.toast-msg svg { width: 15px; height: 15px; flex-shrink: 0; }
.toast-msg.success { display: flex; background: var(--brand-light); color: var(--brand-dark); border: 1px solid var(--brand); }
.toast-msg.error { display: flex; background: #fef2f2; color: var(--error); border: 1px solid #fecaca; }

@media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }
@media (max-width: 600px) { .field-row { grid-template-columns: 1fr; } .profile-banner { flex-direction: column; text-align: center; } .profile-tags { justify-content: center; } }
</style>
</head>
<body>

<?php include __DIR__ . '/collector_navbar.php'; ?>

<main class="rpms-main">
<div class="page-wrap">

  <!-- HEADER -->
  <div class="page-header">
    <h1>My Profile</h1>
    <p class="sub">Manage your account information and security settings.</p>
  </div>

  <!-- PROFILE BANNER -->
  <div class="profile-banner">
    <div class="profile-avatar"><?= $initials ?></div>
    <div>
      <div class="profile-name"><?= htmlspecialchars($collector['first_name'] . ' ' . $collector['last_name']) ?></div>
      <div class="profile-email"><?= htmlspecialchars($collector['email']) ?></div>
      <div class="profile-tags">
        <span class="profile-tag role">Fee Collector</span>
        <span class="profile-tag since">Member since <?= date('M Y', strtotime($collector['created_at'] ?? 'now')) ?></span>
      </div>
    </div>
  </div>

  <!-- FORMS -->
  <div class="grid-2">

    <!-- Profile Info -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Personal Information</span>
      </div>
      <div class="card-body">
        <div class="toast-msg" id="profileMsg"></div>
        <form id="profileForm">
          <div class="field-row">
            <div class="field">
              <label>First Name</label>
              <input type="text" name="first_name" value="<?= htmlspecialchars($collector['first_name']) ?>" required>
            </div>
            <div class="field">
              <label>Last Name</label>
              <input type="text" name="last_name" value="<?= htmlspecialchars($collector['last_name']) ?>" required>
            </div>
          </div>
          <div class="field">
            <label>Email Address</label>
            <input type="email" name="email" value="<?= htmlspecialchars($collector['email']) ?>" required>
          </div>
          <div class="field">
            <label>Phone Number</label>
            <input type="text" name="phone" value="<?= htmlspecialchars($collector['phone'] ?? '') ?>" placeholder="09XX XXX XXXX">
          </div>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
      </div>
    </div>

    <!-- Change Password -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Change Password</span>
      </div>
      <div class="card-body">
        <div class="toast-msg" id="passwordMsg"></div>
        <form id="passwordForm">
          <div class="field">
            <label>Current Password</label>
            <input type="password" name="current_password" placeholder="Enter current password" required>
          </div>
          <div class="field">
            <label>New Password</label>
            <input type="password" name="new_password" placeholder="Enter new password" required>
          </div>
          <div class="field">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" placeholder="Re-enter new password" required>
          </div>
          <button type="submit" class="btn btn-warn">Update Password</button>
        </form>
      </div>
    </div>

  </div>

</div>
</main>

<script>
const iconCheck = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12.75l6 6 9-13.5"/></svg>';
const iconX     = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>';

function showMsg(el, type, msg) {
  el.className = 'toast-msg ' + type;
  el.innerHTML = (type === 'success' ? iconCheck : iconX) + msg;
  setTimeout(() => { el.className = 'toast-msg'; }, 4000);
}

$('#profileForm').on('submit', function(e) {
  e.preventDefault();
  const data = $(this).serialize() + '&action=update_profile';
  $.post('collector_profile.php', data, function(res) {
    showMsg(document.getElementById('profileMsg'), res.success ? 'success' : 'error', res.message);
  }, 'json');
});

$('#passwordForm').on('submit', function(e) {
  e.preventDefault();
  const data = $(this).serialize() + '&action=change_password';
  $.post('collector_profile.php', data, function(res) {
    showMsg(document.getElementById('passwordMsg'), res.success ? 'success' : 'error', res.message);
    if (res.success) $('#passwordForm')[0].reset();
  }, 'json');
});
</script>
</body>
</html>