<?php

require_once 'includes/auth_check.php';

require_once 'includes/db.php';

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {

        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        if (!$username || !$email) {
            $error = 'Username and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
            
            $stmt = $db->prepare('SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?');
            $stmt->execute([$username, $email, $userId]);
            if ($stmt->fetch()) {
                $error = 'Username or email already taken by another account.';
            } else {
                
                $avatarName = $user['avatar'];
                if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $avatarDir = __DIR__ . '/uploads/avatars/';
                    if (!is_dir($avatarDir)) mkdir($avatarDir, 0755, true);
                    $finfo    = new finfo(FILEINFO_MIME_TYPE);
                    $mime     = $finfo->file($_FILES['avatar']['tmp_name']);
                    $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!in_array($mime, $allowed)) {
                        $error = 'Avatar must be an image (JPEG, PNG, GIF, WebP).';
                    } elseif ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
                        $error = 'Avatar must be under 2MB.';
                    } else {
                        $ext        = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                        $avatarName = 'avatar_' . $userId . '_' . uniqid() . '.' . $ext;
                        if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $avatarDir . $avatarName)) {
                            // Move failed (e.g. disk full or permissions) — keep the existing avatar.
                            $error = 'Failed to save avatar. Please try again.';
                            $avatarName = $user['avatar'];
                        } else {
                            // Only remove the old file once we know the new one is safely stored.
                            if ($user['avatar'] && file_exists($avatarDir . $user['avatar'])) {
                                unlink($avatarDir . $user['avatar']);
                            }
                        }
                    }
                }

                if (!$error) {
                    $stmt = $db->prepare('UPDATE users SET username = ?, email = ?, avatar = ? WHERE id = ?');
                    $stmt->execute([$username, $email, $avatarName, $userId]);
                    $_SESSION['username'] = $username;
                    $success = 'Profile updated successfully!';
                    
                    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch();
                }
            }
        }

    } elseif ($action === 'change_password') {

        $current  = $_POST['current_password'] ?? '';
        $newPw    = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (!$current || !$newPw || !$confirm) {
            $error = 'All password fields are required.';
        } elseif (!password_verify($current, $user['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($newPw) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($newPw !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $hash = password_hash($newPw, PASSWORD_DEFAULT);
            $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);
            $success = 'Password changed successfully!';
        }

    }
}

$stmt = $db->prepare('SELECT COUNT(*) as total FROM tasks WHERE user_id = ?');
$stmt->execute([$userId]);
$totalTasks = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM tasks WHERE user_id = ? AND status = 'completed'");
$stmt->execute([$userId]);
$completedTasks = $stmt->fetch()['cnt'];

$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM tasks WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$userId]);
$pendingTasks = $stmt->fetch()['cnt'];

$avatarSrc = $user['avatar']
    ? 'uploads/avatars/' . htmlspecialchars($user['avatar'])
    : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile – TaskFlow</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="app-body">
<?php include 'includes/nav.php'; ?>
<main class="main-content">

    <header class="page-header">
        <div>
            <h2 class="page-greeting">My Profile</h2>
            <p class="page-date">Manage your account settings</p>
        </div>
    </header>

    <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom:1rem"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success" style="margin-bottom:1rem"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Stats row -->
    <div class="stats-grid" style="margin-bottom:2rem">
        <div class="stat-card">
            <div class="stat-icon stat-icon-blue">⊞</div>
            <div class="stat-info">
                <div class="stat-value"><?= $totalTasks ?></div>
                <div class="stat-label">Total Tasks</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-green">✓</div>
            <div class="stat-info">
                <div class="stat-value"><?= $completedTasks ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-yellow">◷</div>
            <div class="stat-info">
                <div class="stat-value"><?= $pendingTasks ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-purple">📅</div>
            <div class="stat-info">
                <div class="stat-value"><?= date('M Y', strtotime($user['created_at'])) ?></div>
                <div class="stat-label">Member Since</div>
            </div>
        </div>
    </div>

    <div class="profile-grid">

        <!-- Profile Info -->
        <div class="profile-card">
            <h3 class="profile-section-title">Profile Information</h3>
            <form method="POST" enctype="multipart/form-data" class="auth-form" style="margin-top:1rem">
                <input type="hidden" name="action" value="update_profile">

                <div class="avatar-upload-area">
                    <div class="avatar-preview" id="avatarPreview">
                        <?php if ($avatarSrc): ?>
                            <img src="<?= $avatarSrc ?>" alt="Avatar" id="avatarImg">
                        <?php else: ?>
                            <div class="avatar-placeholder" id="avatarPlaceholder">
                                <?= strtoupper(substr($user['username'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="btn btn-ghost" style="cursor:pointer">
                            📷 Change Photo
                            <input type="file" name="avatar" accept="image/*" style="display:none" id="avatarInput">
                        </label>
                        <p style="font-size:.75rem;color:var(--text-muted);margin-top:.25rem">JPG, PNG, GIF or WebP · max 2 MB</p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-input"
                           value="<?= htmlspecialchars($user['username']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email address</label>
                    <input type="email" name="email" class="form-input"
                           value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>

        <!-- Change Password -->
        <div class="profile-card">
            <h3 class="profile-section-title">Change Password</h3>
            <form method="POST" class="auth-form" style="margin-top:1rem">
                <input type="hidden" name="action" value="change_password">
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-input"
                           placeholder="Enter current password" required>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-input"
                           placeholder="Min. 6 characters" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-input"
                           placeholder="Repeat new password" required>
                </div>
                <button type="submit" class="btn btn-primary">Update Password</button>
            </form>
        </div>

    </div>

</main>

<script>

document.getElementById('avatarInput').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        const preview = document.getElementById('avatarPreview');
        preview.innerHTML = `<img src="${e.target.result}" alt="Avatar" style="width:80px;height:80px;border-radius:50%;object-fit:cover">`;
    };
    reader.readAsDataURL(file);
});
</script>
</body>
</html>
