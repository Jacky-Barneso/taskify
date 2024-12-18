<?php
session_start();
include '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
    echo "Access denied. You do not have permission to view this page.";
    exit;
}

// Fetch admin data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $_SESSION['admin_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "Access denied. User not found.";
    exit;
}

// Define or retrieve $specificUserId
$specificUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

// Check if $specificUserId is set before using it
if ($specificUserId) {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $specificUserId]);
    $userTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    echo "No specific user ID provided.";
}

// Fetch all users
$stmtUsers = $pdo->prepare("SELECT * FROM users ORDER BY created_at DESC");
$stmtUsers->execute();
$allUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

// Fetch all tasks
$stmtTasks = $pdo->prepare("
    SELECT tasks.id, tasks.user_id, tasks.title, tasks.description, tasks.status, tasks.created_at, users.username 
    FROM tasks 
    INNER JOIN users ON tasks.user_id = users.id 
    ORDER BY tasks.created_at DESC
");
$stmtTasks->execute();
$allTasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

// Fetch all categories
$stmtCategories = $pdo->prepare("
    SELECT categories.id, categories.name, categories.user_id, users.username 
    FROM categories
    INNER JOIN users ON categories.user_id = users.id
    ORDER BY categories.id ASC
");
$stmtCategories->execute();
$allCategories = $stmtCategories->fetchAll(PDO::FETCH_ASSOC);

// Fetch database views
// View: task_summary_by_user
$stmtSummary = $pdo->prepare("SELECT * FROM task_summary_by_user");
$stmtSummary->execute();
$taskSummary = $stmtSummary->fetchAll(PDO::FETCH_ASSOC);

// View: tasks_with_category
$stmtTasksWithCategory = $pdo->prepare("SELECT * FROM tasks_with_category");
$stmtTasksWithCategory->execute();
$tasksWithCategory = $stmtTasksWithCategory->fetchAll(PDO::FETCH_ASSOC);

// Fetch category task stats
$stmtCategoryTaskStats = $pdo->prepare("SELECT * FROM category_task_stats");
$stmtCategoryTaskStats->execute();
$categoryTaskStats = $stmtCategoryTaskStats->fetchAll(PDO::FETCH_ASSOC);

function logActivity($pdo, $actorId, $actionType, $tableName, $details, $actorType = 'user') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action_type, table_name, details, actor_type, timestamp) 
            VALUES (:user_id, :action_type, :table_name, :details, :actor_type, NOW())
        ");
        $stmt->execute([
            ':user_id' => $actorId,
            ':action_type' => $actionType,
            ':table_name' => $tableName,
            ':details' => $details,
            ':actor_type' => $actorType
        ]);
    } catch (PDOException $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}


