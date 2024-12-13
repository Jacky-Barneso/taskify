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

// Add task functionality
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['task_title'])) {
    $task_title = $_POST['task_title'];
    $task_description = $_POST['task_description'] ?? null;
    $category_id = $_POST['category_id'] ?? null;

    $stmt = $pdo->prepare("INSERT INTO tasks (title, description, user_id, category_id) 
                            VALUES (:title, :description, :user_id, :category_id)");
    $stmt->execute([
        ':title' => $task_title,
        ':description' => $task_description,
        ':user_id' => $user_id,
        ':category_id' => $category_id
    ]);
    header('Location: tasks.php');
    exit;
}

// Handle task deletion
if (isset($_GET['delete'])) {
    $task_id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $task_id, ':user_id' => $user_id]);
    header('Location: tasks.php');
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
        $new_status = !$task['status']; // Flip the boolean value
        $stmt = $pdo->prepare("UPDATE tasks SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $new_status, ':id' => $task_id]);
    }

    header('Location: tasks.php');
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
    <div class="container py-5" >
        <!-- Header -->
        <header class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
            <h1 class="display-6 text-primary">Quicklist</h1>
            <a href="login.php" class="btn btn-outline-danger">Logout</a>
        </header>

        <!-- Add Task Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4">Your Tasks</h2>
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
                        <div class="mb-3">
                            <label for="task_title" class="form-label">Task Title</label>
                            <input type="text" id="task_title" name="task_title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="task_description" class="form-label">Description</label>
                            <textarea id="task_description" name="task_description" class="form-control"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="category_id" class="form-label">Category</label>
                            <select id="category_id" name="category_id" class="form-select">
                                <option value="">Select Category</option>
                                <option value="1">Work</option>
                                <option value="2">Personal</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

