<?php
require 'db.php';
require 'auth.php';
require 'helpers.php';

$search = trim($_GET['search'] ?? '');

$sql = "SELECT a.claim_id, a.tuition_amount, a.description, a.funding_status,
               u.user_id AS student_id, u.full_name, u.major, u.gpa, u.classification, u.bio, u.profile_photo,
               COALESCE(SUM(t.amount_sent), 0) AS total_donated,
               (a.tuition_amount - COALESCE(SUM(t.amount_sent), 0)) AS remaining_balance
        FROM claims_approved a
        JOIN users u ON a.student_id = u.user_id
        LEFT JOIN transactions t ON a.claim_id = t.claim_id
        WHERE a.funding_status = 'open' AND u.is_active = 1";

$params = [];
if ($search !== '') {
    $sql .= " AND (u.full_name LIKE ? OR u.major LIKE ? OR u.classification LIKE ? OR u.bio LIKE ? OR a.description LIKE ?)";
    $term   = "%$search%";
    $params = [$term, $term, $term, $term, $term];
}

$sql .= " GROUP BY a.claim_id, a.tuition_amount, a.description, a.funding_status,
                   u.user_id, u.full_name, u.major, u.gpa, u.classification, u.bio, u.profile_photo
          ORDER BY a.approved_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

page_head('Browse Claims');
?>
<?php require 'navbar.php'; ?>

<section class="students-section">
    <div class="section-container">
        <div class="section-header">
            <h2>Students Who Need <span>Your Help</span></h2>
            <p>Browse verified student claims and help close outstanding tuition balances.</p>
        </div>

        <?php if (is_logged_in() && current_user_type() === 'donor'): ?>
            <div class="page-top-actions">
                <a class="back-link" href="donor_dashboard.php">← Donor Dashboard</a>
            </div>
        <?php endif; ?>

        <form method="GET" class="search-row donor-search-wrap">
            <div style="flex:1;">
                <label for="search">Search by name, major, classification, or keyword</label>
                <input type="text" name="search" id="search"
                    value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="e.g. Computer Science, Junior...">
            </div>
            <div style="display:flex;gap:8px;align-items:flex-end;">
                <button type="submit">Search</button>
                <?php if ($search !== ''): ?>
                    <a class="button secondary" href="donor_browse_claims.php">Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($search !== ''): ?>
            <p class="results-note">
                <?php echo count($claims); ?> result<?php echo count($claims) !== 1 ? 's' : ''; ?> for
                <strong><?php echo htmlspecialchars($search); ?></strong>
            </p>
        <?php endif; ?>

        <?php if (count($claims) > 0): ?>
            <div class="students-grid">
                <?php foreach ($claims as $claim): ?>
                    <?php
                    $goal      = (float) $claim['tuition_amount'];
                    $raised    = (float) $claim['total_donated'];
                    $remaining = (float) $claim['remaining_balance'];
                    $progress  = $goal > 0 ? min(100, round(($raised / $goal) * 100)) : 0;
                    ?>
                    <div class="student-card">
                        <div class="student-card-top">
                            <div class="student-avatar">
                                <?php if (!empty($claim['profile_photo'])): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($claim['profile_photo']); ?>"
                                         style="width:48px;height:48px;border-radius:50%;object-fit:cover;">
                                <?php else: ?>🎓<?php endif; ?>
                            </div>
                            <div>
                                <h3><?php echo htmlspecialchars($claim['full_name']); ?></h3>
                                <p><?php echo student_meta($claim, 'Student'); ?></p>
                            </div>
                        </div>

                        <?php if (!empty($claim['bio'])): ?>
                            <p class="student-story"><?php echo htmlspecialchars(mb_strimwidth($claim['bio'], 0, 120, '...')); ?></p>
                        <?php endif; ?>
                        <p class="student-story"><?php echo htmlspecialchars(mb_strimwidth($claim['description'], 0, 140, '...')); ?></p>

                        <div class="student-progress-row">
                            <span>Goal: $<?php echo number_format($goal, 2); ?></span>
                            <span><?php echo $progress; ?>%</span>
                        </div>
                        <div class="progress-bar"><div class="progress-fill" style="width:<?php echo $progress; ?>%;"></div></div>
                        <p class="progress-text">$<?php echo number_format($raised, 2); ?> raised · $<?php echo number_format($remaining, 2); ?> remaining</p>

                        <a class="button full-width" href="student_profile.php?id=<?php echo $claim['student_id']; ?>">
                            View Profile & Fund
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="page-card">
                <p><?php echo $search !== '' ? 'No claims match your search.' : 'No approved claims available right now.'; ?></p>
            </div>
        <?php endif; ?>
    </div>
</section>
</body></html>
