<?php
require 'db.php';
require 'auth.php';
require 'helpers.php';
require_role('donor');

$donor_id = $_SESSION['user_id'];
$page     = max(1, (int) ($_GET['page'] ?? 1));
$per      = 15;
$offset   = ($page - 1) * $per;

$donor = $pdo->prepare("SELECT * FROM users WHERE user_id=?");
$donor->execute([$donor_id]);
$donor = $donor->fetch(PDO::FETCH_ASSOC);

$s = $pdo->prepare("SELECT COALESCE(SUM(amount_sent),0) FROM transactions WHERE donor_id=?");
$s->execute([$donor_id]); $totalGiven = (float)$s->fetchColumn();

$s = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE donor_id=?");
$s->execute([$donor_id]); $donationCount = (int)$s->fetchColumn();

$s = $pdo->prepare("SELECT COUNT(DISTINCT a.student_id) FROM transactions t JOIN claims_approved a ON t.claim_id=a.claim_id WHERE t.donor_id=?");
$s->execute([$donor_id]); $studentsSupported = (int)$s->fetchColumn();

// Monthly totals for chart (last 12 months)
$monthlyStmt = $pdo->prepare("
    SELECT DATE_FORMAT(transaction_date, '%Y-%m') AS month,
           SUM(amount_sent) AS total
    FROM transactions
    WHERE donor_id = ?
      AND transaction_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY month
    ORDER BY month ASC
");
$monthlyStmt->execute([$donor_id]);
$monthlyRaw = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

// Build full 12-month array (fill in zeros for months with no donations)
$monthlyLabels = [];
$monthlyData   = [];
$monthlyMap    = array_column($monthlyRaw, 'total', 'month');
for ($i = 11; $i >= 0; $i--) {
    $key             = date('Y-m', strtotime("-$i months"));
    $label           = date('M Y', strtotime("-$i months"));
    $monthlyLabels[] = $label;
    $monthlyData[]   = (float) ($monthlyMap[$key] ?? 0);
}

// Recent 5 donations (always shown)
$recentStmt = $pdo->prepare("
    SELECT t.amount_sent, t.transaction_date, t.is_anonymous,
           a.claim_id, u.full_name AS student_name, u.user_id AS student_id
    FROM transactions t
    JOIN claims_approved a ON t.claim_id=a.claim_id
    JOIN users u ON a.student_id=u.user_id
    WHERE t.donor_id=?
    ORDER BY t.transaction_date DESC
    LIMIT 5
");
$recentStmt->execute([$donor_id]);
$recentDonations = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

// Paginated full history
$totalDonations = $donationCount;
$historyStmt = $pdo->prepare("
    SELECT t.transaction_id, t.amount_sent, t.transaction_date, t.is_anonymous,
           a.claim_id, a.tuition_amount, a.funding_status,
           COALESCE(SUM(t2.amount_sent),0) AS total_donated,
           u.full_name AS student_name, u.user_id AS student_id, u.major
    FROM transactions t
    JOIN claims_approved a ON t.claim_id=a.claim_id
    JOIN users u ON a.student_id=u.user_id
    LEFT JOIN transactions t2 ON a.claim_id=t2.claim_id
    WHERE t.donor_id=?
    GROUP BY t.transaction_id, t.amount_sent, t.transaction_date, t.is_anonymous,
             a.claim_id, a.tuition_amount, a.funding_status, u.full_name, u.user_id, u.major
    ORDER BY t.transaction_date DESC
    LIMIT $per OFFSET $offset
");
$historyStmt->execute([$donor_id]);
$donations = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

page_head('Donor Dashboard');
?>
<?php require 'navbar.php'; ?>

<div class="container">
    <div class="page-card">
        <div class="dashboard-header">
            <div class="dashboard-avatar">
                <?php if ($donor['profile_photo']): ?>
                    <img src="uploads/<?php echo htmlspecialchars($donor['profile_photo']); ?>" alt="Photo">
                <?php else: ?>
                    <div class="avatar-placeholder">💝</div>
                <?php endif; ?>
            </div>
            <div>
                <h2><?php echo htmlspecialchars($donor['full_name']); ?></h2>
                <p class="dashboard-meta"><?php echo htmlspecialchars($donor['email']); ?></p>
            </div>
        </div>

        <div class="inline-actions" style="margin-bottom:28px;">
            <a class="button" href="donor_browse_claims.php">Browse Claims</a>
            <a class="button secondary" href="account_settings.php">Account Settings</a>
        </div>

        <div class="meta-grid">
            <div class="meta-box"><h4>Total Donated</h4><p>$<?php echo number_format($totalGiven, 2); ?></p></div>
            <div class="meta-box"><h4>Donations Made</h4><p><?php echo $donationCount; ?></p></div>
            <div class="meta-box"><h4>Students Supported</h4><p><?php echo $studentsSupported; ?></p></div>
        </div>

        <!-- Monthly chart -->
        <?php if ($donationCount > 0): ?>
            <h3 class="section-divider">Donations Over Time</h3>
            <div style="max-width:700px;margin:0 auto 8px;">
                <canvas id="donationChart" height="120"></canvas>
            </div>
        <?php endif; ?>

        <h3 class="section-divider">Recent Donations</h3>
        <?php if (count($recentDonations) > 0): ?>
            <table>
                <tr><th>Student</th><th>Amount</th><th>Anonymous?</th><th>Date</th><th>View</th></tr>
                <?php foreach ($recentDonations as $d): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($d['student_name']); ?></td>
                        <td>$<?php echo number_format($d['amount_sent'], 2); ?></td>
                        <td><?php echo $d['is_anonymous'] ? 'Yes' : 'No'; ?></td>
                        <td><?php echo htmlspecialchars($d['transaction_date']); ?></td>
                        <td><a href="student_profile.php?id=<?php echo $d['student_id']; ?>">View Profile</a></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p class="empty-state">No donations yet. <a href="donor_browse_claims.php">Browse claims →</a></p>
        <?php endif; ?>

        <h3 class="section-divider">All Donations</h3>
        <?php if (count($donations) > 0): ?>
            <table>
                <tr><th>Student</th><th>My Donation</th><th>Anon?</th><th>Progress</th><th>Status</th><th>Date</th></tr>
                <?php foreach ($donations as $d): ?>
                    <?php $pct = $d['tuition_amount'] > 0 ? min(100, round(($d['total_donated'] / $d['tuition_amount']) * 100)) : 0; ?>
                    <tr>
                        <td>
                            <a href="student_profile.php?id=<?php echo $d['student_id']; ?>"><?php echo htmlspecialchars($d['student_name']); ?></a>
                            <?php if (!empty($d['major'])): ?><br><small style="color:#94a3b8;"><?php echo htmlspecialchars($d['major']); ?></small><?php endif; ?>
                        </td>
                        <td>$<?php echo number_format($d['amount_sent'], 2); ?></td>
                        <td><?php echo $d['is_anonymous'] ? 'Yes' : 'No'; ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div class="progress-bar" style="flex:1;height:8px;"><div class="progress-fill" style="width:<?php echo $pct; ?>%;"></div></div>
                                <span style="font-size:.82rem;color:#64748b;"><?php echo $pct; ?>%</span>
                            </div>
                            <small style="color:#94a3b8;">$<?php echo number_format($d['total_donated'],2); ?> of $<?php echo number_format($d['tuition_amount'],2); ?></small>
                        </td>
                        <td><span class="badge <?php echo $d['funding_status']==='funded'?'badge-funded':'badge-open'; ?>"><?php echo ucfirst($d['funding_status']); ?></span></td>
                        <td><?php echo htmlspecialchars($d['transaction_date']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php render_pagination($totalDonations, $per, $page, 'donor_dashboard.php'); ?>
        <?php else: ?>
            <p class="empty-state">No donation history yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php if ($donationCount > 0): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('donationChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($monthlyLabels); ?>,
        datasets: [{
            label: 'Amount Donated ($)',
            data: <?php echo json_encode($monthlyData); ?>,
            backgroundColor: 'rgba(249,138,30,0.75)',
            borderColor: '#f98a1e',
            borderWidth: 2,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => '$' + ctx.parsed.y.toFixed(2)
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { callback: val => '$' + val }
            }
        }
    }
});
</script>
<?php endif; ?>
</body></html>
