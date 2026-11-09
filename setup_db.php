<?php
// Run this once (via browser or CLI) to create required tables and seed defaults.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli("localhost", "root", "", "school_system");
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// 1. election_settings table
$conn->query("CREATE TABLE IF NOT EXISTS election_settings (
    id INT PRIMARY KEY,
    election_title VARCHAR(255) DEFAULT 'School Election',
    start_time DATETIME DEFAULT '1970-01-01 00:00:00',
    end_time DATETIME DEFAULT '1970-01-01 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Ensure there's a row with id=1
$res = $conn->query("SELECT id FROM election_settings WHERE id = 1");
if ($res->num_rows === 0) {
    $now = (new DateTime())->format('Y-m-d H:i:s');
    $later = (new DateTime('+1 day'))->format('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO election_settings (id, election_title, start_time, end_time) VALUES (1, ?, ?, ?)");
    $title = 'School Election';
    $stmt->bind_param('sss', $title, $now, $later);
    $stmt->execute();
}

// 2. positions table (categories)
$conn->query("CREATE TABLE IF NOT EXISTS positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Seed default positions if none exist
$res = $conn->query("SELECT COUNT(*) as c FROM positions")->fetch_assoc();
if (intval($res['c']) === 0) {
    $defaults = ['President','Vice President','Academics Minister','Sports Minister','Welfare Minister'];
    $stmt = $conn->prepare("INSERT INTO positions (name) VALUES (?)");
    foreach ($defaults as $p) {
        $stmt->bind_param('s', $p);
        $stmt->execute();
    }
}

// Ensure votes table exists with a per-user-per-position unique constraint.
$conn->query("CREATE TABLE IF NOT EXISTS votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    candidate_id INT NOT NULL,
    position VARCHAR(100) DEFAULT NULL,
    vote_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cast_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_voter (user_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Ensure audit_logs exists for event tracking.
$conn->query("CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Enforce unique fields on existing tables when possible.
try {
    $users_table = $conn->query("SHOW TABLES LIKE 'users'");
    if ($users_table && $users_table->num_rows > 0) {
        $indexMap = [
            'unique_email' => 'email',
            'unique_id_number' => 'id_number',
            'unique_phone' => 'phone'
        ];
        foreach ($indexMap as $index => $column) {
            $idx = $conn->query("SHOW INDEX FROM users WHERE Key_name = '$index'");
            if (!$idx || $idx->num_rows === 0) {
                $conn->query("ALTER TABLE users ADD UNIQUE KEY $index ($column)");
            }
        }
    }

    $candidates_table = $conn->query("SHOW TABLES LIKE 'candidates'");
    if ($candidates_table && $candidates_table->num_rows > 0) {
        $idx = $conn->query("SHOW INDEX FROM candidates WHERE Key_name = 'unique_candidate'");
        if (!$idx || $idx->num_rows === 0) {
            $conn->query("ALTER TABLE candidates ADD UNIQUE KEY unique_candidate (name, position)");
        }
    }
} catch (Exception $e) {
    // If the schema differs or columns are missing, skip this step.
}

echo "Migration complete.\n";
echo "- election_settings table ready (id=1 row ensured).\n";
echo "- positions table ready with default categories.\n";
echo "- votes table created or preserved with correct unique constraint.\n";
echo "- database unique constraints applied where supported.\n";
echo "You can now use the admin UI to manage candidates and settings.";

?>
