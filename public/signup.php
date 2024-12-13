<?php
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Database connection
    include '../config/db.php';
    
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $password_hash = password_hash($password, PASSWORD_DEFAULT); // Hash the password before storing

    // Insert the new user into the database
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :password_hash)");
    
    // Execute the statement with parameters
    if ($stmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':password_hash' => $password_hash
    ])) {
        echo "Registration successful! <a href='login.php'>Login now</a>";
    } else {
        echo "Error occurred during registration!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
</head>
<body>
    <h1>Create Account</h1>
    <form method="POST" action="signup.php">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required><br><br>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" required><br><br>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required><br><br>

        <button type="submit">Sign Up</button>
    </form>

    <p>Already have an account? <a href="login.php">Login here</a></p>
</body>
</html>
