<?php
require 'db.php';
require 'auth.php';
require 'helpers.php';

$profile_id = (int) ($_GET['id'] ?? 0);
if ($profile_id <= 0) { header("Location: donor_browse_claims.php"); exit; }

$stmt = $pdo->prepare("
    SELECT u.user_id, u.full_name, u.email, u.major, u.classification, u.gpa, u.bio, u.profile_photo,
           a.claim_id, a.tuition_amount, a.description, a.proof_file, a.funding_status, a.approved_at,
           COALESCE(SUM(t.amount_sent), 0) AS total_donated
    FROM users u
    JOIN claims_approved a ON u.user_id = a.student_id
    LEFT JOIN transactions t ON a.claim_id = t.claim_id
    WHERE u.user_id = ? AND u.user_type = 'student' AND u.is_active = 1
    GROUP BY u.user_id, a.claim_id
");
$stmt->execute([$profile_id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) { header("Location: donor_browse_claims.php"); exit; }

$goal      = (float) $profile['tuition_amount'];
$raised    = (float) $profile['total_donated'];
$remaining = max(0, $goal - $raised);

$updates = $pdo->prepare("SELECT * FROM student_updates WHERE claim_id = ? ORDER BY created_at DESC");
$updates->execute([$profile['claim_id']]);
$updateList = $updates->fetchAll(PDO::FETCH_ASSOC);

// Donation feedback
$donationMessage = $donationError = "";
if (isset($_GET['donated']))            $donationMessage = "Donation recorded — thank you! Confirmation emails sent.";
if (($_GET['error'] ?? '') === 'exceeds') $donationError = "Donation cannot exceed the remaining balance.";
if (($_GET['error'] ?? '') === 'failed')  $donationError = "Something went wrong. Please try again.";

page_head(htmlspecialchars($profile['full_name']));
?>
<?php require 'navbar.php'; ?>

<div class="container">
    <div class="profile-layout">

        <div class="profile-main">
            <div class="page-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php if ($profile['profile_photo']): ?>
                            <img src="uploads/<?php echo htmlspecialchars($profile['profile_photo']); ?>" alt="Profile">
                        <?php else: ?>
                            <div class="avatar-placeholder large">🎓</div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h2><?php echo htmlspecialchars($profile['full_name']); ?></h2>
                        <p class="dashboard-meta"><?php echo student_meta($profile); ?></p>
                    </div>
                </div>

                <?php if (!empty($profile['bio'])): ?>
                    <p style="line-height:1.7;color:#3a4b68;"><?php echo nl2br(htmlspecialchars($profile['bio'])); ?></p>
                <?php endif; ?>

                <hr style="border:none;border-top:1px solid #e8eef8;margin:20px 0;">
                <h3 style="margin-top:0;">Tuition Claim</h3>
                <p style="color:#3a4b68;"><?php echo nl2br(htmlspecialchars($profile['description'])); ?></p>

                <?php render_progress_bar($raised, $goal); ?>

                <div class="claim-meta-grid">
                    <div class="claim-meta-box"><h4>Goal</h4><p>$<?php echo number_format($goal, 2); ?></p></div>
                    <div class="claim-meta-box"><h4>Raised</h4><p>$<?php echo number_format($raised, 2); ?></p></div>
                    <div class="claim-meta-box"><h4>Remaining</h4><p>$<?php echo number_format($remaining, 2); ?></p></div>
                </div>
            </div>

            <?php if (count($updateList) > 0): ?>
                <div class="page-card" style="margin-top:24px;">
                    <h3 style="margin-top:0;">Updates from <?php echo htmlspecialchars($profile['full_name']); ?></h3>
                    <div class="update-feed">
                        <?php foreach ($updateList as $u): ?>
                            <div class="update-item">
                                <p><?php echo nl2br(htmlspecialchars($u['update_message'])); ?></p>
                                <span class="update-date"><?php echo htmlspecialchars($u['created_at']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="profile-sidebar">
            <div class="page-card">
                <h3 style="margin-top:0;">Support <?php echo htmlspecialchars(explode(' ', $profile['full_name'])[0]); ?></h3>

                <?php if ($donationMessage): ?><div class="message"><?php echo htmlspecialchars($donationMessage); ?></div><?php endif; ?>
                <?php if ($donationError): ?><div class="error-message"><?php echo htmlspecialchars($donationError); ?></div><?php endif; ?>

                <?php if ($remaining > 0): ?>
                    <?php if (is_logged_in() && current_user_type() === 'donor'): ?>
                        <form method="POST" action="donor_donate.php">
                            <input type="hidden" name="claim_id" value="<?php echo $profile['claim_id']; ?>">
                            <label for="amount_sent">Donation Amount ($)</label>
                            <input type="number" step="0.01" min="1" name="amount_sent" id="amount_sent"
                                max="<?php echo $remaining; ?>" required>
                            <label class="checkbox-row">
                                <input type="checkbox" name="is_anonymous" value="1"> Donate anonymously
                            </label>
                            <button type="submit" class="full-width">Donate Now</button>
                        </form>
                    <?php elseif (!is_logged_in()): ?>
                        <p style="color:#64748b;">You must be logged in as a donor to donate.</p>
                        <a class="button full-width" href="login.php?next=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Log In to Donate</a>
                        <p style="text-align:center;margin-top:12px;color:#64748b;font-size:.9rem;">
                            No account? <a href="register.php">Sign up as a donor</a>
                        </p>
                    <?php else: ?>
                        <p style="color:#64748b;">Only donors can fund student claims.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="message">This claim has been fully funded! 🎉</div>
                <?php endif; ?>

                <p class="claim-proof-link" style="margin-top:16px;">
                    <strong>Proof on file:</strong>
                    <a href="uploads/<?php echo urlencode($profile['proof_file']); ?>" target="_blank">View document</a>
                </p>
            </div>
        </div>

    </div>
    <div style="margin-top:20px;">
        <a class="back-link" href="donor_browse_claims.php">← Back to Browse Claims</a>
    </div>
</div>
</body></html>
