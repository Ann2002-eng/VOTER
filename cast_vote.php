<?php
session_start();
// Enable strict error reporting to catch database constraints
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli("localhost", "root", "", "school_system");
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Ensure the votes table allows one vote per position per user.
try {
    $indexCheck = $conn->query("SHOW INDEX FROM votes WHERE Key_name = 'unique_voter'");
    if ($indexCheck && $indexCheck->num_rows > 0) {
        $row = $indexCheck->fetch_assoc();
        if ($row['Column_name'] === 'user_id') {
            $conn->query("ALTER TABLE votes DROP INDEX unique_voter, ADD UNIQUE KEY unique_voter (user_id, position)");
        }
    }
} catch (Exception $e) {
    // If table/schema is not available or already fixed, continue normally.
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cast_multi_vote'])) {
    $user_id = $_SESSION['user_id'];
    
    // Ensure votes were actually sent and is an array
    if (!isset($_POST['votes']) || !is_array($_POST['votes'])) {
        header("Location: dashboard.php?vote_error=" . urlencode("No selections were made."));
        exit();
    }
    
    $votes = $_POST['votes']; 

    // 1. Determine the positions the user has already voted for
    $check = $conn->prepare("SELECT position FROM votes WHERE user_id = ?");
    $check->bind_param("i", $user_id);
    $check->execute();
    $existing_votes = $check->get_result();
    $voted_positions = [];
    while ($row = $existing_votes->fetch_assoc()) {
        $voted_positions[] = $row['position'];
    }

    // 2. Start Transaction
    $conn->begin_transaction();

    try {
        // Prepare the statement once outside the loop for performance
        $stmt = $conn->prepare("INSERT INTO votes (user_id, candidate_id, position, cast_at) VALUES (?, ?, ?, NOW())");
        
        $inserted = 0;
        foreach ($votes as $position => $candidate_id) {
            $pos = htmlspecialchars($position);
            if (in_array($pos, $voted_positions, true)) {
                continue; // skip positions already voted for
            }

            $c_id = intval($candidate_id);
            $stmt->bind_param("iis", $user_id, $c_id, $pos);
            $stmt->execute();
            $inserted++;
        }

        if ($inserted === 0) {
            $conn->rollback();
            header("Location: dashboard.php?vote_error=" . urlencode("No new positions selected or you already voted for the selected positions."));
            exit();
        }

        // 3. Log the action
        $log_action = "Cast ballots for " . $inserted . " new position(s)";
        $log_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
        $log_stmt->bind_param("is", $user_id, $log_action);
        $log_stmt->execute();

        // Save all changes
        $conn->commit();
        header("Location: receipt.php?status=success");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $message = "System error occurred while casting your ballot. Please try again or contact support.";
        header("Location: dashboard.php?vote_error=" . urlencode($message));
        exit();
    }
} else {
    header("Location: dashboard.php");
}
?>

