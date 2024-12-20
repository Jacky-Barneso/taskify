<?php
// activity_log.php
function logActivity($user_id, $action, $table_name, $record_id = null, $action_time = null) {
    global $pdo;

    // If no action time is provided, use the current timestamp
    if ($action_time === null) {
        $action_time = date('Y-m-d H:i:s'); // Current timestamp in 'YYYY-MM-DD HH:MM:SS' format
    }

    // Prepare the SQL query to insert activity log
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, table_name, record_id, action_time)
        VALUES (:user_id, :action, :table_name, :record_id, :action_time)
    ");

    // Bind the parameters to the query
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':action', $action, PDO::PARAM_STR);
    $stmt->bindParam(':table_name', $table_name, PDO::PARAM_STR);
    $stmt->bindParam(':record_id', $record_id, PDO::PARAM_INT);
    $stmt->bindParam(':action_time', $action_time, PDO::PARAM_STR);

    // Execute the query
    try {
        $stmt->execute();
    } catch (PDOException $e) {
        // Handle any errors during execution
        echo "Error logging activity: " . $e->getMessage();
    }
}
?>
