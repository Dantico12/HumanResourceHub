<div class="tab-content">
    <h3>Manage Holidays</h3>

    <?php if (!$leaveSystem->hasPermission('hr_manager')): ?>
        <div class="alert alert-warning">
            You don't have permission to manage holidays.
        </div>
    <?php else: ?>

    <!-- Add Holiday Form -->
    <form method="POST" action="" class="mb-4">
        <input type="hidden" name="action" value="add_holiday">
        <h4>Add New Holiday</h4>
        <div class="form-grid">
            <div class="form-group">
                <label for="name">Holiday Name</label>
                <input type="text" id="name" name="name" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="date">Date</label>
                <input type="date" id="date" name="date" class="form-control" required>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" class="form-control" rows="3"></textarea>
        </div>

        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="is_recurring"> This is a recurring holiday
            </label>
        </div>

        <button type="submit" class="btn btn-primary">Add Holiday</button>
    </form>

    <?php endif; ?>

    <!-- Holidays List -->
    <div class="table-container">
        <h4>Current Holidays</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Recurring</th>
                    <?php if ($leaveSystem->hasPermission('hr_manager')): ?>
                    <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tabData['holidays'])): ?>
                    <tr>
                        <td colspan="<?php echo $leaveSystem->hasPermission('hr_manager') ? '5' : '4'; ?>" class="text-center">No holidays found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tabData['holidays'] as $holiday): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($holiday['name']); ?></td>
                        <td><?php echo LeaveManagementSystem::formatDate($holiday['date']); ?></td>
                        <td><?php echo htmlspecialchars($holiday['description'] ?? 'N/A'); ?></td>
                        <td>
                            <span class="badge <?php echo $holiday['is_recurring'] ? 'badge-success' : 'badge-secondary'; ?>">
                                <?php echo $holiday['is_recurring'] ? 'Yes' : 'No'; ?>
                            </span>
                        </td>
                        <?php if ($leaveSystem->hasPermission('hr_manager')): ?>
                        <td>
                            <a href="leave_management_clean.php?action=delete_holiday&id=<?php echo $holiday['id']; ?>&tab=holidays" 
                               class="btn btn-danger btn-sm" 
                               onclick="return confirm('Are you sure you want to delete this holiday?')">Delete</a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>