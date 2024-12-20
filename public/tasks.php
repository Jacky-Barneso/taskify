<?php
// Start the session to check if the user is logged in
session_start();

// If the user is not logged in, redirect to the login page
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    // Redirect to login page if user_id is not set
    header("Location: login.php");
    exit;
}

// Include the database connection file
include '../config/db.php';
include '../config/activity_log.php';

// Fetch tasks for the logged-in user with remaining days until deadline
$stmt = $pdo->prepare("
    SELECT id, title, description, deadline, status, category_id
    FROM tasks
    WHERE user_id = :user_id
");
$stmt->execute([':user_id' => $user_id]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($tasks as $task) {
    $stmt = $pdo->prepare("
        SELECT calculate_days_until_deadline(:task_id)
    ");
    $stmt->execute([':task_id' => $task['id']]);
    $days_left = $stmt->fetchColumn();

    // Add the result directly to the task array
    $task['days_left'] = $days_left !== false ? $days_left : null;
}




// Get the search query if available
$search = isset($_GET['search']) ? $_GET['search'] : '';
$overdue = isset($_GET['overdue']) ? true : false;

// Build the SQL query dynamically based on search and overdue conditions
$sql = "SELECT * FROM tasks WHERE user_id = :user_id";
$params = [':user_id' => $user_id];

if ($search) {
    $sql .= " AND title LIKE :search";
    $params[':search'] = '%' . $search . '%';
}

if ($overdue) {
    $sql .= " AND deadline < NOW() AND status = FALSE";  // Assuming status = FALSE for incomplete tasks
}

// Prepare and execute the query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Handle Add Task
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_task'])) {
    // Get task data from form
    $task_title = htmlspecialchars($_POST['task_title']);
    $task_description = htmlspecialchars($_POST['task_description'] ?? null);
    $category_id = $_POST['category_id'] ?? null;
    $deadline = $_POST['task_deadline'] ?? null;

    // Insert task into the database
    $stmt = $pdo->prepare("INSERT INTO tasks (title, description, category_id, deadline, user_id) 
                        VALUES (:title, :description, :category_id, :deadline, :user_id)");
    $stmt->execute([
        ':title' => $task_title,
        ':description' => $task_description,
        ':category_id' => $category_id,
        ':deadline' => $deadline, // Add the deadline here
        ':user_id' => $user_id,
    ]);

    // Log the activity for adding a new task
    logActivity($user_id, 'Add Task', 'tasks', $pdo->lastInsertId());  // Log the task creation using the last inserted ID

    // Redirect after successful addition
    header('Location: tasks.php?message=Task added successfully');
    exit;
}



// Handle task deletion
if (isset($_GET['delete'])) {
    $task_id = $_GET['delete'];

    // Ensure the task ID is numeric to prevent SQL injection
    if (!is_numeric($task_id)) {
        header('Location: tasks.php?message=Invalid task ID');
        exit;
    }

    // Check if the task exists and belongs to the logged-in user
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $task_id, ':user_id' => $user_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        header('Location: tasks.php?message=Task not found or unauthorized access');
        exit;
    }

    // Perform the delete operation
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $task_id, ':user_id' => $user_id]);

    // Log the activity for deleting the task
    logActivity($user_id, 'Delete Task', 'tasks', $task_id);  // Log the task deletion using the task's ID

    // Redirect with a success message
    header('Location: tasks.php?message=Task deleted successfully');
    exit;
}

// Handle task editing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_task'])) {
    // Get task ID from the hidden input
    $task_id = $_POST['task_id'];
    
    // Check if the task ID is valid
    if (empty($task_id)) {
        echo "Error: Task ID is missing.";
        exit;
    }

    // Get updated data from form
    $task_title = htmlspecialchars($_POST['task_title']);
    $task_description = htmlspecialchars($_POST['task_description'] ?? null);
    $category_id = $_POST['category_id'] ?? null;
    $deadline = $_POST['task_deadline'] ?? null;

    // Update query with deadline field
    $stmt = $pdo->prepare("UPDATE tasks 
                        SET title = :title, description = :description, category_id = :category_id, deadline = :deadline
                        WHERE id = :id AND user_id = :user_id");
    $stmt->execute([
        ':title' => $task_title,
        ':description' => $task_description,
        ':category_id' => $category_id,
        ':deadline' => $deadline, // Add deadline to the update
        ':id' => $task_id,
        ':user_id' => $user_id, 
    ]);
    
    // Log the task update activity
    logActivity($user_id, 'Edit Task', 'tasks', $task_id);  // Log the task editing action

    // Redirect with success message
    header('Location: tasks.php?message=Task updated successfully');
    exit;
}



