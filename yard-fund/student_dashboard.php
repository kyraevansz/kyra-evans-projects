<?php
require 'db.php';
require 'auth.php';
require 'helpers.php';
require_role('student');

$student_id = $_SESSION['user_id'];

// ── Handle POSTs ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Post a progress update
    if ($action === 'post_update') {
        $claim_id = (int) ($_POST['claim_id'] ?? 0);
        $msg      = trim($_POST['update_message'] ?? '');
        if ($claim_id > 0 && !empty($msg)) {
            $chk = $pdo->prepare("SELECT claim_id FROM claims_approved WHERE claim_id=? AND student_id=?");
            $chk->execute([$claim_id, $student_id]);
            if ($chk->fetch()) {
                $pdo->prepare("INSERT INTO student_updates (claim_id, student_id, update_message) VALUES (?,?,?)")
                    ->execute([$claim_id, $student_id, $msg]);
            }
        }
        header("Location: student_dashboard.php#updates");
        exit;
    }

    // Delete pending claim
    if ($action === 'delete_pending') {
        $claim_id = (int) ($_POST['claim_id'] ?? 0);
        if ($claim_id > 0) {
            // Verify ownership then delete
            $chk = $pdo->prepare("SELECT proof_file FROM claims_pending WHERE claim_id=? AND student_id=?");
            $chk->execute([$claim_id, $student_id]);
            $row = $chk->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                // Clean up proof file
                if ($row['proof_file'] && file_exists('uploads/' . $row['proof_file'])) {
                    unlink('uploads/' . $row['proof_file']);
                }
                $pdo->prepare("DELETE FROM claims_pending WHERE claim_id=? AND student_id=?")
                    ->execute([$claim_id, $student_id]);
            }
        }
        header("Location: student_dashboard.php?msg=deleted");
        exit;
    }
}

// ── Fetch data ────────────────────────────────────────
$student = $pdo->prepare("SELECT * FROM users WHERE user_id=?");
$student->execute([$student_id]);
$student = $student->fetch(PDO::FETCH_ASSOC);

$pending = $pdo->prepare("SELECT * FROM claims_pending WHERE student_id=? ORDER BY submitted_at DESC LIMIT 1");
$pending->execute([$student_id]);
$pendingClaim = $pending->fetch(PDO::FETCH_ASSOC);

