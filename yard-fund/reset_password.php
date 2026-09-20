<?php
require 'db.php';
require 'auth.php';
require 'helpers.php';

redirect_if_logged_in();

$error    = "";
$token    = trim($_GET['token'] ?? $_POST['token'] ?? '');
$resetRow = null;

if ($token) {
    $stmt = $pdo->prepare("
        SELECT r.*, u.email, u.full_name
        FROM password_resets r
        JOIN users u ON r.user_id = u.user_id
        WHERE r.token = ? AND r.used = 0 AND r.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $resetRow = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$resetRow) {
    $error = "This reset link is invalid or has expired. Please request a new one.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resetRow) {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?")
            ->execute([password_hash($password, PASSWORD_BCRYPT), $resetRow['user_id']]);
        $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")
            ->execute([$token]);
        header("Location: login.php?reset=1");
        exit;
    }
}
page_head('Reset Password');
?>
<?php require 'navbar.php'; ?>

<div class="container">
    <div class="auth-card">
        <h2>Set New Password</h2>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php if (!$resetRow): ?>
                <p class="auth-switch"><a href="forgot_password.php">Request a new link</a></p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($resetRow): ?>
            <p>Setting a new password for <strong><?php echo htmlspecialchars($resetRow['email']); ?></strong></p>
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <label for="password">New Password <span class="label-hint">(min. 8 characters)</span></label>
                <div class="pw-wrap">
                    <input type="password" name="password" id="password" required>
                    <button type="button" class="pw-toggle" tabindex="-1">👁</button>
                </div>

                <label for="confirm_password">Confirm New Password</label>
                <div class="pw-wrap">
                    <input type="password" name="confirm_password" id="confirm_password" required>
                    <button type="button" class="pw-toggle" tabindex="-1">👁</button>
                </div>

                <button type="submit" class="full-width">Update Password</button>
            </form>
        <?php endif; ?>
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
