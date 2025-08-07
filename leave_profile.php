<?php
$page_title = "My Leave Profile";
include 'includes/header.php';

// Get user's employee record
$userEmployee = getUserEmployee($user['id'], $conn);

// Initialize variables
$success = '';
$error = '';
$employee = null;
$leaveBalance = null;
$leaveHistory = [];

// Fetch data for profile tab
try {
    if ($userEmployee) {
        $employee = $userEmployee;

        // Get leave balance
        $balanceQuery = "SELECT * FROM leave_balances WHERE employee_id = ? ORDER BY leave_type_id";
        $stmt = $conn->prepare($balanceQuery);
        $stmt->bind_param("i", $employee['id']);
        $stmt->execute();
        $balanceResult = $stmt->get_result();
        $leaveBalance = $balanceResult->fetch_assoc();

        // Get leave history
        $historyQuery = "SELECT la.*, lt.name as leave_type_name
                         FROM leave_applications la
                         JOIN leave_types lt ON la.leave_type_id = lt.id
                         WHERE la.employee_id = ?
                         ORDER BY la.applied_at DESC";
        $stmt = $conn->prepare($historyQuery);
        $stmt->bind_param("i", $employee['id']);
        $stmt->execute();
        $historyResult = $stmt->get_result();
        $leaveHistory = $historyResult->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    $error = "Error fetching data: " . $e->getMessage();
}
?>

<?php include 'includes/nav_tabs.php'; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- My Leave Profile Tab -->
<div class="tab-content">
    <h3>My Leave Profile</h3>

    <?php if ($employee): ?>
    <!-- Employee Information -->
    <div class="employee-info mb-4">
        <div class="form-grid">
            <div>
                <h4>Employee Information</h4>
                <p><strong>Employee ID:</strong> <?php echo htmlspecialchars($employee['employee_id']); ?></p>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></p>
                <p><strong>Employment Type:</strong> <?php echo htmlspecialchars($employee['employment_type']); ?></p>
                <p><strong>Department:</strong> <?php echo htmlspecialchars($employee['department_id'] ?? 'N/A'); ?></p>
            </div>
            <div>
                <h4>Leave Balance (Current Year)</h4>
                <?php if ($leaveBalance): ?>
                    <p><strong>Annual Leave Entitled:</strong> <?php echo $leaveBalance['allocated'] ?? 0; ?> days</p>
                    <p><strong>Annual Leave Used:</strong> <?php echo $leaveBalance['used'] ?? 0; ?> days</p>
                    <p><strong>Annual Leave Balance:</strong> <span class="badge badge-info"><?php echo $leaveBalance['remaining'] ?? 0; ?> days</span></p>
                <?php else: ?>
                    <p class="text-muted">Leave balance not available. Please contact HR.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Leave History -->
    <div class="table-container">
        <h4>My Leave History</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>Leave Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days</th>
                    <th>Applied Date</th>
                    <th>Status</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leaveHistory)): ?>
                    <tr>
                        <td colspan="7" class="text-center">No leave applications found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($leaveHistory as $leave): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                        <td><?php echo formatDate($leave['start_date']); ?></td>
                        <td><?php echo formatDate($leave['end_date']); ?></td>
                        <td><?php echo $leave['days_requested']; ?></td>
                        <td><?php echo formatDate($leave['applied_at']); ?></td>
                        <td>
                            <?php
                            $statusClass = [
                                'pending' => 'badge-warning',
                                'approved' => 'badge-success',
                                'rejected' => 'badge-danger',
                                'cancelled' => 'badge-secondary'
                            ];
                            ?>
                            <span class="badge <?php echo $statusClass[$leave['status']] ?? 'badge-light'; ?>">
                                <?php echo ucfirst($leave['status']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars(substr($leave['reason'], 0, 50) . (strlen($leave['reason']) > 50 ? '...' : '')); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Quick Actions -->
    <div class="action-buttons mt-4">
        <a href="apply_leave.php" class="btn btn-primary">Apply for New Leave</a>
    </div>

    <?php else: ?>
    <div class="alert alert-warning">
        Employee record not found. Please contact HR to resolve this issue.
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>