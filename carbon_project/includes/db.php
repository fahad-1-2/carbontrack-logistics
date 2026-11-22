<?php
// ============================================================
// includes/db.php
// Shared database connection — included in every page
// ============================================================

$host     = "localhost";
$user     = "root";
$password = "";               // XAMPP default = no password
$database = "carbon_logistics";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("
    <div style='font-family:monospace;padding:40px;background:#060a0e;color:#ff4444;font-size:16px;'>
        ❌ Database connection failed: " . $conn->connect_error . "<br><br>
        Make sure XAMPP MySQL is running and the database 'carbon_logistics' exists.
    </div>");
}