$stmtLogs = $pdo->prepare("
    SELECT 
        activity_logs.*, 
        users.username 
    FROM 
        activity_logs 
    INNER JOIN 
        users 
    ON 
        activity_logs.user_id = users.id 
    WHERE 
        activity_logs.user_id = :user_id
    ORDER BY 
        created_at DESC
");
$stmtLogs->execute([':user_id' => $specificUserId]);
$filteredLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to right, #6a11cb, #2575fc);
            color: #fff;
            margin: 0;
            padding: 0;
        }
        .dashboard-container {
            padding: 20px;
        }
        .admin-header {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .btn-logout {
            background-color: #ff6b6b;
            border-color: #ff6b6b;
        }
        .btn-logout:hover {
            background-color: #fc4a4a;
        }

        .search-bar {
            margin-bottom: 20px;
        }
        .options-icon {
            position: relative;
        }
        .dropdown-menu {
            left: auto;
            right: 0;
        }
    </style>
</head>
<body>
    <div class="dashboard-container container">
        <div class="admin-header d-flex justify-content-between align-items-center">
            <h1>Welcome, Admin <?= htmlspecialchars($user['username']); ?></h1>
            <a href="login.php" class="btn btn-logout">Logout</a>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" role="tab">Users</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="tasks-tab" data-bs-toggle="tab" data-bs-target="#tasks" role="tab">Tasks</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories" role="tab">Categories</button>
            </li>
            <li class="nav-item">
            <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" role="tab">Activity Logs</button>
            </li>
        </ul>

        <!-- Tab Contents -->
        <div class="tab-content" id="dashboardTabContent">

            <!-- Users Tab -->
            <div class="tab-pane fade show active" id="users" role="tabpanel">
                <h2>User Management</h2>

                <!-- Search Bar -->
                <input type="text" id="searchUsers" class="form-control mb-3" placeholder="Search Users...">

                <table class="table table-bordered table-striped bg-white text-dark">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <?php foreach ($allUsers as $singleUser): ?>
                            <tr>
                                <td><?= htmlspecialchars($singleUser['id']); ?></td>
                                <td><?= htmlspecialchars($singleUser['username']); ?></td>
                                <td><?= htmlspecialchars($singleUser['email']); ?></td>
                                <td><?= htmlspecialchars($singleUser['role']); ?></td>
                                <td><?= htmlspecialchars($singleUser['created_at']); ?></td>
                                <td>
                                    <?php if ($singleUser['role'] !== 'admin'): ?>
                                        <form action="delete_user.php" method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?= $singleUser['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="tab-pane fade" id="tasks" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="mb-0">Task Management</h2>
                    <div class="dropdown">
                        <button class="btn btn-secondary dropdown-toggle" type="button" id="taskOptionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-three-dots"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="taskOptionsDropdown">
                            <li><a class="dropdown-item" href="#" id="viewTaskSummary">View Task Summary</a></li>
                            <li><a class="dropdown-item" href="#" id="viewTasksWithCategory">Tasks With Categories</a></li>
                            <li><a class="dropdown-item" href="#" id="viewCategoryTaskStats">Category Task Stats</a></li>
                        </ul>
                    </div>
                </div>

                <input type="text" id="searchTasks" class="form-control mb-3" placeholder="Search Tasks...">


                <div id="taskTableContainer">
                    <!-- Default Tasks Table -->
                    <table class="table table-bordered table-striped bg-white text-dark">
                        <thead>
                            <tr>
                                <th>Task ID</th>
                                <th>User</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody id="tasksTableBody">
                            <?php foreach ($allTasks as $task): ?>
                                <tr>
                                    <td><?= htmlspecialchars($task['id']); ?></td>
                                    <td><?= htmlspecialchars($task['username']); ?></td>
                                    <td><?= htmlspecialchars($task['title']); ?></td>
                                    <td><?= htmlspecialchars($task['description']); ?></td>
                                    <td><?= htmlspecialchars($task['status']); ?></td>
                                    <td><?= htmlspecialchars($task['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Task Summary Table -->
                <div class="tab-pane" id="taskSummaryContainer" style="display: none;">
                    <h3>Task Summary by User</h3>
                    <table class="table table-bordered table-striped bg-white text-dark">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Username</th>
                                <th>Completed Tasks</th>
                                <th>Pending Tasks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($taskSummary as $summary): ?>
                                <tr>
                                    <td><?= htmlspecialchars($summary['user_id']); ?></td>
                                    <td><?= htmlspecialchars($summary['username']); ?></td>
                                    <td><?= htmlspecialchars($summary['completed_tasks']); ?></td>
                                    <td><?= htmlspecialchars($summary['pending_tasks']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Tasks With Category Table -->
                <div id="tasksWithCategoryContainer" style="display: none;">
                    <h3>Tasks With Categories</h3>
                    <table class="table table-bordered table-striped bg-white text-dark">
                        <thead>
                            <tr>
                                <th>Task ID</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Category</th>
                                <th>User ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasksWithCategory as $taskWithCategory): ?>
                                <tr>
                                    <td><?= htmlspecialchars($taskWithCategory['task_id']); ?></td>
                                    <td><?= htmlspecialchars($taskWithCategory['title']); ?></td>
                                    <td><?= htmlspecialchars($taskWithCategory['description']); ?></td>
                                    <td><?= htmlspecialchars($taskWithCategory['status']); ?></td>
                                    <td><?= htmlspecialchars($taskWithCategory['category_name']); ?></td>
                                    <td><?= htmlspecialchars($taskWithCategory['user_id']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Category Task Stats Table -->
            <div class="tab-pane" id="categoryTaskStatsContainer" style="display: none;">
                <h3>Category Task Stats</h3>
                <table class="table table-bordered table-striped bg-white text-dark">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>User Name</th>
                            <th>Category ID</th>
                            <th>Category Name</th>
                            <th>Completed Tasks</th>
                            <th>Pending Tasks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categoryTaskStats as $stat): ?>
                            <tr>
                                <td><?= htmlspecialchars($stat['user_id']); ?></td>
                                <td><?= htmlspecialchars($stat['user_name']); ?></td>
                                <td><?= htmlspecialchars($stat['category_id']); ?></td>
                                <td><?= htmlspecialchars($stat['category_name']); ?></td>
                                <td><?= htmlspecialchars($stat['completed_tasks']); ?></td>
                                <td><?= htmlspecialchars($stat['pending_tasks']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>



            <!-- Categories Tab -->
            <div class="tab-pane fade" id="categories" role="tabpanel">
                <h2>Category Management</h2>

                <!-- Search Bar -->
                <input type="text" id="searchCategories" class="form-control mb-3" placeholder="Search Categories...">

                <table class="table table-bordered table-striped bg-white text-dark">
                    <thead>
                        <tr>
                            <th>Category ID</th>
                            <th>Name</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="categoriesTableBody">
                        <?php foreach ($allCategories as $category): ?>
                            <tr>
                                <td><?= htmlspecialchars($category['id']); ?></td>
                                <td><?= htmlspecialchars($category['name']); ?></td>
                                <td><?= htmlspecialchars($category['username']); ?></td>
                                <td>
                                    <form action="delete_category.php" method="POST" style="display: inline;">
                                        <input type="hidden" name="category_id" value="<?= $category['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="tab-pane fade" id="logs" role="tabpanel">
    <h2>Activity Logs</h2>
    <input type="text" id="searchLogs" class="form-control mb-3" placeholder="Search Logs...">

    <table class="table table-bordered table-striped bg-white text-dark">
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Action</th>
                <th>Table</th>
                <th>Details</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody id="logsTableBody">
        <?php foreach ($filteredLogs as $log): ?>
            <tr>
                <td><?= htmlspecialchars($log['username']); ?></td>
                <td><?= htmlspecialchars($log['action_type']); ?></td>
                <td><?= htmlspecialchars($log['table_name']); ?></td>
                <td><?= htmlspecialchars($log['details']); ?></td>
                <td><?= htmlspecialchars($log['actor_type']); ?></td>
                <td><?= htmlspecialchars($log['timestamp']); ?></td>
            </tr>
        <?php endforeach; ?>

        </tbody>
    </table>
</div>

        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Search Filtering JS -->
    <script>
        function filterTable(inputId, tableBodyId) {
            const input = document.getElementById(inputId);
            const filter = input.value.toLowerCase();
            const tableBody = document.getElementById(tableBodyId);
            const rows = tableBody.getElementsByTagName('tr');

        for (const row of rows) {
            const cells = row.getElementsByTagName('td');
            let matches = false;

            for (const cell of cells) {
                if (cell.textContent.toLowerCase().includes(filter)) {
                    matches = true;
                    break;
                }
            }
            row.style.display = matches ? '' : 'none';
        }
    }

        document.getElementById('viewTaskSummary').addEventListener('click', function () {
            document.getElementById('taskTableContainer').style.display = 'none';
            document.getElementById('taskSummaryContainer').style.display = 'block';
            document.getElementById('tasksWithCategoryContainer').style.display = 'none';
            document.getElementById('categoryTaskStatsContainer').style.display = 'none';
        });

        document.getElementById('viewTasksWithCategory').addEventListener('click', function () {
            document.getElementById('taskTableContainer').style.display = 'none';
            document.getElementById('taskSummaryContainer').style.display = 'none';
            document.getElementById('tasksWithCategoryContainer').style.display = 'block';
            document.getElementById('categoryTaskStatsContainer').style.display = 'none';
        });

        // New Event Listener for Category Task Stats
        document.getElementById('viewCategoryTaskStats').addEventListener('click', function () {
            document.getElementById('taskTableContainer').style.display = 'none';
            document.getElementById('taskSummaryContainer').style.display = 'none';
            document.getElementById('tasksWithCategoryContainer').style.display = 'none';
            document.getElementById('categoryTaskStatsContainer').style.display = 'block';
        });

        document.getElementById('searchUsers').addEventListener('keyup', () => filterTable('searchUsers', 'usersTableBody'));
        document.getElementById('searchTasks').addEventListener('keyup', () => filterTable('searchTasks', 'tasksTableBody'));
        document.getElementById('searchCategories').addEventListener('keyup', () => filterTable('searchCategories', 'categoriesTableBody'));
        
        document.getElementById('searchLogs').addEventListener('keyup', function () {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll('#logsTableBody tr');
        
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            const matches = Array.from(cells).some(cell => 
                cell.textContent.toLowerCase().includes(filter)
        );
        row.style.display = matches ? '' : 'none';
        });
    });

    </script>
</body>
</html>
