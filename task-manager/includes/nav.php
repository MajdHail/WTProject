<?php

$currentPage = basename($_SERVER['PHP_SELF'], '.php');

$root = strpos($_SERVER['PHP_SELF'], '/auth/') !== false ? '../' : '';

$avatarSrc = null;
if (!empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/db.php';
    $db   = getDB();
    $stmt = $db->prepare('SELECT avatar FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row  = $stmt->fetch();
    if ($row && $row['avatar']) {
        $avatarSrc = $root . 'uploads/avatars/' . htmlspecialchars($row['avatar']);
    }
}

?>

<aside class="sidebar">

    <div class="sidebar-brand">

        <div class="brand-icon">✓</div>

        <span class="brand-name">TaskFlow</span>

    </div>

    <nav class="sidebar-nav">

        <a href="<?= $root ?>dashboard.php" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">

            <span class="nav-icon">⊞</span>

            <span>Dashboard</span>

        </a>

        <a href="<?= $root ?>calendar.php" class="nav-link <?= $currentPage === 'calendar' ? 'active' : '' ?>">

            <span class="nav-icon">◫</span>

            <span>Calendar</span>

        </a>

        <a href="<?= $root ?>profile.php" class="nav-link <?= $currentPage === 'profile' ? 'active' : '' ?>">

            <span class="nav-icon">👤</span>

            <span>Profile</span>

        </a>

    </nav>

    <div class="sidebar-footer">

        <div class="user-info">

            <div class="user-avatar">
                <?php if ($avatarSrc): ?>
                    <img src="<?= $avatarSrc ?>" alt="avatar" style="width:100%;height:100%;border-radius:50%;object-fit:cover">
                <?php else: ?>
                    <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?>
                <?php endif; ?>
            </div>

            <div class="user-details">

                <div class="user-name"><?= htmlspecialchars($_SESSION['username'] ?? '') ?></div>

                <div class="user-role">Member</div>

            </div>

        </div>

        <a href="<?= $root ?>auth/logout.php" class="logout-btn" title="Logout">⏻</a>

    </div>

</aside>
