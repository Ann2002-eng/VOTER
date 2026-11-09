<?php

$conn = new mysqli("localhost", "root", "", "school_system");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
function logActivity($conn, $user_id, $action) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $action, $ip);
    $stmt->execute();
}
?>

