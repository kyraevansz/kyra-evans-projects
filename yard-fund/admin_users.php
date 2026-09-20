<?php
require 'db.php';
require 'auth.php';
require 'helpers.php';
require_role('admin');

$message = $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_id = (int) ($_POST['user_id'] ?? 0);
    $action    = $_POST['action'] ?? '';

    if ($target_id <= 0) {
        $error = "Invalid user.";
    } elseif ($target_id === (int) $_SESSION['user_id']) {
        $error = "You cannot suspend your own account.";
    } elseif (!in_array($action, ['suspend', 'unsuspend'], true)) {
        $error = "Invalid action.";
    } else {
        $pdo->prepare("UPDATE users SET is_active=? WHERE user_id=? AND user_type != 'admin'")
            ->execute([$action === 'unsuspend' ? 1 : 0, $target_id]);
        $message = $action === 'suspend' ? "User suspended." : "User reinstated.";
    }
}

$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'all';
$page   = max(1, (int) ($_GET['page'] ?? 1));
$per    = 20;
$offset = ($page - 1) * $per;

$where  = "WHERE user_type != 'admin'";
$params = [];

if ($search !== '') {
    $where   .= " AND (full_name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (in_array($filter, ['student', 'donor'])) {
    $where   .= " AND user_type = ?";
    $params[] = $filter;
}
if ($filter === 'suspended') {
    $where .= " AND is_active = 0";
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$dataStmt = $pdo->prepare("SELECT user_id, full_name, email, user_type, major, classification, is_active, created_at FROM users $where ORDER BY created_at DESC LIMIT $per OFFSET $offset");
$dataStmt->execute($params);
$users = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

$baseUrl = "admin_users.php?filter=$filter" . ($search ? "&search=" . urlencode($search) : "");

page_head('Manage Users');
?>
<?php require 'navbar.php'; ?>

<div class="container">
    <div class="page-card">
        <h2>Manage Users</h2>

        <div class="inline-actions" style="margin-bottom:24px;">
            <a class="button secondary" href="admin_dashboard.php">← Dashboard</a>
        </div>

        <?php if ($message): ?><div class="message"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error-message"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <form method="GET" class="search-row" style="margin-bottom:16px;">
            <div style="flex:1;">
                <label for="search">Search users</label>
                <input type="text" name="search" id="search"
                    value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Name or email...">
            </div>
            <div>
                <label for="filter">Filter</label>
                <select name="filter" id="filter" onchange="this.form.submit()" style="margin-bottom:0;">
                    <option value="all"       <?php echo $filter === 'all'       ? 'selected' : ''; ?>>All Users</option>
                    <option value="student"   <?php echo $filter === 'student'   ? 'selected' : ''; ?>>Students</option>
                    <option value="donor"     <?php echo $filter === 'donor'     ? 'selected' : ''; ?>>Donors</option>
                    <option value="suspended" <?php echo $filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>
            <div style="align-self:flex-end;"><button type="submit">Search</button></div>
        </form>

        <p style="color:#64748b;font-size:.9rem;margin-bottom:16px;">
            Showing <?php echo count($users); ?> of <?php echo $total; ?> user<?php echo $total !== 1 ? 's' : ''; ?>
        </p>

        <?php if (count($users) > 0): ?>
            <table>
                <tr><th>Name</th><th>Email</th><th>Role</th><th>Details</th><th>Status</th><th>Joined</th><th>Action</th></tr>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <?php if ($u['user_type'] === 'student'): ?>
                                <a href="student_profile.php?id=<?php echo $u['user_id']; ?>"><?php echo htmlspecialchars($u['full_name']); ?></a>
                            <?php else: ?>
                                <?php echo htmlspecialchars($u['full_name']); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td>
                            <span class="badge <?php echo $u['user_type'] === 'student' ? 'badge-open' : 'badge-approved'; ?>">
                                <?php echo ucfirst($u['user_type']); ?>
                            </span>
                        </td>
                        <td style="font-size:.85rem;color:#64748b;">
                            <?php echo $u['user_type'] === 'student' ? student_meta($u, '—') : '—'; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $u['is_active'] ? 'badge-approved' : 'badge-denied'; ?>">
                                <?php echo $u['is_active'] ? 'Active' : 'Suspended'; ?>
                            </span>
                        </td>
                        <td style="font-size:.85rem;color:#64748b;"><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                        <td>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                <input type="hidden" name="action" value="<?php echo $u['is_active'] ? 'suspend' : 'unsuspend'; ?>">
                                <button type="submit"
                                    class="button btn-sm <?php echo $u['is_active'] ? 'btn-danger' : ''; ?>"
                                    onclick="return confirm('<?php echo $u['is_active'] ? 'Suspend' : 'Reinstate'; ?> <?php echo htmlspecialchars(addslashes($u['full_name'])); ?>?')">
                                    <?php echo $u['is_active'] ? 'Suspend' : 'Reinstate'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <?php render_pagination($total, $per, $page, $baseUrl); ?>

        <?php else: ?>
            <p class="empty-state">No users found.</p>
        <?php endif; ?>
    </div>
</div>
</body></html>
