<?php
// ============================================================
// login.php — User login page
// ============================================================
session_start();

// Already logged in — redirect
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'includes/db.php';

$page_title = "Login — CarbonTrack";
$css_path   = "";
$base_path  = "";

$error = "";

// --- Handle login form ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($conn->real_escape_string($_POST['email']));
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Please enter your email and password.";

    } else {
        // Fetch user by email
        $result = $conn->query("SELECT * FROM users WHERE email = '$email' LIMIT 1");

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Verify password against stored hash
            if (password_verify($password, $user['password_hash'])) {
                // Login successful — store session
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];

                // Redirect admin to admin panel, users to dashboard
                if ($user['role'] === 'admin') {
                    header('Location: admin/carbon_tracker.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit;

            } else {
                $error = "Incorrect password. Please try again.";
            }

        } else {
            $error = "No account found with that email address.";
        }
    }
}

include 'includes/header.php';
?>

<div class="auth-wrap">
    <div class="auth-box">

        <div class="auth-header">
            <div class="auth-icon">🔐</div>
            <h1 class="auth-title">Welcome Back</h1>
            <p class="auth-sub">Log in to view your carbon tracking dashboard</p>
        </div>

        <div class="auth-body">

            <?php if ($error): ?>
                <div class="flash flash-error">⚠ <?= $error ?></div>
            <?php endif; ?>

            <form method="POST" style="display:contents">

                <div class="field">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="abc@company.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                </div>

                <div class="field">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Your password" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:4px">
                    Log In →
                </button>

            </form>

          

        </div>

        <div class="auth-footer">
            Don't have an account? <a href="register.php">Sign up free</a>
        </div>

    </div>
</div>

<?php
include 'includes/footer.php';
$conn->close();
?>