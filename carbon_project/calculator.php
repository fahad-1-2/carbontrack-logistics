<?php
// ============================================================
// calculator.php — Public CO2 calculator
// Anyone can use it. Logged-in users get results saved.
// ============================================================
session_start();
require_once 'includes/db.php';

$page_title = "CO₂ Calculator — CarbonTrack";
$css_path   = "";
$base_path  = "";

$result_co2 = null;
$result_data = [];
$error       = "";
$saved       = false;

// --- Fetch emission rates for dropdown ---
$rates_query = $conn->query("SELECT DISTINCT vehicle_type, fuel_type, co2_per_km_per_kg FROM emission_rates ORDER BY vehicle_type");
$rates_list  = [];
while ($r = $rates_query->fetch_assoc()) {
    $rates_list[] = $r;
}

// --- Handle calculation form ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calculate'])) {
    $vehicle_type = $conn->real_escape_string($_POST['vehicle_type']);
    $fuel_type    = $conn->real_escape_string($_POST['fuel_type']);
    $distance     = floatval($_POST['distance_km']);
    $weight       = floatval($_POST['weight_kg']);

    if ($distance <= 0 || $weight <= 0) {
        $error = "Distance and weight must be greater than zero.";

    } else {
        // Look up emission rate from DB
        $rate_row = $conn->query("
            SELECT rate_id, co2_per_km_per_kg
            FROM emission_rates
            WHERE vehicle_type = '$vehicle_type'
              AND fuel_type    = '$fuel_type'
            LIMIT 1
        ")->fetch_assoc();

        if (!$rate_row) {
            $error = "No emission rate found for that vehicle and fuel combination.";
        } else {
            // Apply the formula: CO2 = Distance x Weight x Rate
            $rate       = $rate_row['co2_per_km_per_kg'];
            $result_co2 = round($distance * $weight * $rate, 4);

            $result_data = [
                'vehicle_type' => $vehicle_type,
                'fuel_type'    => $fuel_type,
                'distance'     => $distance,
                'weight'       => $weight,
                'rate'         => $rate,
                'co2'          => $result_co2,
            ];

            // If logged in, save to user_calculations table
            if (isset($_SESSION['user_id'])) {
                $uid = intval($_SESSION['user_id']);
                $conn->query("
                    INSERT INTO user_calculations
                        (user_id, vehicle_type, fuel_type, distance_km, weight_kg, co2_result_kg)
                    VALUES
                        ($uid, '$vehicle_type', '$fuel_type', $distance, $weight, $result_co2)
                ");
                $saved = true;
            }
        }
    }
}

// --- Fetch unique vehicle types for first dropdown ---
$vehicle_types = $conn->query("SELECT DISTINCT vehicle_type FROM emission_rates ORDER BY vehicle_type");

// --- Fetch calculation history if logged in ---
$history = [];
if (isset($_SESSION['user_id'])) {
    $uid = intval($_SESSION['user_id']);
    $hq  = $conn->query("
        SELECT * FROM user_calculations
        WHERE user_id = $uid
        ORDER BY calculated_at DESC
        LIMIT 15
    ");
    while ($h = $hq->fetch_assoc()) $history[] = $h;
}

include 'includes/header.php';
?>

<main>

    <div class="section-head">
        <div class="section-tag">Calculator</div>
        <div class="section-title">CO₂ Emissions Calculator</div>
        <div class="section-line"></div>
    </div>

    <!-- Flash messages -->
    <?php if ($error): ?>
        <div class="flash flash-error">⚠ <?= $error ?></div>
    <?php endif; ?>

    <?php if ($saved): ?>
        <div class="flash flash-success">✅ Result saved to your history!</div>
    <?php elseif ($result_co2 !== null && !isset($_SESSION['user_id'])): ?>
        <div class="flash flash-info">ℹ <a href="register.php" style="color:var(--green);font-weight:600">Sign up free</a> to save your calculation history.</div>
    <?php endif; ?>

    <div class="calc-wrap">

        <!-- LEFT: Input Form -->
        <div>
            <div class="card">
                <div class="card-header">
                    <span style="font-size:20px">🚛</span>
                    <span class="card-title">Enter Shipment Details</span>
                </div>
                <div class="card-body">
                    <form method="POST" style="display:flex;flex-direction:column;gap:18px">

                        <div class="field">
                            <label>Vehicle Type</label>
                            <select name="vehicle_type" required>
                                <option value="">Select vehicle type</option>
                                <?php while ($vt = $vehicle_types->fetch_assoc()): ?>
                                    <option value="<?= $vt['vehicle_type'] ?>"
                                        <?= ($_POST['vehicle_type'] ?? '') === $vt['vehicle_type'] ? 'selected' : '' ?>>
                                        <?= $vt['vehicle_type'] ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label>Fuel Type</label>
                            <select name="fuel_type" required>
                                <option value="">Select fuel type</option>
                                <?php foreach ($rates_list as $rl): ?>
                                    <option value="<?= $rl['fuel_type'] ?>"
                                        <?= ($_POST['fuel_type'] ?? '') === $rl['fuel_type'] ? 'selected' : '' ?>>
                                        <?= $rl['vehicle_type'] ?> — <?= $rl['fuel_type'] ?> (<?= $rl['co2_per_km_per_kg'] ?> kg/km/kg)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="field">
                                <label>Distance (km)</label>
                                <input type="number" name="distance_km" placeholder="e.g. 1500"
                                       step="0.01" min="0.01"
                                       value="<?= htmlspecialchars($_POST['distance_km'] ?? '') ?>" required>
                            </div>
                            <div class="field">
                                <label>Cargo Weight (kg)</label>
                                <input type="number" name="weight_kg" placeholder="e.g. 500"
                                       step="0.01" min="0.01"
                                       value="<?= htmlspecialchars($_POST['weight_kg'] ?? '') ?>" required>
                            </div>
                        </div>

                        <button type="submit" name="calculate" class="btn btn-primary" style="width:100%;justify-content:center">
                            🧮 Calculate CO₂
                        </button>

                    </form>
                </div>
            </div>

            <!-- Emission rates reference -->
            <div class="card" style="margin-top:20px">
                <div class="card-header">
                    <span style="font-size:18px">📋</span>
                    <span class="card-title">Emission Rate Reference</span>
                </div>
                <div class="scroll-x">
                    <table>
                        <thead>
                            <tr>
                                <th>Vehicle</th>
                                <th>Fuel</th>
                                <th>Rate (kg/km/kg)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rates_list as $rl): ?>
                            <tr>
                                <td><?= $rl['vehicle_type'] ?></td>
                                <td><?= $rl['fuel_type'] ?></td>
                                <td><span class="co2-val"><?= $rl['co2_per_km_per_kg'] ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- RIGHT: Result Box -->
        <div>
            <div class="calc-result">
                <div class="result-label">CO₂ Emitted</div>

                <?php if ($result_co2 !== null): ?>
                    <div class="result-value"><?= number_format($result_co2, 2) ?></div>
                    <div class="result-unit">kilograms of CO₂</div>

                    <div class="result-formula">
                        Vehicle:   <?= $result_data['vehicle_type'] ?> / <?= $result_data['fuel_type'] ?><br>
                        Distance:  <?= number_format($result_data['distance'], 2) ?> km<br>
                        Weight:    <?= number_format($result_data['weight'], 2) ?> kg<br>
                        Rate:      <?= $result_data['rate'] ?> kg/km/kg<br>
                        ──────────────────────<br>
                        CO₂ = <?= $result_data['distance'] ?> × <?= $result_data['weight'] ?> × <?= $result_data['rate'] ?><br>
                        <strong style="color:var(--green)">   = <?= $result_co2 ?> kg</strong>
                    </div>

                    <!-- Context comparisons -->
                    <div style="margin-top:20px;display:flex;flex-direction:column;gap:10px">
                        <div style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px 14px;font-size:13px;color:var(--text-dim)">
                            🌳 Equivalent to <strong style="color:var(--text)"><?= round($result_co2 / 21, 1) ?> trees</strong> needed to absorb this in a year
                        </div>
                        <div style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px 14px;font-size:13px;color:var(--text-dim)">
                            🚗 Same as driving <strong style="color:var(--text)"><?= round($result_co2 / 0.21, 0) ?> km</strong> in a petrol car
                        </div>
                    </div>

                    <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="register.php" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:20px">
                        Sign Up to Save Results
                    </a>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="result-value" style="color:var(--text-muted);font-size:40px">—</div>
                    <div class="result-unit">Fill in the form to calculate</div>
                    <div class="result-formula">
                        Formula:<br>
                        CO₂ (kg) = Distance (km)<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;× Weight (kg)<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;× Emission Factor
                    </div>
                <?php endif; ?>
            </div>

            <!-- Calculation History (logged in users only) -->
            <?php if (isset($_SESSION['user_id'])): ?>
            <div class="card" style="margin-top:20px">
                <div class="card-header">
                    <span style="font-size:18px">🕘</span>
                    <span class="card-title">Your Calculation History</span>
                </div>
                <?php if (count($history) > 0): ?>
                <div class="scroll-x">
                    <table>
                        <thead>
                            <tr>
                                <th>Vehicle</th>
                                <th>Fuel</th>
                                <th>Distance</th>
                                <th>Weight</th>
                                <th>CO₂ (kg)</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td><?= $h['vehicle_type'] ?></td>
                                <td><?= $h['fuel_type'] ?></td>
                                <td><?= number_format($h['distance_km'], 0) ?> km</td>
                                <td><?= number_format($h['weight_kg'], 0) ?> kg</td>
                                <td><span class="co2-val"><?= number_format($h['co2_result_kg'], 2) ?></span></td>
                                <td style="color:var(--text-dim)"><?= date('d M Y', strtotime($h['calculated_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="history-empty">No calculations yet. Use the form to get started.</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>

</main>

<?php
include 'includes/footer.php';
$conn->close();
?>