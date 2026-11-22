<?php
// ============================================================
// includes/header.php
// Shared navigation bar — included at top of every page
// Call session_start() before including this file
// ============================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'CarbonTrack Logistics' ?></title>
    <link rel="stylesheet" href="<?= $css_path ?? '' ?>assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>

<nav class="navbar">
    <a href="<?= $base_path ?? '' ?>index.php" class="nav-logo">
        <div class="nav-logo-icon">🌿</div>
        <span>Carbon<strong>Track</strong></span>
    </a>

    <div class="nav-links">
        <a href="<?= $base_path ?? '' ?>index.php">Home</a>
        <a href="<?= $base_path ?? '' ?>calculator.php">Calculator</a>

        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?= $base_path ?? '' ?>dashboard.php">Dashboard</a>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="<?= $base_path ?? '' ?>admin/carbon_tracker.php" class="nav-admin">Admin</a>
            <?php endif; ?>
            <a href="<?= $base_path ?? '' ?>logout.php" class="nav-btn nav-btn-outline">Logout</a>
        <?php else: ?>
            <a href="<?= $base_path ?? '' ?>login.php">Login</a>
            <a href="<?= $base_path ?? '' ?>register.php" class="nav-btn">Sign Up Free</a>
        <?php endif; ?>
    </div>
</nav>