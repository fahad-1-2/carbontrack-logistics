<?php
// ============================================================
// admin/carbon_tracker.php — Admin Panel
// PROTECTED: Only accessible to users with role = 'admin'
// ============================================================
session_start();

// Security check — must be logged in AND be an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../includes/db.php';

$page_title = "Admin Panel — CarbonTrack";
$css_path   = "../";
$base_path  = "../";

// ============================================================
// HANDLE FORM SUBMISSIONS
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
            VALUES ('$name','$country','$industry','$email',$budget,'$date')";

    if ($conn->query($sql)) $success_msg = "✅ Customer '$name' added!";
    else $error_msg = "❌ " . $conn->error;
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

    $sql = "INSERT INTO shipments (customer_id,origin_location_id,dest_location_id,total_weight_kg,cargo_type,shipment_date,delivery_deadline,status,priority_level)
            VALUES ($cid,$orig,$dest,$weight,'$cargo','$date','$deadline','Pending','$priority')";

    if ($conn->query($sql)) $success_msg = "✅ Shipment added! ID: " . $conn->insert_id;
    else $error_msg = "❌ " . $conn->error;
}

// --- INSERT: New Vehicle ---
if (isset($_POST['add_vehicle'])) {
    $name    = $conn->real_escape_string($_POST['vehicle_name']);
    $type    = $conn->real_escape_string($_POST['vehicle_type']);
    $fuel    = $conn->real_escape_string($_POST['fuel_type']);
    $payload = floatval($_POST['max_payload_kg']);
    $year    = intval($_POST['year_manufactured']);

    $sql = "INSERT INTO vehicles (vehicle_name,vehicle_type,fuel_type,max_payload_kg,year_manufactured,is_active)
            VALUES ('$name','$type','$fuel',$payload,$year,1)";

    if ($conn->query($sql)) $success_msg = "✅ Vehicle '$name' added!";
    else $error_msg = "❌ " . $conn->error;
}

// --- UPDATE: Shipment Status ---
if (isset($_POST['update_status'])) {
    $sid    = intval($_POST['shipment_id']);
    $status = $conn->real_escape_string($_POST['new_status']);
    if ($conn->query("UPDATE shipments SET status='$status' WHERE shipment_id=$sid"))
        $success_msg = "✅ Shipment #$sid updated to '$status'";
    else $error_msg = "❌ " . $conn->error;
}

// ============================================================
// FETCH ALL DATA
// ============================================================
$customers_result = $conn->query("SELECT * FROM customers ORDER BY customer_id");

