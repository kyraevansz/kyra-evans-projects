<?php
require 'db.php';
require 'auth.php';
require 'helpers.php';
require_role('admin');

$tab    = in_array($_GET['tab'] ?? '', ['approved', 'denied']) ? $_GET['tab'] : 'approved';
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));
$per    = 15;
$offset = ($page - 1) * $per;

if ($tab === 'approved') {
    $countSql = "SELECT COUNT(*) FROM claims_approved a JOIN users u ON a.student_id = u.user_id";
    $dataSql  = "SELECT a.claim_id, a.tuition_amount, a.description, a.funding_status, a.approved_at,
                        u.user_id AS student_id, u.full_name, u.email, u.major, u.classification,
                        COALESCE(SUM(t.amount_sent),0) AS total_donated
                 FROM claims_approved a
                 JOIN users u ON a.student_id = u.user_id
                 LEFT JOIN transactions t ON a.claim_id = t.claim_id";
    $group    = " GROUP BY a.claim_id, a.tuition_amount, a.description, a.funding_status, a.approved_at,
                           u.user_id, u.full_name, u.email, u.major, u.classification";
    $order    = " ORDER BY a.approved_at DESC";
} else {
    $countSql = "SELECT COUNT(*) FROM claims_denied a JOIN users u ON a.student_id = u.user_id";
    $dataSql  = "SELECT a.claim_id, a.tuition_amount, a.description, a.denial_reason,
                        a.submitted_at, a.denied_at,
                        u.user_id AS student_id, u.full_name, u.email, u.major, u.classification
                 FROM claims_denied a
                 JOIN users u ON a.student_id = u.user_id";
    $group    = "";
    $order    = " ORDER BY a.denied_at DESC";
}

$where  = "";
$params = [];
if ($search !== '') {
    $where    = " WHERE (u.full_name LIKE ? OR u.email LIKE ? OR a.description LIKE ?)";
    $term     = "%$search%";
    $params   = [$term, $term, $term];
}

$total = (int) $pdo->prepare($countSql . $where)->execute($params) ?
    $pdo->prepare($countSql . $where)->execute($params) : 0;
$countStmt = $pdo->prepare($countSql . $where);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$dataStmt = $pdo->prepare($dataSql . $where . $group . $order . " LIMIT $per OFFSET $offset");
$dataStmt->execute($params);
$rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

$baseUrl = "admin_claim_history.php?tab=$tab" . ($search ? "&search=" . urlencode($search) : "");

page_head('Claim History');
?>
<?php require 'navbar.php'; ?>

<div class="container">
    <div class="page-card">
        <h2>Claim History</h2>

        <div class="inline-actions" style="margin-bottom:24px;">
            <a class="button secondary" href="admin_dashboard.php">← Dashboard</a>
            <a class="button secondary" href="admin_pending_claims.php">Pending Claims</a>
        </div>

        <!-- Tabs -->
        <div class="history-tabs">
            <a href="admin_claim_history.php?tab=approved<?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
               class="history-tab <?php echo $tab === 'approved' ? 'active' : ''; ?>">
                ✓ Approved Claims
            </a>
            <a href="admin_claim_history.php?tab=denied<?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
               class="history-tab <?php echo $tab === 'denied' ? 'active' : ''; ?>">
                ✕ Denied Claims
            </a>
        </div>

        <!-- Search -->
        <form method="GET" class="search-row" style="margin:20px 0 16px;">
            <input type="hidden" name="tab" value="<?php echo $tab; ?>">
            <div style="flex:1;">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Search by name, email, or description...">
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit">Search</button>
                <?php if ($search): ?>
                    <a class="button secondary" href="admin_claim_history.php?tab=<?php echo $tab; ?>">Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <p style="color:#64748b;font-size:.9rem;margin-bottom:16px;">
            <?php echo $total; ?> record<?php echo $total !== 1 ? 's' : ''; ?>
            <?php echo $search ? ' matching "' . htmlspecialchars($search) . '"' : ''; ?>
        </p>

        <?php if (count($rows) > 0): ?>
            <table>
                <?php if ($tab === 'approved'): ?>
                    <tr><th>Student</th><th>Amount</th><th>Raised</th><th>Status</th><th>Approved</th><th>View</th></tr>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td>
                                <a href="student_profile.php?id=<?php echo $r['student_id']; ?>"><?php echo htmlspecialchars($r['full_name']); ?></a>
                                <?php $m = student_meta($r, ''); if ($m) echo '<br><small style="color:#94a3b8;">' . $m . '</small>'; ?>
                            </td>
                            <td>$<?php echo number_format($r['tuition_amount'], 2); ?></td>
                            <td>$<?php echo number_format($r['total_donated'], 2); ?></td>
                            <td><span class="badge <?php echo $r['funding_status'] === 'funded' ? 'badge-funded' : 'badge-open'; ?>"><?php echo ucfirst($r['funding_status']); ?></span></td>
                            <td style="font-size:.85rem;color:#64748b;"><?php echo date('M j, Y', strtotime($r['approved_at'])); ?></td>
                            <td><a href="student_profile.php?id=<?php echo $r['student_id']; ?>">Profile</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><th>Student</th><th>Amount</th><th>Denial Reason</th><th>Submitted</th><th>Denied</th></tr>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($r['full_name']); ?>
                                <?php $m = student_meta($r, ''); if ($m) echo '<br><small style="color:#94a3b8;">' . $m . '</small>'; ?>
                            </td>
                            <td>$<?php echo number_format($r['tuition_amount'], 2); ?></td>
                            <td style="font-size:.88rem;color:#64748b;">
                                <?php echo $r['denial_reason'] ? htmlspecialchars($r['denial_reason']) : '<em style="color:#cbd5e1;">No reason given</em>'; ?>
                            </td>
                            <td style="font-size:.85rem;color:#64748b;"><?php echo date('M j, Y', strtotime($r['submitted_at'])); ?></td>
                            <td style="font-size:.85rem;color:#64748b;"><?php echo date('M j, Y', strtotime($r['denied_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>

            <?php render_pagination($total, $per, $page, $baseUrl); ?>

        <?php else: ?>
            <p class="empty-state">No <?php echo $tab; ?> claims found.</p>
        <?php endif; ?>

    </div>
</div>
</body></html>
