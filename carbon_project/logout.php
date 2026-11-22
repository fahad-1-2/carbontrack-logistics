<?php
// ============================================================
// logout.php — Destroys session and redirects to home
// ============================================================
session_start();
session_destroy();
header('Location: index.php');
exit;
?>