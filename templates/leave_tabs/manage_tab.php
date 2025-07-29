<div class="tab-content">
    <h3>Manage Leave Applications</h3>

    <?php if (!$leaveSystem->hasPermission('section_head')): ?>
        <div class="alert alert-warning">
            You don't have permission to manage leave applications.
        </div>
    <?php else: ?>

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
                    <th>Deduction Breakdown</th>
                    <th>Applied Date</th>
                    <th>Department/Section</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tabData['pendingLeaves'])): ?>
                    <tr>
                        <td colspan="9" class="text-center">No pending leave applications</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tabData['pendingLeaves'] as $leave): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(($leave['employee_id'] ?? $leave['emp_id'] ?? '') . ' - ' . $leave['first_name'] . ' ' . $leave['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                        <td><?php echo LeaveManagementSystem::formatDate($leave['start_date']); ?></td>
                        <td><?php echo LeaveManagementSystem::formatDate($leave['end_date']); ?></td>
                        <td><?php echo $leave['days_requested']; ?></td>
                        <td>
                            <?php if (isset($leave['primary_days'], $leave['annual_days'], $leave['unpaid_days'])): ?>
                            <small>
                                <?php if ($leave['primary_days'] > 0): ?>
                                Primary: <?php echo $leave['primary_days']; ?><br>
                                <?php endif; ?>
                                <?php if ($leave['annual_days'] > 0): ?>
                                Annual: <?php echo $leave['annual_days']; ?><br>
                                <?php endif; ?>
                                <?php if ($leave['unpaid_days'] > 0): ?>
                                <span style="color: #dc3545;">Unpaid: <?php echo $leave['unpaid_days']; ?></span>
                                <?php endif; ?>
                            </small>
                            <?php else: ?>
                            <small class="text-muted">Not calculated</small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo LeaveManagementSystem::formatDate($leave['applied_at']); ?></td>
                        <td><?php echo htmlspecialchars(($leave['department_name'] ?? 'N/A') . ' / ' . ($leave['section_name'] ?? 'N/A')); ?></td>
                        <td>
                            <?php
                            $approveAction = '';
                            $rejectAction = '';
                            
                            if ($leave['status'] === 'pending_section_head' && $leaveSystem->hasPermission('section_head')) {
                                $approveAction = 'section_head_approve';
                                $rejectAction = 'section_head_reject';
                            } elseif ($leave['status'] === 'pending_dept_head' && $leaveSystem->hasPermission('dept_head')) {
                                $approveAction = 'dept_head_approve';
                                $rejectAction = 'dept_head_reject';
                            } elseif ($leaveSystem->hasPermission('hr_manager')) {
                                $approveAction = 'approve_leave';
                                $rejectAction = 'reject_leave';
                            }
                            
                            if ($approveAction):
                            ?>
                            <a href="leave_management_clean.php?action=<?php echo $approveAction; ?>&id=<?php echo $leave['id']; ?>&tab=manage" 
                               class="btn btn-success btn-sm" 
                               onclick="return confirm('Approve this leave application?')">Approve</a>
                            <a href="leave_management_clean.php?action=<?php echo $rejectAction; ?>&id=<?php echo $leave['id']; ?>&tab=manage" 
                               class="btn btn-danger btn-sm" 
                               onclick="return confirm('Reject this leave application?')">Reject</a>
                            <?php else: ?>
                            <span class="text-muted">No actions available</span>
                            <?php endif; ?>
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
                    <th>Deduction Breakdown</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tabData['approvedLeaves'])): ?>
                    <tr>
                        <td colspan="7" class="text-center">No approved leaves found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tabData['approvedLeaves'] as $leave): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($leave['employee_id'] . ' - ' . $leave['first_name'] . ' ' . $leave['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                        <td><?php echo LeaveManagementSystem::formatDate($leave['start_date']); ?></td>
                        <td><?php echo LeaveManagementSystem::formatDate($leave['end_date']); ?></td>
                        <td><?php echo $leave['days_requested']; ?></td>
                        <td>
                            <?php if (isset($leave['primary_days'], $leave['annual_days'], $leave['unpaid_days'])): ?>
                            <small>
                                <?php if ($leave['primary_days'] > 0): ?>
                                Primary: <?php echo $leave['primary_days']; ?><br>
                                <?php endif; ?>
                                <?php if ($leave['annual_days'] > 0): ?>
                                Annual: <?php echo $leave['annual_days']; ?><br>
                                <?php endif; ?>
                                <?php if ($leave['unpaid_days'] > 0): ?>
                                <span style="color: #dc3545;">Unpaid: <?php echo $leave['unpaid_days']; ?></span>
                                <?php endif; ?>
                            </small>
                            <?php else: ?>
                            <small class="text-muted">Legacy data</small>
                            <?php endif; ?>
                        </td>
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
                    <th>Reason for Rejection</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tabData['rejectedLeaves'])): ?>
                    <tr>
                        <td colspan="7" class="text-center">No rejected leaves found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tabData['rejectedLeaves'] as $leave): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($leave['employee_id'] . ' - ' . $leave['first_name'] . ' ' . $leave['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                        <td><?php echo LeaveManagementSystem::formatDate($leave['start_date']); ?></td>
                        <td><?php echo LeaveManagementSystem::formatDate($leave['end_date']); ?></td>
                        <td><?php echo $leave['days_requested']; ?></td>
                        <td><?php echo htmlspecialchars($leave['approver_comments'] ?? 'No comments provided'); ?></td>
                        <td><span class="badge badge-danger">Rejected</span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php endif; ?>
</div>