<?php
session_start();
$conn = new mysqli("localhost", "root", "", "school_system");

// Security Check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    die("Unauthorized Access.");
}

// --- HANDLE ADDING CANDIDATE ---
if (isset($_POST['add_candidate'])) {
    $name = trim($_POST['name']);
    $position = trim($_POST['position']);
    $photo = "default.png"; // Default image

    // Handle File Upload
    if (!empty($_FILES['photo']['name'])) {
        $target_dir = "uploads/";
        // Create directory if it doesn't exist
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION);
        $photo = time() . "_" . preg_replace("/[^a-zA-Z0-0]/", "_", $name) . "." . $file_extension;
        $target_file = $target_dir . $photo;

        if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
            $photo = "default.png"; // Fallback if upload fails
        }
    }
    
    $stmt = $conn->prepare("INSERT INTO candidates (name, position, photo) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $position, $photo);
    
    if ($stmt->execute()) {
        header("Location: manage_candidates.php?msg=added");
        exit();
    }
}

// --- HANDLE ADDING POSITION/CATEGORY ---
if (isset($_POST['add_position'])) {
    $pos_name = trim($_POST['position_name']);
    if (!empty($pos_name)) {
        $stmt = $conn->prepare("INSERT IGNORE INTO positions (name) VALUES (?)");
        $stmt->bind_param('s', $pos_name);
        $stmt->execute();
        header("Location: manage_candidates.php?msg=pos_added");
        exit();
    }
}

// --- HANDLE DELETING CANDIDATE ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Optional: Delete the actual photo file from the server
    $res = $conn->query("SELECT photo FROM candidates WHERE id = $id");
    $row = $res->fetch_assoc();
    if($row && $row['photo'] != 'default.png' && file_exists("uploads/".$row['photo'])){
        unlink("uploads/".$row['photo']);
    }

    $conn->query("DELETE FROM candidates WHERE id = $id");
    header("Location: manage_candidates.php?msg=deleted");
    exit();
}

$candidates = $conn->query("SELECT * FROM candidates ORDER BY position ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Candidates | Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; padding: 20px; }
        .container { max-width: 900px; margin: auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .form-box { background: #e8eaf6; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        input, select { padding: 10px; margin: 10px 0; width: 100%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        label { font-weight: bold; font-size: 0.9em; color: #333; }
        button { padding: 12px 20px; background: #1a237e; color: white; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-weight: bold; }
        button:hover { background: #0d1440; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        .candidate-thumb { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 1px solid #ddd; }
        .edit-btn { color: #1a237e; text-decoration: none; font-weight: bold; margin-right: 15px; }
        .delete-btn { color: #f44336; text-decoration: none; font-weight: bold; }
        .nav-link { display: inline-block; margin-bottom: 20px; text-decoration: none; color: #1a237e; font-weight: bold; }
        .msg { padding: 10px; background: #d4edda; color: #155724; border-radius: 5px; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="container">
    <a href="admin.php" class="nav-link">← Back to Dashboard</a>
    <h2>Manage Candidates</h2>

    <?php if(isset($_GET['msg'])): ?>
        <div class="msg">
            <?php 
                if($_GET['msg'] == 'added') echo "Candidate registered successfully!";
                if($_GET['msg'] == 'deleted') echo "Candidate removed successfully!";
                if($_GET['msg'] == 'updated') echo "Candidate details updated successfully!";
            ?>
        </div>
    <?php endif; ?>

    <div class="form-box">
        <h3>Add New Candidate</h3>
        <form method="POST" enctype="multipart/form-data">
            <label>Full Name</label>
            <input type="text" name="name" placeholder="Enter candidate's full name" required>
            
            <label>Position</label>
            <select name="position" required>
                <option value="">Select Position</option>
                <?php
                    $pos_q = $conn->query("SELECT name FROM positions ORDER BY name ASC");
                    while($p = $pos_q->fetch_assoc()):
                ?>
                    <option value="<?php echo htmlspecialchars($p['name']); ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                <?php endwhile; ?>
            </select>

            <label>Candidate Photo</label>
            <input type="file" name="photo" accept="image/*">
            
            <button type="submit" name="add_candidate">Register Candidate</button>
        </form>
    </div>

    <div class="form-box">
        <h3>Add New Category / Position</h3>
        <form method="POST">
            <label>Category Name</label>
            <input type="text" name="position_name" placeholder="e.g. Welfare Minister" required>
            <button type="submit" name="add_position">Add Category</button>
        </form>
    </div>

    <h3>Current Candidates List</h3>
    <table>
        <thead>
            <tr>
                <th>Photo</th>
                <th>Name</th>
                <th>Position</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while($c = $candidates->fetch_assoc()): ?>
            <tr>
                <td><img src="uploads/<?php echo htmlspecialchars($c['photo']); ?>" class="candidate-thumb" alt="Profile"></td>
                <td><strong><?php echo htmlspecialchars($c['name']); ?></strong></td>
                <td><?php echo htmlspecialchars($c['position']); ?></td>
                <td>
                    <a href="edit_candidate.php?id=<?php echo $c['id']; ?>" class="edit-btn">Edit</a>
                    
                    <a href="manage_candidates.php?delete=<?php echo $c['id']; ?>" 
                       class="delete-btn" 
                       onclick="return confirm('Are you sure? This will remove all data for this candidate!')">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

</body>
</html>

