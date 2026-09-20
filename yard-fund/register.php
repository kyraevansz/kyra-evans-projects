<?php
require 'db.php';
require 'auth.php';
require 'helpers.php';

redirect_if_logged_in();

$error = "";
$form  = ['full_name' => '', 'email' => '', 'user_type' => 'student'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim(strtolower($_POST['email'] ?? ''));
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';
    $user_type = $_POST['user_type'] ?? '';

    $form = compact('full_name', 'email', 'user_type');

    if (empty($full_name) || empty($email) || empty($password) || empty($user_type)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!in_array($user_type, ['student', 'donor'], true)) {
        $error = "Invalid account type.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = "An account with that email already exists.";
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO users (full_name, email, password_hash, user_type) VALUES (?, ?, ?, ?)")
                ->execute([$full_name, $email, $hash, $user_type]);
            login_user([
                'user_id'   => $pdo->lastInsertId(),
                'user_type' => $user_type,
                'full_name' => $full_name,
                'email'     => $email,
            ]);
            header("Location: " . dashboard_url());
            exit;
        }
    }
}
page_head('Create Account');
?>
<?php require 'navbar.php'; ?>

<div class="container">
    <div class="auth-card">
        <h2>Create an Account</h2>
        <p>Join <?php echo APP_NAME; ?> as a student or donor.</p>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <label for="full_name">Full Name</label>
            <input type="text" name="full_name" id="full_name" required
                value="<?php echo htmlspecialchars($form['full_name']); ?>">

            <label for="email">Email Address</label>
            <input type="email" name="email" id="email" required
                value="<?php echo htmlspecialchars($form['email']); ?>">

            <label for="password">Password <span class="label-hint">(min. 8 characters)</span></label>
            <div class="pw-wrap">
                <input type="password" name="password" id="password" required>
                <button type="button" class="pw-toggle" tabindex="-1">👁</button>
            </div>

            <label for="confirm_password">Confirm Password</label>
            <div class="pw-wrap">
                <input type="password" name="confirm_password" id="confirm_password" required>
                <button type="button" class="pw-toggle" tabindex="-1">👁</button>
            </div>

            <label>I am a...</label>
            <div class="role-toggle">
                <label class="role-option <?php echo $form['user_type'] === 'student' ? 'active' : ''; ?>">
                    <input type="radio" name="user_type" value="student" <?php echo $form['user_type'] === 'student' ? 'checked' : ''; ?>>
                    <span>🎓 Student</span>
                    <small>Submit a tuition claim</small>
                </label>
                <label class="role-option <?php echo $form['user_type'] === 'donor' ? 'active' : ''; ?>">
                    <input type="radio" name="user_type" value="donor" <?php echo $form['user_type'] === 'donor' ? 'checked' : ''; ?>>
                    <span>💝 Donor</span>
                    <small>Support students in need</small>
                </label>
            </div>

            <button type="submit" class="full-width" style="margin-top:10px;">Create Account</button>
        </form>

        <p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
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
document.querySelectorAll('.role-option input').forEach(r => {
    r.addEventListener('change', () => {
        document.querySelectorAll('.role-option').forEach(el => el.classList.remove('active'));
        r.closest('.role-option').classList.add('active');
    });
});
</script>
</body></html>
