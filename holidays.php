<?php
$page_title = "Manage Holidays";
include 'includes/header.php';

// Check if user has permission to manage holidays
if (!hasPermission('hr_manager')) {
    setFlashMessage("Access denied. You don't have permission to manage holidays.", "danger");
    header("Location: apply_leave.php");
    exit();
}

// Initialize variables
$success = '';
$error = '';
$holidays = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_holiday') {
        $name = sanitizeInput($_POST['name']);
        $date = $_POST['date'];
        $description = sanitizeInput($_POST['description']);
        $isRecurring = isset($_POST['is_recurring']) ? 1 : 0;

        try {
            $stmt = $conn->prepare("INSERT INTO holidays (name, date, description, is_recurring) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $name, $date, $description, $isRecurring);

            if ($stmt->execute()) {
                setFlashMessage("Holiday added successfully!", "success");
                header("Location: holidays.php");
                exit();
            } else {
                $error = "Error adding holiday.";
            }
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Handle GET actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action === 'delete_holiday' && isset($_GET['id'])) {
        $holidayId = (int)$_GET['id'];
        try {
            $stmt = $conn->prepare("DELETE FROM holidays WHERE id = ?");
            $stmt->bind_param("i", $holidayId);

            if ($stmt->execute()) {
                setFlashMessage("Holiday deleted successfully!", "success");
            } else {
                setFlashMessage("Error deleting holiday.", "danger");
            }
        } catch (Exception $e) {
            setFlashMessage("Database error: " . $e->getMessage(), "danger");
        }

        header("Location: holidays.php");
        exit();
    }
}

// Fetch holidays data
try {
    $holidaysResult = $conn->query("SELECT * FROM holidays ORDER BY date DESC");
    $holidays = $holidaysResult->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $error = "Error fetching holidays: " . $e->getMessage();
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

<!-- Holidays Management Content -->
<div class="tab-content">
    <h3>Manage Holidays</h3>
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
            <textarea id="description" name="description" class="form-control"></textarea>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="is_recurring"> This is a recurring holiday
            </label>
        </div>

        <button type="submit" class="btn btn-primary">Add Holiday</button>
    </form>

    <div class="table-container">
        <h4>Current Holidays</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Recurring</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($holidays)): ?>
                    <tr>
                        <td colspan="5" class="text-center">No holidays found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($holidays as $holiday): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($holiday['name']); ?></td>
                        <td><?php echo formatDate($holiday['date']); ?></td>
                        <td><?php echo htmlspecialchars($holiday['description'] ?? 'N/A'); ?></td>
                        <td>
                            <span class="badge <?php echo $holiday['is_recurring'] ? 'badge-success' : 'badge-secondary'; ?>">
                                <?php echo $holiday['is_recurring'] ? 'Yes' : 'No'; ?>
                            </span>
                        </td>
                        <td>
                            <a href="holidays.php?action=delete_holiday&id=<?php echo $holiday['id']; ?>" 
                               class="btn btn-danger btn-sm" 
                               onclick="return confirm('Are you sure you want to delete this holiday?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>