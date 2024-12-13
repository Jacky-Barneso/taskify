<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Database connection
    include '../config/db.php';
    
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Check if username exists in the database
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        // If credentials match, store user session
        $_SESSION['user_id'] = $user['id'];
        header('Location: tasks.php');
        exit;
    } else {
        echo "Invalid username or password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
</head>
<body>
    <h1>Login</h1>
    <form method="POST" action="login.php">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required><br><br>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required><br><br>

        <button type="submit">Login</button>
    </form>

    <!-- Sign Up Button -->
    <p>Don't have an account? <a href="signup.php"><button type="button">Sign Up</button></a></p>
</body>
</html>
