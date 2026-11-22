<?php
// ============================================================
//   CARBON FOOTPRINT LOGISTICS TRACKER — PHP Frontend
//   Database: carbon_logistics (MySQL via XAMPP)
//   Open this file at: http://localhost/carbon_tracker.php
// ============================================================

// --- DATABASE CONNECTION ---
$host     = "localhost";
$user     = "root";
$password = "";           // XAMPP default has no password
$database = "carbon_logistics";

$conn = new mysqli($host, $user, $password, $database);

// Stop and show error if connection fails
if ($conn->connect_error) {
    die("<div style='font-family:monospace;padding:40px;background:#0a0a0a;color:#ff4444;font-size:18px;'>
        ❌ Database Connection Failed: " . $conn->connect_error . "
        <br><br>Make sure XAMPP MySQL is running and the database 'carbon_logistics' exists.
    </div>");
}

// ============================================================
//   HANDLE FORM SUBMISSIONS (INSERT new data)
//   All POST requests are handled here before page renders
// ============================================================

$success_msg = "";
$error_msg   = "";

// --- INSERT: New Customer ---
if (isset($_POST['add_customer'])) {
    $name     = $conn->real_escape_string($_POST['company_name']);
    $country  = $conn->real_escape_string($_POST['country']);
    $industry = $conn->real_escape_string($_POST['industry_type']);
    $email    = $conn->real_escape_string($_POST['contact_email']);
    $budget   = floatval($_POST['carbon_budget_kg']);
    $date     = $conn->real_escape_string($_POST['joined_date']);

    $sql = "INSERT INTO customers (company_name, country, industry_type, contact_email, carbon_budget_kg, joined_date)
            VALUES ('$name', '$country', '$industry', '$email', $budget, '$date')";

    if ($conn->query($sql)) $success_msg = "✅ Customer '$name' added successfully!";
    else $error_msg = "❌ Error: " . $conn->error;
}

// --- INSERT: New Shipment ---
if (isset($_POST['add_shipment'])) {
    $cid      = intval($_POST['customer_id']);
    $orig     = intval($_POST['origin_location_id']);
    $dest     = intval($_POST['dest_location_id']);
    $weight   = floatval($_POST['total_weight_kg']);
    $cargo    = $conn->real_escape_string($_POST['cargo_type']);
    $date     = $conn->real_escape_string($_POST['shipment_date']);
    $deadline = $conn->real_escape_string($_POST['delivery_deadline']);
    $priority = $conn->real_escape_string($_POST['priority_level']);

    $sql = "INSERT INTO shipments (customer_id, origin_location_id, dest_location_id, total_weight_kg, cargo_type, shipment_date, delivery_deadline, status, priority_level)
            VALUES ($cid, $orig, $dest, $weight, '$cargo', '$date', '$deadline', 'Pending', '$priority')";

    if ($conn->query($sql)) $success_msg = "✅ Shipment added successfully! ID: " . $conn->insert_id;
    else $error_msg = "❌ Error: " . $conn->error;
}

// --- INSERT: New Vehicle ---
if (isset($_POST['add_vehicle'])) {
    $name    = $conn->real_escape_string($_POST['vehicle_name']);
    $type    = $conn->real_escape_string($_POST['vehicle_type']);
    $fuel    = $conn->real_escape_string($_POST['fuel_type']);
    $payload = floatval($_POST['max_payload_kg']);
    $year    = intval($_POST['year_manufactured']);

    $sql = "INSERT INTO vehicles (vehicle_name, vehicle_type, fuel_type, max_payload_kg, year_manufactured, is_active)
            VALUES ('$name', '$type', '$fuel', $payload, $year, 1)";

    if ($conn->query($sql)) $success_msg = "✅ Vehicle '$name' added successfully!";
    else $error_msg = "❌ Error: " . $conn->error;
}

// --- UPDATE: Shipment Status ---
if (isset($_POST['update_status'])) {
    $sid    = intval($_POST['shipment_id']);
    $status = $conn->real_escape_string($_POST['new_status']);

    $sql = "UPDATE shipments SET status = '$status' WHERE shipment_id = $sid";

    if ($conn->query($sql)) $success_msg = "✅ Shipment #$sid status updated to '$status'";
    else $error_msg = "❌ Error: " . $conn->error;
}

// ============================================================
//   FETCH ALL DATA FOR DISPLAY
// ============================================================

// Fetch all customers
$customers_result = $conn->query("SELECT * FROM customers ORDER BY customer_id");

// Fetch all shipments with customer name and location names
$shipments_result = $conn->query("
    SELECT s.*, c.company_name,
           ol.city AS from_city, dl.city AS to_city
    FROM shipments s
    JOIN customers c   ON s.customer_id = c.customer_id
    JOIN locations ol  ON s.origin_location_id = ol.location_id
    JOIN locations dl  ON s.dest_location_id   = dl.location_id
    ORDER BY s.shipment_id DESC
");

// Fetch all vehicles
$vehicles_result = $conn->query("SELECT * FROM vehicles ORDER BY vehicle_id");

// Fetch emission logs with full details
$emission_result = $conn->query("
    SELECT el.*, v.vehicle_name, v.vehicle_type,
           ol.city AS from_city, dl.city AS to_city,
           c.company_name
    FROM emission_logs el
    JOIN routes r      ON el.route_id    = r.route_id
    JOIN vehicles v    ON el.vehicle_id  = v.vehicle_id
    JOIN locations ol  ON r.origin_location_id = ol.location_id
    JOIN locations dl  ON r.dest_location_id   = dl.location_id
    JOIN shipments s   ON el.shipment_id = s.shipment_id
    JOIN customers c   ON s.customer_id  = c.customer_id
    ORDER BY el.log_id DESC
");

// Fetch alerts
$alerts_result = $conn->query("
    SELECT a.*, c.company_name
    FROM alerts a
    JOIN customers c ON a.customer_id = c.customer_id
    ORDER BY a.triggered_at DESC
");

// Fetch goal status view
$goals_result = $conn->query("SELECT * FROM v_goal_status ORDER BY status_color");

// Fetch fleet efficiency view
$fleet_result = $conn->query("SELECT * FROM v_fleet_efficiency ORDER BY efficiency_rank");

// Fetch summary stats for dashboard cards
$total_co2    = $conn->query("SELECT ROUND(SUM(co2_emitted_kg),2) AS total FROM emission_logs")->fetch_assoc()['total'];
$total_ships  = $conn->query("SELECT COUNT(*) AS total FROM shipments")->fetch_assoc()['total'];
$total_cust   = $conn->query("SELECT COUNT(*) AS total FROM customers")->fetch_assoc()['total'];
$over_budget  = $conn->query("SELECT COUNT(*) AS total FROM goal_performance WHERE met_goal = 'NO'")->fetch_assoc()['total'];

// Fetch dropdown data for forms
$customers_dd  = $conn->query("SELECT customer_id, company_name FROM customers");
$locations_dd  = $conn->query("SELECT location_id, city, country FROM locations");

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Carbon Footprint Logistics Tracker</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<style>
/* ============================================================
   ROOT VARIABLES & RESET
============================================================ */
:root {
    --bg:         #080c10;
    --bg2:        #0d1117;
    --bg3:        #111820;
    --border:     #1e2d3d;
    --green:      #00e676;
    --green-dim:  #00e67622;
    --green-glow: #00e67644;
    --red:        #ff4444;
    --red-dim:    #ff444422;
    --yellow:     #ffd600;
    --yellow-dim: #ffd60022;
    --blue:       #40c4ff;
    --blue-dim:   #40c4ff18;
    --text:       #e8edf2;
    --text-dim:   #6b8299;
    --text-muted: #3d5166;
    --accent:     #00e676;
    --card-bg:    #0d1117;
    --radius:     12px;
    --radius-sm:  8px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html { scroll-behavior: smooth; }

body {
    font-family: 'DM Mono', monospace;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    overflow-x: hidden;
}

/* ============================================================
   BACKGROUND GRID EFFECT
============================================================ */
body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image:
        linear-gradient(var(--border) 1px, transparent 1px),
        linear-gradient(90deg, var(--border) 1px, transparent 1px);
    background-size: 40px 40px;
    opacity: 0.25;
    pointer-events: none;
    z-index: 0;
}

/* ============================================================
   HEADER / NAV
============================================================ */
header {
    position: sticky;
    top: 0;
    z-index: 100;
    background: rgba(8,12,16,0.92);
    backdrop-filter: blur(16px);
    border-bottom: 1px solid var(--border);
    padding: 0 40px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 64px;
}

.logo {
    display: flex;
    align-items: center;
    gap: 12px;
}

.logo-icon {
    width: 36px; height: 36px;
    background: var(--green);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
}

.logo-text {
    font-family: 'Syne', sans-serif;
    font-weight: 800;
    font-size: 16px;
    letter-spacing: -0.3px;
    color: var(--text);
}

.logo-text span { color: var(--green); }

nav {
    display: flex;
    gap: 4px;
}

nav a {
    color: var(--text-dim);
    text-decoration: none;
    font-size: 12px;
    font-weight: 500;
    padding: 6px 14px;
    border-radius: 6px;
    transition: all 0.2s;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

nav a:hover {
    color: var(--green);
    background: var(--green-dim);
}

.live-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    color: var(--green);
    letter-spacing: 1px;
    text-transform: uppercase;
}

.live-dot {
    width: 8px; height: 8px;
    background: var(--green);
    border-radius: 50%;
    animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50%       { opacity: 0.4; transform: scale(0.8); }
}

/* ============================================================
   MAIN LAYOUT
============================================================ */
main {
    position: relative;
    z-index: 1;
    max-width: 1400px;
    margin: 0 auto;
    padding: 40px 40px 80px;
}

section {
    margin-bottom: 60px;
    animation: fadeUp 0.5s ease both;
}

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ============================================================
   SECTION HEADERS
============================================================ */
.section-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}

.section-tag {
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--green);
    background: var(--green-dim);
    border: 1px solid var(--green-glow);
    padding: 4px 10px;
    border-radius: 4px;
}

.section-title {
    font-family: 'Syne', sans-serif;
    font-size: 22px;
    font-weight: 700;
    color: var(--text);
}

.section-line {
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, var(--border), transparent);
}

/* ============================================================
   DASHBOARD STAT CARDS
============================================================ */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 60px;
}

