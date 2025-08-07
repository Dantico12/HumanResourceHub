<?php
// Get current page to highlight active tab
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>

<div class="leave-tabs">
    <a href="apply_leave.php" class="leave-tab <?php echo $current_page === 'apply_leave' ? 'active' : ''; ?>">Apply Leave</a>
    <?php if (hasPermission('hr_manager')): ?>
    <a href="manage_leave.php" class="leave-tab <?php echo $current_page === 'manage_leave' ? 'active' : ''; ?>">Manage Leave</a>
    <a href="leave_history.php" class="leave-tab <?php echo $current_page === 'leave_history' ? 'active' : ''; ?>">Leave History</a>
    <a href="holidays.php" class="leave-tab <?php echo $current_page === 'holidays' ? 'active' : ''; ?>">Holidays</a>
    <?php endif; ?>
    <a href="leave_profile.php" class="leave-tab <?php echo $current_page === 'leave_profile' ? 'active' : ''; ?>">My Leave Profile</a>
</div>