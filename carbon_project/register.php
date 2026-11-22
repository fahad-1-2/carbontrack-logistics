<?php
// ============================================================
// register.php — User signup page
// ============================================================
session_start();

// If already logged in redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'includes/db.php';

$page_title = "Sign Up — CarbonTrack";
$css_path   = "";
$base_path  = "";

$error   = "";
$success = "";

// --- Handle form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($conn->real_escape_string($_POST['full_name']));
    $email    = trim($conn->real_escape_string($_POST['email']));
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];
    $company  = trim($conn->real_escape_string($_POST['company_name'] ?? ''));
    $country  = trim($conn->real_escape_string($_POST['country'] ?? ''));

    // Simple validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";

    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";

    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";

    } else {
        // Check if email already exists
        $check = $conn->query("SELECT user_id FROM users WHERE email = '$email'");

        if ($check->num_rows > 0) {
            $error = "An account with this email already exists.";

        } else {
            // Hash the password securely — never store plain text
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $sql = "INSERT INTO users (full_name, email, password_hash, company_name, country, role)
                    VALUES ('$name', '$email', '$hash', '$company', '$country', 'user')";

            if ($conn->query($sql)) {
                $success = "Account created! You can now log in.";
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="auth-wrap">
    <div class="auth-box">

        <div class="auth-header">
            <div class="auth-icon">🌿</div>
            <h1 class="auth-title">Create Account</h1>
            <p class="auth-sub">Start tracking your logistics carbon footprint</p>
        </div>

        <div class="auth-body">

            <?php if ($error):   ?><div class="flash flash-error">⚠ <?= $error ?></div><?php endif; ?>
            <?php if ($success): ?><div class="flash flash-success">✅ <?= $success ?></div><?php endif; ?>

            <?php if (!$success): ?>
            <form method="POST" style="display:contents">

                <div class="field">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" placeholder="Your full name"
                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                </div>

                <div class="field">
                    <label>Email Address *</label>
                    <input type="email" name="email" placeholder="you@company.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label>Company Name</label>
                        <input type="text" name="company_name" placeholder="Optional"
                               value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label>Country</label>
                        <input type="text" name="country" placeholder="e.g. Pakistan"
                               value="<?= htmlspecialchars($_POST['country'] ?? '') ?>">
                    </div>
                </div>

                <div class="field">
                    <label>Password * (min 6 characters)</label>
                    <input type="password" name="password" placeholder="Create a password" required>
                </div>

                <div class="field">
                    <label>Confirm Password *</label>
                    <input type="password" name="confirm_password" placeholder="Repeat your password" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:4px">
                    Create Account →
                </button>

            </form>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary" style="width:100%;justify-content:center">
                    Go to Login →
                </a>
            <?php endif; ?>

        </div>

        <div class="auth-footer">
            Already have an account? <a href="login.php">Log in here</a>
        </div>

    </div>
</div>

<?php
include 'includes/footer.php';
$conn->close();
?>