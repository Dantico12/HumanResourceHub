<?php
$page_title = "Manage Leave Applications";
include 'includes/header.php';

// Check if user has permission to manage leaves
if (!hasPermission('hr_manager')) {
    setFlashMessage("Access denied. You don't have permission to manage leave applications.", "danger");
    header("Location: apply_leave.php");
    exit();
}

// Initialize variables
$success = '';
$error = '';
$pendingLeaves = [];
$approvedLeaves = [];
$rejectedLeaves = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'approve_leave':
            $applicationId = (int)$_POST['application_id'];
            $approverComments = sanitizeInput($_POST['approver_comments']);

            try {
                $conn->begin_transaction();

                // Get application details
                $stmt = $conn->prepare("SELECT * FROM leave_applications WHERE id = ?");
                $stmt->bind_param("i", $applicationId);
                $stmt->execute();
                $application = $stmt->get_result()->fetch_assoc();

                // Update application status
                $stmt = $conn->prepare("UPDATE leave_applications 
                                      SET status = 'approved', approver_id = ?, approver_comments = ?, 
                                          approved_date = NOW() WHERE id = ?");
                $stmt->bind_param("isi", $user['id'], $approverComments, $applicationId);
                $stmt->execute();

                // Update leave balance
                updateLeaveBalance($application['employee_id'], $application['leave_type_id'], 
                                 $application['days_requested'], $conn, 'use');

                $conn->commit();
                setFlashMessage("Leave application approved successfully!", "success");
                header("Location: manage_leave.php");
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error approving leave: " . $e->getMessage();
            }
            break;

        case 'reject_leave':
            $applicationId = (int)$_POST['application_id'];
            $approverComments = sanitizeInput($_POST['approver_comments']);

            try {
                $stmt = $conn->prepare("UPDATE leave_applications 
                                      SET status = 'rejected', approver_id = ?, approver_comments = ?, 
                                          approved_date = NOW() WHERE id = ?");
                $stmt->bind_param("isi", $user['id'], $approverComments, $applicationId);

                if ($stmt->execute()) {
                    setFlashMessage("Leave application rejected.", "warning");
                    header("Location: manage_leave.php");
                    exit();
                } else {
                    $error = "Error rejecting application.";
                }
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
            }
            break;
    }
}

// Handle GET actions (for quick approve/reject)
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action === 'approve_leave' && isset($_GET['id'])) {
        $leaveId = (int)$_GET['id'];
        try {
            $conn->begin_transaction();

            // Get application details
            $stmt = $conn->prepare("SELECT * FROM leave_applications WHERE id = ?");
            $stmt->bind_param("i", $leaveId);
            $stmt->execute();
            $application = $stmt->get_result()->fetch_assoc();

            // Update application status
            $stmt = $conn->prepare("UPDATE leave_applications SET status = 'approved', approver_id = ?, approved_date = NOW() WHERE id = ?");
            $stmt->bind_param("si", $user['id'], $leaveId);
            $stmt->execute();

            // Update leave balance
            updateLeaveBalance($application['employee_id'], $application['leave_type_id'], 
                             $application['days_requested'], $conn, 'use');

            $conn->commit();
            setFlashMessage("Leave application approved successfully!", "success");
        } catch (Exception $e) {
            $conn->rollback();
            setFlashMessage("Database error: " . $e->getMessage(), "danger");
        }

        header("Location: manage_leave.php");
        exit();
    }

    if ($action === 'reject_leave' && isset($_GET['id'])) {
        $leaveId = (int)$_GET['id'];
        try {
            $stmt = $conn->prepare("UPDATE leave_applications SET status = 'rejected', approver_id = ?, approved_date = NOW() WHERE id = ?");
            $stmt->bind_param("si", $user['id'], $leaveId);

            if ($stmt->execute()) {
                setFlashMessage("Leave application rejected!", "warning");
            } else {
                setFlashMessage("Error rejecting leave application.", "danger");
            }
        } catch (Exception $e) {
            setFlashMessage("Database error: " . $e->getMessage(), "danger");
        }

        header("Location: manage_leave.php");
        exit();
    }
}

