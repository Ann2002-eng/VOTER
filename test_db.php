<?php
$conn = new mysqli("localhost", "root", "", "school_system");

if ($conn->connect_error) {
    die("❌ Connection Failed: " . $conn->connect_error);
}

echo "<h3>Database Connection: Success!</h3>";

// Let's list every single user currently in the table
$result = $conn->query("SELECT email, user_role FROM users");

if ($result->num_rows > 0) {
    echo "<h4>Users found in database:</h4><ul>";
    while($row = $result->fetch_assoc()) {
        echo "<li>Email: <strong>" . $row['email'] . "</strong> | Role: " . $row['user_role'] . "</li>";
    }
    echo "</ul>";
} else {
    echo "<h4 style='color:red;'>❌ ZERO USERS FOUND. Your 'users' table is empty.</h4>";
}
?>

