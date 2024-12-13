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
    <link rel="stylesheet" href="style.css"> <!-- Link to an external CSS file for better styling -->
</head>
<style>
    /* General Reset */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* Body and Font */
body {
    font-family: Arial, sans-serif;
    background-color: #f4f4f9;
    color: #333;
    padding: 20px;
}

/* Container */
.container {
    width: 80%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    background-color: #fff;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
}

/* Header */
header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid #eee;
    padding-bottom: 10px;
    margin-bottom: 20px;
}

header h1 {
    font-size: 2rem;
    color: #333;
}

.logout-btn {
    padding: 8px 16px;
    background-color: #007BFF;
    color: #fff;
    text-decoration: none;
    border-radius: 5px;
}

.logout-btn:hover {
    background-color: #0056b3;
}

/* Form Section */
.task-form {
    margin-bottom: 30px;
}

.task-form h2 {
    font-size: 1.5rem;
    margin-bottom: 15px;
}

.task-form input, .task-form textarea, .task-form select, .task-form button {
    width: 100%;
    padding: 10px;
    margin-bottom: 10px;
    font-size: 1rem;
    border: 1px solid #ccc;
    border-radius: 5px;
}

.task-form button {
    background-color: #28a745;
    color: white;
    border: none;
}

.task-form button:hover {
    background-color: #218838;
}

/* Task List */
.task-list {
    margin-top: 30px;
}

.task-list h2 {
    font-size: 1.5rem;
    margin-bottom: 15px;
}

.task-item {
    background-color: #fafafa;
    padding: 15px;
    margin-bottom: 10px;
    border-radius: 5px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.task-details {
    max-width: 80%;
}

.task-actions {
    display: flex;
    gap: 10px;
}

.delete-btn {
    background-color: #dc3545;
    color: white;
    padding: 5px 10px;
    text-decoration: none;
    border-radius: 5px;
}

.delete-btn:hover {
    background-color: #c82333;
}

/* Task Actions */
.status-btn {
    background-color: #17a2b8;
    color: white;
    padding: 5px 10px;
    border-radius: 5px;
    text-decoration: none;
}

.status-btn:hover {
    background-color: #138496;
}

.delete-btn {
    background-color: #dc3545;
    color: white;
    padding: 5px 10px;
    border-radius: 5px;
    text-decoration: none;
}

.delete-btn:hover {
    background-color: #c82333;
}

</style>
<body>
    <div class="container">
        <header>
            <h1>Your Tasks</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </header>

        <!-- Form to add new task -->
        <section class="task-form">
            <h2>Add New Task</h2>
            <form method="POST" action="tasks.php">
                <label for="task_title">Task Title</label>
                <input type="text" id="task_title" name="task_title" required><br><br>

                <label for="task_description">Description</label>
                <textarea id="task_description" name="task_description"></textarea><br><br>

                <label for="category_id">Category</label>
                <select id="category_id" name="category_id">
                    <option value="">Select Category</option>
                    <!-- Example categories, you should fetch categories from the database -->
                    <option value="1">Work</option>
                    <option value="2">Personal</option>
                </select><br><br>

                <button type="submit">Add Task</button>
            </form>
        </section>

        <section class="task-list">
            <h2>All Tasks</h2>
            <ul>
                <?php foreach ($tasks as $task): ?>
                    <li class="task-item">
                        <div class="task-details">
                            <strong><?= htmlspecialchars($task['title']) ?></strong><br>
                            <?= htmlspecialchars($task['description']) ?>
                        </div>
                        <div class="task-actions">
                            <!-- Toggle task status (mark as completed/pending) -->
                            <a href="tasks.php?toggle_status=<?= $task['id'] ?>" class="status-btn">
                                <?= $task['status'] ? 'Mark as Pending' : 'Mark as Completed' ?>
                            </a>
                            <!-- Delete task -->
                            <a href="tasks.php?delete=<?= $task['id'] ?>" onclick="return confirm('Are you sure you want to delete this task?')" class="delete-btn">Delete</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
</body>
</html>
