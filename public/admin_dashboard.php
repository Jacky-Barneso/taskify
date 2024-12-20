<?php
session_start();
include '../config/db.php';
include '../config/activity_log.php';

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

// Fetch activity logs
$stmtLogs = $pdo->prepare("SELECT * FROM activity_logs ORDER BY action_time DESC");
$stmtLogs->execute();
$activityLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);


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

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id_to_delete = $_POST['user_id'];

    // Ensure the user ID is numeric to prevent SQL injection
    if (!is_numeric($user_id_to_delete)) {
        header('Location: admin_dashboard.php?message=Invalid user ID');
        exit;
    }

    // Check if the user exists and is not an admin
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND role != 'admin'");
    $stmt->execute([':id' => $user_id_to_delete]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header('Location: admin_dashboard.php?message=User not found or cannot delete admin');
        exit;
    }

    // Log the activity before deleting the user
    logActivity($admin_id, 'Delete User', 'users', $user_id_to_delete);

    // Perform the delete operation
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id_to_delete]);

    // Redirect with a success message
    header('Location: admin_dashboard.php?message=User deleted successfully');
    exit;
}

// Handle user update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $user_id_to_update = $_POST['user_id'];
    $updated_username = htmlspecialchars($_POST['username']);
    $updated_email = htmlspecialchars($_POST['email']);
    $updated_role = $_POST['role'];

    // Ensure the user ID is numeric to prevent SQL injection
    if (!is_numeric($user_id_to_update)) {
        header('Location: admin_dashboard.php?message=Invalid user ID');
        exit;
    }

    // Update the user information
    $stmt = $pdo->prepare("UPDATE users SET username = :username, email = :email, role = :role WHERE id = :id");
    $stmt->execute([
        ':username' => $updated_username,
        ':email' => $updated_email,
        ':role' => $updated_role,
        ':id' => $user_id_to_update,
    ]);

    // Log the activity
    logActivity($admin_id, 'Edit User', 'users', $user_id_to_update);

    // Redirect with a success message
    header('Location: admin_dashboard.php?message=User updated successfully');
    exit;
}

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
                                    <!-- Delete Button -->
                                    <form action="admin_dashboard.php" method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?= $singleUser['id']; ?>">
                                        <button type="submit" name="delete_user" class="btn btn-danger btn-sm" 
                                                onclick="return confirm('Are you sure you want to delete this user?');">Delete</button>
                                    </form>

                                    <!-- Edit Button -->
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $singleUser['id']; ?>">
                                        Edit
                                    </button>

                                    <!-- Edit User Modal -->
                                    <div class="modal fade" id="editUserModal<?= $singleUser['id']; ?>" tabindex="-1" aria-labelledby="editUserModalLabel<?= $singleUser['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="admin_dashboard.php" method="POST">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="editUserModalLabel<?= $singleUser['id']; ?>">Edit User Information</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="user_id" value="<?= $singleUser['id']; ?>">
                                                        <div class="mb-3">
                                                            <label for="username<?= $singleUser['id']; ?>" class="form-label">Username</label>
                                                            <input type="text" class="form-control" id="username<?= $singleUser['id']; ?>" name="username" value="<?= htmlspecialchars($singleUser['username']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="email<?= $singleUser['id']; ?>" class="form-label">Email</label>
                                                            <input type="email" class="form-control" id="email<?= $singleUser['id']; ?>" name="email" value="<?= htmlspecialchars($singleUser['email']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="role<?= $singleUser['id']; ?>" class="form-label">Role</label>
                                                            <select class="form-select" id="role<?= $singleUser['id']; ?>" name="role" required>
                                                                <option value="user" <?= $singleUser['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                                                <option value="admin" <?= $singleUser['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="edit_user" class="btn btn-success">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
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

            <!-- Activity Logs Tab -->
            <div class="tab-pane fade" id="logs" role="tabpanel">
                <h2>Activity Logs</h2>

                <!-- Search Bar -->
                <input type="text" id="searchLogs" class="form-control mb-3" placeholder="Search Activity Logs...">

                <table class="table table-bordered table-striped bg-white text-dark">
                    <thead>
                        <tr>
                            <th>Log ID</th>
                            <th>User ID</th>
                            <th>Action</th>
                            <th>Table Name</th>
                            <th>Record ID</th>
                            <th>Action Time</th>
                        </tr>
                    </thead>
                    <tbody id="logsTableBody">
                        <?php foreach ($activityLogs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['id']); ?></td>
                                <td><?= htmlspecialchars($log['user_id']); ?></td>
                                <td><?= htmlspecialchars($log['action']); ?></td>
                                <td><?= htmlspecialchars($log['table_name']); ?></td>
                                <td><?= htmlspecialchars($log['record_id']); ?></td>
                                <td><?= htmlspecialchars($log['action_time']); ?></td>
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
