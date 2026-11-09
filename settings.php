<?php
session_start();
$conn = new mysqli("localhost", "root", "", "school_system");

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    die("Access Denied.");
}

$msg = "";
$admin_id = $_SESSION['user_id'];

// Handle Settings Update
if (isset($_POST['save_settings'])) {
    $title = $_POST['election_title'];
    $start = $_POST['start_time'];
    $end = $_POST['end_time'];

    $stmt = $conn->prepare("UPDATE election_settings SET election_title=?, start_time=?, end_time=? WHERE id=1");
    $stmt->bind_param("sss", $title, $start, $end);
    
    if ($stmt->execute()) {
        $msg = "<div class='alert success'>Election settings updated successfully!</div>";
    } else {
        $msg = "<div class='alert danger'>Error updating settings.</div>";
    }
}

// Fetch Current Settings
$settings = $conn->query("SELECT * FROM election_settings WHERE id = 1")->fetch_assoc();
$admin = $conn->query("SELECT profile_pic, first_name FROM users WHERE id = $admin_id")->fetch_assoc();
$pic = !empty($admin['profile_pic']) ? $admin['profile_pic'] : 'default_user.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Election Settings | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary: #0f172a; --accent: #38bdf8; --bg: #f8fafc; }
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg); display: flex; }
        
        .sidebar { width: 260px; height: 100vh; background: var(--primary); color: white; position: fixed; display: flex; flex-direction: column; }
        .sidebar-header { padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .nav-item { padding: 15px 25px; display: block; color: #94a3b8; text-decoration: none; transition: 0.3s; }
        .nav-item.active { background: #1e293b; color: white; border-left: 4px solid var(--accent); }

        .main-content { margin-left: 260px; padding: 40px; width: 100%; }
        .settings-card { background: white; max-width: 700px; margin: auto; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); overflow: hidden; }
        .card-header { background: var(--primary); color: white; padding: 25px; display: flex; align-items: center; }
        .card-body { padding: 40px; }
        
        .form-group { margin-bottom: 25px; }
        label { display: block; font-size: 0.85em; font-weight: 700; color: #64748b; margin-bottom: 8px; text-transform: uppercase; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 1em; box-sizing: border-box; }
        
        .btn-save { background: var(--accent); color: var(--primary); border: none; padding: 15px 30px; border-radius: 8px; font-weight: 800; cursor: pointer; width: 100%; transition: 0.3s; }
        .btn-save:hover { background: #7dd3fc; transform: translateY(-2px); }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: 600; }
        .success { background: #dcfce7; color: #166534; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="uploads/profiles/<?php echo $pic; ?>" style="width: 70px; height: 70px; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent);">
            <p style="margin: 10px 0 0; font-weight: bold;">Admin Control</p>
        </div>
        <nav style="margin-top: 20px;">
            <a href="admin.php" class="nav-item"><i class="fas fa-chart-pie"></i> Overview</a>
            <a href="manage_candidates.php" class="nav-item"><i class="fas fa-user-tie"></i> Candidates</a>
            <a href="settings.php" class="nav-item active"><i class="fas fa-cog"></i> Settings</a>
            <a href="logout.php" class="nav-item" style="color: #fda4af;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="settings-card">
            <div class="card-header">
                <i class="fas fa-tools" style="font-size: 1.5em; margin-right: 15px;"></i>
                <h2 style="margin:0;">Election Configuration</h2>
            </div>
            <div class="card-body">
                <?php echo $msg; ?>
                <form method="POST">
                    <div class="form-group">
                        <label>Election Title</label>
                        <input type="text" name="election_title" class="form-control" value="<?php echo htmlspecialchars($settings['election_title']); ?>" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Start Date & Time</label>
                            <input type="datetime-local" name="start_time" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($settings['start_time'])); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>End Date & Time</label>
                            <input type="datetime-local" name="end_time" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($settings['end_time'])); ?>" required>
                        </div>
                    </div>

                    <div style="background: #f1f5f9; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                        <small style="color: #64748b;"><i class="fas fa-info-circle"></i> <strong>Note:</strong> Changing these times will immediately affect the Voter Dashboard countdown and voting access.</small>
                    </div>

                    <button type="submit" name="save_settings" class="btn-save">Apply System Changes</button>
                </form>
            </div>
        </div>
    </main>

</body>
</html>

