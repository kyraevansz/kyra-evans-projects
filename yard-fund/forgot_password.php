<?php
require 'db.php';
require 'auth.php';
require 'mailer.php';
require 'helpers.php';

redirect_if_logged_in();

$message = $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(strtolower($_POST['email'] ?? ''));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $stmt = $pdo->prepare("SELECT user_id, full_name FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user['user_id']]);
            $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)")
                ->execute([$user['user_id'], $token, $expires]);

            $body = "
                <p>Hello <strong>" . htmlspecialchars($user['full_name']) . "</strong>,</p>
                <p>We received a request to reset your password. Click below to set a new one.</p>
                <p>This link expires in <strong>1 hour</strong>. If you didn't request this, ignore this email.</p>
            ";
            sendEmailTemplate(
                $email, $user['full_name'],
                'Reset your ' . APP_NAME . ' password', 'Password Reset',
                $body, 'Reset Password', APP_URL . '/reset_password.php?token=' . $token
            );
        }
        $message = "If an account exists for that email, a reset link has been sent.";
    }
}
page_head('Forgot Password');
?>
<?php require 'navbar.php'; ?>

<div class="container">
    <div class="auth-card">
        <h2>Forgot Password</h2>
        <p>Enter your email and we'll send you a reset link.</p>

        <?php if ($message): ?><div class="message"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error-message"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <?php if (!$message): ?>
            <form method="POST">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" required>
                <button type="submit" class="full-width">Send Reset Link</button>
            </form>
        <?php endif; ?>

        <p class="auth-switch"><a href="login.php">← Back to Login</a></p>
    </div>
</div>
</body></html>