// Fetch data for manage leave tab
try {
    // Get pending leaves
    $pendingQuery = "SELECT la.*, e.employee_id, e.first_name, e.last_name, 
                     lt.name as leave_type_name, d.name as department_name, s.name as section_name
                     FROM leave_applications la
                     JOIN employees e ON la.employee_id = e.id
                     JOIN leave_types lt ON la.leave_type_id = lt.id
                     LEFT JOIN departments d ON e.department_id = d.id
                     LEFT JOIN sections s ON e.section_id = s.id
                     WHERE la.status = 'pending'
                     ORDER BY la.applied_at DESC";
    $pendingResult = $conn->query($pendingQuery);
    $pendingLeaves = $pendingResult->fetch_all(MYSQLI_ASSOC);

    // Get approved leaves
    $approvedQuery = "SELECT la.*, e.employee_id, e.first_name, e.last_name, lt.name as leave_type_name
                      FROM leave_applications la
                      JOIN employees e ON la.employee_id = e.id
                      JOIN leave_types lt ON la.leave_type_id = lt.id
                      WHERE la.status = 'approved'
                      ORDER BY la.applied_at DESC
                      LIMIT 20";
    $approvedResult = $conn->query($approvedQuery);
    $approvedLeaves = $approvedResult->fetch_all(MYSQLI_ASSOC);

    // Get rejected leaves
    $rejectedQuery = "SELECT la.*, e.employee_id, e.first_name, e.last_name, lt.name as leave_type_name
                      FROM leave_applications la
                      JOIN employees e ON la.employee_id = e.id
                      JOIN leave_types lt ON la.leave_type_id = lt.id
                      WHERE la.status = 'rejected'
                      ORDER BY la.applied_at DESC
                      LIMIT 20";
    $rejectedResult = $conn->query($rejectedQuery);
    $rejectedLeaves = $rejectedResult->fetch_all(MYSQLI_ASSOC);

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

<!-- Manage Leave Tab -->
<div class="tab-content">
    <h3>Manage Leave Applications</h3>

    <!-- Pending Leaves -->
    <div class="table-container mb-4">
        <h4>Pending Leave Applications</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Leave Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days</th>
                    <th>Applied Date</th>
                    <th>Department/Section</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pendingLeaves)): ?>
                    <tr>
                        <td colspan="8" class="text-center">No pending leave applications</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pendingLeaves as $leave): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($leave['employee_id'] . ' - ' . $leave['first_name'] . ' ' . $leave['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                        <td><?php echo formatDate($leave['start_date']); ?></td>
                        <td><?php echo formatDate($leave['end_date']); ?></td>
                        <td><?php echo $leave['days_requested']; ?></td>
                        <td><?php echo formatDate($leave['applied_at']); ?></td>
                        <td><?php echo htmlspecialchars(($leave['department_name'] ?? 'N/A') . ' / ' . ($leave['section_name'] ?? 'N/A')); ?></td>
                        <td>
                            <a href="manage_leave.php?action=approve_leave&id=<?php echo $leave['id']; ?>" 
                               class="btn btn-success btn-sm" 
                               onclick="return confirm('Approve this leave application?')">Approve</a>
                            <a href="manage_leave.php?action=reject_leave&id=<?php echo $leave['id']; ?>" 
                               class="btn btn-danger btn-sm" 
                               onclick="return confirm('Reject this leave application?')">Reject</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Approved Leaves -->
    <div class="table-container mb-4">
        <h4>Recently Approved Leaves</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Leave Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($approvedLeaves)): ?>
                    <tr>
                        <td colspan="6" class="text-center">No approved leaves found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($approvedLeaves as $leave): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($leave['employee_id'] . ' - ' . $leave['first_name'] . ' ' . $leave['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                        <td><?php echo formatDate($leave['start_date']); ?></td>
                        <td><?php echo formatDate($leave['end_date']); ?></td>
                        <td><?php echo $leave['days_requested']; ?></td>
                        <td><span class="badge badge-success">Approved</span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Rejected Leaves -->
    <div class="table-container">
        <h4>Recently Rejected Leaves</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Leave Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rejectedLeaves)): ?>
                    <tr>
                        <td colspan="6" class="text-center">No rejected leaves found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rejectedLeaves as $leave): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($leave['employee_id'] . ' - ' . $leave['first_name'] . ' ' . $leave['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                        <td><?php echo formatDate($leave['start_date']); ?></td>
                        <td><?php echo formatDate($leave['end_date']); ?></td>
                        <td><?php echo $leave['days_requested']; ?></td>
                        <td><span class="badge badge-danger">Rejected</span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>