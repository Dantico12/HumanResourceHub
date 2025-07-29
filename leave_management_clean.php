<?php
/**
 * Enhanced Leave Management System
 * Clean, optimized version with improved maintainability
 * 
 * Features:
 * - Multi-level approval workflow
 * - Smart leave balance deduction with fallback
 * - Comprehensive audit trail
 * - Role-based access control
 * - Real-time balance calculation
 */

// Initialize error reporting and session
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ob_start();

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Security check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

class LeaveManagementSystem {
    private $conn;
    private $user;
    private $userEmployee;
    
    // Role hierarchy for permission checking
    private const ROLE_HIERARCHY = [
        'super_admin' => 5,
        'hr_manager' => 4,
        'dept_head' => 3,
        'section_head' => 2,
        'manager' => 1,
        'employee' => 0
    ];

    public function __construct($conn) {
        $this->conn = $conn;
        $this->initializeUser();
    }

    /**
     * Initialize user data and employee record
     */
    private function initializeUser() {
        $this->user = [
            'first_name' => isset($_SESSION['user_name']) ? explode(' ', $_SESSION['user_name'])[0] : 'User',
            'last_name' => isset($_SESSION['user_name']) ? (explode(' ', $_SESSION['user_name'])[1] ?? '') : '',
            'role' => $_SESSION['user_role'] ?? 'guest',
            'id' => $_SESSION['user_id']
        ];

        // Get user's employee record
        $query = "SELECT e.* FROM employees e 
                  LEFT JOIN users u ON u.employee_id = e.employee_id 
                  WHERE u.id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->user['id']);
        $stmt->execute();
        $this->userEmployee = $stmt->get_result()->fetch_assoc();
    }

    /**
     * Check if user has required permission
     */
    public function hasPermission($required_role) {
        $user_level = self::ROLE_HIERARCHY[$this->user['role']] ?? 0;
        $required_level = self::ROLE_HIERARCHY[$required_role] ?? 0;
        return $user_level >= $required_level;
    }

    /**
     * Sanitize user input
     */
    public static function sanitizeInput($input) {
        return htmlspecialchars(strip_tags(trim($input ?? '')));
    }

    /**
     * Format date for display
     */
    public static function formatDate($date) {
        if (!$date) return 'N/A';
        return date('M d, Y', strtotime($date));
    }

    /**
     * Get badge class for status
     */
    public static function getStatusBadgeClass($status) {
        $classes = [
            'approved' => 'badge-success',
            'rejected' => 'badge-danger',
            'pending' => 'badge-warning',
            'pending_section_head' => 'badge-info',
            'pending_dept_head' => 'badge-primary',
            'pending_hr' => 'badge-warning'
        ];
        return $classes[$status] ?? 'badge-secondary';
    }

    /**
     * Get display name for status
     */
    public static function getStatusDisplayName($status) {
        $names = [
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'pending' => 'Pending',
            'pending_section_head' => 'Pending Section Head Approval',
            'pending_dept_head' => 'Pending Department Head Approval',
            'pending_hr' => 'Pending HR Approval'
        ];
        return $names[$status] ?? ucfirst($status);
    }

    /**
     * Get flash message from session
     */
    public function getFlashMessage() {
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            $type = $_SESSION['flash_type'] ?? 'info';
            unset($_SESSION['flash_message'], $_SESSION['flash_type']);
            return ['message' => $message, 'type' => $type];
        }
        return false;
    }

    /**
     * Set flash message in session
     */
    public function setFlashMessage($message, $type = 'info') {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }

    /**
     * Calculate business days between dates
     */
    public function calculateBusinessDays($startDate, $endDate, $leaveTypeId = null) {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $days = 0;

        // Get holidays
        $holidays = $this->getHolidays($startDate, $endDate);
        
        // Check leave type settings
        $includeWeekends = false;
        if ($leaveTypeId) {
            $leaveType = $this->getLeaveTypeDetails($leaveTypeId);
            $includeWeekends = ($leaveType['counts_weekends'] ?? 0) == 1;
        }

        $current = clone $start;
        while ($current <= $end) {
            $dayOfWeek = $current->format('N'); // 1 = Monday, 7 = Sunday
            $currentDate = $current->format('Y-m-d');

            // Skip weekends if not included
            if (!$includeWeekends && ($dayOfWeek == 6 || $dayOfWeek == 7)) {
                $current->add(new DateInterval('P1D'));
                continue;
            }

            // Skip holidays
            if (!in_array($currentDate, $holidays)) {
                $days++;
            }

            $current->add(new DateInterval('P1D'));
        }

        return $days;
    }

    /**
     * Get holidays between dates
     */
    private function getHolidays($startDate, $endDate) {
        $query = "SELECT date FROM holidays WHERE date BETWEEN ? AND ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();

        $holidays = [];
        while ($row = $result->fetch_assoc()) {
            $holidays[] = $row['date'];
        }
        return $holidays;
    }

    /**
     * Get leave type details
     */
    public function getLeaveTypeDetails($leaveTypeId) {
        $query = "SELECT * FROM leave_types WHERE id = ? AND is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $leaveTypeId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Get leave balance for employee and leave type
     */
    public function getLeaveTypeBalance($employeeId, $leaveTypeId) {
        $leaveType = $this->getLeaveTypeDetails($leaveTypeId);
        if (!$leaveType) {
            return ['allocated' => 0, 'used' => 0, 'remaining' => 0];
        }

        // Determine balance type
        $isAnnual = (stripos($leaveType['name'], 'annual') !== false);
        $isSick = (stripos($leaveType['name'], 'sick') !== false);
        $maxDaysPerYear = (int)($leaveType['max_days_per_year'] ?? 0);

        if ($isAnnual) {
            $query = "SELECT annual_leave_entitled as allocated, annual_leave_used as used, 
                             annual_leave_balance as remaining 
                      FROM leave_balances WHERE employee_id = ? AND leave_type_id = ?";
        } else {
            $usedColumn = $isSick ? 'sick_leave_used' : 'other_leave_used';
            $query = "SELECT {$usedColumn} as used, {$maxDaysPerYear} as allocated,
                             GREATEST(0, {$maxDaysPerYear} - {$usedColumn}) as remaining
                      FROM leave_balances WHERE employee_id = ? AND leave_type_id = ?";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $employeeId, $leaveTypeId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return [
                'allocated' => (int)$row['allocated'],
                'used' => (int)$row['used'],
                'remaining' => (int)$row['remaining'],
                'leave_type_name' => $leaveType['name'],
                'max_days_per_year' => $maxDaysPerYear,
                'counts_weekends' => $leaveType['counts_weekends'] ?? 0,
                'deducted_from_annual' => $leaveType['deducted_from_annual'] ?? 0
            ];
        }

        // Create record if doesn't exist
        $this->initializeLeaveBalance($employeeId, $leaveTypeId, $maxDaysPerYear);
        return [
            'allocated' => $maxDaysPerYear,
            'used' => 0,
            'remaining' => $maxDaysPerYear,
            'leave_type_name' => $leaveType['name'],
            'max_days_per_year' => $maxDaysPerYear,
            'counts_weekends' => $leaveType['counts_weekends'] ?? 0,
            'deducted_from_annual' => $leaveType['deducted_from_annual'] ?? 0
        ];
    }

    /**
     * Initialize leave balance record
     */
    private function initializeLeaveBalance($employeeId, $leaveTypeId, $allocated) {
        $currentYear = date('Y');
        $query = "INSERT INTO leave_balances 
                  (employee_id, leave_type_id, financial_year, 
                   annual_leave_entitled, annual_leave_used, annual_leave_balance,
                   sick_leave_used, other_leave_used, created_at) 
                  VALUES (?, ?, ?, ?, 0, ?, 0, 0, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iiiii", $employeeId, $leaveTypeId, $currentYear, $allocated, $allocated);
        $stmt->execute();
    }

    /**
     * Calculate leave deduction plan
     */
    public function calculateLeaveDeduction($employeeId, $leaveTypeId, $requestedDays) {
        $leaveType = $this->getLeaveTypeDetails($leaveTypeId);
        $leaveBalance = $this->getLeaveTypeBalance($employeeId, $leaveTypeId);
        
        $deductionPlan = [
            'primary_deduction' => 0,
            'annual_deduction' => 0,
            'unpaid_days' => 0,
            'warnings' => [],
            'is_valid' => true,
            'total_days' => $requestedDays
        ];

        if (!$leaveType) {
            $deductionPlan['is_valid'] = false;
            $deductionPlan['warnings'][] = "Invalid leave type selected.";
            return $deductionPlan;
        }

        // Check maximum days per year
        if ($leaveType['max_days_per_year'] && $requestedDays > $leaveType['max_days_per_year']) {
            $deductionPlan['warnings'][] = "Requested days ({$requestedDays}) exceed maximum allowed per year ({$leaveType['max_days_per_year']}).";
        }

        $availablePrimaryBalance = $leaveBalance['remaining'];

        if ($requestedDays <= $availablePrimaryBalance) {
            // Sufficient primary balance
            $deductionPlan['primary_deduction'] = $requestedDays;
            $deductionPlan['warnings'][] = "Will be deducted from {$leaveType['name']} balance.";
        } else {
            // Insufficient primary balance
            $primaryUsed = $availablePrimaryBalance;
            $remainingDays = $requestedDays - $primaryUsed;
            
            $deductionPlan['primary_deduction'] = $primaryUsed;

            // Check fallback to annual leave
            if ($leaveType['deducted_from_annual'] == 1 && stripos($leaveType['name'], 'maternity') === false && $remainingDays > 0) {
                $annualBalance = $this->getAnnualLeaveBalance($employeeId);
                
                if ($annualBalance['remaining'] >= $remainingDays) {
                    $deductionPlan['annual_deduction'] = $remainingDays;
                    $deductionPlan['warnings'][] = "Primary balance insufficient. {$primaryUsed} days from {$leaveType['name']}, {$remainingDays} days from Annual Leave.";
                } else {
                    $annualUsed = $annualBalance['remaining'];
                    $unpaidDays = $remainingDays - $annualUsed;
                    
                    $deductionPlan['annual_deduction'] = $annualUsed;
                    $deductionPlan['unpaid_days'] = $unpaidDays;
                    $deductionPlan['warnings'][] = "Insufficient leave balance. {$primaryUsed} days from {$leaveType['name']}, {$annualUsed} days from Annual Leave, {$unpaidDays} days will be unpaid.";
                }
            } else {
                $deductionPlan['unpaid_days'] = $remainingDays;
                if ($primaryUsed > 0) {
                    $deductionPlan['warnings'][] = "{$primaryUsed} days from {$leaveType['name']}, {$remainingDays} days will be unpaid.";
                } else {
                    $deductionPlan['warnings'][] = "No available balance. All {$requestedDays} days will be unpaid.";
                }
            }
        }

        return $deductionPlan;
    }

    /**
     * Get annual leave balance
     */
    private function getAnnualLeaveBalance($employeeId) {
        $annualTypeId = $this->getAnnualLeaveTypeId();
        return $this->getLeaveTypeBalance($employeeId, $annualTypeId);
    }

    /**
     * Get annual leave type ID
     */
    private function getAnnualLeaveTypeId() {
        $stmt = $this->conn->prepare("SELECT id FROM leave_types WHERE name LIKE '%annual%' LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return $row['id'];
        }
        return 1; // Default
    }

    /**
     * Process leave deduction
     */
    public function processLeaveDeduction($employeeId, $leaveTypeId, $deductionPlan) {
        $this->conn->begin_transaction();
        
        try {
            // Deduct from primary leave type
            if ($deductionPlan['primary_deduction'] > 0) {
                $this->updateLeaveBalance($employeeId, $leaveTypeId, $deductionPlan['primary_deduction'], 'use');
            }
            
            // Deduct from annual leave if applicable
            if ($deductionPlan['annual_deduction'] > 0) {
                $annualTypeId = $this->getAnnualLeaveTypeId();
                $this->updateLeaveBalance($employeeId, $annualTypeId, $deductionPlan['annual_deduction'], 'use');
            }
            
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    /**
     * Update leave balance
     */
    public function updateLeaveBalance($employeeId, $leaveTypeId, $days, $action = 'use') {
        $leaveType = $this->getLeaveTypeDetails($leaveTypeId);
        if (!$leaveType) return false;

        $isAnnual = (stripos($leaveType['name'], 'annual') !== false);
        $isSick = (stripos($leaveType['name'], 'sick') !== false);
        
        $balance = $this->getLeaveTypeBalance($employeeId, $leaveTypeId);

        if ($action == 'use') {
            $newUsed = $balance['used'] + $days;
            $newRemaining = max(0, $balance['allocated'] - $newUsed);
        } else {
            $newUsed = max(0, $balance['used'] - $days);
            $newRemaining = $balance['allocated'] - $newUsed;
        }

        if ($isAnnual) {
            $query = "UPDATE leave_balances SET annual_leave_used = ?, annual_leave_balance = ?, updated_at = NOW() WHERE employee_id = ? AND leave_type_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("iiii", $newUsed, $newRemaining, $employeeId, $leaveTypeId);
        } elseif ($isSick) {
            $query = "UPDATE leave_balances SET sick_leave_used = ?, updated_at = NOW() WHERE employee_id = ? AND leave_type_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("iii", $newUsed, $employeeId, $leaveTypeId);
        } else {
            $query = "UPDATE leave_balances SET other_leave_used = ?, updated_at = NOW() WHERE employee_id = ? AND leave_type_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("iii", $newUsed, $employeeId, $leaveTypeId);
        }
        
        return $stmt->execute();
    }

    /**
     * Log leave transaction
     */
    public function logLeaveTransaction($applicationId, $employeeId, $leaveTypeId, $days, $deductionPlan) {
        $transactionData = [
            'primary_leave_type' => $leaveTypeId,
            'primary_days' => $deductionPlan['primary_deduction'],
            'annual_days' => $deductionPlan['annual_deduction'],
            'unpaid_days' => $deductionPlan['unpaid_days'],
            'warnings' => implode('; ', $deductionPlan['warnings'])
        ];
        
        $query = "INSERT INTO leave_transactions (application_id, employee_id, transaction_date, transaction_type, details) VALUES (?, ?, NOW(), 'deduction', ?)";
        $stmt = $this->conn->prepare($query);
        $details = json_encode($transactionData);
        $stmt->bind_param("iis", $applicationId, $employeeId, $details);
        return $stmt->execute();
    }

    /**
     * Log leave history
     */
    public function logLeaveHistory($applicationId, $action, $comments = '') {
        $query = "INSERT INTO leave_history (leave_application_id, action, performed_by, comments, performed_at) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iiss", $applicationId, $action, $this->user['id'], $comments);
        return $stmt->execute();
    }

    /**
     * Get managers for employee
     */
    public function getEmployeeManagers($employeeId) {
        $query = "SELECT
            e.section_id, e.department_id,
            (SELECT e2.id FROM employees e2 JOIN users u2 ON u2.employee_id = e2.employee_id WHERE e2.section_id = e.section_id AND u2.role = 'section_head' LIMIT 1) as section_head_emp_id,
            (SELECT e3.id FROM employees e3 JOIN users u3 ON u3.employee_id = e3.employee_id WHERE e3.department_id = e.department_id AND u3.role = 'dept_head' LIMIT 1) as dept_head_emp_id
            FROM employees e WHERE e.id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $employeeId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Submit leave application
     */
    public function submitLeaveApplication($data) {
        try {
            $this->conn->begin_transaction();

            $employeeId = (int)$data['employee_id'];
            $leaveTypeId = (int)$data['leave_type_id'];
            $startDate = $data['start_date'];
            $endDate = $data['end_date'];
            $reason = self::sanitizeInput($data['reason']);

            // Calculate days and deduction plan
            $days = $this->calculateBusinessDays($startDate, $endDate, $leaveTypeId);
            $deductionPlan = $this->calculateLeaveDeduction($employeeId, $leaveTypeId, $days);

            if (!$deductionPlan['is_valid']) {
                throw new Exception(implode(' ', $deductionPlan['warnings']));
            }

            // Get managers
            $managers = $this->getEmployeeManagers($employeeId);
            $sectionHeadEmpId = $managers['section_head_emp_id'] ?? null;
            $deptHeadEmpId = $managers['dept_head_emp_id'] ?? null;

            // Insert application
            $query = "INSERT INTO leave_applications
                (employee_id, leave_type_id, start_date, end_date, days_requested, reason,
                 status, applied_at, section_head_emp_id, dept_head_emp_id, deduction_details,
                 primary_days, annual_days, unpaid_days)
                VALUES (?, ?, ?, ?, ?, ?, 'pending_section_head', NOW(), ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($query);
            $deductionDetails = json_encode($deductionPlan);
            $stmt->bind_param("iissssiisiii", 
                $employeeId, $leaveTypeId, $startDate, $endDate, $days, $reason,
                $sectionHeadEmpId, $deptHeadEmpId, $deductionDetails,
                $deductionPlan['primary_deduction'], $deductionPlan['annual_deduction'], $deductionPlan['unpaid_days']
            );

            if (!$stmt->execute()) {
                throw new Exception("Error submitting application: " . $this->conn->error);
            }

            $applicationId = $this->conn->insert_id;

            // Log transaction and history
            $this->logLeaveTransaction($applicationId, $employeeId, $leaveTypeId, $days, $deductionPlan);
            $this->logLeaveHistory($applicationId, 'applied', "Leave application submitted for $days days");

            $this->conn->commit();

            $warningMessages = implode('<br>', $deductionPlan['warnings']);
            return [
                'success' => true,
                'message' => "Leave application submitted successfully!<br><strong>Deduction Summary:</strong><br>" . $warningMessages
            ];

        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Approve leave application
     */
    public function approveLeaveApplication($applicationId, $approverRole, $comments = '') {
        try {
            $this->conn->begin_transaction();

            // Get application details
            $query = "SELECT * FROM leave_applications WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $applicationId);
            $stmt->execute();
            $application = $stmt->get_result()->fetch_assoc();

            if (!$application) {
                throw new Exception("Application not found");
            }

            // Determine next status
            $currentStatus = $application['status'];
            $newStatus = $this->getNextApprovalStatus($currentStatus, $approverRole);

            // Validate approver authority
            if (!$this->canApprove($application, $approverRole)) {
                throw new Exception("You are not authorized to approve this application");
            }

            // Update application
            $updateQuery = "UPDATE leave_applications SET status = ?";
            $params = [$newStatus];
            $types = "s";

            if ($approverRole === 'section_head') {
                $updateQuery .= ", section_head_approval = 'approved', section_head_approved_by = ?, section_head_approved_at = NOW()";
                $params[] = $this->userEmployee['id'];
                $types .= "i";
            } elseif ($approverRole === 'dept_head') {
                $updateQuery .= ", dept_head_approval = 'approved', dept_head_approved_by = ?, dept_head_approved_at = NOW()";
                $params[] = $this->userEmployee['id'];
                $types .= "i";
            } elseif ($approverRole === 'hr_manager') {
                $updateQuery .= ", approver_id = ?, approved_date = NOW()";
                $params[] = $this->user['id'];
                $types .= "i";
            }

            if ($comments) {
                $updateQuery .= ", approver_comments = ?";
                $params[] = $comments;
                $types .= "s";
            }

            $updateQuery .= " WHERE id = ?";
            $params[] = $applicationId;
            $types .= "i";

            $stmt = $this->conn->prepare($updateQuery);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            // Process deductions if final approval
            if ($newStatus === 'approved' && $application['deduction_details']) {
                $deductionPlan = json_decode($application['deduction_details'], true);
                $this->processLeaveDeduction($application['employee_id'], $application['leave_type_id'], $deductionPlan);
            }

            // Log history
            $action = $approverRole . '_approved';
            $this->logLeaveHistory($applicationId, $action, $comments ?: "Approved by {$approverRole}");

            $this->conn->commit();
            return ['success' => true, 'message' => 'Leave application approved successfully!'];

        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Reject leave application
     */
    public function rejectLeaveApplication($applicationId, $approverRole, $comments = '') {
        try {
            $this->conn->begin_transaction();

            $query = "SELECT * FROM leave_applications WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $applicationId);
            $stmt->execute();
            $application = $stmt->get_result()->fetch_assoc();

            if (!$application) {
                throw new Exception("Application not found");
            }

            if (!$this->canApprove($application, $approverRole)) {
                throw new Exception("You are not authorized to reject this application");
            }

            // Update application
            $updateQuery = "UPDATE leave_applications SET status = 'rejected'";
            $params = [];
            $types = "";

            if ($approverRole === 'section_head') {
                $updateQuery .= ", section_head_approval = 'rejected', section_head_approved_by = ?, section_head_approved_at = NOW()";
                $params[] = $this->userEmployee['id'];
                $types .= "i";
            } elseif ($approverRole === 'dept_head') {
                $updateQuery .= ", dept_head_approval = 'rejected', dept_head_approved_by = ?, dept_head_approved_at = NOW()";
                $params[] = $this->userEmployee['id'];
                $types .= "i";
            } elseif ($approverRole === 'hr_manager') {
                $updateQuery .= ", approver_id = ?, approved_date = NOW()";
                $params[] = $this->user['id'];
                $types .= "i";
            }

            if ($comments) {
                $updateQuery .= ", approver_comments = ?";
                $params[] = $comments;
                $types .= "s";
            }

            $updateQuery .= " WHERE id = ?";
            $params[] = $applicationId;
            $types .= "i";

            $stmt = $this->conn->prepare($updateQuery);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();

            // Log rejection
            $deductionPlan = ['warnings' => ['Application rejected by ' . $approverRole]];
            $this->logLeaveTransaction($applicationId, $application['employee_id'], $application['leave_type_id'], 
                                      $application['days_requested'], $deductionPlan);

            $action = $approverRole . '_rejected';
            $this->logLeaveHistory($applicationId, $action, $comments ?: "Rejected by {$approverRole}");

            $this->conn->commit();
            return ['success' => true, 'message' => 'Leave application rejected.'];

        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get next approval status
     */
    private function getNextApprovalStatus($currentStatus, $approverRole) {
        $statusFlow = [
            'pending_section_head' => ['section_head' => 'pending_dept_head'],
            'pending_dept_head' => ['dept_head' => 'approved'],
            'pending' => ['hr_manager' => 'approved']
        ];

        return $statusFlow[$currentStatus][$approverRole] ?? 'approved';
    }

    /**
     * Check if user can approve application
     */
    private function canApprove($application, $role) {
        if ($role === 'hr_manager') {
            return $this->hasPermission('hr_manager');
        }

        if ($role === 'section_head' && $this->hasPermission('section_head')) {
            $empQuery = "SELECT section_id FROM employees WHERE id = ?";
            $stmt = $this->conn->prepare($empQuery);
            $stmt->bind_param("i", $application['employee_id']);
            $stmt->execute();
            $empSection = $stmt->get_result()->fetch_assoc();
            return $empSection['section_id'] == $this->userEmployee['section_id'];
        }

        if ($role === 'dept_head' && $this->hasPermission('dept_head')) {
            $empQuery = "SELECT department_id FROM employees WHERE id = ?";
            $stmt = $this->conn->prepare($empQuery);
            $stmt->bind_param("i", $application['employee_id']);
            $stmt->execute();
            $empDept = $stmt->get_result()->fetch_assoc();
            return $empDept['department_id'] == $this->userEmployee['department_id'];
        }

        return false;
    }

    /**
     * Get data for specific tab
     */
    public function getTabData($tab) {
        $data = [];

        try {
            switch ($tab) {
                case 'apply':
                    $data = $this->getApplyTabData();
                    break;
                case 'manage':
                    $data = $this->getManageTabData();
                    break;
                case 'history':
                    $data = $this->getHistoryTabData();
                    break;
                case 'profile':
                    $data = $this->getProfileTabData();
                    break;
                case 'holidays':
                    $data = $this->getHolidaysTabData();
                    break;
            }
        } catch (Exception $e) {
            error_log("Error fetching tab data: " . $e->getMessage());
        }

        return $data;
    }

    /**
     * Get data for apply tab
     */
    private function getApplyTabData() {
        $data = [
            'leaveTypes' => [],
            'employees' => [],
            'leaveApplications' => [],
            'leaveBalances' => []
        ];

        // Get leave types
        $query = "SELECT * FROM leave_types WHERE is_active = 1 ORDER BY name";
        $result = $this->conn->query($query);
        $data['leaveTypes'] = $result->fetch_all(MYSQLI_ASSOC);

        // Get employees (for managers)
        if ($this->hasPermission('section_head')) {
            $query = "SELECT e.*, d.name as department_name, s.name as section_name 
                      FROM employees e 
                      LEFT JOIN departments d ON e.department_id = d.id 
                      LEFT JOIN sections s ON e.section_id = s.id";
            
            if ($this->user['role'] === 'dept_head') {
                $query .= " WHERE e.department_id = " . (int)$this->userEmployee['department_id'];
            } elseif ($this->user['role'] === 'section_head') {
                $query .= " WHERE e.section_id = " . (int)$this->userEmployee['section_id'];
            }
            
            $query .= " ORDER BY e.first_name, e.last_name";
            $result = $this->conn->query($query);
            $data['employees'] = $result->fetch_all(MYSQLI_ASSOC);
        }

        // Get user's leave applications
        if ($this->userEmployee) {
            $query = "SELECT la.*, lt.name as leave_type_name,
                             u.first_name as approver_first_name, u.last_name as approver_last_name
                      FROM leave_applications la
                      JOIN leave_types lt ON la.leave_type_id = lt.id
                      LEFT JOIN users u ON la.approver_id = u.id
                      WHERE la.employee_id = ?
                      ORDER BY la.applied_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $this->userEmployee['id']);
            $stmt->execute();
            $data['leaveApplications'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            // Get leave balances
            $query = "SELECT lb.*, lt.name as leave_type_name, lt.max_days_per_year, 
                             lt.counts_weekends, lt.deducted_from_annual
                      FROM leave_balances lb
                      JOIN leave_types lt ON lb.leave_type_id = lt.id
                      WHERE lb.employee_id = ? AND lt.is_active = 1
                      ORDER BY lt.name";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $this->userEmployee['id']);
            $stmt->execute();
            $data['leaveBalances'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        return $data;
    }

    /**
     * Get data for manage tab
     */
    private function getManageTabData() {
        $data = [
            'pendingLeaves' => [],
            'approvedLeaves' => [],
            'rejectedLeaves' => []
        ];

        if (!$this->hasPermission('section_head')) {
            return $data;
        }

        $baseQuery = "SELECT la.*, e.employee_id, e.first_name, e.last_name,
                      lt.name as leave_type_name, d.name as department_name, s.name as section_name,
                      la.primary_days, la.annual_days, la.unpaid_days
                      FROM leave_applications la
                      JOIN employees e ON la.employee_id = e.id
                      JOIN leave_types lt ON la.leave_type_id = lt.id
                      LEFT JOIN departments d ON e.department_id = d.id
                      LEFT JOIN sections s ON e.section_id = s.id";

        // Filter based on role
        if ($this->user['role'] === 'section_head') {
            $whereClause = " WHERE e.section_id = " . (int)$this->userEmployee['section_id'];
            $pendingStatus = "'pending_section_head'";
        } elseif ($this->user['role'] === 'dept_head') {
            $whereClause = " WHERE e.department_id = " . (int)$this->userEmployee['department_id'];
            $pendingStatus = "'pending_dept_head'";
        } else {
            $whereClause = "";
            $pendingStatus = "'pending', 'pending_section_head', 'pending_dept_head'";
        }

        // Pending leaves
        $pendingQuery = $baseQuery . $whereClause . " AND la.status IN ({$pendingStatus}) ORDER BY la.applied_at DESC";
        $result = $this->conn->query($pendingQuery);
        $data['pendingLeaves'] = $result->fetch_all(MYSQLI_ASSOC);

        // Approved leaves
        $approvedQuery = $baseQuery . $whereClause . " AND la.status = 'approved' ORDER BY la.applied_at DESC LIMIT 20";
        $result = $this->conn->query($approvedQuery);
        $data['approvedLeaves'] = $result->fetch_all(MYSQLI_ASSOC);

        // Rejected leaves
        $rejectedQuery = $baseQuery . $whereClause . " AND la.status = 'rejected' ORDER BY la.applied_at DESC LIMIT 20";
        $result = $this->conn->query($rejectedQuery);
        $data['rejectedLeaves'] = $result->fetch_all(MYSQLI_ASSOC);

        return $data;
    }

    /**
     * Get data for profile tab
     */
    private function getProfileTabData() {
        $data = [
            'employee' => $this->userEmployee,
            'leaveBalances' => [],
            'leaveHistory' => []
        ];

        if (!$this->userEmployee) {
            return $data;
        }

        // Get leave balances
        $query = "SELECT lb.*, lt.name as leave_type_name, lt.max_days_per_year, 
                         lt.counts_weekends, lt.deducted_from_annual
                  FROM leave_balances lb
                  JOIN leave_types lt ON lb.leave_type_id = lt.id
                  WHERE lb.employee_id = ? AND lt.is_active = 1
                  ORDER BY lt.name";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->userEmployee['id']);
        $stmt->execute();
        $data['leaveBalances'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Get leave history
        $query = "SELECT la.*, lt.name as leave_type_name,
                         la.primary_days, la.annual_days, la.unpaid_days
                  FROM leave_applications la
                  JOIN leave_types lt ON la.leave_type_id = lt.id
                  WHERE la.employee_id = ?
                  ORDER BY la.applied_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->userEmployee['id']);
        $stmt->execute();
        $data['leaveHistory'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return $data;
    }

    /**
     * Get data for holidays tab
     */
    private function getHolidaysTabData() {
        $query = "SELECT * FROM holidays ORDER BY date DESC";
        $result = $this->conn->query($query);
        return ['holidays' => $result->fetch_all(MYSQLI_ASSOC)];
    }

    /**
     * Get data for history tab
     */
    private function getHistoryTabData() {
        $data = [
            'currentLeaves' => [],
            'allLeaves' => []
        ];

        if (!$this->hasPermission('hr_manager')) {
            return $data;
        }

        // Current leaves
        $query = "SELECT la.*, e.employee_id, e.first_name, e.last_name, lt.name as leave_type_name
                  FROM leave_applications la
                  JOIN employees e ON la.employee_id = e.id
                  JOIN leave_types lt ON la.leave_type_id = lt.id
                  WHERE la.start_date <= CURDATE() AND la.end_date >= CURDATE() AND la.status = 'approved'
                  ORDER BY la.start_date";
        $result = $this->conn->query($query);
        $data['currentLeaves'] = $result->fetch_all(MYSQLI_ASSOC);

        // All leaves
        $query = "SELECT la.*, e.employee_id, e.first_name, e.last_name, lt.name as leave_type_name
                  FROM leave_applications la
                  JOIN employees e ON la.employee_id = e.id
                  JOIN leave_types lt ON la.leave_type_id = lt.id
                  ORDER BY la.applied_at DESC
                  LIMIT 50";
        $result = $this->conn->query($query);
        $data['allLeaves'] = $result->fetch_all(MYSQLI_ASSOC);

        return $data;
    }

    /**
     * Add holiday
     */
    public function addHoliday($name, $date, $description, $isRecurring) {
        try {
            $query = "INSERT INTO holidays (name, date, description, is_recurring) VALUES (?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("sssi", $name, $date, $description, $isRecurring);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Holiday added successfully!'];
            } else {
                return ['success' => false, 'message' => 'Error adding holiday.'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Get user data
     */
    public function getUser() {
        return $this->user;
    }

    /**
     * Get user employee data
     */
    public function getUserEmployee() {
        return $this->userEmployee;
    }
}

// Initialize the system
$conn = getConnection();
$leaveSystem = new LeaveManagementSystem($conn);

// Get tab and initialize variables
$tab = isset($_GET['tab']) ? LeaveManagementSystem::sanitizeInput($_GET['tab']) : 'apply';
$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'apply_leave':
            $result = $leaveSystem->submitLeaveApplication($_POST);
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
            break;

        case 'add_holiday':
            if ($leaveSystem->hasPermission('hr_manager')) {
                $name = LeaveManagementSystem::sanitizeInput($_POST['name']);
                $date = $_POST['date'];
                $description = LeaveManagementSystem::sanitizeInput($_POST['description']);
                $isRecurring = isset($_POST['is_recurring']) ? 1 : 0;

                $result = $leaveSystem->addHoliday($name, $date, $description, $isRecurring);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
            }
            break;
    }
}

// Handle GET actions (approvals/rejections)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $applicationId = (int)$_GET['id'];

    $roleActions = [
        'section_head_approve' => ['role' => 'section_head', 'action' => 'approve'],
        'section_head_reject' => ['role' => 'section_head', 'action' => 'reject'],
        'dept_head_approve' => ['role' => 'dept_head', 'action' => 'approve'],
        'dept_head_reject' => ['role' => 'dept_head', 'action' => 'reject'],
        'approve_leave' => ['role' => 'hr_manager', 'action' => 'approve'],
        'reject_leave' => ['role' => 'hr_manager', 'action' => 'reject']
    ];

    if (isset($roleActions[$action])) {
        $roleAction = $roleActions[$action];
        
        if ($roleAction['action'] === 'approve') {
            $result = $leaveSystem->approveLeaveApplication($applicationId, $roleAction['role']);
        } else {
            $result = $leaveSystem->rejectLeaveApplication($applicationId, $roleAction['role']);
        }

        $leaveSystem->setFlashMessage($result['message'], $result['success'] ? 'success' : 'danger');
        header("Location: leave_management_clean.php?tab=manage");
        exit();
    }
}

// Get tab data
$tabData = $leaveSystem->getTabData($tab);
$user = $leaveSystem->getUser();
$userEmployee = $leaveSystem->getUserEmployee();

// Update todos
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clean Leave Management - HR Management System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Enhanced styles for better UX */
        .leave-balance-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .balance-header {
            font-weight: bold;
            font-size: 16px;
            color: #495057;
            margin-bottom: 12px;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 8px;
        }
        
        .balance-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        
        .balance-item {
            text-align: center;
            padding: 8px;
            border-radius: 4px;
        }
        
        .balance-allocated {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .balance-used {
            background-color: #fff3e0;
            color: #f57c00;
        }
        
        .balance-remaining {
            background-color: #e8f5e8;
            color: #388e3c;
        }
        
        .balance-negative {
            background-color: #ffebee;
            color: #d32f2f;
        }
        
        .info-text {
            font-size: 12px;
            color: #6c757d;
            margin-top: 4px;
        }
        
        .warning-text {
            color: #ff9800;
            font-size: 12px;
        }
        
        .unpaid-warning {
            color: #d32f2f;
            font-size: 12px;
            font-weight: bold;
        }
        
        .deduction-preview {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 16px;
            margin: 16px 0;
        }
        
        .deduction-item {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .deduction-item:last-child {
            border-bottom: none;
        }
        
        .leave-tabs {
            display: flex;
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 20px;
        }
        
        .leave-tab {
            padding: 12px 20px;
            text-decoration: none;
            color: #6c757d;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .leave-tab:hover {
            color: #495057;
            background-color: #f8f9fa;
        }
        
        .leave-tab.active {
            color: #007bff;
            border-bottom-color: #007bff;
            background-color: #fff;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .table-container {
            overflow-x: auto;
            margin: 20px 0;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .table tr:hover {
            background-color: #f8f9fa;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .badge-success { background-color: #d4edda; color: #155724; }
        .badge-danger { background-color: #f8d7da; color: #721c24; }
        .badge-warning { background-color: #fff3cd; color: #856404; }
        .badge-info { background-color: #d1ecf1; color: #0c5460; }
        .badge-primary { background-color: #d1ecf1; color: #004085; }
        .badge-secondary { background-color: #e2e3e5; color: #383d41; }
        
        .alert {
            padding: 12px 20px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .alert-warning {
            color: #856404;
            background-color: #fff3cd;
            border-color: #ffeaa7;
        }
        
        .alert-info {
            color: #0c5460;
            background-color: #d1ecf1;
            border-color: #bee5eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-brand">
                <h1>HR System</h1>
                <p>Management Portal</p>
            </div>
            <div class="nav">
                <ul>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="employees.php">Employees</a></li>
                    <?php if ($leaveSystem->hasPermission('hr_manager')): ?>
                    <li><a href="departments.php">Departments</a></li>
                    <?php endif; ?>
                    <?php if ($leaveSystem->hasPermission('super_admin') || $leaveSystem->hasPermission('hr_manager')): ?>
                    <li><a href="admin.php">Admin</a></li>
                    <?php endif; ?>
                    <li><a href="leave_management_clean.php" class="active">Leave Management</a></li>
                    <?php if ($leaveSystem->hasPermission('hr_manager')): ?>
                    <li><a href="reports.php">Reports</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h1>Clean Leave Management System</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                    <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
                </div>
            </div>

            <div class="content">
                <!-- Flash Messages -->
                <?php $flash = $leaveSystem->getFlashMessage(); if ($flash): ?>
                    <div class="alert alert-<?php echo $flash['type']; ?>">
                        <?php echo htmlspecialchars($flash['message']); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Navigation Tabs -->
                <div class="leave-tabs">
                    <a href="leave_management_clean.php?tab=apply" class="leave-tab <?php echo $tab === 'apply' ? 'active' : ''; ?>">Apply Leave</a>
                    <?php if ($leaveSystem->hasPermission('section_head')): ?>
                    <a href="leave_management_clean.php?tab=manage" class="leave-tab <?php echo $tab === 'manage' ? 'active' : ''; ?>">Manage Leave</a>
                    <a href="leave_management_clean.php?tab=history" class="leave-tab <?php echo $tab === 'history' ? 'active' : ''; ?>">Leave History</a>
                    <a href="leave_management_clean.php?tab=holidays" class="leave-tab <?php echo $tab === 'holidays' ? 'active' : ''; ?>">Holidays</a>
                    <?php endif; ?>
                    <a href="leave_management_clean.php?tab=profile" class="leave-tab <?php echo $tab === 'profile' ? 'active' : ''; ?>">My Leave Profile</a>
                </div>

                <!-- Tab Content -->
                <?php include "templates/leave_tabs/{$tab}_tab.php"; ?>
            </div>
        </div>
    </div>

    <!-- Enhanced JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize form elements
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            const leaveTypeInput = document.getElementById('leave_type_id');
            const calculatedDays = document.getElementById('calculated_days');
            const deductionPreview = document.getElementById('deduction_preview');
            const deductionDetails = document.getElementById('deduction_details');
            const submitBtn = document.getElementById('submit_btn');

            // Leave data from PHP
            const leaveBalances = <?php echo json_encode($tabData['leaveBalances'] ?? []); ?>;
            const leaveTypes = <?php echo json_encode($tabData['leaveTypes'] ?? []); ?>;

            function calculateDays() {
                if (startDateInput && endDateInput && leaveTypeInput && 
                    startDateInput.value && endDateInput.value && leaveTypeInput.value) {
                    
                    const start = new Date(startDateInput.value);
                    const end = new Date(endDateInput.value);
                    const leaveTypeId = parseInt(leaveTypeInput.value);
                    
                    if (end >= start) {
                        const selectedLeaveType = leaveTypes.find(lt => lt.id == leaveTypeId);
                        const countsWeekends = selectedLeaveType ? selectedLeaveType.counts_weekends == '1' : false;
                        
                        let diffDays = 0;
                        let current = new Date(start);
                        
                        while (current <= end) {
                            const dayOfWeek = current.getDay();
                            
                            if (countsWeekends || (dayOfWeek !== 0 && dayOfWeek !== 6)) {
                                diffDays++;
                            }
                            
                            current.setDate(current.getDate() + 1);
                        }
                        
                        calculatedDays.value = diffDays + ' days';
                        calculateDeduction(leaveTypeId, diffDays);
                    } else {
                        calculatedDays.value = 'Invalid date range';
                        if (deductionPreview) deductionPreview.style.display = 'none';
                    }
                } else {
                    if (calculatedDays) calculatedDays.value = '';
                    if (deductionPreview) deductionPreview.style.display = 'none';
                }
            }

            function calculateDeduction(leaveTypeId, requestedDays) {
                if (!deductionPreview || !deductionDetails) return;

                const selectedLeaveType = leaveTypes.find(lt => lt.id == leaveTypeId);
                const leaveBalance = leaveBalances.find(lb => lb.leave_type_id == leaveTypeId);
                const annualBalance = leaveBalances.find(lb => lb.leave_type_name && lb.leave_type_name.includes('Annual'));

                if (!selectedLeaveType || !leaveBalance) {
                    deductionPreview.style.display = 'none';
                    return;
                }

                let deductionHtml = '';
                let primaryDeduction = 0;
                let annualDeduction = 0;
                let unpaidDays = 0;
                let warnings = [];

                const availablePrimaryBalance = parseInt(leaveBalance.remaining || leaveBalance.annual_leave_balance || 0);
                
                // Check maximum days per year
                if (selectedLeaveType.max_days_per_year && requestedDays > parseInt(selectedLeaveType.max_days_per_year)) {
                    warnings.push(`⚠️ Requested days (${requestedDays}) exceed maximum allowed per year (${selectedLeaveType.max_days_per_year}).`);
                }

                if (requestedDays <= availablePrimaryBalance) {
                    primaryDeduction = requestedDays;
                    warnings.push(`✅ Will be deducted from ${selectedLeaveType.name} balance.`);
                } else {
                    primaryDeduction = Math.max(0, availablePrimaryBalance);
                    let remainingDays = requestedDays - primaryDeduction;

                    if (selectedLeaveType.deducted_from_annual == '1' && remainingDays > 0 && annualBalance) {
                        const availableAnnualBalance = parseInt(annualBalance.remaining || annualBalance.annual_leave_balance || 0);
                        
                        if (availableAnnualBalance >= remainingDays) {
                            annualDeduction = remainingDays;
                            warnings.push(`⚠️ Primary balance insufficient. ${primaryDeduction} days from ${selectedLeaveType.name}, ${annualDeduction} days from Annual Leave.`);
                        } else {
                            annualDeduction = Math.max(0, availableAnnualBalance);
                            unpaidDays = remainingDays - annualDeduction;
                            warnings.push(`❌ Insufficient leave balance. ${primaryDeduction} days from ${selectedLeaveType.name}, ${annualDeduction} days from Annual Leave, ${unpaidDays} days will be unpaid.`);
                        }
                    } else {
                        unpaidDays = remainingDays;
                        if (primaryDeduction > 0) {
                            warnings.push(`❌ ${primaryDeduction} days from ${selectedLeaveType.name}, ${unpaidDays} days will be unpaid.`);
                        } else {
                            warnings.push(`❌ No available balance. All ${requestedDays} days will be unpaid.`);
                        }
                    }
                }

                // Build deduction HTML
                deductionHtml += `<div class="deduction-item"><span>Requested Days:</span><span>${requestedDays}</span></div>`;
                
                if (primaryDeduction > 0) {
                    deductionHtml += `<div class="deduction-item"><span>${selectedLeaveType.name} Deduction:</span><span>${primaryDeduction} days</span></div>`;
                }
                
                if (annualDeduction > 0) {
                    deductionHtml += `<div class="deduction-item"><span>Annual Leave Deduction:</span><span>${annualDeduction} days</span></div>`;
                }
                
                if (unpaidDays > 0) {
                    deductionHtml += `<div class="deduction-item" style="color: #dc3545;"><span>Unpaid Days:</span><span>${unpaidDays} days</span></div>`;
                }

                warnings.forEach(function(warning) {
                    let warningClass = 'info-text';
                    if (warning.includes('❌') || warning.includes('unpaid')) {
                        warningClass = 'unpaid-warning';
                    } else if (warning.includes('⚠️')) {
                        warningClass = 'warning-text';
                    }
                    deductionHtml += `<div class="${warningClass}">${warning}</div>`;
                });

                deductionDetails.innerHTML = deductionHtml;
                deductionPreview.style.display = 'block';

                // Update submit button
                if (submitBtn) {
                    if (unpaidDays > 0) {
                        submitBtn.innerHTML = 'Submit Application (Includes Unpaid Leave)';
                        submitBtn.className = 'btn btn-warning';
                    } else {
                        submitBtn.innerHTML = 'Submit Application';
                        submitBtn.className = 'btn btn-primary';
                    }
                }
            }

            // Event listeners
            if (startDateInput) {
                startDateInput.addEventListener('change', calculateDays);
                // Set minimum date to today
                const today = new Date().toISOString().split('T')[0];
                startDateInput.min = today;
                
                startDateInput.addEventListener('change', function() {
                    if (endDateInput) {
                        endDateInput.min = startDateInput.value;
                    }
                });
            }
            
            if (endDateInput) {
                endDateInput.addEventListener('change', calculateDays);
                const today = new Date().toISOString().split('T')[0];
                endDateInput.min = today;
            }
            
            if (leaveTypeInput) {
                leaveTypeInput.addEventListener('change', calculateDays);
            }
        });
    </script>
</body>
</html>