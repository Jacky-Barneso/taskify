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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Tasks</title>
</head>
<body>
    <h1>Your Tasks</h1>

    <!-- Form to add new task -->
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

    <h2>All Tasks</h2>
    <ul>
        <?php foreach ($tasks as $task): ?>
            <li>
                <strong><?= htmlspecialchars($task['title']) ?></strong><br>
                <?= htmlspecialchars($task['description']) ?><br>
                <!-- Add edit and delete links -->
                <a href="tasks.php?delete=<?= $task['id'] ?>" onclick="return confirm('Are you sure you want to delete this task?')">Delete</a>
            </li>
        <?php endforeach; ?>
    </ul>
</body>
</html>
