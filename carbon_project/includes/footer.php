<?php
// ============================================================
// includes/footer.php
// Shared footer — included at bottom of every page
// ============================================================
?>

<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <div class="nav-logo-icon">🌿</div>
            <span class="footer-name">Carbon<strong>Track</strong></span>
            <p class="footer-tagline">Making logistics emissions transparent, measurable, and actionable.</p>
        </div>

        <div class="footer-links">
            <div class="footer-col">
                <div class="footer-col-title">Platform</div>
                <a href="<?= $base_path ?? '' ?>index.php">Home</a>
                <a href="<?= $base_path ?? '' ?>calculator.php">CO₂ Calculator</a>
                <a href="<?= $base_path ?? '' ?>register.php">Sign Up</a>
                <a href="<?= $base_path ?? '' ?>login.php">Login</a>
            </div>
            <div class="footer-col">
                <div class="footer-col-title">Project</div>
                <a href="#">Database Schema</a>
                <a href="#">SQL Source</a>
                <a href="#">Documentation</a>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <span>© <?= date('Y') ?> CarbonTrack Logistics — Pure SQL + PHP Project</span>
        <span>Database: <strong>carbon_logistics</strong> · MySQL</span>
    </div>
</footer>

</body>
</html>