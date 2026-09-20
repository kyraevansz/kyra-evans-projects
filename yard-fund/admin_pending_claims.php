<?php
require 'db.php';
require 'auth.php';
require 'helpers.php';
require_role('admin');

$claims = $pdo->query("
    SELECT p.claim_id, p.tuition_amount, p.description, p.proof_file, p.submitted_at,
           u.user_id AS student_id, u.full_name, u.email, u.major, u.classification,
           (SELECT claim_id FROM claims_approved WHERE student_id = p.student_id LIMIT 1) AS existing_approved
    FROM claims_pending p
    JOIN users u ON p.student_id = u.user_id
    ORDER BY p.submitted_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

page_head('Pending Claims');
?>
<?php require 'navbar.php'; ?>

<div class="container">
    <div class="page-card">
        <h2>Pending Claims</h2>
        <p style="color:#64748b;">
            <?php echo count($claims); ?> claim<?php echo count($claims) !== 1 ? 's' : ''; ?> awaiting review.
        </p>

        <div class="inline-actions" style="margin-bottom:24px;">
            <a class="button secondary" href="admin_dashboard.php">← Dashboard</a>
            <a class="button secondary" href="admin_claim_history.php">View Claim History</a>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div class="message"><?php echo htmlspecialchars($_GET['msg']); ?></div>
        <?php endif; ?>

        <?php if (count($claims) > 0): ?>
            <div class="admin-claims-list">
                <?php foreach ($claims as $claim): ?>
                    <div class="admin-claim-card" id="claim-<?php echo $claim['claim_id']; ?>">
                        <div class="admin-claim-header">
                            <div>
                                <h3>
                                    <a href="student_profile.php?id=<?php echo $claim['student_id']; ?>">
                                        <?php echo htmlspecialchars($claim['full_name']); ?>
                                    </a>
                                </h3>
                                <p class="dashboard-meta">
                                    <?php echo htmlspecialchars($claim['email']); ?>
                                    <?php $m = student_meta($claim, ''); echo $m ? ' · ' . $m : ''; ?>
                                </p>
                            </div>
                            <div style="text-align:right;">
                                <p class="claim-amount">$<?php echo number_format($claim['tuition_amount'], 2); ?></p>
                                <p style="font-size:.82rem;color:#94a3b8;">
                                    Submitted <?php echo htmlspecialchars($claim['submitted_at']); ?>
                                </p>
                            </div>
                        </div>

                        <p style="color:#3a4b68;margin:10px 0;"><?php echo htmlspecialchars($claim['description']); ?></p>

                        <?php if ($claim['existing_approved']): ?>
                            <div class="notice-box">
                                ⚠️ This student already has an approved claim. Approving will <strong>update their existing amount</strong>.
                            </div>
                        <?php endif; ?>

                        <div class="admin-claim-actions">
                            <a href="uploads/<?php echo urlencode($claim['proof_file']); ?>" target="_blank" class="button secondary">
                                📄 View Proof
                            </a>
                            <a href="admin_review_claim.php?id=<?php echo $claim['claim_id']; ?>&action=approve"
                               class="button"
                               onclick="return confirm('Approve claim for <?php echo htmlspecialchars(addslashes($claim['full_name'])); ?>?')">
                                ✓ Approve
                            </a>
                            <button type="button" class="button btn-danger"
                                onclick="toggleDenyForm(<?php echo $claim['claim_id']; ?>)">
                                ✕ Deny
                            </button>
                        </div>

                        <!-- Inline deny form — hidden until button clicked -->
                        <div class="deny-form" id="deny-form-<?php echo $claim['claim_id']; ?>" style="display:none;">
                            <form method="POST" action="admin_review_claim.php">
                                <input type="hidden" name="id" value="<?php echo $claim['claim_id']; ?>">
                                <input type="hidden" name="action" value="deny">
                                <label for="denial_reason_<?php echo $claim['claim_id']; ?>">
                                    Reason for denial <span style="color:#94a3b8;font-weight:400;">(shown to student)</span>
                                </label>
                                <textarea
                                    name="denial_reason"
                                    id="denial_reason_<?php echo $claim['claim_id']; ?>"
                                    rows="3"
                                    placeholder="e.g. Proof document is unclear. Please resubmit with a clearer image of your tuition balance statement."
                                ></textarea>
                                <div class="inline-actions">
                                    <button type="submit" class="button btn-danger">Confirm Denial</button>
                                    <button type="button" class="button secondary"
                                        onclick="toggleDenyForm(<?php echo $claim['claim_id']; ?>)">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="padding:40px;text-align:center;">
                <p style="font-size:2.5rem;">✅</p>
                <p class="empty-state">No pending claims — you're all caught up!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleDenyForm(id) {
    const form = document.getElementById('deny-form-' + id);
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
</script>
</body></html>
