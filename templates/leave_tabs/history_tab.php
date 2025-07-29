<div class="tab-content">
    <h3>Leave History</h3>

    <?php if (!$leaveSystem->hasPermission('hr_manager')): ?>
        <div class="alert alert-warning">
            You don't have permission to view leave history.
        </div>
    <?php else: ?>

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
                <?php if (empty($tabData['currentLeaves'])): ?>
                    <tr>
                        <td colspan="6" class="text-center">No employees currently on leave</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tabData['currentLeaves'] as $leave): ?>
                    <?php
                        $today = new DateTime();
                        $endDate = new DateTime($leave['end_date']);
                        $remainingDays = $today->diff($endDate)->days;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($leave['employee_id'] . ' - ' . $leave['first_name'] . ' ' . $leave['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                        <td><?php echo LeaveManagementSystem::formatDate($leave['start_date']); ?></td>
                        <td><?php echo LeaveManagementSystem::formatDate($leave['end_date']); ?></td>
                        <td><?php echo $leave['days_requested']; ?></td>
                        <td>
                            <?php if ($endDate > $today): ?>
                                <?php echo $remainingDays; ?> days
                            <?php else: ?>
                                <span style="color: #dc3545;">Overdue</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- All Leave History -->
    <div class="table-container">
        <h4>All Leave Applications (Recent 50)</h4>
        <div style="margin-bottom: 15px;">
            <small class="text-muted">
                Showing the most recent leave applications across all employees. 
                Use the reports section for detailed analytics.
            </small>
        </div>
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
                    <th>Approver</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tabData['allLeaves'])): ?>
                    <tr>
                        <td colspan="8" class="text-center">No leave applications found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tabData['allLeaves'] as $leave): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($leave['employee_id'] . ' - ' . $leave['first_name'] . ' ' . $leave['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                        <td><?php echo LeaveManagementSystem::formatDate($leave['start_date']); ?></td>
                        <td><?php echo LeaveManagementSystem::formatDate($leave['end_date']); ?></td>
                        <td><?php echo $leave['days_requested']; ?></td>
                        <td><?php echo LeaveManagementSystem::formatDate($leave['applied_at']); ?></td>
                        <td>
                            <span class="badge <?php echo LeaveManagementSystem::getStatusBadgeClass($leave['status']); ?>">
                                <?php echo LeaveManagementSystem::getStatusDisplayName($leave['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                            if (isset($leave['approver_first_name']) && $leave['approver_first_name']) {
                                echo htmlspecialchars($leave['approver_first_name'] . ' ' . ($leave['approver_last_name'] ?? ''));
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Quick Stats -->
    <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
        <h5>Quick Statistics</h5>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 15px;">
            <div style="text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: #007bff;">
                    <?php echo count($tabData['currentLeaves'] ?? []); ?>
                </div>
                <div style="color: #6c757d;">Currently on Leave</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: #28a745;">
                    <?php 
                    $approvedCount = 0;
                    foreach (($tabData['allLeaves'] ?? []) as $leave) {
                        if ($leave['status'] === 'approved') $approvedCount++;
                    }
                    echo $approvedCount;
                    ?>
                </div>
                <div style="color: #6c757d;">Approved Applications</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: #ffc107;">
                    <?php 
                    $pendingCount = 0;
                    foreach (($tabData['allLeaves'] ?? []) as $leave) {
                        if (strpos($leave['status'], 'pending') !== false) $pendingCount++;
                    }
                    echo $pendingCount;
                    ?>
                </div>
                <div style="color: #6c757d;">Pending Applications</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: #dc3545;">
                    <?php 
                    $rejectedCount = 0;
                    foreach (($tabData['allLeaves'] ?? []) as $leave) {
                        if ($leave['status'] === 'rejected') $rejectedCount++;
                    }
                    echo $rejectedCount;
                    ?>
                </div>
                <div style="color: #6c757d;">Rejected Applications</div>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>