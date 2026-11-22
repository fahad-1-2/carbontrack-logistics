<?php
// ============================================================
// dashboard.php — Personal user dashboard
// Requires login — redirects to login if not logged in
// ============================================================
session_start();

// Protect this page — must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'includes/db.php';

$page_title = "Dashboard — CarbonTrack";
$css_path   = "";
$base_path  = "";

$uid = intval($_SESSION['user_id']);

// Fetch logged-in user's info
$user = $conn->query("SELECT * FROM users WHERE user_id = $uid")->fetch_assoc();

// Total calculations this user has done
$total_calcs = $conn->query("SELECT COUNT(*) AS t FROM user_calculations WHERE user_id = $uid")->fetch_assoc()['t'];

// Total CO2 they've calculated
$total_co2   = $conn->query("SELECT ROUND(SUM(co2_result_kg),2) AS t FROM user_calculations WHERE user_id = $uid")->fetch_assoc()['t'] ?? 0;

// Their highest single emission calculation
$highest     = $conn->query("SELECT ROUND(MAX(co2_result_kg),2) AS t FROM user_calculations WHERE user_id = $uid")->fetch_assoc()['t'] ?? 0;

// Their most used vehicle type
$fav_vehicle = $conn->query("
    SELECT vehicle_type, COUNT(*) AS cnt
    FROM user_calculations
    WHERE user_id = $uid
    GROUP BY vehicle_type
    ORDER BY cnt DESC
    LIMIT 1
")->fetch_assoc()['vehicle_type'] ?? '—';

// Last 10 calculations
$calcs = $conn->query("
    SELECT * FROM user_calculations
    WHERE user_id = $uid
    ORDER BY calculated_at DESC
    LIMIT 10
");

// CO2 by vehicle type (for breakdown)
$by_vehicle = $conn->query("
    SELECT vehicle_type, COUNT(*) AS count, ROUND(SUM(co2_result_kg),2) AS total_co2
    FROM user_calculations
    WHERE user_id = $uid
    GROUP BY vehicle_type
    ORDER BY total_co2 DESC
");

include 'includes/header.php';
?>

<main>

    <!-- Welcome bar -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:36px;flex-wrap:wrap;gap:16px">
        <div>
            <h1 style="font-size:26px;font-weight:800;margin-bottom:4px">
                Welcome back, <?= htmlspecialchars($user['full_name']) ?> 👋
            </h1>
            <p style="color:var(--text-dim);font-size:14px">
                <?= $user['company_name'] ? htmlspecialchars($user['company_name']) . ' · ' : '' ?>
                <?= htmlspecialchars($user['country'] ?? '') ?>
                · Member since <?= date('M Y', strtotime($user['created_at'])) ?>
            </p>
        </div>
        <a href="calculator.php" class="btn btn-primary">+ New Calculation</a>
    </div>

    <!-- ======================================================
         PERSONAL STATS
    ====================================================== -->
    <section>
        <div class="section-head">
            <div class="section-tag">Your Stats</div>
            <div class="section-title">Your Carbon Overview</div>
            <div class="section-line"></div>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon">🧮</div>
                <div class="stat-label">Total Calculations</div>
                <div class="stat-value"><?= $total_calcs ?></div>
                <div class="stat-sub">routes you've calculated</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🌍</div>
                <div class="stat-label">Total CO₂ Tracked</div>
                <div class="stat-value"><?= number_format($total_co2, 1) ?></div>
                <div class="stat-sub">kilograms across all your routes</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📈</div>
                <div class="stat-label">Highest Single Emission</div>
                <div class="stat-value"><?= number_format($highest, 1) ?></div>
                <div class="stat-sub">kg CO₂ in one calculation</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🚛</div>
                <div class="stat-label">Most Used Vehicle</div>
                <div class="stat-value" style="font-size:22px;padding-top:6px"><?= $fav_vehicle ?></div>
                <div class="stat-sub">your most calculated transport type</div>
            </div>
        </div>
    </section>

    <!-- ======================================================
         MAIN DASHBOARD GRID
    ====================================================== -->
    <div class="dash-grid">

        <!-- Recent Calculations -->
        <section style="margin-bottom:0">
            <div class="section-head">
                <div class="section-tag">History</div>
                <div class="section-title">Recent Calculations</div>
            </div>

            <div class="table-wrap">
                <?php if ($calcs->num_rows > 0): ?>
                <div class="scroll-x">
                    <table>
                        <thead>
                            <tr>
                                <th>Vehicle</th>
                                <th>Fuel</th>
                                <th>Distance</th>
                                <th>Weight</th>
                                <th>CO₂</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($c = $calcs->fetch_assoc()): ?>
                            <tr>
                                <td style="font-weight:600"><?= $c['vehicle_type'] ?></td>
                                <td><?= $c['fuel_type'] ?></td>
                                <td><?= number_format($c['distance_km'],0) ?> km</td>
                                <td><?= number_format($c['weight_kg'],0) ?> kg</td>
                                <td><span class="co2-val"><?= number_format($c['co2_result_kg'],2) ?></span> kg</td>
                                <td style="color:var(--text-dim)"><?= date('d M Y', strtotime($c['calculated_at'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="history-empty">
                    No calculations yet.<br>
                    <a href="calculator.php" style="color:var(--green);font-weight:600">Start calculating →</a>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- CO2 By Vehicle Breakdown -->
        <section style="margin-bottom:0">
            <div class="section-head">
                <div class="section-tag">Breakdown</div>
                <div class="section-title">CO₂ by Vehicle Type</div>
            </div>

            <div class="table-wrap">
                <?php if ($by_vehicle->num_rows > 0): ?>
                <div class="scroll-x">
                    <table>
                        <thead>
                            <tr>
                                <th>Vehicle Type</th>
                                <th>Times Used</th>
                                <th>Total CO₂ (kg)</th>
                                <th>Share</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $rows_bv = [];
                        while ($bv = $by_vehicle->fetch_assoc()) $rows_bv[] = $bv;
                        $sum_bv = array_sum(array_column($rows_bv, 'total_co2')) ?: 1;
                        foreach ($rows_bv as $bv):
                            $pct = round(($bv['total_co2'] / $sum_bv) * 100, 1);
                        ?>
                            <tr>
                                <td style="font-weight:600"><?= $bv['vehicle_type'] ?></td>
                                <td><?= $bv['count'] ?></td>
                                <td><span class="co2-val"><?= number_format($bv['total_co2'],2) ?></span></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px">
                                        <div style="flex:1;background:var(--border);border-radius:4px;height:5px;overflow:hidden">
                                            <div style="width:<?= $pct ?>%;height:100%;background:var(--green);border-radius:4px"></div>
                                        </div>
                                        <span style="font-size:11px;color:var(--text-dim);min-width:32px"><?= $pct ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="history-empty">No data yet.</div>
                <?php endif; ?>
            </div>

            <!-- Quick actions -->
            <div style="margin-top:20px;display:flex;flex-direction:column;gap:10px">
                <a href="calculator.php" class="btn btn-primary" style="justify-content:center">
                    🧮 Calculate New Route
                </a>
                <a href="index.php" class="btn btn-outline" style="justify-content:center">
                    🌍 View Platform Stats
                </a>
            </div>
        </section>

    </div>

</main>

<?php
include 'includes/footer.php';
$conn->close();
?>