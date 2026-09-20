<?php
require 'db.php';
require 'auth.php';
require 'helpers.php';
require_role('admin');

$stats = [
    'pending'   => $pdo->query("SELECT COUNT(*) FROM claims_pending")->fetchColumn(),
    'approved'  => $pdo->query("SELECT COUNT(*) FROM claims_approved")->fetchColumn(),
    'denied'    => $pdo->query("SELECT COUNT(*) FROM claims_denied")->fetchColumn(),
    'funded'    => $pdo->query("SELECT COUNT(*) FROM claims_approved WHERE funding_status='funded'")->fetchColumn(),
    'donations' => $pdo->query("SELECT COALESCE(SUM(amount_sent),0) FROM transactions")->fetchColumn(),
    'students'  => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type='student' AND is_active=1")->fetchColumn(),
    'donors'    => $pdo->query("SELECT COUNT(*) FROM users WHERE user_type='donor' AND is_active=1")->fetchColumn(),
    'suspended' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_active=0")->fetchColumn(),
];

$recentTx = $pdo->query("
    SELECT t.amount_sent, t.transaction_date, t.is_anonymous,
           u_d.full_name AS donor_name,
           u_s.full_name AS student_name, u_s.user_id AS student_id
    FROM transactions t
    JOIN users u_d ON t.donor_id = u_d.user_id
    JOIN claims_approved a ON t.claim_id = a.claim_id
    JOIN users u_s ON a.student_id = u_s.user_id
    ORDER BY t.transaction_date DESC LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

page_head('Admin Dashboard');
?>
<?php require 'navbar.php'; ?>

<div class="container">
    <div class="page-card" style="margin-bottom:24px;">
        <h2>Admin Dashboard</h2>
        <p style="color:#64748b;">Logged in as <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></p>

        <div class="inline-actions" style="margin-bottom:28px;">
            <a class="button" href="admin_pending_claims.php">
                Review Pending Claims
                <?php if ($stats['pending'] > 0): ?>
                    <span class="badge-count"><?php echo $stats['pending']; ?></span>
                <?php endif; ?>
            </a>
            <a class="button secondary" href="admin_claim_history.php">Claim History</a>
            <a class="button secondary" href="admin_users.php">Manage Users</a>
            <a class="button secondary" href="account_settings.php">Settings</a>
        </div>

        <div class="meta-grid">
            <div class="meta-box"><h4>Pending Claims</h4><p><?php echo $stats['pending']; ?></p></div>
            <div class="meta-box"><h4>Approved Claims</h4><p><?php echo $stats['approved']; ?></p></div>
            <div class="meta-box"><h4>Denied Claims</h4><p><?php echo $stats['denied']; ?></p></div>
            <div class="meta-box"><h4>Fully Funded</h4><p><?php echo $stats['funded']; ?></p></div>
            <div class="meta-box"><h4>Total Donations</h4><p>$<?php echo number_format($stats['donations'], 2); ?></p></div>
            <div class="meta-box"><h4>Active Students</h4><p><?php echo $stats['students']; ?></p></div>
            <div class="meta-box"><h4>Active Donors</h4><p><?php echo $stats['donors']; ?></p></div>
            <div class="meta-box"><h4>Suspended</h4><p><?php echo $stats['suspended']; ?></p></div>
        </div>
    </div>

    <div class="page-card">
        <h3 style="margin-top:0;">Recent Donations</h3>
        <?php if (count($recentTx) > 0): ?>
            <table>
                <tr><th>Donor</th><th>Student</th><th>Amount</th><th>Anonymous?</th><th>Date</th></tr>
                <?php foreach ($recentTx as $tx): ?>
                    <tr>
                        <td><?php echo $tx['is_anonymous'] ? '<em style="color:#94a3b8;">Anonymous</em>' : htmlspecialchars($tx['donor_name']); ?></td>
                        <td><a href="student_profile.php?id=<?php echo $tx['student_id']; ?>"><?php echo htmlspecialchars($tx['student_name']); ?></a></td>
                        <td>$<?php echo number_format($tx['amount_sent'], 2); ?></td>
                        <td><?php echo $tx['is_anonymous'] ? 'Yes' : 'No'; ?></td>
                        <td><?php echo htmlspecialchars($tx['transaction_date']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p class="empty-state">No donations yet.</p>
        <?php endif; ?>
    </div>
</div>
</body></html>