.stat-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 28px 24px;
    position: relative;
    overflow: hidden;
    transition: border-color 0.3s, transform 0.2s;
}

.stat-card:hover {
    border-color: var(--green);
    transform: translateY(-2px);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    background: var(--green);
    transform: scaleX(0);
    transition: transform 0.3s;
    transform-origin: left;
}

.stat-card:hover::before { transform: scaleX(1); }

.stat-label {
    font-size: 10px;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 12px;
}

.stat-value {
    font-family: 'Syne', sans-serif;
    font-size: 36px;
    font-weight: 800;
    color: var(--green);
    line-height: 1;
    margin-bottom: 8px;
}

.stat-sub {
    font-size: 11px;
    color: var(--text-dim);
}

.stat-icon {
    position: absolute;
    top: 24px; right: 24px;
    font-size: 28px;
    opacity: 0.15;
}

/* ============================================================
   DATA TABLES
============================================================ */
.table-wrapper {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
}

.table-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    background: var(--bg3);
}

.table-name {
    font-size: 11px;
    color: var(--text-dim);
    letter-spacing: 1px;
    text-transform: uppercase;
}

.row-count {
    font-size: 11px;
    color: var(--text-muted);
    background: var(--border);
    padding: 3px 10px;
    border-radius: 20px;
}

