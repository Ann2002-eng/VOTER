<?php
// 1. Force Error Reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 2. Database Credentials
$host = "localhost";
$user = "root";
$pass = "";
$db   = "school_system"; // IMPORTANT: Ensure this matches phpMyAdmin exactly

try {
    $conn = new mysqli($host, $user, $pass, $db);
} catch (Exception $e) {
    die("❌ Connection Failed: " . $e->getMessage());
}

if (isset($_POST['submit_btn'])) {
    // 3. Capture and Hash
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $email = $_POST['pmail'];
    $idn   = $_POST['idn'];
    $phone = $_POST['phoneno'];
    $pass_hash = password_hash($_POST['pass'], PASSWORD_BCRYPT);

    try {
        $dup_check = $conn->prepare("SELECT id FROM users WHERE email = ? OR id_number = ? OR phone = ? LIMIT 1");
        $dup_check->bind_param("sss", $email, $idn, $phone);
        $dup_check->execute();
        $dup_exist = $dup_check->get_result()->fetch_assoc();

        if ($dup_exist) {
            die("❌ Duplicate entry detected: email, ID number, or phone is already registered.");
        }

        // 4. Prepared Statement
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, id_number, phone, password) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $fname, $lname, $email, $idn, $phone, $pass_hash);
        
        $stmt->execute();

        // 5. THE ULTIMATE CHECK
        if ($stmt->affected_rows > 0) {
            echo "<script>alert('✅ DATA SAVED! Check phpMyAdmin now.'); window.location='login.php';</script>";
        } else {
            echo "⚠️ The query ran but 0 rows were inserted.";
        }
        $stmt->close();

    } catch (mysqli_sql_exception $e) {
        die("❌ Database Error: " . $e->getMessage());
    }
}
$conn->close();
?>

