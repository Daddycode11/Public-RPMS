<?php
require_once __DIR__ . '/../includes/protected.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'vendor') {
    header("Location: ../auth/login.php");
    exit;
}

$vendor_user_id = $_SESSION['user_id'];

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

        $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, contact_information=? WHERE id=?");
        $updated = $stmt->execute([$first_name, $last_name, $email, $phone, $vendor_user_id]);

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
        $stmt->execute([$vendor_user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($current, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
            exit;
        }

        $new_hashed = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->execute([$new_hashed, $vendor_user_id]);

        echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
        exit;
    }
}

// Fetch user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$stmt->execute([$vendor_user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch vendor info
$stmt = $pdo->prepare("
    SELECT v.*, s.section_name
    FROM vendors v
    LEFT JOIN sections s ON v.section_id = s.id
    WHERE v.user_id = ?
");
$stmt->execute([$vendor_user_id]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

$initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Account | RPMS</title>
<?php include __DIR__ . '/../includes/favicon.php'; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Fraunces:opsz,wght@9..144,700&display=swap" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --green:       #0e9e52;
  --green-dark:  #077a3c;
  --green-light: #d4f5e3;
  --green-glow:  rgba(14,158,82,.15);
  --ink:         #0d1f14;
  --ink-2:       #3a5042;
  --ink-3:       #6b8878;
  --cream:       #f0f4f1;
  --white:       #ffffff;
  --border:      rgba(14,158,82,.12);
  --radius:      14px;
  --radius-sm:   10px;
  --error:       #dc2626;
}
body { font-family: 'DM Sans', sans-serif; background: var(--cream); color: var(--ink); }
.page-wrap { display: flex; flex-direction: column; gap: 24px; }

/* Header */
.page-header h1 { font-family: 'Fraunces', serif; font-size: 1.7rem; font-weight: 700; line-height: 1; }
.page-header .sub { font-size: .88rem; color: var(--ink-3); margin-top: 4px; }

/* Profile banner */
.profile-banner {
  background: linear-gradient(135deg, var(--ink) 0%, #1a3020 100%);
  border-radius: var(--radius);
  padding: 28px 32px;
  display: flex; align-items: center; gap: 20px;
}
.profile-avatar {
  width: 72px; height: 72px; border-radius: 16px; flex-shrink: 0;
  background: linear-gradient(135deg, var(--green), var(--green-dark));
  color: #fff; font-family: 'Fraunces', serif; font-size: 1.6rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 16px rgba(14,158,82,.3);
}
.profile-name { font-family: 'Fraunces', serif; font-size: 1.3rem; font-weight: 700; color: #fff; }
.profile-email { font-size: .85rem; color: rgba(255,255,255,.45); margin-top: 3px; }
.profile-tags { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
.profile-tag {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 12px; border-radius: 6px;
  font-size: .75rem; font-weight: 700;
}
.profile-tag.role { background: rgba(14,158,82,.25); color: #6dffa8; }
.profile-tag.stall { background: rgba(255,255,255,.1); color: rgba(255,255,255,.6); }

/* Stall info grid */
.stall-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.stall-card {
  background: var(--white); border: 1px solid var(--border); border-radius: var(--radius);
  padding: 20px 22px; display: flex; align-items: center; gap: 14px; transition: .25s;
}
.stall-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px var(--green-glow); }
.sc-icon { width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; background: var(--green-light); }
.sc-label { font-size: .73rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--ink-3); margin-bottom: 3px; }
.sc-val { font-family: 'Fraunces', serif; font-size: 1.3rem; font-weight: 700; color: var(--ink); }

/* Cards */
.card { background: var(--white); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
.card-header { padding: 18px 22px 14px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #edf2ee; }
.card-title { font-size: .95rem; font-weight: 700; color: var(--ink); }
.card-body { padding: 22px; }

/* Grid */
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

/* Form */
.field { margin-bottom: 18px; }
.field label { display: block; font-size: .82rem; font-weight: 600; color: var(--ink-2); margin-bottom: 5px; }
.field input {
  width: 100%; padding: 11px 14px;
  border: 1.5px solid #e0e8e3; border-radius: 9px;
  font-family: inherit; font-size: .9rem; color: var(--ink);
  outline: none; transition: .2s; background: var(--white);
}
.field input:focus { border-color: var(--green); box-shadow: 0 0 0 3px var(--green-glow); }
.field input::placeholder { color: #b0c4b8; }
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

.btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 11px 24px; border-radius: 9px;
  font-family: inherit; font-size: .87rem; font-weight: 600;
  cursor: pointer; border: none; text-decoration: none; transition: .2s;
  width: 100%; justify-content: center;
}
.btn-primary { background: var(--green); color: #fff; }
.btn-primary:hover { background: var(--green-dark); transform: translateY(-1px); }
.btn-warn { background: #fff7ed; color: #b45309; border: 1px solid #fde68a; }
.btn-warn:hover { background: #fde68a; }

/* Toast */
.toast-msg {
  padding: 12px 16px; border-radius: 9px;
  font-size: .85rem; font-weight: 500; margin-bottom: 16px;
  display: none; align-items: center; gap: 8px;
}
.toast-msg.success { display: flex; background: var(--green-light); color: var(--green-dark); border: 1px solid var(--green); }
.toast-msg.error { display: flex; background: #fef2f2; color: var(--error); border: 1px solid #fecaca; }

@media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } .stall-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 600px) { .field-row { grid-template-columns: 1fr; } .stall-grid { grid-template-columns: 1fr; } .profile-banner { flex-direction: column; text-align: center; } .profile-tags { justify-content: center; } }
</style>
</head>
<body>

<?php include __DIR__ . '/vendor_navbar.php'; ?>

<main class="rpms-main">
<div class="page-wrap">

  <!-- HEADER -->
  <div class="page-header">
    <h1>My Account</h1>
    <p class="sub">View your stall details and manage your profile settings.</p>
  </div>

  <!-- PROFILE BANNER -->
  <div class="profile-banner">
    <div class="profile-avatar"><?= $initials ?></div>
    <div>
      <div class="profile-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
      <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>
      <div class="profile-tags">
        <span class="profile-tag role">Stall Vendor</span>
        <?php if ($vendor): ?>
          <span class="profile-tag stall">Stall <?= htmlspecialchars($vendor['stall_number']) ?></span>
          <span class="profile-tag stall"><?= htmlspecialchars($vendor['section_name'] ?? 'No Section') ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- STALL INFO -->
  <?php if ($vendor): ?>
  <div class="stall-grid">
    <div class="stall-card">
      <div class="sc-icon">🏪</div>
      <div><div class="sc-label">Stall Number</div><div class="sc-val"><?= htmlspecialchars($vendor['stall_number']) ?></div></div>
    </div>
    <div class="stall-card">
      <div class="sc-icon">⊞</div>
      <div><div class="sc-label">Section</div><div class="sc-val"><?= htmlspecialchars($vendor['section_name'] ?? '—') ?></div></div>
    </div>
    <div class="stall-card">
      <div class="sc-icon">💰</div>
      <div><div class="sc-label">Monthly Rent</div><div class="sc-val">₱<?= number_format($vendor['monthly_rent'], 2) ?></div></div>
    </div>
  </div>
  <?php endif; ?>

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
              <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
            </div>
            <div class="field">
              <label>Last Name</label>
              <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
            </div>
          </div>
          <div class="field">
            <label>Email Address</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
          </div>
          <div class="field">
            <label>Phone Number</label>
            <input type="text" name="phone" value="<?= htmlspecialchars($user['contact_information'] ?? '') ?>" placeholder="09XX XXX XXXX">
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
function showMsg(el, type, msg) {
  el.className = 'toast-msg ' + type;
  el.textContent = (type === 'success' ? '✓ ' : '✗ ') + msg;
  setTimeout(() => { el.className = 'toast-msg'; }, 4000);
}

$('#profileForm').on('submit', function(e) {
  e.preventDefault();
  const data = $(this).serialize() + '&action=update_profile';
  $.post('vendor_account.php', data, function(res) {
    showMsg(document.getElementById('profileMsg'), res.success ? 'success' : 'error', res.message);
  }, 'json');
});

$('#passwordForm').on('submit', function(e) {
  e.preventDefault();
  const data = $(this).serialize() + '&action=change_password';
  $.post('vendor_account.php', data, function(res) {
    showMsg(document.getElementById('passwordMsg'), res.success ? 'success' : 'error', res.message);
    if (res.success) $('#passwordForm')[0].reset();
  }, 'json');
});
</script>
</body>
</html>
