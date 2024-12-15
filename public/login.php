<?php
session_start();
 // Database connection
include '../config/db.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Check if username and role exist in the database
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username AND role = :role");
    $stmt->execute([':username' => $username, ':role' => $role]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
          // Check if the user is an admin
        if ($user['role'] === 'admin') {
            $_SESSION['admin_id'] = $user['id']; // Log in as admin
        }
        // If credentials match, store user session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role']; // Store role in the session
        
        // Redirect based on the selected role
        if ($role === 'admin') {
            header('Location: admin_dashboard.php');
        } else {
            header('Location: tasks.php');
        }
        exit;
    } else {
        echo "Invalid username, password, or role!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to right, #6a11cb, #2575fc);
            color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.5); /* Updated to make it more transparent */
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
        }
        .form-label {
            color: #333;
        }
        .btn-primary {
            background-color: #6a11cb;
            border-color: #6a11cb;
        }
        .btn-primary:hover {
            background-color: #2575fc;
            border-color: #2575fc;
        }
        .signup-link {
            color: #6a11cb;
            font-weight: bold;
        }
        .signup-link:hover {
            color: #2575fc;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1 class="text-center mb-4">Login</h1>
        <form method="POST" action="login.php">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" id="username" name="username" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="role" class="form-label">Role</label>
                <select id="role" name="role" class="form-select" required>
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>

        <div class="text-center mt-3">
            <p>Don't have an account? <a href="signup.php" class="signup-link">Sign Up</a></p>
        </div>
    </div>

    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
