<div class="tab-content">
    <h3>Apply for Leave</h3>

    <?php if ($userEmployee): ?>
    <!-- Leave Balance Overview -->
    <div class="leave-balance-overview mb-4">
        <h4>Your Leave Balance Overview</h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
            <?php if (!empty($tabData['leaveBalances'])): ?>
                <?php foreach ($tabData['leaveBalances'] as $balance): ?>
                <div class="leave-balance-card">
                    <div class="balance-header"><?php echo htmlspecialchars($balance['leave_type_name'] ?? 'Unknown Leave Type'); ?></div>
                    <div class="balance-details">
                        <div class="balance-item balance-allocated">
                            <div>Allocated</div>
                            <strong><?php echo intval($balance['allocated'] ?? $balance['annual_leave_entitled'] ?? 0); ?></strong>
                        </div>
                        <div class="balance-item balance-used">
                            <div>Used</div>
                            <strong><?php echo intval($balance['used'] ?? $balance['annual_leave_used'] ?? 0); ?></strong>
                        </div>
                        <div class="balance-item <?php echo (intval(($balance['remaining'] ?? $balance['annual_leave_balance'] ?? 0)) < 0) ? 'balance-negative' : 'balance-remaining'; ?>">
                            <div>Remaining</div>
                            <strong><?php echo intval($balance['remaining'] ?? $balance['annual_leave_balance'] ?? 0); ?></strong>
                        </div>
                    </div>
                    <?php if (isset($balance['max_days_per_year']) && $balance['max_days_per_year']): ?>
                    <div class="info-text" style="margin-top: 8px; font-size: 12px;">
                        Max per year: <?php echo intval($balance['max_days_per_year']); ?> days
                        <?php if (isset($balance['deducted_from_annual']) && $balance['deducted_from_annual']): ?>
                        | Fallback to Annual Leave
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    <p>No leave balance information available. This might be because:</p>
                    <ul>
                        <li>Your employee record is not properly set up</li>
                        <li>No leave types have been configured</li>
                        <li>Leave balances haven't been initialized for this year</li>
                    </ul>
                    <p>Please contact HR for assistance.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Leave Application Form -->
    <form method="POST" action="" id="leaveApplicationForm">
        <input type="hidden" name="action" value="apply_leave">
        <div class="form-grid">
            <div class="form-group">
                <label for="employee_id">Employee</label>
                <select id="employee_id" name="employee_id" class="form-control" required>
                    <option value="">Select Employee</option>
                    <?php 
                    if ($userEmployee) {
                        if (!$leaveSystem->hasPermission('section_head')) {
                            echo '<option value="' . $userEmployee['id'] . '" selected>' . 
                                 htmlspecialchars(
                                     $userEmployee['employee_id'] . ' - ' . 
                                     $userEmployee['first_name'] . ' ' . 
                                     $userEmployee['last_name'] . ' (' . 
                                     ($userEmployee['designation'] ?? '') . ')'
                                 ) . '</option>';
                        } elseif (isset($tabData['employees']) && is_array($tabData['employees'])) {
                            foreach ($tabData['employees'] as $employee) {
                                $selected = ($employee['id'] == $userEmployee['id']) ? 'selected' : '';
                                echo '<option value="' . $employee['id'] . '" ' . $selected . '>' . 
                                     htmlspecialchars(
                                         $employee['employee_id'] . ' - ' . 
                                         $employee['first_name'] . ' ' . 
                                         $employee['last_name'] . ' (' . 
                                         ($employee['designation'] ?? '') . ')'
                                     ) . '</option>';
                            }
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label for="leave_type_id">Leave Type</label>
                <select name="leave_type_id" id="leave_type_id" class="form-control" required>
                    <option value="">Select Leave Type</option>
                    <?php foreach ($tabData['leaveTypes'] as $type): ?>
                    <option value="<?php echo $type['id']; ?>" 
                            data-max-days="<?php echo $type['max_days_per_year']; ?>"
                            data-counts-weekends="<?php echo $type['counts_weekends']; ?>"
                            data-fallback="<?php echo $type['deducted_from_annual']; ?>">
                        <?php echo htmlspecialchars($type['name']); ?>
                        <?php if ($type['max_days_per_year']): ?>
                        (Max: <?php echo $type['max_days_per_year']; ?> days/year)
                        <?php endif; ?>
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

        <!-- Enhanced Deduction Preview -->
        <div id="deduction_preview" class="deduction-preview" style="display: none;">
            <h5>Leave Deduction Preview</h5>
            <div id="deduction_details"></div>
        </div>

        <div class="form-group">
            <label for="reason">Reason for Leave</label>
            <textarea name="reason" id="reason" class="form-control" rows="3" required></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary" id="submit_btn">Submit Application</button>
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
                    <th>Deduction Details</th>
                    <th>Status</th>
                    <th>Applied Date</th>
                    <th>Approver</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($tabData['leaveApplications'])): ?>
                    <?php foreach ($tabData['leaveApplications'] as $application): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($application['leave_type_name']); ?></td>
                        <td><?php echo LeaveManagementSystem::formatDate($application['start_date']); ?></td>
                        <td><?php echo LeaveManagementSystem::formatDate($application['end_date']); ?></td>
                        <td><?php echo $application['days_requested']; ?></td>
                        <td>
                            <?php if (isset($application['primary_days'], $application['annual_days'], $application['unpaid_days'])): ?>
                            <small>
                                <?php if ($application['primary_days'] > 0): ?>
                                Primary: <?php echo $application['primary_days']; ?><br>
                                <?php endif; ?>
                                <?php if ($application['annual_days'] > 0): ?>
                                Annual: <?php echo $application['annual_days']; ?><br>
                                <?php endif; ?>
                                <?php if ($application['unpaid_days'] > 0): ?>
                                <span style="color: #dc3545;">Unpaid: <?php echo $application['unpaid_days']; ?></span>
                                <?php endif; ?>
                            </small>
                            <?php else: ?>
                            <small class="text-muted">Legacy application</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo LeaveManagementSystem::getStatusBadgeClass($application['status']); ?>">
                                <?php echo LeaveManagementSystem::getStatusDisplayName($application['status']); ?>
                            </span>
                        </td>
                        <td><?php echo LeaveManagementSystem::formatDate($application['applied_at']); ?></td>
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
                        <td colspan="8" class="text-center text-muted">No leave applications found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>