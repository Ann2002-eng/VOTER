<?php
session_start();

// 1. Connection (Using XAMPP default: no password)
$conn = new mysqli("localhost", "root", "", "school_system");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. Security Check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    die("Unauthorized Access.");
}

// 3. Clear any previous output (Crucial for CSV downloads)
if (ob_get_level()) ob_end_clean();

// 4. Set Headers for CSV Download
$filename = "Election_Results_" . date('Y-m-d_H-i-s') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// 5. Open output stream
$output = fopen('php://output', 'w');

// 6. Column Headers
fputcsv($output, array('Candidate Name', 'Position', 'Total Votes'));

// 7. Fetch Results from Database
$query = "SELECT c.name, c.position, COUNT(v.id) as vote_count 
          FROM candidates c 
          LEFT JOIN votes v ON c.id = v.candidate_id 
          GROUP BY c.id 
          ORDER BY vote_count DESC";

$results = $conn->query($query);

if (!$results) {
    die("Error in query: " . $conn->error);
}

// 8. Write rows to CSV
while ($row = $results->fetch_assoc()) {
    fputcsv($output, array(
        $row['name'], 
        $row['position'], 
        $row['vote_count']
    ));
}

fclose($output);
exit();
?>

