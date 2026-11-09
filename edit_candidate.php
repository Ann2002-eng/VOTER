<?php
session_start();
$conn = new mysqli("localhost", "root", "", "school_system");

// Security Check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    die("Unauthorized Access.");
}

// 1. Get current candidate data
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $res = $conn->query("SELECT * FROM candidates WHERE id = $id");
    $c = $res->fetch_assoc();
    if (!$c) die("Candidate not found.");
}

// 2. Handle the Update Logic
if (isset($_POST['update_candidate'])) {
    $name = trim($_POST['name']);
    $position = trim($_POST['position']);
    $id = intval($_POST['id']);
    $photo = $_POST['old_photo']; // Keep old photo by default

    // Check if a new photo was uploaded
    if (!empty($_FILES['photo']['name'])) {
        $target_dir = "uploads/";
        $file_extension = pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION);
        $new_photo = time() . "_" . preg_replace("/[^a-zA-Z0-0]/", "_", $name) . "." . $file_extension;
        
        if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_dir . $new_photo)) {
            // Delete the old photo file if it's not the default
            if ($photo != 'default.png' && file_exists($target_dir . $photo)) {
                unlink($target_dir . $photo);
            }
            $photo = $new_photo;
        }
    }

    $stmt = $conn->prepare("UPDATE candidates SET name = ?, position = ?, photo = ? WHERE id = ?");
    $stmt->bind_param("sssi", $name, $position, $photo, $id);
    
    if ($stmt->execute()) {
        header("Location: manage_candidates.php?msg=updated");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Candidate</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; padding: 40px; }
        .container { max-width: 500px; margin: auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        input, select { padding: 10px; margin: 10px 0; width: 100%; box-sizing: border-box; }
        .current-img { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; display: block; margin: 10px auto; border: 2px solid #1a237e; }
        button { padding: 12px; background: #1a237e; color: white; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-weight: bold; }
        .back-btn { display: block; text-align: center; margin-top: 15px; text-decoration: none; color: #666; }
    </style>
</head>
<body>

<div class="container">
    <h2>Edit Candidate</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
        <input type="hidden" name="old_photo" value="<?php echo $c['photo']; ?>">

        <label>Current Photo:</label>
        <img src="uploads/<?php echo htmlspecialchars($c['photo']); ?>" class="current-img">

        <label>Full Name</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($c['name']); ?>" required>
        
        <label>Position</label>
        <select name="position" required>
            <option value="President" <?php if($c['position'] == 'President') echo 'selected'; ?>>President</option>
            <option value="Vice President" <?php if($c['position'] == 'Vice President') echo 'selected'; ?>>Vice President</option>
            <option value="Secretary" <?php if($c['position'] == 'Secretary') echo 'selected'; ?>>Secretary</option>
            <option value="Treasurer" <?php if($c['position'] == 'Treasurer') echo 'selected'; ?>>Treasurer</option>
        </select>

        <label>Change Photo (Optional)</label>
        <input type="file" name="photo" accept="image/*">
        
        <button type="submit" name="update_candidate">Save Changes</button>
        <a href="manage_candidates.php" class="back-btn">Cancel</a>
    </form>
</div>

</body>
</html>

