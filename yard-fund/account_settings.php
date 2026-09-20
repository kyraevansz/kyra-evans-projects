<?php
require 'db.php';
require 'auth.php';
require 'helpers.php';
require_login();

$user_id   = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$message   = $error = "";

$user = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$user->execute([$user_id]);
$user = $user->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $full_name      = trim($_POST['full_name'] ?? '');
        $bio            = trim($_POST['bio'] ?? '');
        $major          = trim($_POST['major'] ?? '');
        $classification = trim($_POST['classification'] ?? '');
        $gpa            = trim($_POST['gpa'] ?? '');

        if (empty($full_name)) {
            $error = "Name cannot be empty.";
        } elseif ($gpa !== '' && (!is_numeric($gpa) || $gpa < 0 || $gpa > 4.0)) {
            $error = "GPA must be between 0.00 and 4.00.";
        } else {
            $photo = $user['profile_photo'];

            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === 0) {
                $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                $mime    = mime_content_type($_FILES['profile_photo']['tmp_name']);

                if (!in_array($mime, $allowed)) {
                    $error = "Photo must be JPG, PNG, WebP, or GIF.";
                } elseif ($_FILES['profile_photo']['size'] > 3 * 1024 * 1024) {
                    $error = "Photo must be under 3MB.";
                } else {
                    if (!is_dir('uploads')) mkdir('uploads', 0777, true);
                    $ext      = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
                    $newPhoto = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], 'uploads/' . $newPhoto)) {
                        // Delete old photo file if it exists
                        if ($photo && file_exists('uploads/' . $photo)) {
                            unlink('uploads/' . $photo);
                        }
                        $photo = $newPhoto;
                    } else {
                        $error = "Photo upload failed.";
                    }
                }
            }

            if (empty($error)) {
                $pdo->prepare("
                    UPDATE users SET full_name=?, bio=?, major=?, classification=?, gpa=?, profile_photo=?
                    WHERE user_id=?
                ")->execute([
                    $full_name, $bio, $major,
                    $classification,
                    ($gpa === '' ? null : $gpa),
                    $photo,
                    $user_id
                ]);
                $_SESSION['full_name'] = $full_name;
                $message = "Profile updated successfully.";
                $user = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                $user->execute([$user_id]);
                $user = $user->fetch(PDO::FETCH_ASSOC);
            }
        }
    }

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password_hash'])) {
            $error = "Current password is incorrect.";
        } elseif (strlen($new) < 8) {
            $error = "New password must be at least 8 characters.";
        } elseif ($new !== $confirm) {
            $error = "New passwords do not match.";
        } else {
            $pdo->prepare("UPDATE users SET password_hash=? WHERE user_id=?")
                ->execute([password_hash($new, PASSWORD_BCRYPT), $user_id]);
            $message = "Password changed successfully.";
        }
    }
}
page_head('Account Settings');
?>
<?php require 'navbar.php'; ?>

<div class="container">
    <?php if ($message): ?><div class="message"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error-message"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="page-card" style="margin-bottom:24px;">
        <h2>Profile Settings</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="profile">

            <div class="settings-photo-row">
                <div class="settings-avatar">
                    <?php if ($user['profile_photo']): ?>
                        <img src="uploads/<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profile photo">
                    <?php else: ?>
                        <div class="avatar-placeholder">
                            <?php echo $user_type === 'student' ? '🎓' : ($user_type === 'donor' ? '💝' : '🛡️'); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div style="flex:1;">
                    <label for="profile_photo">Profile Photo</label>
                    <input type="file" name="profile_photo" id="profile_photo" accept=".jpg,.jpeg,.png,.webp,.gif">
                    <p class="field-hint">JPG, PNG, WebP or GIF — max 3MB</p>
                </div>
            </div>

            <div class="form-grid">
                <div>
                    <label for="full_name">Full Name</label>
                    <input type="text" name="full_name" id="full_name" required
                        value="<?php echo htmlspecialchars($user['full_name']); ?>">
                </div>
                <div>
                    <label>Email Address</label>
                    <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                    <p class="field-hint">Email cannot be changed</p>
                </div>
            </div>

            <?php if ($user_type === 'student'): ?>
                <div class="form-grid">
                    <div>
                        <label for="major">Major</label>
                        <input type="text" name="major" id="major"
                            value="<?php echo htmlspecialchars($user['major'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="classification">Classification</label>
                        <input type="text" name="classification" id="classification"
                            placeholder="Freshman, Sophomore, Junior, Senior"
                            value="<?php echo htmlspecialchars($user['classification'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="gpa">GPA</label>
                        <input type="number" step="0.01" min="0" max="4.00" name="gpa" id="gpa"
                            value="<?php echo $user['gpa'] !== null ? htmlspecialchars($user['gpa']) : ''; ?>">
                    </div>
                </div>
            <?php endif; ?>

            <label for="bio">Bio</label>
            <textarea name="bio" id="bio" rows="3"
                placeholder="Tell us a little about yourself"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>

            <button type="submit">Save Profile</button>
        </form>
    </div>

    <div class="page-card">
        <h2>Change Password</h2>
        <form method="POST">
            <input type="hidden" name="action" value="password">

            <label for="current_password">Current Password</label>
            <div class="pw-wrap">
                <input type="password" name="current_password" id="current_password" required>
                <button type="button" class="pw-toggle" tabindex="-1">👁</button>
            </div>

            <div class="form-grid">
                <div>
                    <label for="new_password">New Password <span class="label-hint">(min. 8 characters)</span></label>
                    <div class="pw-wrap">
                        <input type="password" name="new_password" id="new_password" required>
                        <button type="button" class="pw-toggle" tabindex="-1">👁</button>
                    </div>
                </div>
                <div>
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="pw-wrap">
                        <input type="password" name="confirm_password" id="confirm_password" required>
                        <button type="button" class="pw-toggle" tabindex="-1">👁</button>
                    </div>
                </div>
            </div>

            <button type="submit">Change Password</button>
        </form>
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
