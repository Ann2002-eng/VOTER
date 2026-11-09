<?php
session_start();
require_once 'db_config.php';

if (isset($_POST['update_btn'])) {
    $uid = $_SESSION['user_id'];
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    
    $stmt = $conn->prepare("UPDATE users SET phone = ? WHERE id = ?");
    $stmt->bind_param("si", $phone, $uid);
    
    if ($stmt->execute()) {
        header("Location: dashboard.php?update=success");
    }
}
?>

