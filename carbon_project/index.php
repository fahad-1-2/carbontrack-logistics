<?php
// ============================================================
// index.php — Public landing page
// ============================================================
session_start();
require_once 'includes/db.php';

$page_title = "CarbonTrack Logistics — Track Your CO₂ Emissions";
$css_path   = "";
$base_path  = "";

// Fetch live stats for display on landing page
$total_co2   = $conn->query("SELECT ROUND(SUM(co2_emitted_kg),0) AS t FROM emission_logs")->fetch_assoc()['t'] ?? 0;
$total_ships = $conn->query("SELECT COUNT(*) AS t FROM shipments")->fetch_assoc()['t'] ?? 0;
$total_cust  = $conn->query("SELECT COUNT(*) AS t FROM customers")->fetch_assoc()['t'] ?? 0;
$total_veh   = $conn->query("SELECT COUNT(*) AS t FROM vehicles WHERE is_active = 1")->fetch_assoc()['t'] ?? 0;

include 'includes/header.php';
?>

<main>

    <!-- ======================================================
         HERO SECTION
    ====================================================== -->
    <div class="hero">
        <div class="hero-tag">
            <span class="live-dot"></span>
            Live Database Connected
        </div>

        <h1 class="hero-title">
            Track the Carbon Cost<br>
            of Every <span>Shipment</span>
        </h1>

        <p class="hero-desc">
            A logistics CO₂ tracking system that calculates exactly how much carbon
            your shipments produce — across every vehicle, route, and delivery leg.
            Built on a real SQL database.
        </p>

        <div class="hero-btns">
            <a href="calculator.php" class="btn btn-primary btn-lg">🧮 Try the Calculator</a>
            <a href="register.php"   class="btn btn-outline btn-lg">Create Free Account</a>
        </div>
    </div>

    <!-- ======================================================
         LIVE STATS
    ====================================================== -->
    <section>
        <div class="section-head">
            <div class="section-tag">Live Data</div>
            <div class="section-title">Platform Statistics</div>
            <div class="section-line"></div>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon">🌍</div>
                <div class="stat-label">Total CO₂ Tracked</div>
                <div class="stat-value"><?= number_format($total_co2, 0) ?></div>
                <div class="stat-sub">kilograms emitted across all shipments</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-label">Shipments Logged</div>
                <div class="stat-value"><?= $total_ships ?></div>
                <div class="stat-sub">complete shipment records in the database</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🏢</div>
                <div class="stat-label">Companies Tracked</div>
                <div class="stat-value"><?= $total_cust ?></div>
                <div class="stat-sub">logistics customers with carbon budgets</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🚛</div>
                <div class="stat-label">Active Vehicles</div>
                <div class="stat-value"><?= $total_veh ?></div>
                <div class="stat-sub">planes, trucks, vans and ships in fleet</div>
            </div>
        </div>
    </section>

    <!-- ======================================================
         HOW IT WORKS
    ====================================================== -->
    <section>
        <div class="section-head">
            <div class="section-tag">How It Works</div>
            <div class="section-title">Simple. Accurate. Data-Driven.</div>
            <div class="section-line"></div>
        </div>

        <div class="steps-grid">
            <div class="step-card">
                <div class="step-num">Step 01</div>
                <div class="step-icon">📍</div>
                <div class="step-title">Enter Your Route</div>
                <div class="step-desc">
                    Select your origin city, destination, vehicle type, cargo weight,
                    and distance. All location data is pulled live from the database.
                </div>
            </div>
            <div class="step-card">
                <div class="step-num">Step 02</div>
                <div class="step-icon">⚙️</div>
                <div class="step-title">Formula is Applied</div>
                <div class="step-desc">
                    The system looks up the real-world emission factor for your vehicle
                    from the database and runs:<br>
                    <code style="color:var(--green)">CO₂ = Distance × Weight × Rate</code>
                </div>
            </div>
            <div class="step-card">
                <div class="step-num">Step 03</div>
                <div class="step-icon">📊</div>
                <div class="step-title">See Your Impact</div>
                <div class="step-desc">
                    Get an instant CO₂ result in kg. Sign up to save your calculations,
                    track history over time, and compare different routes.
                </div>
            </div>
        </div>
    </section>

    <!-- ======================================================
         EMISSION RATES TABLE (from DB)
    ====================================================== -->
    <section>
        <div class="section-head">
            <div class="section-tag">Reference Data</div>
            <div class="section-title">Emission Rates by Vehicle</div>
            <div class="section-line"></div>
        </div>

        <div class="table-wrap">
            <div class="table-bar">
                <span class="table-label">emission_rates — source: IPCC / EEA / IMO</span>
            </div>
            <div class="scroll-x">
                <table>
                    <thead>
                        <tr>
                            <th>Vehicle Type</th>
                            <th>Fuel Type</th>
                            <th>CO₂ per km per kg</th>
                            <th>Data Source</th>
                            <th>Efficiency</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $rates = $conn->query("SELECT * FROM emission_rates ORDER BY co2_per_km_per_kg ASC");
                    while ($r = $rates->fetch_assoc()):
                        $eff = $r['co2_per_km_per_kg'] <= 0.00005 ? ['badge-green','Excellent'] :
                              ($r['co2_per_km_per_kg'] <= 0.0002  ? ['badge-blue','Good'] :
                              ($r['co2_per_km_per_kg'] <= 0.00025 ? ['badge-yellow','Moderate'] :
                                                                     ['badge-red','High']));
                    ?>
                        <tr>
                            <td style="font-weight:600"><?= $r['vehicle_type'] ?></td>
                            <td><?= $r['fuel_type'] ?></td>
                            <td><span class="co2-val"><?= $r['co2_per_km_per_kg'] ?></span> kg</td>
                            <td><span class="badge badge-gray"><?= $r['data_source'] ?></span></td>
                            <td><span class="badge <?= $eff[0] ?>"><?= $eff[1] ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- ======================================================
         CTA BANNER
    ====================================================== -->
    <section>
        <div style="background:var(--green-dim);border:1px solid var(--green-glow);border-radius:var(--radius);padding:48px;text-align:center;">
            <h2 style="font-size:30px;margin-bottom:12px">Ready to track your carbon footprint?</h2>
            <p style="color:var(--text-dim);font-size:16px;margin-bottom:28px">
                Create a free account and start calculating your shipment emissions in seconds.
            </p>
            <div class="hero-btns">
                <a href="register.php"   class="btn btn-primary btn-lg">Get Started Free</a>
                <a href="calculator.php" class="btn btn-outline btn-lg">Try Without Signing Up</a>
            </div>
        </div>
    </section>

</main>

<?php
include 'includes/footer.php';
$conn->close();
?>