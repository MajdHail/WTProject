<?php

$currentPage = basename($_SERVER['PHP_SELF'], '.php');

$root = strpos($_SERVER['PHP_SELF'], '/auth/') !== false ? '../' : '';

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

    </nav>

    <div class="sidebar-footer">

        <div class="user-info">

            <div class="user-avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?></div>

            <div class="user-details">

                <div class="user-name"><?= htmlspecialchars($_SESSION['username'] ?? '') ?></div>

                <div class="user-role">Member</div>

            </div>

        </div>

        <a href="<?= $root ?>auth/logout.php" class="logout-btn" title="Logout">⏻</a>

    </div>

</aside>
