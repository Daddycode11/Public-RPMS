<?php
require_once __DIR__ . '/../includes/protected.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once '../config/database.php';

// --- AUTH CHECK ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}
if (empty($_SESSION['otp_verified'])) {
    header("Location: ../auth/otp_verify.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = $error = "";

// --- FETCH ADMIN DATA ---
$stmt = $pdo->prepare("
    SELECT first_name, last_name, email, image, two_factor_enabled, created_at
    FROM users
    WHERE id = ?
");
$stmt->execute([$user_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    die("Admin account not found.");
}

// --- HANDLE PROFILE UPDATE ---
if (isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $twoFA = 1; // Email OTP is required for every administrator login.

    $imageName = $admin['image'];

    if (!empty($_FILES['avatar']['name'])) {
        $uploadDir = "../uploads/avatars/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $imageName = 'admin_'.$user_id.'.'.$ext;
        if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir.$imageName)) {
            $error .= "Failed to upload avatar.";
        }
    }

    $stmt = $pdo->prepare("
        UPDATE users
        SET first_name=?, last_name=?, email=?, image=?, two_factor_enabled=?
        WHERE id=?
    ");
    $stmt->execute([$first_name, $last_name, $email, $imageName, $twoFA, $user_id]);

    $pdo->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?,?)")
        ->execute([$user_id, 'Profile updated']);

    $_SESSION['first_name'] = $first_name;
    $_SESSION['last_name'] = $last_name;
    $admin['first_name'] = $first_name;
    $admin['last_name'] = $last_name;
    $admin['email'] = $email;
    $admin['image'] = $imageName;
    $admin['two_factor_enabled'] = $twoFA;
    $success = "Profile updated successfully.";
}

// --- HANDLE PASSWORD CHANGE ---
if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $hashed = $stmt->fetchColumn();

    if (!password_verify($current, $hashed)) {
        $error = "Current password is incorrect.";
    } elseif ($new !== $confirm) {
        $error = "New passwords do not match.";
    } elseif (strlen($new) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->execute([$newHash, $user_id]);
        $pdo->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?,?)")
            ->execute([$user_id, 'Password changed']);
        $success = "Password changed successfully.";
    }
}

