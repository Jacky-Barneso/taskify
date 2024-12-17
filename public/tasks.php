<?php
// Start the session to check if the user is logged in
session_start();

// If the user is not logged in, redirect to the login page
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Include the database connection file
include '../config/db.php';

// Fetch tasks for the logged-in user
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id = :user_id");
$stmt->execute([':user_id' => $user_id]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get the search query if available
$search = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';

// Modify the query to filter tasks by title if search is not empty
$sql = "SELECT * FROM tasks WHERE user_id = :user_id";
$params = [':user_id' => $user_id];

if ($search) {
    $sql .= " AND title LIKE :search";
    $params[':search'] = '%' . $search . '%';
}

// Fetch tasks for the logged-in user
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Handle Add Task
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_task'])) {
    // Get task data from form
    $task_title = htmlspecialchars($_POST['task_title']);
    $task_description = htmlspecialchars($_POST['task_description'] ?? null);
    $category_id = $_POST['category_id'] ?? null;

    // Insert task into the database
    $stmt = $pdo->prepare("INSERT INTO tasks (title, description, category_id, user_id) VALUES (:title, :description, :category_id, :user_id)");
    $stmt->execute([
        ':title' => $task_title,
        ':description' => $task_description,
        ':category_id' => $category_id,
        ':user_id' => $user_id, // Assuming you have user authentication logic
    ]);
    
    // Redirect after successful addition
    header('Location: tasks.php?message=Task added successfully');
    exit;
}


// Handle task deletion
if (isset($_GET['delete'])) {
    $task_id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $task_id, ':user_id' => $user_id]);
    header('Location: tasks.php?message=Task deleted successfully');
    exit;
}


// Handle task editing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_task'])) {
    // Get task ID from the hidden input
    $task_id = $_POST['task_id'];
    
    // Check if the task ID is valid (this ensures you're updating, not inserting)
    if (empty($task_id)) {
        echo "Error: Task ID is missing.";
        exit;
    }

    // Get updated data from form
    $task_title = htmlspecialchars($_POST['task_title']);
    $task_description = htmlspecialchars($_POST['task_description'] ?? null);
    $category_id = $_POST['category_id'] ?? null;

    // Update query: ensure task_id is used and prevent duplicates
    $stmt = $pdo->prepare("UPDATE tasks 
                           SET title = :title, description = :description, category_id = :category_id 
                           WHERE id = :id AND user_id = :user_id"); // Ensure the task belongs to the logged-in user
    $stmt->execute([
        ':title' => $task_title,
        ':description' => $task_description,
        ':category_id' => $category_id,
        ':id' => $task_id, // Update the task with the correct task ID
        ':user_id' => $user_id, // Ensure task belongs to the logged-in user
    ]);
    
    // Redirect with success message
    header('Location: tasks.php?message=Task updated successfully');
    exit;
}


// Handle task status update (mark as completed or pending)
if (isset($_GET['toggle_status'])) {
    $task_id = $_GET['toggle_status'];
    // Fetch current status
    $stmt = $pdo->prepare("SELECT status FROM tasks WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $task_id, ':user_id' => $user_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    // Toggle the status
    if ($task) {
        $new_status = $task['status'] ? 0 : 1; // Flip the boolean value
        $stmt = $pdo->prepare("UPDATE tasks SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $new_status, ':id' => $task_id]);
    }

    header('Location: tasks.php?message=Task status updated');
    exit;
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
    </style>
</head>
<body>
    <div class="container py-5">
        <!-- Header -->
        <header class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
            <h1 class="display-6 text-primary">Quicklist</h1>
            <a href="login.php" class="btn btn-outline-danger">Logout</a>
        </header>

        <!-- Notification Messages -->
        <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_GET['message']) ?>
            </div>
        <?php endif; ?>

        <!-- Add Task Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4">Your Tasks</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">Add Task</button>
        </div>

        <!-- Search Bar -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h4"></h2>
    <form method="GET" class="d-flex">
        <input type="text" name="search" class="form-control me-2" placeholder="Search task title" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
        <button type="submit" class="btn btn-outline-secondary">Search</button>
    </form>
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
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div>
                                        <span class="fw-bold">
                                            <?= htmlspecialchars($task['title']) ?>
                                        </span>
                                        <p class="text-muted mb-1">
                                            <?= htmlspecialchars($task['description']) ?>
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
                                           data-category="<?= $task['category_id'] ?>">
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
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div>
                                        <span class="fw-bold">
                                            <?= htmlspecialchars($task['title']) ?>
                                        </span>
                                        <p class="text-muted mb-1">
                                            <?= htmlspecialchars($task['description']) ?>
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
                                           data-category="<?= $task['category_id'] ?>">
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
    </div>

    <!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTaskModalLabel">Add New Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="tasks.php">
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
                        <select id="add_category_id" name="category_id" class="form-select">
                            <option value="">Select Category</option>
                            <option value="1">Work</option>
                            <option value="2">Personal</option>
                        </select>
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
            <form method="POST" action="tasks.php">
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
                        <select id="edit_category_id" name="category_id" class="form-select">
                            <option value="">Select Category</option>
                            <option value="1">Work</option>
                            <option value="2">Personal</option>
                        </select>
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



</script>

</html>