// Handle task status update (mark as completed or pending)
if (isset($_GET['toggle_status'])) {
    $task_id = $_GET['toggle_status'];

    // Ensure the task ID is numeric to prevent SQL injection
    if (!is_numeric($task_id)) {
        header('Location: tasks.php?message=Invalid task ID');
        exit;
    }

    // Check if the task exists and belongs to the logged-in user
    $stmt = $pdo->prepare("SELECT status FROM tasks WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $task_id, ':user_id' => $user_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    // Proceed only if the task exists
    if ($task) {
        // Toggle the status (if current status is true, make it false and vice versa)
        $new_status = $task['status'] ? 0 : 1;

        // Update the task status in the database
        $stmt = $pdo->prepare("UPDATE tasks SET status = :status WHERE id = :id AND user_id = :user_id");
        $stmt->execute([
            ':status' => $new_status,
            ':id' => $task_id,
            ':user_id' => $user_id
        ]);

        // Log the status update activity
        logActivity($user_id, 'Update Task Status', 'tasks', $task_id);  // Log the task status update action

        // Add a specific success message based on the new status
        $status_message = $new_status ? 'Task marked as completed' : 'Task marked as pending';
        header("Location: tasks.php?message=$status_message");
        exit;
    } else {
        // Task not found or unauthorized access
        header('Location: tasks.php?message=Task not found or unauthorized access');
        exit;
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Tasks</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .category-badge {
            font-size: 0.9rem;
        }
        /* Optional: Customize the dropdown button appearance */
        .dropdown-toggle {
            background-color: #dc3545; /* red */
            border: none;
        }

        .dropdown-menu {
            width: 200px; /* Set custom width for the dropdown */
        }

        #profileButton {
        cursor: pointer;
        font-weight: bold;
        text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <!-- Header -->
        <header class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
            <h1 class="display-6 text-primary">Quicklist</h1>
            <div class="d-flex align-items-center">
                <a href="#" id="profileButton" role="button" data-bs-toggle="dropdown" aria-expanded="false" class="rounded-circle bg-primary text-white d-flex justify-content-center align-items-center" style="width: 40px; height: 40px; text-decoration: none;">
                    <?php
                    $username = $_SESSION['username'] ?? 'User';
                    $initials = '';
                    foreach (explode(' ', $username) as $word) {
                        $initials .= strtoupper($word[0]);
                    }
                    echo htmlspecialchars($initials);
                    ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileButton">
                    <?php
                    // Fetch email from the session or database
                    $email = $_SESSION['email'] ?? 'example@example.com'; // Replace with actual email fetching logic if needed
                    ?>
                    <li class="dropdown-item text-muted"><?php echo htmlspecialchars($email); ?></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="login.php">Logout</a></li>
                </ul>
            </div>
        </header>





        <!-- Notification Messages -->
        <?php if (isset($_GET['message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_GET['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" onclick="clearMessageFromURL()"></button>
        </div>
        <?php endif; ?>

        <!-- Add Task Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4">Your Tasks</h2>
            <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4"></h2>
                <form method="GET" action="" class="d-flex">
                    <input type="text" name="search" class="form-control me-2" placeholder="Search" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                    <button type="submit" class="btn btn-outline-secondary">Search</button>
                </form>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
        <form method="GET" class="d-flex">
            <div class="dropdown">
                <!-- Dropdown button -->
                <button class="btn btn-danger dropdown-toggle" type="button" id="taskDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    View Tasks
                </button>
                <ul class="dropdown-menu" aria-labelledby="taskDropdown">
                    <!-- All Tasks Option -->
                    <li><a class="dropdown-item" href="tasks.php">All Tasks</a></li>
                    <!-- Overdue Tasks Option -->
                    <li><a class="dropdown-item" href="tasks.php?overdue=true">Overdue Tasks</a></li>
                </ul>
            </div>
        </form>

    <!-- Add Task Button -->
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">Add Task</button>
</div>




        <!-- Task List -->
        <section>
    <div class="row">
        <!-- Work Category -->
        <div class="col-md-6">
            <h3 class="h5 text-secondary">Work</h3>
            <ul class="list-group">
                <?php foreach ($tasks as $task): ?>
                    <?php if ($task['category_id'] == 1): ?>
                        <?php
                            // Calculate remaining days for the task
                            $stmt = $pdo->prepare("
                                SELECT calculate_days_until_deadline(:task_id)
                            ");
                            $stmt->execute([':task_id' => $task['id']]);
                            $days_left = $stmt->fetchColumn();
                            $task['days_left'] = $days_left !== false ? $days_left : null;
                        ?>
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                            <div>
                                <span class="fw-bold">
                                    <?= htmlspecialchars($task['title']) ?>
                                </span>
                                <p class="text-muted mb-1">
                                    <?= htmlspecialchars($task['description']) ?>
                                </p>
                                <p class="text-muted mb-1">
                                    <small>Deadline: <?= htmlspecialchars($task['deadline']) ?></small><br>
                                    <small>
                                        <?= isset($task['days_left']) && $task['days_left'] !== null 
                                            ? ($task['days_left'] > 0 
                                                ? "{$task['days_left']} days remaining" 
                                                : "Deadline passed") 
                                            : "Deadline not set" ?>
                                    </small>
                                </p>
                                <span class="badge bg-info category-badge">
                                    <?= $task['status'] ? 'Completed' : 'Pending' ?>
                                </span>
                            </div>
                            <div>
                                <a href="tasks.php?toggle_status=<?= $task['id'] ?>" class="btn btn-sm btn-outline-info">
                                    <?= $task['status'] ? 'Mark as Pending' : 'Mark as Completed' ?>
                                </a>
                                <a href="tasks.php?delete=<?= $task['id'] ?>" onclick="return confirm('Are you sure?')" class="btn btn-sm btn-outline-danger">Delete</a>
                                <a href="#" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" 
                                   data-bs-target="#editTaskModal" 
                                   data-id="<?= $task['id'] ?>" 
                                   data-title="<?= htmlspecialchars($task['title']) ?>" 
                                   data-description="<?= htmlspecialchars($task['description']) ?>" 
                                   data-category="<?= $task['category_id'] ?>" 
                                   data-deadline="<?= htmlspecialchars($task['deadline']) ?>">
                                    Edit
                                </a>
                            </div>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Personal Category -->
        <div class="col-md-6">
            <h3 class="h5 text-secondary">Personal</h3>
            <ul class="list-group">
                <?php foreach ($tasks as $task): ?>
                    <?php if ($task['category_id'] == 2): ?>
                        <?php
                            // Calculate remaining days for the task
                            $stmt = $pdo->prepare("
                                SELECT calculate_days_until_deadline(:task_id)
                            ");
                            $stmt->execute([':task_id' => $task['id']]);
                            $days_left = $stmt->fetchColumn();
                            $task['days_left'] = $days_left !== false ? $days_left : null;
                        ?>
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                            <div>
                                <span class="fw-bold">
                                    <?= htmlspecialchars($task['title']) ?>
                                </span>
                                <p class="text-muted mb-1">
                                    <?= htmlspecialchars($task['description']) ?>
                                </p>
                                <p class="text-muted mb-1">
                                    <small>Deadline: <?= htmlspecialchars($task['deadline']) ?></small><br>
                                    <small>
                                        <?= isset($task['days_left']) && $task['days_left'] !== null 
                                            ? ($task['days_left'] > 0 
                                                ? "{$task['days_left']} days remaining" 
                                                : "Deadline passed") 
                                            : "Deadline not set" ?>
                                    </small>
                                </p>
                                <span class="badge bg-info category-badge">
                                    <?= $task['status'] ? 'Completed' : 'Pending' ?>
                                </span>
                            </div>
                            <div>
                                <a href="tasks.php?toggle_status=<?= $task['id'] ?>" class="btn btn-sm btn-outline-info">
                                    <?= $task['status'] ? 'Mark as Pending' : 'Mark as Completed' ?>
                                </a>
                                <a href="tasks.php?delete=<?= $task['id'] ?>" onclick="return confirm('Are you sure?')" class="btn btn-sm btn-outline-danger">Delete</a>
                                <a href="#" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" 
                                   data-bs-target="#editTaskModal" 
                                   data-id="<?= $task['id'] ?>" 
                                   data-title="<?= htmlspecialchars($task['title']) ?>" 
                                   data-description="<?= htmlspecialchars($task['description']) ?>" 
                                   data-category="<?= $task['category_id'] ?>" 
                                   data-deadline="<?= htmlspecialchars($task['deadline']) ?>">
                                    Edit
                                </a>
                            </div>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>




<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTaskModalLabel">Add New Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="tasks.php" id="addTaskForm">
                <div class="modal-body">
                    <!-- Task Title -->
                    <div class="mb-3">
                        <label for="add_task_title" class="form-label">Task Title</label>
                        <input type="text" id="add_task_title" name="task_title" class="form-control" required>
                    </div>

                    <!-- Task Description -->
                    <div class="mb-3">
                        <label for="add_task_description" class="form-label">Description</label>
                        <textarea id="add_task_description" name="task_description" class="form-control"></textarea>
                    </div>

                    <!-- Task Category -->
                    <div class="mb-3">
                        <label for="add_category_id" class="form-label">Category</label>
                        <select id="add_category_id" name="category_id" class="form-select" required>
                            <option value="">Select Category</option>
                            <option value="1">Work</option>
                            <option value="2">Personal</option>
                        </select>
                    </div>

                    <!-- Task Deadline -->
                    <div class="mb-3">
                        <label for="add_task_deadline" class="form-label">Deadline</label>
                        <input type="datetime-local" id="add_task_deadline" name="task_deadline" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" name="add_task">Add Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Task Modal -->
<div class="modal fade" id="editTaskModal" tabindex="-1" aria-labelledby="editTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTaskModalLabel">Edit Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="tasks.php" id="editTaskForm">
                <div class="modal-body">
                    <!-- Hidden field for task ID (for updating) -->
                    <input type="hidden" id="edit_task_id" name="task_id">

                    <!-- Task Title -->
                    <div class="mb-3">
                        <label for="edit_task_title" class="form-label">Task Title</label>
                        <input type="text" id="edit_task_title" name="task_title" class="form-control" required>
                    </div>

                    <!-- Task Description -->
                    <div class="mb-3">
                        <label for="edit_task_description" class="form-label">Description</label>
                        <textarea id="edit_task_description" name="task_description" class="form-control"></textarea>
                    </div>

                    <!-- Task Category -->
                    <div class="mb-3">
                        <label for="edit_category_id" class="form-label">Category</label>
                        <select id="edit_category_id" name="category_id" class="form-select" required>
                            <option value="">Select Category</option>
                            <option value="1">Work</option>
                            <option value="2">Personal</option>
                        </select>
                    </div>

                    <!-- Task Deadline -->
                    <div class="mb-3">
                        <label for="edit_task_deadline" class="form-label">Deadline</label>
                        <input type="datetime-local" id="edit_task_deadline" name="task_deadline" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" name="edit_task">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>






    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

<script>
// Function to show success message
function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.role = 'alert';
    alertDiv.innerHTML = `${message} <button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.insertBefore(alertDiv, document.body.firstChild);
}

// Client-side validation for Add Task Form
document.getElementById('addTaskForm').addEventListener('submit', function(event) {
    // Check if all required fields are filled out
    const title = document.getElementById('add_task_title').value;
    const category = document.getElementById('add_category_id').value;
    const deadline = document.getElementById('add_task_deadline').value;
    
    if (!title || !category || !deadline) {
        event.preventDefault(); // Prevent form submission
        showAlert('All fields are required.', 'danger');
    } else {
        // Optionally you can check if the deadline is a valid date here as well
        showAlert('Task added successfully!', 'success');
    }
});

// Client-side validation for Edit Task Form
document.getElementById('editTaskForm').addEventListener('submit', function(event) {
    // Check if all required fields are filled out
    const title = document.getElementById('edit_task_title').value;
    const category = document.getElementById('edit_category_id').value;
    const deadline = document.getElementById('edit_task_deadline').value;
    
    if (!title || !category || !deadline) {
        event.preventDefault(); // Prevent form submission
        showAlert('All fields are required.', 'danger');
    } else {
        // Optionally you can check if the deadline is a valid date here as well
        showAlert('Task updated successfully!', 'success');
    }
});

// Function to show modal data in edit modal
const editTaskModal = document.getElementById('editTaskModal');
editTaskModal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget; // Button that triggered the modal
    
    // Get task data from the button's data attributes
    const taskId = button.getAttribute('data-id');
    const taskTitle = button.getAttribute('data-title');
    const taskDescription = button.getAttribute('data-description');
    const categoryId = button.getAttribute('data-category');
    
    // Populate the modal fields
    document.getElementById('edit_task_id').value = taskId;
    document.getElementById('edit_task_title').value = taskTitle;
    document.getElementById('edit_task_description').value = taskDescription;
    document.getElementById('edit_category_id').value = categoryId;
});

function clearMessageFromURL() {
        const url = new URL(window.location);
        url.searchParams.delete('message'); // Remove 'message' query parameter
        window.history.replaceState({}, document.title, url); // Update the URL without reloading the page
    }

</script>

</html>

