<div class="tab-content">
    <h3>My Leave Profile</h3>

    <?php if ($tabData['employee']): ?>
    <!-- Employee Information -->
    <div class="employee-info mb-4">
        <div class="form-grid">
            <div>
                <h4>Employee Information</h4>
                <p><strong>Employee ID:</strong> <?php echo htmlspecialchars($tabData['employee']['employee_id']); ?></p>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($tabData['employee']['first_name'] . ' ' . $tabData['employee']['last_name']); ?></p>
                <p><strong>Employment Type:</strong> <?php echo htmlspecialchars($tabData['employee']['employment_type']); ?></p>
                <p><strong>Department:</strong> <?php echo htmlspecialchars($tabData['employee']['department_id'] ?? 'N/A'); ?></p>
            </div>
        </div>
    </div>

    <!-- Enhanced Leave Balance Display -->
    <div class="leave-balance-section mb-4">
        <h4>Detailed Leave Balance (Current Year)</h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px;">
            <?php if (!empty($tabData['leaveBalances'])): ?>
                <?php foreach ($tabData['leaveBalances'] as $balance): ?>
                <div class="leave-balance-card">
                    <div class="balance-header">
                        <?php echo htmlspecialchars($balance['leave_type_name']); ?>
                        <?php if ($balance['max_days_per_year']): ?>
                        <span style="font-size: 12px; color: #6c757d;">(Max: <?php echo $balance['max_days_per_year']; ?>/year)</span>
                        <?php endif; ?>
                    </div>
                    <div class="balance-details">
                        <div class="balance-item balance-allocated">
                            <div>Allocated</div>
                            <strong><?php echo intval($balance['allocated'] ?? $balance['annual_leave_entitled'] ?? 0); ?> days</strong>
                        </div>
                        <div class="balance-item balance-used">
                            <div>Used</div>
                            <strong><?php echo intval($balance['used'] ?? $balance['annual_leave_used'] ?? 0); ?> days</strong>
                        </div>
                        <div class="balance-item <?php echo (intval(($balance['remaining'] ?? $balance['annual_leave_balance'] ?? 0)) < 0) ? 'balance-negative' : 'balance-remaining'; ?>">
                            <div>Remaining</div>
                            <strong><?php echo intval($balance['remaining'] ?? $balance['annual_leave_balance'] ?? 0); ?> days</strong>
                        </div>
                    </div>
                    <div style="margin-top: 10px; font-size: 12px;">
                        <?php if (($balance['counts_weekends'] ?? 0) == 0): ?>
                        <div class="info-text">Working days only (excludes weekends)</div>
                        <?php else: ?>
                        <div class="info-text">Includes weekends</div>
                        <?php endif; ?>
                        
                        <?php if ($balance['deducted_from_annual'] ?? 0): ?>
                        <div class="info-text">Falls back to Annual Leave when exhausted</div>
                        <?php endif; ?>
                        
                        <?php if ((intval($balance['remaining'] ?? $balance['annual_leave_balance'] ?? 0)) < 0): ?>
                        <div class="unpaid-warning">Negative balance - previous leave was unpaid</div>
                        <?php endif; ?>
                    </div>
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

    <!-- Enhanced Leave History -->
    <div class="table-container">
        <h4>My Leave History</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>Leave Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days</th>
                    <th>Deduction Breakdown</th>
                    <th>Applied Date</th>
                    <th>Status</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tabData['leaveHistory'])): ?>
                    <tr>
                        <td colspan="8" class="text-center">No leave applications found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tabData['leaveHistory'] as $leave): ?>
                    <tr>
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
                            <small class="text-muted">Not specified</small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo LeaveManagementSystem::formatDate($leave['applied_at']); ?></td>
                        <td>
                            <span class="badge <?php echo LeaveManagementSystem::getStatusBadgeClass($leave['status']); ?>">
                                <?php echo LeaveManagementSystem::getStatusDisplayName($leave['status']); ?>
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
        <a href="leave_management_clean.php?tab=apply" class="btn btn-primary">Apply for New Leave</a>
    </div>

    <?php else: ?>
    <div class="alert alert-warning">
        Employee record not found. Please contact HR to resolve this issue.
    </div>
    <?php endif; ?>
</div>