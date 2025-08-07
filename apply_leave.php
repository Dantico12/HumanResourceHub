<?php
$page_title = "Apply for Leave";
include 'includes/header.php';

// Get user's employee record for auto-filling
$userEmployee = getUserEmployee($user['id'], $conn);

// Initialize variables
$success = '';
$error = '';
$employees = [];
$leaveTypes = [];
$leaveApplications = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'apply_leave') {
        $employeeId = $userEmployee['id'] ?? 0;
        $leaveTypeId = (int)$_POST['leave_type_id'];
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        $reason = sanitizeInput($_POST['reason']);
        $emergencyContact = sanitizeInput($_POST['emergency_contact']);
        $emergencyPhone = sanitizeInput($_POST['emergency_phone']);

        // Calculate days
        $days = calculateBusinessDays($startDate, $endDate, $conn);

        // Check balance
        $balance = getLeaveTypeBalance($employeeId, $leaveTypeId, $conn);
        if ($days > $balance['remaining']) {
            $error = "Insufficient leave balance. You have {$balance['remaining']} days remaining.";
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO leave_applications 
                    (employee_id, leave_type_id, start_date, end_date, days_requested, reason, 
                     emergency_contact, emergency_phone, status, applied_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
                $stmt->bind_param("iissssss", $employeeId, $leaveTypeId, $startDate, $endDate, 
                                $days, $reason, $emergencyContact, $emergencyPhone);

                if ($stmt->execute()) {
                    setFlashMessage("Leave application submitted successfully!", "success");
                    header("Location: apply_leave.php");
                    exit();
                } else {
                    $error = "Error submitting application.";
                }
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Fetch data for dropdowns and displays
try {
    // Get leave types
    $leaveTypesResult = $conn->query("SELECT * FROM leave_types ORDER BY name");
    $leaveTypes = $leaveTypesResult->fetch_all(MYSQLI_ASSOC);

    // Get employees (for managers)
    if (hasPermission('hr_manager')) {
        $employeesResult = $conn->query("SELECT e.*, d.name as department_name, s.name as section_name 
                                        FROM employees e 
                                        LEFT JOIN departments d ON e.department_id = d.id 
                                        LEFT JOIN sections s ON e.section_id = s.id 
                                        ORDER BY e.first_name, e.last_name");
        $employees = $employeesResult->fetch_all(MYSQLI_ASSOC);
    }

    // Get leave applications
    if (hasPermission('hr_manager')) {
        // Managers can see all applications
        $applicationsQuery = "SELECT la.*, e.employee_id, e.first_name, e.last_name, 
                             lt.name as leave_type_name,
                             u.first_name as approver_first_name, u.last_name as approver_last_name
                             FROM leave_applications la
                             JOIN employees e ON la.employee_id = e.id
                             JOIN leave_types lt ON la.leave_type_id = lt.id
                             LEFT JOIN users u ON la.approver_id = u.id
                             ORDER BY la.applied_at DESC";
        $applicationsResult = $conn->query($applicationsQuery);
        $leaveApplications = $applicationsResult->fetch_all(MYSQLI_ASSOC);
    } else {
        // Regular employees see only their applications
        if ($userEmployee) {
            $stmt = $conn->prepare("SELECT la.*, lt.name as leave_type_name,
                                   u.first_name as approver_first_name, u.last_name as approver_last_name
                                   FROM leave_applications la
                                   JOIN leave_types lt ON la.leave_type_id = lt.id
                                   LEFT JOIN users u ON la.approver_id = u.id
                                   WHERE la.employee_id = ?
                                   ORDER BY la.applied_at DESC");
            $stmt->bind_param("i", $userEmployee['id']);
            $stmt->execute();
            $leaveApplications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
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

<!-- Apply Leave Tab -->
<div class="tab-content">
    <h3>Apply for Leave</h3>

    <?php if ($userEmployee): ?>
    <form method="POST" action="">
        <input type="hidden" name="action" value="apply_leave">
        
        <div class="form-grid">
        <div class="form-group">
            <label for="employee_id">Employee</label>
            <select id="employee_id" name="employee_id" class="form-control" required>
                <option value="">Select Employee</option>
                <?php 
                if ($userEmployee) {
                    echo '<!-- Current User: ' . htmlspecialchars($userEmployee['first_name'] . ' ' . $userEmployee['last_name']) . ' (Role: ' . $user['role'] . ') -->';

                    if ($user['role'] === 'hr_manager') {
                        // HR Manager: All employees including themselves
                        foreach ($employees as $employee) {
                            $selected = ($employee['id'] == $userEmployee['id']) ? 'selected' : '';
                            echo '<option value="' . $employee['id'] . '" ' . $selected . '>' . 
                                 htmlspecialchars($employee['employee_id'] . ' - ' . $employee['first_name'] . ' ' . $employee['last_name']) . 
                                 '</option>';
                        }
                    } elseif ($user['role'] === 'dept_head') {
                        // Department Head: All employees in their department including themselves
                        foreach ($employees as $employee) {
                            if ($employee['department_id'] == $userEmployee['department_id']) {
                                $selected = ($employee['id'] == $userEmployee['id']) ? 'selected' : '';
                                echo '<option value="' . $employee['id'] . '" ' . $selected . '>' . 
                                     htmlspecialchars($employee['employee_id'] . ' - ' . $employee['first_name'] . ' ' . $employee['last_name']) . 
                                     '</option>';
                            }
                        }
                    } elseif ($user['role'] === 'section_head') {
                        // Section Head: All employees in their section including themselves
                        foreach ($employees as $employee) {
                            if ($employee['section_id'] == $userEmployee['section_id']) {
                                $selected = ($employee['id'] == $userEmployee['id']) ? 'selected' : '';
                                echo '<option value="' . $employee['id'] . '" ' . $selected . '>' . 
                                     htmlspecialchars($employee['employee_id'] . ' - ' . $employee['first_name'] . ' ' . $employee['last_name']) . 
                                     '</option>';
                            }
                        }
                    } else {
                        // Regular Employee: Only their own name
                        echo '<option value="' . $userEmployee['id'] . '" selected>' . 
                             htmlspecialchars($userEmployee['employee_id'] . ' - ' . $userEmployee['first_name'] . ' ' . $userEmployee['last_name']) . 
                             '</option>';
                    }
                } else {
                    echo '<option value="">No employee record found</option>';
                }
                ?>
            </select>
        </div>
            <div class="form-group">
                <label for="leave_type_id">Leave Type</label>
                <select name="leave_type_id" id="leave_type_id" class="form-control" required>
                    <option value="">Select Leave Type</option>
                    <?php foreach ($leaveTypes as $type): ?>
                    <option value="<?php echo $type['id']; ?>">
                        <?php echo htmlspecialchars($type['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="start_date">Start Date</label>
                <input type="date" name="start_date" id="start_date" class="form-control" required>
            </div>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label for="end_date">End Date</label>
                <input type="date" name="end_date" id="end_date" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="calculated_days">Calculated Days</label>
                <input type="text" id="calculated_days" class="form-control" readonly>
            </div>
        </div>

        <div class="form-group">
            <label for="reason">Reason for Leave</label>
            <textarea name="reason" id="reason" class="form-control" rows="3" required></textarea>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label for="emergency_contact">Emergency Contact</label>
                <input type="text" name="emergency_contact" id="emergency_contact" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="emergency_phone">Emergency Phone</label>
                <input type="tel" name="emergency_phone" id="emergency_phone" class="form-control" required>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Submit Application</button>
            <button type="reset" class="btn btn-secondary">Reset Form</button>
        </div>
    </form>
    <?php else: ?>
    <div class="alert alert-warning">
        Your user account is not linked to an employee record. Please contact HR to resolve this issue.
    </div>
    <?php endif; ?>

    <!-- My Leave Applications -->
    <div class="table-container mt-4">
        <h3>My Leave Applications</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Leave Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th>Applied Date</th>
                    <th>Approver</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($leaveApplications)): ?>
                    <?php foreach ($leaveApplications as $application): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($application['leave_type_name']); ?></td>
                        <td><?php echo formatDate($application['start_date']); ?></td>
                        <td><?php echo formatDate($application['end_date']); ?></td>
                        <td><?php echo $application['days_requested']; ?></td>
                        <td>
                            <span class="badge <?php echo getStatusBadgeClass($application['status']); ?>">
                                <?php echo ucfirst($application['status']); ?>
                            </span>
                        </td>
                        <td><?php echo formatDate($application['applied_at']); ?></td>
                        <td>
                            <?php 
                            if ($application['approver_first_name']) {
                                echo htmlspecialchars($application['approver_first_name'] . ' ' . $application['approver_last_name']);
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">No leave applications found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>