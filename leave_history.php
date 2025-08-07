<?php
$page_title = "Leave History";
include 'includes/header.php';

// Check if user has permission to view leave history
if (!hasPermission('hr_manager')) {
    setFlashMessage("Access denied. You don't have permission to view leave history.", "danger");
    header("Location: apply_leave.php");
    exit();
}

// Initialize variables
$success = '';
$error = '';
$currentLeaves = [];
$allLeaves = [];

// Fetch data for leave history tab
try {
    // Get current leaves
    $currentQuery = "SELECT la.*, e.employee_id, e.first_name, e.last_name, lt.name as leave_type_name
                     FROM leave_applications la
                     JOIN employees e ON la.employee_id = e.id
                     JOIN leave_types lt ON la.leave_type_id = lt.id
                     WHERE la.start_date <= CURDATE() AND la.end_date >= CURDATE() AND la.status = 'approved'
                     ORDER BY la.start_date";
    $currentResult = $conn->query($currentQuery);
    $currentLeaves = $currentResult->fetch_all(MYSQLI_ASSOC);

    // Get all leaves
    $allQuery = "SELECT la.*, e.employee_id, e.first_name, e.last_name, lt.name as leave_type_name
                 FROM leave_applications la
                 JOIN employees e ON la.employee_id = e.id
                 JOIN leave_types lt ON la.leave_type_id = lt.id
                 ORDER BY la.applied_at DESC
                 LIMIT 50";
    $allResult = $conn->query($allQuery);
    $allLeaves = $allResult->fetch_all(MYSQLI_ASSOC);

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

<!-- Leave History Tab -->
<div class="tab-content">
    <h3>Leave History</h3>

    <!-- Employees Currently on Leave -->
    <div class="table-container mb-4">
        <h4>Employees Currently on Leave</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Leave Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days</th>
                    <th>Remaining Days</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($currentLeaves)): ?>
                    <tr>
                        <td colspan="6" class="text-center">No employees currently on leave</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($currentLeaves as $leave): ?>
                    <?php
                        $today = new DateTime();
                        $endDate = new DateTime($leave['end_date']);
                        $remainingDays = $today->diff($endDate)->days;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($leave['employee_id'] . ' - ' . $leave['first_name'] . ' ' . $leave['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                        <td><?php echo formatDate($leave['start_date']); ?></td>
                        <td><?php echo formatDate($leave['end_date']); ?></td>
                        <td><?php echo $leave['days_requested']; ?></td>
                        <td><?php echo $remainingDays; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- All Leave History -->
    <div class="table-container">
        <h4>All Leave Applications (Recent 50)</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Leave Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days</th>
                    <th>Applied Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allLeaves)): ?>
                    <tr>
                        <td colspan="7" class="text-center">No leave applications found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($allLeaves as $leave): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($leave['employee_id'] . ' - ' . $leave['first_name'] . ' ' . $leave['last_name']); ?></td>
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
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>