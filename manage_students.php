<?php
session_start();
$conn = new mysqli("localhost", "root", "", "school_system");

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin')
    {
    die("Unauthorized.");
}

// 1. Handle Adding New Candidate
if (isset($_POST['add_candidate'])) {
    $name = $_POST['name'];
    $position = $_POST['position'];
    
    // Photo Upload Logic
    $target_dir = "uploads/";
    $photo_name = time() . "_" . basename($_FILES["photo"]["name"]);
    $target_file = $target_dir . $photo_name;

    if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
        $stmt = $conn->prepare("INSERT INTO candidates (name, position, photo) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $position, $photo_name);
        $stmt->execute();
        $msg = "Candidate added successfully!";
    }
}

// 2. Handle Deletion
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    // Delete photo from folder first
    $res = $conn->query("SELECT photo FROM candidates WHERE id = $id");
    $row = $res->fetch_assoc();
    if($row['photo']) unlink("uploads/" . $row['photo']);
    
    $conn->query("DELETE FROM candidates WHERE id = $id");
    header("Location: manage_candidates.php");
}

$candidates = $conn->query("SELECT * FROM candidates ORDER BY position ASC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Candidates</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { display: flex; margin: 0; font-family: 'Segoe UI', sans-serif; background: #f0f2f5; }
        .sidebar { width: 250px; background: #1a237e; color: white; height: 100vh; position: fixed; }
        .sidebar a { display: block; padding: 15px; color: white; text-decoration: none; border-bottom: 1px solid #283593; }
        .main { margin-left: 250px; padding: 30px; width: 100%; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        input, select { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .btn { background: #1a237e; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>

<div class="sidebar">
    <div style="padding: 20px; font-weight: bold; background: #0d1245;">VOTER ADMIN</div>
    <a href="admin.php"><i class="fas fa-home"></i> Overview</a>
    <a href="manage_students.php"><i class="fas fa-users"></i> Manage Students</a>
    <a href="manage_candidates.php"><i class="fas fa-user-tie"></i> Manage Candidates</a>
    <a href="settings.php"><i class="fas fa-cog"></i> Election Settings</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main">
    <h2>Manage Candidates</h2>

    <div class="card">
        <h3>Add New Candidate</h3>
        <form method="POST" enctype="multipart/form-data">
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: end;">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" required placeholder="e.g. John Doe">
                </div>
                <div class="form-group">
                    <label>Position</label>
                    <input type="text" name="position" required placeholder="e.g. President">
                </div>
                <div class="form-group">
                    <label>Photo</label>
                    <input type="file" name="photo" required accept="image/*">
                </div>
                <button type="submit" name="add_candidate" class="btn">Add Candidate</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>Current Nominees</h3>
        <table>
            <thead>
                <tr>
                    <th>Photo</th><th>Name</th><th>Position</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($c = $candidates->fetch_assoc()): ?>
                <tr>
                    <td><img src="uploads/<?php echo $c['photo']; ?>" width="50" height="50" style="border-radius:50%; object-fit:cover;"></td>
                    <td><?php echo htmlspecialchars($c['name']); ?></td>
                    <td><strong><?php echo htmlspecialchars($c['position']); ?></strong></td>
                    <td>
                        <a href="?delete_id=<?php echo $c['id']; ?>" style="color:red;" onclick="return confirm('Delete candidate?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>

