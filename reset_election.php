<?php
session_start();
$conn = new mysqli("localhost", "root", "", "school_system");

// 1. Security Check: Only an Admin can trigger a reset
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    die("Unauthorized Access.");
}

// 2. Clear all votes from the database
$sql_clear_votes = "TRUNCATE TABLE votes";

if ($conn->query($sql_clear_votes)) {
    
    // 3. Optional: Reset student 'has_voted' status if you use a column for it
    // $conn->query("UPDATE users SET has_voted = 0");

    // 4. Log the reset action for security purposes
    $admin_id = $_SESSION['user_id'];
    $ip = $_SERVER['REMOTE_ADDR'];
    $action = "System Reset: All election votes were cleared.";
    
    $log_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $log_stmt->bind_param("iss", $admin_id, $action, $ip);
    $log_stmt->execute();

    // 5. Redirect back to admin dashboard with a success message
    header("Location: admin.php?reset=success");
    exit();
} else {
    echo "Error resetting election: " . $conn->error;
}
?>