$shipments_result = $conn->query("
    SELECT s.*, c.company_name, ol.city AS from_city, dl.city AS to_city
    FROM shipments s
    JOIN customers c  ON s.customer_id = c.customer_id
    JOIN locations ol ON s.origin_location_id = ol.location_id
    JOIN locations dl ON s.dest_location_id   = dl.location_id
    ORDER BY s.shipment_id DESC
");

$emission_result = $conn->query("
    SELECT el.*, v.vehicle_name, v.vehicle_type,
           ol.city AS from_city, dl.city AS to_city, c.company_name
    FROM emission_logs el
    JOIN routes r     ON el.route_id    = r.route_id
    JOIN vehicles v   ON el.vehicle_id  = v.vehicle_id
    JOIN locations ol ON r.origin_location_id = ol.location_id
    JOIN locations dl ON r.dest_location_id   = dl.location_id
    JOIN shipments s  ON el.shipment_id = s.shipment_id
    JOIN customers c  ON s.customer_id  = c.customer_id
    ORDER BY el.log_id DESC
");

$alerts_result = $conn->query("
    SELECT a.*, c.company_name FROM alerts a
    JOIN customers c ON a.customer_id = c.customer_id
    ORDER BY a.triggered_at DESC
");

$goals_result  = $conn->query("SELECT * FROM v_goal_status ORDER BY status_color");
$fleet_result  = $conn->query("SELECT * FROM v_fleet_efficiency ORDER BY efficiency_rank");

// Dashboard summary numbers
$total_co2   = $conn->query("SELECT ROUND(SUM(co2_emitted_kg),2) AS t FROM emission_logs")->fetch_assoc()['t'] ?? 0;
$total_ships = $conn->query("SELECT COUNT(*) AS t FROM shipments")->fetch_assoc()['t'];
$total_cust  = $conn->query("SELECT COUNT(*) AS t FROM customers")->fetch_assoc()['t'];
$over_budget = $conn->query("SELECT COUNT(*) AS t FROM goal_performance WHERE met_goal='NO'")->fetch_assoc()['t'];
$total_users = $conn->query("SELECT COUNT(*) AS t FROM users")->fetch_assoc()['t'];

include '../includes/header.php';
?>

<main>

    <!-- Admin Banner -->
    <div class="admin-banner">
        🔐 Admin Panel — Logged in as <?= htmlspecialchars($_SESSION['user_name']) ?>
        <span style="margin-left:auto;font-size:11px;color:var(--text-muted)">Only admins can access this page</span>
    </div>

    <!-- Flash Messages -->
    <?php if ($success_msg): ?><div class="flash flash-success"><?= $success_msg ?></div><?php endif; ?>
    <?php if ($error_msg):   ?><div class="flash flash-error"><?= $error_msg ?></div><?php endif; ?>

    <!-- ======================================================
         DASHBOARD STATS
    ====================================================== -->
    <section>
        <div class="section-head">
            <div class="section-tag">Overview</div>
            <div class="section-title">Dashboard</div>
            <div class="section-line"></div>
        </div>
        <div class="stats-row" style="grid-template-columns:repeat(5,1fr)">
            <div class="stat-card">
                <div class="stat-icon">🌍</div>
                <div class="stat-label">Total CO₂</div>
                <div class="stat-value"><?= number_format($total_co2,1) ?></div>
                <div class="stat-sub">kg emitted</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-label">Shipments</div>
                <div class="stat-value"><?= $total_ships ?></div>
                <div class="stat-sub">total logged</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🏢</div>
                <div class="stat-label">Customers</div>
                <div class="stat-value"><?= $total_cust ?></div>
                <div class="stat-sub">companies</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-label">Users</div>
                <div class="stat-value"><?= $total_users ?></div>
                <div class="stat-sub">registered accounts</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⚠️</div>
                <div class="stat-label">Budget Breaches</div>
                <div class="stat-value" style="color:var(--red)"><?= $over_budget ?></div>
                <div class="stat-sub">over CO₂ limit</div>
            </div>
        </div>
    </section>

    <!-- ======================================================
         CUSTOMERS TABLE
    ====================================================== -->
    <section>
        <div class="section-head">
            <div class="section-tag">Table</div>
            <div class="section-title">Customers</div>
            <div class="section-line"></div>
        </div>
        <div class="table-wrap">
            <div class="table-bar">
                <span class="table-label">customers</span>
                <span class="row-badge"><?= $customers_result->num_rows ?> rows</span>
            </div>
            <div class="scroll-x">
                <table>
                    <thead>
                        <tr><th>ID</th><th>Company</th><th>Country</th><th>Industry</th><th>Email</th><th>CO₂ Budget</th><th>Joined</th></tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $customers_result->fetch_assoc()): ?>
                        <tr>
                            <td class="id-val">#<?= $row['customer_id'] ?></td>
                            <td style="font-weight:600"><?= htmlspecialchars($row['company_name']) ?></td>
                            <td><?= htmlspecialchars($row['country']) ?></td>
                            <td><span class="badge badge-blue"><?= htmlspecialchars($row['industry_type']) ?></span></td>
                            <td style="color:var(--text-dim)"><?= htmlspecialchars($row['contact_email']) ?></td>
                            <td><span class="co2-val"><?= number_format($row['carbon_budget_kg'],0) ?></span> kg/mo</td>
                            <td style="color:var(--text-dim)"><?= $row['joined_date'] ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- ======================================================
         SHIPMENTS TABLE
    ====================================================== -->
    <section>
        <div class="section-head">
            <div class="section-tag">Table</div>
            <div class="section-title">Shipments</div>
            <div class="section-line"></div>
        </div>
        <div class="table-wrap">
            <div class="table-bar">
                <span class="table-label">shipments — update status inline</span>
                <span class="row-badge"><?= $shipments_result->num_rows ?> rows</span>
            </div>
            <div class="scroll-x">
                <table>
                    <thead>
                        <tr><th>ID</th><th>Customer</th><th>From</th><th>To</th><th>Weight</th><th>Cargo</th><th>Date</th><th>Priority</th><th>Status</th><th>Update</th></tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $shipments_result->fetch_assoc()):
                        $sc = match($row['status']) {
                            'Delivered'  => 'badge-green',
                            'In-Transit' => 'badge-yellow',
                            'Pending'    => 'badge-blue',
                            default      => 'badge-red'
                        };
                        $pc = match($row['priority_level']) {
                            'Critical' => 'badge-red',
                            'Express'  => 'badge-yellow',
                            default    => 'badge-gray'
                        };
                    ?>
                        <tr>
                            <td class="id-val">#<?= $row['shipment_id'] ?></td>
                            <td style="font-weight:600"><?= htmlspecialchars($row['company_name']) ?></td>
                            <td><?= htmlspecialchars($row['from_city']) ?></td>
                            <td><?= htmlspecialchars($row['to_city']) ?></td>
                            <td><span class="co2-val"><?= number_format($row['total_weight_kg'],0) ?></span> kg</td>
                            <td><?= htmlspecialchars($row['cargo_type']) ?></td>
                            <td style="color:var(--text-dim)"><?= $row['shipment_date'] ?></td>
                            <td><span class="badge <?= $pc ?>"><?= $row['priority_level'] ?></span></td>
                            <td><span class="badge <?= $sc ?>"><?= $row['status'] ?></span></td>
                            <td>
                                <form method="POST" style="display:inline">
                                    <div class="status-form">
                                        <input type="hidden" name="shipment_id" value="<?= $row['shipment_id'] ?>">
                                        <select name="new_status">
                                            <option value="Pending"    <?= $row['status']=='Pending'    ?'selected':'' ?>>Pending</option>
                                            <option value="In-Transit" <?= $row['status']=='In-Transit' ?'selected':'' ?>>In-Transit</option>
                                            <option value="Delivered"  <?= $row['status']=='Delivered'  ?'selected':'' ?>>Delivered</option>
                                            <option value="Cancelled"  <?= $row['status']=='Cancelled'  ?'selected':'' ?>>Cancelled</option>
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

    <!-- ======================================================
         EMISSION LOGS TABLE
    ====================================================== -->
    <section>
        <div class="section-head">
            <div class="section-tag">Calculations</div>
            <div class="section-title">Emission Logs</div>
            <div class="section-line"></div>
        </div>
        <div class="table-wrap">
            <div class="table-bar">
                <span class="table-label">emission_logs — CO₂ = Distance × Weight × Rate</span>
                <span class="row-badge"><?= $emission_result->num_rows ?> rows</span>
            </div>
            <div class="scroll-x">
                <table>
                    <thead>
                        <tr><th>Log ID</th><th>Company</th><th>Route</th><th>Vehicle</th><th>Distance</th><th>Weight</th><th>CO₂ (kg)</th><th>Offset</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $emission_result->fetch_assoc()): ?>
                        <tr>
                            <td class="id-val">#<?= $row['log_id'] ?></td>
                            <td style="font-weight:600"><?= htmlspecialchars($row['company_name']) ?></td>
                            <td><?= htmlspecialchars($row['from_city']) ?> → <?= htmlspecialchars($row['to_city']) ?></td>
                            <td><span class="badge badge-blue"><?= $row['vehicle_type'] ?></span></td>
                            <td><?= number_format($row['distance_km'],0) ?> km</td>
                            <td><?= number_format($row['weight_kg'],0) ?> kg</td>
                            <td><span class="co2-val"><?= number_format($row['co2_emitted_kg'],2) ?></span></td>
                            <td style="color:var(--green)"><?= number_format($row['carbon_offset_kg'],2) ?></td>
                            <td style="color:var(--text-dim)"><?= date('d M Y', strtotime($row['calculated_at'])) ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- ======================================================
         FLEET EFFICIENCY VIEW
    ====================================================== -->
    <section>
        <div class="section-head">
            <div class="section-tag">View</div>
            <div class="section-title">Fleet Efficiency Ranking</div>
            <div class="section-line"></div>
        </div>
        <div class="table-wrap">
            <div class="table-bar">
                <span class="table-label">v_fleet_efficiency — lower rate = greener vehicle</span>
            </div>
            <div class="scroll-x">
                <table>
                    <thead>
                        <tr><th>Rank</th><th>Vehicle</th><th>Type</th><th>Fuel</th><th>CO₂/km/kg</th><th>Total CO₂</th><th>Used</th></tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $fleet_result->fetch_assoc()):
                        $rank = $row['efficiency_rank'];
                        $medal = $rank==1?'🥇':($rank==2?'🥈':($rank==3?'🥉':"#$rank"));
                    ?>
                        <tr>
                            <td style="font-size:18px"><?= $medal ?></td>
                            <td style="font-weight:600"><?= htmlspecialchars($row['vehicle_name']) ?></td>
                            <td><span class="badge badge-blue"><?= $row['vehicle_type'] ?></span></td>
                            <td><?= $row['fuel_type'] ?></td>
                            <td><span class="co2-val"><?= $row['co2_per_km_per_kg'] ?></span></td>
                            <td><span class="co2-val"><?= number_format($row['total_co2_produced']??0,2) ?></span> kg</td>
                            <td><?= $row['times_used']??0 ?>×</td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- ======================================================
         CARBON GOAL STATUS
    ====================================================== -->
    <section>
        <div class="section-head">
            <div class="section-tag">View</div>
            <div class="section-title">Carbon Goal Status</div>
            <div class="section-line"></div>
        </div>
        <div class="goals-grid">
        <?php while ($row = $goals_result->fetch_assoc()):
            $color = $row['status_color'];
            $pct   = $row['goal_kg'] > 0 ? min(100, ($row['actual_co2_kg'] / $row['goal_kg']) * 100) : 0;
            $emoji = $color=='GREEN'?'✅':($color=='YELLOW'?'⚡':'🚨');
        ?>
            <div class="goal-card <?= $color ?>">
                <div class="goal-badge-icon"><?= $emoji ?></div>
                <div class="goal-company"><?= htmlspecialchars($row['company_name']) ?></div>
                <div style="font-size:11px;color:var(--text-muted)"><?= $row['target_month'] ?>/<?= $row['target_year'] ?> — <?= $row['met_goal']=='YES'?'MET':'MISSED' ?></div>
                <div class="goal-bar-wrap"><div class="goal-bar" style="width:<?= $pct ?>%"></div></div>
                <div class="goal-nums">
                    <span>Actual: <strong><?= number_format($row['actual_co2_kg'],1) ?> kg</strong></span>
                    <span>Limit: <?= number_format($row['goal_kg'],0) ?> kg</span>
                </div>
                <div style="margin-top:8px;font-size:11px;color:<?= $row['variance_kg']>0?'var(--red)':'var(--green)' ?>">
                    <?= $row['variance_kg']>0 ? '↑ Over by '.number_format($row['variance_kg'],1).' kg' : '↓ Under by '.number_format(abs($row['variance_kg']),1).' kg' ?>
                </div>
            </div>
        <?php endwhile; ?>
        </div>
    </section>

    <!-- ======================================================
         ALERTS
    ====================================================== -->
    <section>
        <div class="section-head">
            <div class="section-tag">Warnings</div>
            <div class="section-title">System Alerts</div>
            <div class="section-line"></div>
        </div>
        <div class="alert-list">
        <?php
        $has = false;
        while ($row = $alerts_result->fetch_assoc()):
            $has = true;
        ?>
            <div class="alert-item <?= $row['is_resolved']?'resolved':'' ?>">
                <div class="alert-dot"></div>
                <div>
                    <div class="alert-type"><?= htmlspecialchars($row['alert_type']) ?> — <?= htmlspecialchars($row['company_name']) ?></div>
                    <div class="alert-msg"><?= htmlspecialchars($row['message']) ?></div>
                    <div class="alert-meta">
                        <?= date('d M Y, H:i', strtotime($row['triggered_at'])) ?>
                        <?php if ($row['shipment_id']): ?>· Shipment #<?= $row['shipment_id'] ?><?php endif; ?>
                        · <?= $row['is_resolved']?'<span style="color:var(--green)">Resolved</span>':'<span style="color:var(--red)">Active</span>' ?>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
        <?php if (!$has): ?>
            <div style="text-align:center;padding:40px;color:var(--text-muted);font-size:14px">✅ No alerts</div>
        <?php endif; ?>
        </div>
    </section>

    <!-- ======================================================
         DATA ENTRY FORMS
    ====================================================== -->
    <section>
        <div class="section-head">
            <div class="section-tag">Insert</div>
            <div class="section-title">Add New Data</div>
            <div class="section-line"></div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">

            <!-- Add Customer -->
            <div class="card">
                <div class="card-header"><span style="font-size:18px">🏢</span><span class="card-title">Add New Customer</span></div>
                <div class="card-body">
                    <form method="POST" style="display:flex;flex-direction:column;gap:14px">
                        <div class="form-row">
                            <div class="field"><label>Company Name</label><input type="text" name="company_name" placeholder="Company name" required></div>
                            <div class="field"><label>Country</label><input type="text" name="country" placeholder="Country" required></div>
                        </div>
                        <div class="form-row">
                            <div class="field">
                                <label>Industry</label>
                                <select name="industry_type" required>
                                    <option value="">Select</option>
                                    <option>Logistics</option><option>Retail</option><option>Electronics</option>
                                    <option>Food</option><option>Manufacturing</option><option>E-Commerce</option><option>Other</option>
                                </select>
                            </div>
                            <div class="field"><label>CO₂ Budget (kg/mo)</label><input type="number" name="carbon_budget_kg" placeholder="1000" step="0.01" min="1" required></div>
                        </div>
                        <div class="form-row">
                            <div class="field"><label>Email</label><input type="email" name="contact_email" placeholder="ops@company.com" required></div>
                            <div class="field"><label>Joined Date</label><input type="date" name="joined_date" required></div>
                        </div>
                        <button type="submit" name="add_customer" class="btn btn-primary">+ Add Customer</button>
                    </form>
                </div>
            </div>

            <!-- Add Shipment -->
            <div class="card">
                <div class="card-header"><span style="font-size:18px">📦</span><span class="card-title">Add New Shipment</span></div>
                <div class="card-body">
                    <form method="POST" style="display:flex;flex-direction:column;gap:14px">
                        <div class="field">
                            <label>Customer</label>
                            <select name="customer_id" required>
                                <option value="">Select customer</option>
                                <?php $cdd=$conn->query("SELECT customer_id,company_name FROM customers"); while($r=$cdd->fetch_assoc()): ?>
                                <option value="<?= $r['customer_id'] ?>"><?= htmlspecialchars($r['company_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-row">
                            <div class="field">
                                <label>Origin City</label>
                                <select name="origin_location_id" required>
                                    <option value="">Select</option>
                                    <?php $ldd=$conn->query("SELECT location_id,city,country FROM locations"); while($r=$ldd->fetch_assoc()): ?>
                                    <option value="<?= $r['location_id'] ?>"><?= htmlspecialchars($r['city']) ?>, <?= $r['country'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Destination City</label>
                                <select name="dest_location_id" required>
                                    <option value="">Select</option>
                                    <?php $ldd2=$conn->query("SELECT location_id,city,country FROM locations"); while($r=$ldd2->fetch_assoc()): ?>
                                    <option value="<?= $r['location_id'] ?>"><?= htmlspecialchars($r['city']) ?>, <?= $r['country'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="field"><label>Weight (kg)</label><input type="number" name="total_weight_kg" placeholder="500" step="0.01" min="0.01" required></div>
                            <div class="field">
                                <label>Cargo Type</label>
                                <select name="cargo_type" required>
                                    <option value="">Select</option>
                                    <option>Electronics</option><option>Food</option><option>General</option>
                                    <option>Chemicals</option><option>Manufacturing</option><option>Textiles</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="field"><label>Shipment Date</label><input type="date" name="shipment_date" required></div>
                            <div class="field"><label>Deadline</label><input type="date" name="delivery_deadline"></div>
                        </div>
                        <div class="field">
                            <label>Priority</label>
                            <select name="priority_level" required>
                                <option value="Standard">Standard</option>
                                <option value="Express">Express</option>
                                <option value="Critical">Critical</option>
                            </select>
                        </div>
                        <button type="submit" name="add_shipment" class="btn btn-primary">+ Add Shipment</button>
                    </form>
                </div>
            </div>

            <!-- Add Vehicle -->
            <div class="card">
                <div class="card-header"><span style="font-size:18px">🚛</span><span class="card-title">Add New Vehicle</span></div>
                <div class="card-body">
                    <form method="POST" style="display:flex;flex-direction:column;gap:14px">
                        <div class="field"><label>Vehicle Name</label><input type="text" name="vehicle_name" placeholder="e.g. Boeing 777F" required></div>
                        <div class="form-row">
                            <div class="field">
                                <label>Type</label>
                                <select name="vehicle_type" required>
                                    <option value="">Select</option>
                                    <option>Plane</option><option>Truck</option><option>Van</option><option>Ship</option>
                                </select>
                            </div>
                            <div class="field">
                                <label>Fuel</label>
                                <select name="fuel_type" required>
                                    <option value="">Select</option>
                                    <option>Diesel</option><option>Petrol</option><option>Electric</option><option>Jet-A</option><option>Heavy Fuel</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="field"><label>Max Payload (kg)</label><input type="number" name="max_payload_kg" placeholder="25000" step="0.01" min="1" required></div>
                            <div class="field"><label>Year</label><input type="number" name="year_manufactured" placeholder="2020" min="1950" max="2030"></div>
                        </div>
                        <button type="submit" name="add_vehicle" class="btn btn-primary">+ Add Vehicle</button>
                    </form>
                </div>
            </div>

            <!-- Registered Users -->
            <div class="card">
                <div class="card-header"><span style="font-size:18px">👥</span><span class="card-title">Registered Users</span></div>
                <div class="scroll-x">
                    <table>
                        <thead>
                            <tr><th>ID</th><th>Name</th><th>Email</th><th>Company</th><th>Role</th><th>Joined</th></tr>
                        </thead>
                        <tbody>
                        <?php
                        $users_r = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
                        while ($ur = $users_r->fetch_assoc()):
                        ?>
                            <tr>
                                <td class="id-val">#<?= $ur['user_id'] ?></td>
                                <td style="font-weight:600"><?= htmlspecialchars($ur['full_name']) ?></td>
                                <td style="color:var(--text-dim);font-size:12px"><?= htmlspecialchars($ur['email']) ?></td>
                                <td><?= htmlspecialchars($ur['company_name'] ?? '—') ?></td>
                                <td>
                                    <span class="badge <?= $ur['role']==='admin'?'badge-yellow':'badge-blue' ?>">
                                        <?= $ur['role'] ?>
                                    </span>
                                </td>
                                <td style="color:var(--text-dim)"><?= date('d M Y', strtotime($ur['created_at'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>

</main>

<?php
include '../includes/footer.php';
$conn->close();
?>