// --- FETCH RECENT ACTIVITY LOGS ---
try {
    $stmt = $pdo->prepare("SELECT action, created_at FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 6");
    $stmt->execute([$user_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $logs = [];
}

$initials = strtoupper(substr($admin['first_name'], 0, 1) . substr($admin['last_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Profile | RPMS</title>
<?php include __DIR__ . '/../includes/favicon.php'; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Fraunces:opsz,wght@9..144,700&display=swap" rel="stylesheet">

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

/* Alert toast */
.alert-toast {
  padding: 14px 18px; border-radius: var(--radius-sm);
  font-size: .88rem; font-weight: 500;
  display: flex; align-items: center; gap: 8px;
  animation: fadeUp .4s ease both;
}
@keyframes fadeUp { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
.alert-success { background: var(--green-light); color: var(--green-dark); border: 1px solid var(--green); }
.alert-error   { background: #fef2f2; color: var(--error); border: 1px solid #fecaca; }

/* Profile banner */
.profile-banner {
  background: linear-gradient(135deg, var(--ink) 0%, #1a3020 100%);
  border-radius: var(--radius);
  padding: 28px 32px;
  display: flex; align-items: center; gap: 20px;
}
.profile-avatar-wrap { position: relative; flex-shrink: 0; }
.profile-avatar {
  width: 80px; height: 80px; border-radius: 18px;
  object-fit: cover;
  border: 3px solid rgba(14,158,82,.4);
}
.profile-avatar-fallback {
  width: 80px; height: 80px; border-radius: 18px; flex-shrink: 0;
  background: linear-gradient(135deg, var(--green), var(--green-dark));
  color: #fff; font-family: 'Fraunces', serif; font-size: 1.8rem; font-weight: 700;
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
.profile-tag.info { background: rgba(255,255,255,.1); color: rgba(255,255,255,.6); }

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
.field input, .field select {
  width: 100%; padding: 11px 14px;
  border: 1.5px solid #e0e8e3; border-radius: 9px;
  font-family: inherit; font-size: .9rem; color: var(--ink);
  outline: none; transition: .2s; background: var(--white);
}
.field input:focus, .field select:focus { border-color: var(--green); box-shadow: 0 0 0 3px var(--green-glow); }
.field input::placeholder { color: #b0c4b8; }
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

/* Toggle switch */
.toggle-wrap { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
.toggle-switch { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
.toggle-switch input { display: none; }
.toggle-slider {
  position: absolute; inset: 0; cursor: pointer;
  background: #d0ddd5; border-radius: 12px; transition: .3s;
}
.toggle-slider::before {
  content: ""; position: absolute; left: 3px; top: 3px;
  width: 18px; height: 18px; border-radius: 50%;
  background: #fff; transition: .3s; box-shadow: 0 1px 3px rgba(0,0,0,.2);
}
.toggle-switch input:checked + .toggle-slider { background: var(--green); }
.toggle-switch input:checked + .toggle-slider::before { transform: translateX(20px); }
.toggle-label { font-size: .85rem; font-weight: 500; color: var(--ink-2); }
.toggle-sub { font-size: .75rem; color: var(--ink-3); }

/* Avatar upload */
.avatar-upload { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }
.avatar-upload-btn {
  padding: 8px 16px; border-radius: 8px;
  background: var(--cream); border: 1px solid var(--border);
  font-family: inherit; font-size: .82rem; font-weight: 600;
  color: var(--ink-2); cursor: pointer; transition: .2s;
}
.avatar-upload-btn:hover { background: var(--green-light); color: var(--green-dark); }

/* Buttons */
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

/* Password strength */
.strength-bar { height: 4px; border-radius: 2px; background: #edf2ee; margin-top: 6px; overflow: hidden; }
.strength-fill { height: 100%; border-radius: 2px; transition: width .3s, background .3s; }
.strength-text { font-size: .75rem; color: var(--ink-3); margin-top: 3px; }

/* Activity log */
.log-list { display: flex; flex-direction: column; }
.log-item { display: flex; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f0f4f1; }
.log-item:last-child { border-bottom: none; }
.log-dot { width: 9px; height: 9px; flex-shrink: 0; border-radius: 50%; background: var(--green); margin-top: 5px; box-shadow: 0 0 0 3px var(--green-light); }
.log-text { font-size: .84rem; color: var(--ink-2); line-height: 1.5; }
.log-time { font-size: .75rem; color: var(--ink-3); margin-top: 2px; }

@media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }
@media (max-width: 600px) { .field-row { grid-template-columns: 1fr; } .profile-banner { flex-direction: column; text-align: center; } .profile-tags { justify-content: center; } }
</style>
</head>
<body>

<?php include 'navbar.php'; ?>

<main class="rpms-main">
<div class="page-wrap">

  <!-- HEADER -->
  <div class="page-header">
    <h1>Profile Settings</h1>
    <p class="sub">Manage your admin account, security, and preferences.</p>
  </div>

  <!-- ALERTS -->
  <?php if ($success): ?>
    <div class="alert-toast alert-success">✓ <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert-toast alert-error">✗ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- PROFILE BANNER -->
  <div class="profile-banner">
    <div class="profile-avatar-wrap">
      <?php if ($admin['image']): ?>
        <img src="../uploads/avatars/<?= htmlspecialchars($admin['image']) ?>" class="profile-avatar" alt="">
      <?php else: ?>
        <div class="profile-avatar-fallback"><?= $initials ?></div>
      <?php endif; ?>
    </div>
    <div>
      <div class="profile-name"><?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?></div>
      <div class="profile-email"><?= htmlspecialchars($admin['email']) ?></div>
      <div class="profile-tags">
        <span class="profile-tag role">Administrator</span>
        <span class="profile-tag info">2FA Required</span>
        <span class="profile-tag info">Since <?= date('M Y', strtotime($admin['created_at'] ?? 'now')) ?></span>
      </div>
    </div>
  </div>

  <!-- FORMS ROW -->
  <div class="grid-2">

    <!-- Profile Info -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Personal Information</span>
      </div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data">

          <!-- Avatar upload -->
          <div class="avatar-upload">
            <?php if ($admin['image']): ?>
              <img src="../uploads/avatars/<?= htmlspecialchars($admin['image']) ?>" style="width:48px;height:48px;border-radius:12px;object-fit:cover;">
            <?php else: ?>
              <div style="width:48px;height:48px;border-radius:12px;background:var(--green-light);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--green-dark);"><?= $initials ?></div>
            <?php endif; ?>
            <div>
              <label class="avatar-upload-btn" for="avatarInput">Change Photo</label>
              <input type="file" id="avatarInput" name="avatar" accept="image/*" style="display:none">
              <div style="font-size:.75rem;color:var(--ink-3);margin-top:3px;">JPG, PNG. Max 2MB.</div>
            </div>
          </div>

          <div class="field-row">
            <div class="field">
              <label>First Name</label>
              <input type="text" name="first_name" value="<?= htmlspecialchars($admin['first_name']) ?>" required>
            </div>
            <div class="field">
              <label>Last Name</label>
              <input type="text" name="last_name" value="<?= htmlspecialchars($admin['last_name']) ?>" required>
            </div>
          </div>

          <div class="field">
            <label>Email Address</label>
            <input type="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required>
          </div>

          <!-- 2FA Toggle -->
          <div class="toggle-wrap">
            <label class="toggle-switch">
              <input type="checkbox" name="enable_2fa" checked disabled>
              <span class="toggle-slider"></span>
            </label>
            <div>
              <div class="toggle-label">Two-Factor Authentication</div>
              <div class="toggle-sub">Email OTP is required on every administrator login.</div>
            </div>
          </div>

          <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
        </form>
      </div>
    </div>

    <!-- Change Password -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Change Password</span>
      </div>
      <div class="card-body">
        <form method="POST">
          <div class="field">
            <label>Current Password</label>
            <input type="password" name="current_password" placeholder="Enter current password" required>
          </div>
          <div class="field">
            <label>New Password</label>
            <input type="password" name="new_password" id="newPassword" placeholder="Enter new password" required>
            <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
            <div class="strength-text" id="strengthText"></div>
          </div>
          <div class="field">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" placeholder="Re-enter new password" required>
          </div>
          <button type="submit" name="change_password" class="btn btn-warn">Update Password</button>
        </form>
      </div>
    </div>

  </div>

  <!-- ACTIVITY LOG -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Recent Activity</span>
      <a href="audit_logs.php" style="font-size:.82rem;color:var(--green);text-decoration:none;font-weight:600;">View all →</a>
    </div>
    <div class="card-body">
      <div class="log-list">
        <?php foreach ($logs as $l): ?>
        <div class="log-item">
          <div class="log-dot"></div>
          <div>
            <div class="log-text"><?= htmlspecialchars($l['action']) ?></div>
            <div class="log-time"><?= date('M d, Y h:i A', strtotime($l['created_at'])) ?></div>
          </div>
        </div>
        <?php endforeach ?>
        <?php if (empty($logs)): ?>
          <p style="font-size:.85rem;color:var(--ink-3);text-align:center;padding:20px 0">No recent activity.</p>
        <?php endif ?>
      </div>
    </div>
  </div>

</div>
</main>

<script>
// Password strength indicator
const pass = document.getElementById('newPassword');
const fill = document.getElementById('strengthFill');
const text = document.getElementById('strengthText');

pass.addEventListener('input', () => {
  let v = pass.value, s = 0;
  if (v.length >= 6) s++;
  if (/[A-Z]/.test(v)) s++;
  if (/[0-9]/.test(v)) s++;
  if (/[^A-Za-z0-9]/.test(v)) s++;

  const colors = ['#dc2626', '#f59e0b', '#f59e0b', '#0e9e52'];
  const labels = ['Weak', 'Fair', 'Good', 'Strong'];

  fill.style.width = (s * 25) + '%';
  fill.style.background = colors[s - 1] || '#edf2ee';
  text.textContent = labels[s - 1] || '';
  text.style.color = colors[s - 1] || 'var(--ink-3)';
});
</script>
</body>
</html>