.scroll-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

thead th {
    background: #0a1019;
    color: var(--text-dim);
    font-size: 10px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    font-weight: 500;
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
}

tbody tr {
    border-bottom: 1px solid #0d1520;
    transition: background 0.15s;
}

tbody tr:last-child { border-bottom: none; }

tbody tr:hover { background: var(--blue-dim); }

tbody td {
    padding: 12px 16px;
    color: var(--text);
    vertical-align: middle;
    white-space: nowrap;
}

/* ============================================================
   BADGES / PILLS
============================================================ */
.badge {
    display: inline-block;
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    padding: 3px 10px;
    border-radius: 20px;
}

.badge-green  { background: var(--green-dim);  color: var(--green);  border: 1px solid var(--green-glow); }
.badge-red    { background: var(--red-dim);    color: var(--red);    border: 1px solid #ff444433; }
.badge-yellow { background: var(--yellow-dim); color: var(--yellow); border: 1px solid #ffd60033; }
.badge-blue   { background: var(--blue-dim);   color: var(--blue);   border: 1px solid #40c4ff22; }
.badge-gray   { background: #1e2d3d22;         color: var(--text-dim); border: 1px solid var(--border); }

/* CO2 value highlight */
.co2-val {
    font-family: 'Syne', sans-serif;
    font-weight: 700;
    color: var(--green);
}

/* ============================================================
   FORMS
============================================================ */
.forms-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
}

.form-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    transition: border-color 0.3s;
}

.form-card:hover { border-color: #1e3a4a; }

.form-header {
    padding: 18px 24px;
    border-bottom: 1px solid var(--border);
    background: var(--bg3);
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-icon { font-size: 18px; }

.form-title {
    font-family: 'Syne', sans-serif;
    font-size: 15px;
    font-weight: 700;
    color: var(--text);
}

.form-body {
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.field.full { grid-column: 1 / -1; }

label {
    font-size: 10px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--text-muted);
    font-weight: 500;
}

input, select, textarea {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    color: var(--text);
    font-family: 'DM Mono', monospace;
    font-size: 13px;
    padding: 10px 14px;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
    width: 100%;
    appearance: none;
}

input:focus, select:focus, textarea:focus {
    border-color: var(--green);
    box-shadow: 0 0 0 3px var(--green-dim);
}

select option { background: var(--bg2); }

/* ============================================================
   BUTTONS
============================================================ */
.btn {
    font-family: 'Syne', sans-serif;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.5px;
    padding: 11px 22px;
    border-radius: var(--radius-sm);
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
}

.btn-primary {
    background: var(--green);
    color: #000;
}

.btn-primary:hover {
    background: #00ff88;
    transform: translateY(-1px);
    box-shadow: 0 4px 20px var(--green-glow);
}

.btn-danger {
    background: var(--red-dim);
    color: var(--red);
    border: 1px solid #ff444433;
}

.btn-danger:hover {
    background: var(--red);
    color: #fff;
}

.btn-sm {
    font-size: 11px;
    padding: 6px 14px;
}

/* ============================================================
   ALERT / NOTIFICATION MESSAGES
============================================================ */
.flash {
    padding: 14px 20px;
    border-radius: var(--radius-sm);
    font-size: 13px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: fadeUp 0.3s ease;
}

.flash-success {
    background: var(--green-dim);
    border: 1px solid var(--green-glow);
    color: var(--green);
}

.flash-error {
    background: var(--red-dim);
    border: 1px solid #ff444433;
    color: var(--red);
}

/* ============================================================
   ALERT CARDS (database alerts table)
============================================================ */
.alert-cards {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.alert-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-left: 3px solid var(--red);
    border-radius: var(--radius-sm);
    padding: 16px 20px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
    transition: background 0.2s;
}

.alert-card:hover { background: var(--red-dim); }

.alert-card.resolved { border-left-color: var(--text-muted); opacity: 0.5; }

.alert-dot {
    width: 8px; height: 8px; min-width: 8px;
    border-radius: 50%;
    background: var(--red);
    margin-top: 4px;
}

.alert-card.resolved .alert-dot { background: var(--text-muted); }

.alert-type {
    font-size: 10px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--red);
    margin-bottom: 4px;
}

.alert-msg {
    font-size: 13px;
    color: var(--text);
    line-height: 1.5;
}

.alert-meta {
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 6px;
}

/* ============================================================
   GOAL STATUS CARDS
============================================================ */
.goals-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}

.goal-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 24px;
    position: relative;
    overflow: hidden;
    transition: transform 0.2s;
}

.goal-card:hover { transform: translateY(-2px); }

.goal-card.GREEN { border-top: 3px solid var(--green); }
.goal-card.YELLOW { border-top: 3px solid var(--yellow); }
.goal-card.RED { border-top: 3px solid var(--red); }

.goal-company {
    font-family: 'Syne', sans-serif;
    font-size: 15px;
    font-weight: 700;
    margin-bottom: 12px;
}

.goal-bar-wrap {
    background: var(--border);
    border-radius: 4px;
    height: 6px;
    margin: 12px 0;
    overflow: hidden;
}

.goal-bar {
    height: 100%;
    border-radius: 4px;
    transition: width 1s ease;
}

.GREEN .goal-bar  { background: var(--green); }
.YELLOW .goal-bar { background: var(--yellow); }
.RED .goal-bar    { background: var(--red); }

.goal-numbers {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: var(--text-muted);
}

.goal-status-badge {
    position: absolute;
    top: 20px; right: 20px;
    font-size: 18px;
}

/* ============================================================
   UPDATE STATUS FORM (inline in table)
============================================================ */
.status-form {
    display: flex;
    gap: 8px;
    align-items: center;
}

.status-form select {
    padding: 5px 10px;
    font-size: 12px;
    border-radius: 6px;
    min-width: 130px;
}

/* ============================================================
   FOOTER
============================================================ */
footer {
    position: relative;
    z-index: 1;
    border-top: 1px solid var(--border);
    padding: 24px 40px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 11px;
    color: var(--text-muted);
    letter-spacing: 0.5px;
}

footer span { color: var(--green); }

/* ============================================================
   RESPONSIVE
============================================================ */
@media (max-width: 1100px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .goals-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 760px) {
    header { padding: 0 20px; }
    main   { padding: 24px 20px 60px; }
    .stats-grid  { grid-template-columns: 1fr; }
    .forms-grid  { grid-template-columns: 1fr; }
    .goals-grid  { grid-template-columns: 1fr; }
    nav { display: none; }
    .form-row { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- ============================================================
     HEADER
============================================================ -->
<header>
    <div class="logo">
        <div class="logo-icon">🌿</div>
        <div class="logo-text">Carbon<span>Track</span> Logistics</div>
    </div>

    <nav>
        <a href="#dashboard">Dashboard</a>
        <a href="#shipments">Shipments</a>
        <a href="#emissions">Emissions</a>
        <a href="#fleet">Fleet</a>
        <a href="#goals">Goals</a>
        <a href="#alerts">Alerts</a>
        <a href="#manage">Manage</a>
    </nav>

    <div class="live-badge">
        <div class="live-dot"></div>
        Live DB
    </div>
</header>

<!-- ============================================================
     MAIN CONTENT
============================================================ -->
<main>

    <!-- Flash Messages -->
    <?php if ($success_msg): ?>
        <div class="flash flash-success"><?= $success_msg ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="flash flash-error"><?= $error_msg ?></div>
    <?php endif; ?>

    <!-- ========================================================
         SECTION 1: DASHBOARD STATS
    ======================================================== -->
    <section id="dashboard">
        <div class="section-header">
            <div class="section-tag">Overview</div>
            <div class="section-title">Dashboard</div>
            <div class="section-line"></div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">🌍</div>
                <div class="stat-label">Total CO₂ Emitted</div>
                <div class="stat-value"><?= number_format($total_co2, 1) ?></div>
                <div class="stat-sub">kilograms across all shipments</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-label">Total Shipments</div>
                <div class="stat-value"><?= $total_ships ?></div>
                <div class="stat-sub">logged in the system</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🏢</div>
                <div class="stat-label">Active Customers</div>
                <div class="stat-value"><?= $total_cust ?></div>
                <div class="stat-sub">companies being tracked</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⚠️</div>
                <div class="stat-label">Budget Breaches</div>
                <div class="stat-value" style="color:var(--red)"><?= $over_budget ?></div>
                <div class="stat-sub">customers over CO₂ limit</div>
            </div>
        </div>
    </section>

    <!-- ========================================================
         SECTION 2: ALL CUSTOMERS TABLE
    ======================================================== -->
    <section id="customers">
        <div class="section-header">
            <div class="section-tag">Table</div>
            <div class="section-title">Customers</div>
            <div class="section-line"></div>
        </div>

        <div class="table-wrapper">
            <div class="table-toolbar">
                <span class="table-name">customers</span>
                <span class="row-count"><?= $customers_result->num_rows ?> rows</span>
            </div>
            <div class="scroll-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Company</th>
                            <th>Country</th>
                            <th>Industry</th>
                            <th>Email</th>
                            <th>CO₂ Budget (kg)</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $customers_result->fetch_assoc()): ?>
                        <tr>
                            <td style="color:var(--text-muted)">#<?= $row['customer_id'] ?></td>
                            <td style="font-family:'Syne',sans-serif;font-weight:600"><?= htmlspecialchars($row['company_name']) ?></td>
                            <td><?= htmlspecialchars($row['country']) ?></td>
                            <td><span class="badge badge-blue"><?= htmlspecialchars($row['industry_type']) ?></span></td>
                            <td style="color:var(--text-dim)"><?= htmlspecialchars($row['contact_email']) ?></td>
                            <td><span class="co2-val"><?= number_format($row['carbon_budget_kg'], 0) ?></span> kg</td>
                            <td style="color:var(--text-dim)"><?= $row['joined_date'] ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- ========================================================
         SECTION 3: SHIPMENTS TABLE
    ======================================================== -->
    <section id="shipments">
        <div class="section-header">
            <div class="section-tag">Table</div>
            <div class="section-title">Shipments</div>
            <div class="section-line"></div>
        </div>

        <div class="table-wrapper">
            <div class="table-toolbar">
                <span class="table-name">shipments — with live status update</span>
                <span class="row-count"><?= $shipments_result->num_rows ?> rows</span>
            </div>
            <div class="scroll-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Weight (kg)</th>
                            <th>Cargo</th>
                            <th>Date</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Update</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $shipments_result->fetch_assoc()):
                        $status_class = match($row['status']) {
                            'Delivered'  => 'badge-green',
                            'In-Transit' => 'badge-yellow',
                            'Pending'    => 'badge-blue',
                            'Cancelled'  => 'badge-red',
                            default      => 'badge-gray'
                        };
                        $priority_class = match($row['priority_level']) {
                            'Critical' => 'badge-red',
                            'Express'  => 'badge-yellow',
                            default    => 'badge-gray'
                        };
                    ?>
                        <tr>
                            <td style="color:var(--text-muted)">#<?= $row['shipment_id'] ?></td>
                            <td style="font-family:'Syne',sans-serif;font-weight:600"><?= htmlspecialchars($row['company_name']) ?></td>
                            <td><?= htmlspecialchars($row['from_city']) ?></td>
                            <td><?= htmlspecialchars($row['to_city']) ?></td>
                            <td><span class="co2-val"><?= number_format($row['total_weight_kg'], 0) ?></span></td>
                            <td><?= htmlspecialchars($row['cargo_type']) ?></td>
                            <td style="color:var(--text-dim)"><?= $row['shipment_date'] ?></td>
                            <td><span class="badge <?= $priority_class ?>"><?= $row['priority_level'] ?></span></td>
                            <td><span class="badge <?= $status_class ?>"><?= $row['status'] ?></span></td>
                            <td>
                                <!-- Inline status update form -->
                                <form method="POST" style="display:inline">
                                    <div class="status-form">
                                        <input type="hidden" name="shipment_id" value="<?= $row['shipment_id'] ?>">
                                        <select name="new_status">
                                            <option value="Pending"    <?= $row['status']=='Pending'    ? 'selected':'' ?>>Pending</option>
                                            <option value="In-Transit" <?= $row['status']=='In-Transit' ? 'selected':'' ?>>In-Transit</option>
                                            <option value="Delivered"  <?= $row['status']=='Delivered'  ? 'selected':'' ?>>Delivered</option>
                                            <option value="Cancelled"  <?= $row['status']=='Cancelled'  ? 'selected':'' ?>>Cancelled</option>
                                        </select>
                                        <button type="submit" name="update_status" class="btn btn-primary btn-sm">→</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- ========================================================
         SECTION 4: EMISSION LOGS TABLE
    ======================================================== -->
    <section id="emissions">
        <div class="section-header">
            <div class="section-tag">Calculations</div>
            <div class="section-title">Emission Logs</div>
            <div class="section-line"></div>
        </div>

        <div class="table-wrapper">
            <div class="table-toolbar">
                <span class="table-name">emission_logs — CO₂ = Distance × Weight × Rate</span>
                <span class="row-count"><?= $emission_result->num_rows ?> rows</span>
            </div>
            <div class="scroll-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Log ID</th>
                            <th>Company</th>
                            <th>Route</th>
                            <th>Vehicle</th>
                            <th>Distance (km)</th>
                            <th>Weight (kg)</th>
                            <th>CO₂ Emitted (kg)</th>
                            <th>Offset (kg)</th>
                            <th>Calculated</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $emission_result->fetch_assoc()): ?>
                        <tr>
                            <td style="color:var(--text-muted)">#<?= $row['log_id'] ?></td>
                            <td style="font-family:'Syne',sans-serif;font-weight:600"><?= htmlspecialchars($row['company_name']) ?></td>
                            <td><?= htmlspecialchars($row['from_city']) ?> → <?= htmlspecialchars($row['to_city']) ?></td>
                            <td><span class="badge badge-blue"><?= htmlspecialchars($row['vehicle_type']) ?></span></td>
                            <td><?= number_format($row['distance_km'], 0) ?></td>
                            <td><?= number_format($row['weight_kg'], 0) ?></td>
                            <td><span class="co2-val"><?= number_format($row['co2_emitted_kg'], 2) ?></span> kg</td>
                            <td style="color:var(--green)"><?= number_format($row['carbon_offset_kg'], 2) ?></td>
                            <td style="color:var(--text-muted)"><?= date('d M Y', strtotime($row['calculated_at'])) ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- ========================================================
         SECTION 5: FLEET EFFICIENCY
    ======================================================== -->
    <section id="fleet">
        <div class="section-header">
            <div class="section-tag">View</div>
            <div class="section-title">Fleet Efficiency Ranking</div>
            <div class="section-line"></div>
        </div>

        <div class="table-wrapper">
            <div class="table-toolbar">
                <span class="table-name">v_fleet_efficiency — ranked by emission rate (lower = greener)</span>
                <span class="row-count"><?= $fleet_result->num_rows ?> vehicles</span>
            </div>
            <div class="scroll-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Vehicle</th>
                            <th>Type</th>
                            <th>Fuel</th>
                            <th>CO₂/km/kg Rate</th>
                            <th>Total CO₂ Produced</th>
                            <th>Times Used</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $fleet_result->fetch_assoc()):
                        $rank = $row['efficiency_rank'];
                        $medal = $rank == 1 ? '🥇' : ($rank == 2 ? '🥈' : ($rank == 3 ? '🥉' : "#$rank"));
                    ?>
                        <tr>
                            <td style="font-family:'Syne',sans-serif;font-size:18px"><?= $medal ?></td>
                            <td style="font-family:'Syne',sans-serif;font-weight:600"><?= htmlspecialchars($row['vehicle_name']) ?></td>
                            <td><span class="badge badge-blue"><?= $row['vehicle_type'] ?></span></td>
                            <td><?= $row['fuel_type'] ?></td>
                            <td style="color:var(--green);font-family:'Syne',sans-serif;font-weight:700"><?= $row['co2_per_km_per_kg'] ?></td>
                            <td><span class="co2-val"><?= number_format($row['total_co2_produced'] ?? 0, 2) ?></span> kg</td>
                            <td><?= $row['times_used'] ?? 0 ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- ========================================================
         SECTION 6: CARBON GOALS STATUS
    ======================================================== -->
    <section id="goals">
        <div class="section-header">
            <div class="section-tag">View</div>
            <div class="section-title">Carbon Goal Status</div>
            <div class="section-line"></div>
        </div>

        <div class="goals-grid">
        <?php while ($row = $goals_result->fetch_assoc()):
            $color  = $row['status_color'];
            $pct    = $row['goal_kg'] > 0 ? min(100, ($row['actual_co2_kg'] / $row['goal_kg']) * 100) : 0;
            $emoji  = $color == 'GREEN' ? '✅' : ($color == 'YELLOW' ? '⚡' : '🚨');
        ?>
            <div class="goal-card <?= $color ?>">
                <div class="goal-status-badge"><?= $emoji ?></div>
                <div class="goal-company"><?= htmlspecialchars($row['company_name']) ?></div>
                <div style="font-size:11px;color:var(--text-muted)">
                    <?= $row['target_month'] ?>/<?= $row['target_year'] ?> — <?= $row['met_goal'] == 'YES' ? 'GOAL MET' : 'GOAL MISSED' ?>
                </div>
                <div class="goal-bar-wrap">
                    <div class="goal-bar" style="width:<?= $pct ?>%"></div>
                </div>
                <div class="goal-numbers">
                    <span>Actual: <strong><?= number_format($row['actual_co2_kg'], 1) ?> kg</strong></span>
                    <span>Limit: <?= number_format($row['goal_kg'], 0) ?> kg</span>
                </div>
                <?php if ($row['variance_kg'] > 0): ?>
                    <div style="margin-top:10px;font-size:11px;color:var(--red)">
                        ↑ Over by <?= number_format($row['variance_kg'], 1) ?> kg
                    </div>
                <?php else: ?>
                    <div style="margin-top:10px;font-size:11px;color:var(--green)">
                        ↓ Under by <?= number_format(abs($row['variance_kg']), 1) ?> kg
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
        </div>
    </section>

    <!-- ========================================================
         SECTION 7: ALERTS
    ======================================================== -->
    <section id="alerts">
        <div class="section-header">
            <div class="section-tag">Warnings</div>
            <div class="section-title">System Alerts</div>
            <div class="section-line"></div>
        </div>

        <div class="alert-cards">
        <?php
        $has_alerts = false;
        while ($row = $alerts_result->fetch_assoc()):
            $has_alerts = true;
        ?>
            <div class="alert-card <?= $row['is_resolved'] ? 'resolved' : '' ?>">
                <div class="alert-dot"></div>
                <div style="flex:1">
                    <div class="alert-type"><?= htmlspecialchars($row['alert_type']) ?> — <?= htmlspecialchars($row['company_name']) ?></div>
                    <div class="alert-msg"><?= htmlspecialchars($row['message']) ?></div>
                    <div class="alert-meta">
                        Triggered: <?= date('d M Y, H:i', strtotime($row['triggered_at'])) ?>
                        <?php if ($row['shipment_id']): ?>· Shipment #<?= $row['shipment_id'] ?><?php endif; ?>
                        · <?= $row['is_resolved'] ? '<span style="color:var(--green)">Resolved</span>' : '<span style="color:var(--red)">Active</span>' ?>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
        <?php if (!$has_alerts): ?>
            <div style="text-align:center;padding:40px;color:var(--text-muted);font-size:13px;">
                ✅ No alerts — all customers within budget
            </div>
        <?php endif; ?>
        </div>
    </section>

    <!-- ========================================================
         SECTION 8: DATA ENTRY FORMS
    ======================================================== -->
    <section id="manage">
        <div class="section-header">
            <div class="section-tag">Insert</div>
            <div class="section-title">Add New Data</div>
            <div class="section-line"></div>
        </div>

        <div class="forms-grid">

            <!-- FORM 1: Add New Customer -->
            <div class="form-card">
                <div class="form-header">
                    <div class="form-icon">🏢</div>
                    <div class="form-title">Add New Customer</div>
                </div>
                <form method="POST" class="form-body">
                    <div class="form-row">
                        <div class="field">
                            <label>Company Name</label>
                            <input type="text" name="company_name" placeholder="e.g. FastFreight Co" required>
                        </div>
                        <div class="field">
                            <label>Country</label>
                            <input type="text" name="country" placeholder="e.g. Pakistan" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label>Industry Type</label>
                            <select name="industry_type" required>
                                <option value="">Select industry</option>
                                <option>Logistics</option>
                                <option>Retail</option>
                                <option>Electronics</option>
                                <option>Food</option>
                                <option>Manufacturing</option>
                                <option>E-Commerce</option>
                                <option>Chemicals</option>
                                <option>Other</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>CO₂ Monthly Budget (kg)</label>
                            <input type="number" name="carbon_budget_kg" placeholder="e.g. 1000" step="0.01" min="1" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label>Contact Email</label>
                            <input type="email" name="contact_email" placeholder="ops@company.com" required>
                        </div>
                        <div class="field">
                            <label>Joined Date</label>
                            <input type="date" name="joined_date" required>
                        </div>
                    </div>
                    <button type="submit" name="add_customer" class="btn btn-primary">+ Add Customer</button>
                </form>
            </div>

            <!-- FORM 2: Add New Shipment -->
            <div class="form-card">
                <div class="form-header">
                    <div class="form-icon">📦</div>
                    <div class="form-title">Add New Shipment</div>
                </div>
                <form method="POST" class="form-body">
                    <div class="field full">
                        <label>Customer</label>
                        <select name="customer_id" required>
                            <option value="">Select customer</option>
                            <?php
                            // Re-fetch customers for dropdown
                            $cdd = $conn->query("SELECT customer_id, company_name FROM customers");
                            while ($r = $cdd->fetch_assoc()):
                            ?>
                            <option value="<?= $r['customer_id'] ?>"><?= htmlspecialchars($r['company_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label>Origin City</label>
                            <select name="origin_location_id" required>
                                <option value="">Select origin</option>
                                <?php
                                $ldd = $conn->query("SELECT location_id, city, country FROM locations");
                                while ($r = $ldd->fetch_assoc()):
                                ?>
                                <option value="<?= $r['location_id'] ?>"><?= htmlspecialchars($r['city']) ?>, <?= $r['country'] ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Destination City</label>
                            <select name="dest_location_id" required>
                                <option value="">Select destination</option>
                                <?php
                                $ldd2 = $conn->query("SELECT location_id, city, country FROM locations");
                                while ($r = $ldd2->fetch_assoc()):
                                ?>
                                <option value="<?= $r['location_id'] ?>"><?= htmlspecialchars($r['city']) ?>, <?= $r['country'] ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label>Total Weight (kg)</label>
                            <input type="number" name="total_weight_kg" placeholder="e.g. 500" step="0.01" min="0.01" required>
                        </div>
                        <div class="field">
                            <label>Cargo Type</label>
                            <select name="cargo_type" required>
                                <option value="">Select type</option>
                                <option>Electronics</option>
                                <option>Food</option>
                                <option>General</option>
                                <option>Chemicals</option>
                                <option>Manufacturing</option>
                                <option>Textiles</option>
                                <option>Machinery</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label>Shipment Date</label>
                            <input type="date" name="shipment_date" required>
                        </div>
                        <div class="field">
                            <label>Delivery Deadline</label>
                            <input type="date" name="delivery_deadline">
                        </div>
                    </div>
                    <div class="field full">
                        <label>Priority Level</label>
                        <select name="priority_level" required>
                            <option value="Standard">Standard</option>
                            <option value="Express">Express</option>
                            <option value="Critical">Critical</option>
                        </select>
                    </div>
                    <button type="submit" name="add_shipment" class="btn btn-primary">+ Add Shipment</button>
                </form>
            </div>

            <!-- FORM 3: Add New Vehicle -->
            <div class="form-card">
                <div class="form-header">
                    <div class="form-icon">🚛</div>
                    <div class="form-title">Add New Vehicle</div>
                </div>
                <form method="POST" class="form-body">
                    <div class="field full">
                        <label>Vehicle Name</label>
                        <input type="text" name="vehicle_name" placeholder="e.g. Boeing 777F" required>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label>Vehicle Type</label>
                            <select name="vehicle_type" required>
                                <option value="">Select type</option>
                                <option>Plane</option>
                                <option>Truck</option>
                                <option>Van</option>
                                <option>Ship</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Fuel Type</label>
                            <select name="fuel_type" required>
                                <option value="">Select fuel</option>
                                <option>Diesel</option>
                                <option>Petrol</option>
                                <option>Electric</option>
                                <option>Jet-A</option>
                                <option>Heavy Fuel</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label>Max Payload (kg)</label>
                            <input type="number" name="max_payload_kg" placeholder="e.g. 25000" step="0.01" min="1" required>
                        </div>
                        <div class="field">
                            <label>Year Manufactured</label>
                            <input type="number" name="year_manufactured" placeholder="e.g. 2020" min="1950" max="2030">
                        </div>
                    </div>
                    <button type="submit" name="add_vehicle" class="btn btn-primary">+ Add Vehicle</button>
                </form>
            </div>

            <!-- FORM 4: Vehicles table display -->
            <div class="form-card">
                <div class="form-header">
                    <div class="form-icon">🚀</div>
                    <div class="form-title">Current Fleet</div>
                </div>
                <div style="padding:0">
                    <div class="scroll-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Fuel</th>
                                    <th>Payload</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $vr = $conn->query("SELECT * FROM vehicles ORDER BY vehicle_id");
                            while ($row = $vr->fetch_assoc()):
                            ?>
                                <tr>
                                    <td style="color:var(--text-muted)">#<?= $row['vehicle_id'] ?></td>
                                    <td style="font-size:12px"><?= htmlspecialchars($row['vehicle_name']) ?></td>
                                    <td><span class="badge badge-blue"><?= $row['vehicle_type'] ?></span></td>
                                    <td style="color:var(--text-dim);font-size:12px"><?= $row['fuel_type'] ?></td>
                                    <td style="font-size:12px"><?= number_format($row['max_payload_kg'],0) ?> kg</td>
                                    <td>
                                        <?php if ($row['is_active']): ?>
                                            <span class="badge badge-green">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-red">Retired</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div><!-- end forms-grid -->
    </section>

</main>

<!-- ============================================================
     FOOTER
============================================================ -->
<footer>
    <div>Carbon Footprint Logistics Tracker — Pure SQL + PHP Project</div>
    <div>Database: <span>carbon_logistics</span> · MySQL via XAMPP · <?= date('Y') ?></div>
</footer>

<?php $conn->close(); ?>
</body>
</html>