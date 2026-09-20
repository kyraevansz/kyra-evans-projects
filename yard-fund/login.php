<?php
require 'db.php';
require 'auth.php';
require 'helpers.php';

redirect_if_logged_in();

$error = "";
$email = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (is_rate_limited()) {
        $mins = ceil(lockout_seconds_remaining() / 60);
        $error = "Too many failed attempts. Please wait {$mins} minute(s) before trying again.";
    } else {
        $email    = trim(strtolower($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = "Please enter your email and password.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($password, $user['password_hash'])) {
                record_failed_login();
                $error = "Incorrect email or password.";
                if (is_rate_limited()) {
                    $error = "Too many failed attempts. Your account is locked for 15 minutes.";
                }
            } elseif (!$user['is_active']) {
                $error = "Your account has been suspended. Please contact the administrator.";
            } else {
                reset_login_attempts();
                login_user($user);
                $next = $_POST['next'] ?? '';
                $next = filter_var($next, FILTER_SANITIZE_URL);
                header("Location: " . (!empty($next) && !preg_match('/^https?:\/\//i', $next) ? $next : dashboard_url()));
                exit;
            }
        }
    }
}
page_head('Log In');
?>
<?php require 'navbar.php'; ?>

<div class="container">
    <div class="auth-card">
        <h2>Welcome Back</h2>
        <p>Log in to your <?php echo APP_NAME; ?> account.</p>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['reset'])): ?>
            <div class="message">Password reset successfully. Please log in.</div>
        <?php endif; ?>

        <form method="POST">
            <?php if (!empty($_GET['next'])): ?>
                <input type="hidden" name="next" value="<?php echo htmlspecialchars($_GET['next']); ?>">
            <?php endif; ?>

            <label for="email">Email Address</label>
            <input type="email" name="email" id="email" required
                value="<?php echo htmlspecialchars($email); ?>">

            <label for="password">Password</label>
            <div class="pw-wrap">
                <input type="password" name="password" id="password" required>
                <button type="button" class="pw-toggle" tabindex="-1" aria-label="Show password">👁</button>
            </div>

            <div class="forgot-link">
                <a href="forgot_password.php">Forgot your password?</a>
            </div>

            <button type="submit" class="full-width">Log In</button>
        </form>

        <p class="auth-switch">Don't have an account? <a href="register.php">Sign up</a></p>
    </div>
</div>

<script>
document.querySelectorAll('.pw-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
        const i = btn.previousElementSibling;
        const hidden = i.type === 'password';
        i.type = hidden ? 'text' : 'password';
        btn.textContent = hidden ? '🙈' : '👁';
    });
});
</script>
</body></html>