$approved = $pdo->prepare("
    SELECT a.*, COALESCE(SUM(t.amount_sent),0) AS total_donated
    FROM claims_approved a
    LEFT JOIN transactions t ON a.claim_id=t.claim_id
    WHERE a.student_id=?
    GROUP BY a.claim_id
");
$approved->execute([$student_id]);
$approvedClaim = $approved->fetch(PDO::FETCH_ASSOC);

$denied = $pdo->prepare("SELECT * FROM claims_denied WHERE student_id=? ORDER BY denied_at DESC");
$denied->execute([$student_id]);
$deniedClaims = $denied->fetchAll(PDO::FETCH_ASSOC);

$raised = $remaining = $progress = 0;
if ($approvedClaim) {
    $goal      = (float) $approvedClaim['tuition_amount'];
    $raised    = (float) $approvedClaim['total_donated'];
    $remaining = max(0, $goal - $raised);
    $progress  = $goal > 0 ? min(100, round(($raised / $goal) * 100)) : 0;
}

// Profile completeness
$completeness = profile_completeness($student);

page_head('Student Dashboard');
?>
<?php require 'navbar.php'; ?>

<div class="container">
    <div class="page-card">

        <!-- Profile header -->
        <div class="dashboard-header">
            <div class="dashboard-avatar">
                <?php if ($student['profile_photo']): ?>
                    <img src="uploads/<?php echo htmlspecialchars($student['profile_photo']); ?>" alt="Photo">
                <?php else: ?>
                    <div class="avatar-placeholder">🎓</div>
                <?php endif; ?>
            </div>
            <div style="flex:1;">
                <h2><?php echo htmlspecialchars($student['full_name']); ?></h2>
                <p class="dashboard-meta"><?php echo student_meta($student, 'Student Account'); ?></p>
            </div>
        </div>

        <!-- Profile completeness -->
        <?php if ($completeness['pct'] < 100): ?>
            <div class="completeness-bar-wrap">
                <div class="completeness-header">
                    <span>Profile Completeness — <?php echo $completeness['pct']; ?>%</span>
                    <a href="account_settings.php" style="font-size:.85rem;font-weight:700;color:#214a9b;">Complete Profile →</a>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill completeness-fill" style="width:<?php echo $completeness['pct']; ?>%;"></div>
                </div>
                <?php if (!empty($completeness['missing'])): ?>
                    <p class="field-hint" style="margin-top:6px;">
                        Missing: <?php echo implode(', ', $completeness['missing']); ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="inline-actions" style="margin:20px 0 28px;">
            <a class="button" href="student_submit_claim.php">
                <?php echo ($pendingClaim || $approvedClaim) ? 'Update Claim' : 'Submit a Claim'; ?>
            </a>
            <?php if ($approvedClaim): ?>
                <a class="button secondary" href="student_profile.php?id=<?php echo $student_id; ?>">View Public Profile</a>
            <?php endif; ?>
            <a class="button secondary" href="account_settings.php">Account Settings</a>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
            <div class="message">Pending claim deleted.</div>
        <?php endif; ?>

        <!-- PENDING CLAIM -->
        <h3 class="section-divider">Pending Claim</h3>
        <?php if ($pendingClaim): ?>
            <div class="student-card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;">
                    <div>
                        <p style="margin:0 0 6px;"><strong>Status:</strong> <span class="badge badge-pending">Awaiting Review</span></p>
                        <p style="margin:0 0 6px;"><strong>Amount:</strong> $<?php echo number_format($pendingClaim['tuition_amount'], 2); ?></p>
                        <p style="margin:0 0 6px;"><strong>Description:</strong> <?php echo htmlspecialchars($pendingClaim['description']); ?></p>
                        <p style="margin:0 0 6px;"><strong>Submitted:</strong> <?php echo htmlspecialchars($pendingClaim['submitted_at']); ?></p>
                        <a href="uploads/<?php echo urlencode($pendingClaim['proof_file']); ?>" target="_blank">View Proof</a>
                    </div>
                    <div class="inline-actions">
                        <a class="button secondary btn-sm" href="student_submit_claim.php">Edit</a>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="action" value="delete_pending">
                            <input type="hidden" name="claim_id" value="<?php echo $pendingClaim['claim_id']; ?>">
                            <button type="submit" class="button btn-danger btn-sm"
                                onclick="return confirm('Delete this pending claim? This cannot be undone.')">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <p class="empty-state">No pending claim. <a href="student_submit_claim.php">Submit one →</a></p>
        <?php endif; ?>

        <!-- CLAIM EDIT HISTORY -->
        <?php
        $histStmt = $pdo->prepare("SELECT * FROM claims_history WHERE student_id=? ORDER BY saved_at DESC");
        $histStmt->execute([$student_id]);
        $claimHistory = $histStmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <?php if (count($claimHistory) > 0): ?>
            <div class="claim-history-wrap">
                <button class="claim-history-toggle" onclick="this.nextElementSibling.classList.toggle('open'); this.classList.toggle('open');">
                    📋 Claim Edit History (<?php echo count($claimHistory); ?>)
                    <svg class="chevron" width="12" height="12" viewBox="0 0 12 12" fill="none">
                        <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <div class="claim-history-list">
                    <table>
                        <tr><th>Amount</th><th>Description</th><th>Proof</th><th>Saved</th></tr>
                        <?php foreach ($claimHistory as $h): ?>
                            <tr>
                                <td>$<?php echo number_format($h['tuition_amount'], 2); ?></td>
                                <td style="font-size:.88rem;color:#64748b;"><?php echo htmlspecialchars($h['description']); ?></td>
                                <td><a href="uploads/<?php echo urlencode($h['proof_file']); ?>" target="_blank">View</a></td>
                                <td style="font-size:.85rem;color:#94a3b8;"><?php echo htmlspecialchars($h['saved_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- APPROVED CLAIM -->
        <h3 class="section-divider">Approved Claim</h3>
        <?php if ($approvedClaim): ?>
            <div class="student-card">
                <p>
                    <strong>Status:</strong>
                    <span class="badge badge-approved">Approved</span>
                    <span class="badge <?php echo $approvedClaim['funding_status'] === 'funded' ? 'badge-funded' : 'badge-open'; ?>">
                        <?php echo ucfirst($approvedClaim['funding_status']); ?>
                    </span>
                </p>
                <p><strong>Amount:</strong> $<?php echo number_format($approvedClaim['tuition_amount'], 2); ?></p>
                <p><strong>Description:</strong> <?php echo htmlspecialchars($approvedClaim['description']); ?></p>
                <p><strong>Approved:</strong> <?php echo htmlspecialchars($approvedClaim['approved_at']); ?></p>
                <?php render_progress_bar($raised, (float)$approvedClaim['tuition_amount']); ?>
                <a href="uploads/<?php echo urlencode($approvedClaim['proof_file']); ?>" target="_blank">View Proof</a>
            </div>

            <!-- Progress Updates -->
            <h3 class="section-divider" id="updates">Progress Updates</h3>
            <?php
            $updates = $pdo->prepare("SELECT * FROM student_updates WHERE claim_id=? ORDER BY created_at DESC");
            $updates->execute([$approvedClaim['claim_id']]);
            $updateList = $updates->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <form method="POST" style="margin-bottom:20px;">
                <input type="hidden" name="action" value="post_update">
                <input type="hidden" name="claim_id" value="<?php echo $approvedClaim['claim_id']; ?>">
                <label for="update_message">Post an Update</label>
                <textarea name="update_message" id="update_message" rows="3"
                    placeholder="Share your progress with donors..."></textarea>
                <button type="submit">Post Update</button>
            </form>
            <?php if (count($updateList) > 0): ?>
                <div class="update-feed">
                    <?php foreach ($updateList as $u): ?>
                        <div class="update-item">
                            <p><?php echo nl2br(htmlspecialchars($u['update_message'])); ?></p>
                            <span class="update-date"><?php echo htmlspecialchars($u['created_at']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty-state">No updates posted yet.</p>
            <?php endif; ?>

        <?php else: ?>
            <p class="empty-state">No approved claim yet.</p>
        <?php endif; ?>

        <!-- DENIED HISTORY -->
        <h3 class="section-divider">Denied Claims History</h3>
        <?php if (count($deniedClaims) > 0): ?>
            <div class="admin-claims-list">
                <?php foreach ($deniedClaims as $d): ?>
                    <div class="admin-claim-card">
                        <div class="admin-claim-header">
                            <div>
                                <p style="margin:0 0 4px;"><strong>Amount:</strong> $<?php echo number_format($d['tuition_amount'], 2); ?></p>
                                <p style="margin:0 0 4px;color:#3a4b68;"><?php echo htmlspecialchars($d['description']); ?></p>
                            </div>
                            <div style="text-align:right;">
                                <span class="badge badge-denied">Denied</span>
                                <p style="font-size:.82rem;color:#94a3b8;margin:4px 0 0;">
                                    <?php echo date('M j, Y', strtotime($d['denied_at'])); ?>
                                </p>
                            </div>
                        </div>

                        <?php if (!empty($d['denial_reason'])): ?>
                            <div class="notice-box" style="background:#fff5f5;border-color:#fca5a5;color:#991b1b;margin-top:10px;">
                                <strong>Reason:</strong> <?php echo htmlspecialchars($d['denial_reason']); ?>
                            </div>
                        <?php endif; ?>

                        <div class="admin-claim-actions">
                            <a href="uploads/<?php echo urlencode($d['proof_file']); ?>" target="_blank" class="button secondary btn-sm">View Proof</a>
                            <?php if (!$pendingClaim): ?>
                                <a class="button btn-sm" href="student_submit_claim.php?resubmit=<?php echo $d['claim_id']; ?>">
                                    Resubmit Claim
                                </a>
                            <?php else: ?>
                                <span style="font-size:.85rem;color:#94a3b8;">Edit your pending claim to address this.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty-state">No denied claims on record.</p>
        <?php endif; ?>

    </div>
</div>
</body></html>
