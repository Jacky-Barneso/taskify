<?php
session_start();
include '../config/db.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch user data from the database
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Verify if the logged-in user is an admin
if ($user['role'] !== 'admin') {
    echo "Access denied. You do not have permission to view this page.";
    exit;
}

// Admin-specific functionality
$stmtUsers = $pdo->prepare("SELECT * FROM users ORDER BY created_at DESC");
$stmtUsers->execute();
$allUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
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
    </style>
</head>
<body>
    <div class="dashboard-container container">
        <div class="admin-header d-flex justify-content-between align-items-center">
            <h1>Welcome, Admin <?= htmlspecialchars($user['username']); ?></h1>
            <a href="login.php" class="btn btn-logout">Logout</a>
        </div>

        <h2>User Management</h2>
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
            <tbody>
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
