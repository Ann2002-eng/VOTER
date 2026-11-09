<?php
session_start();
// Enable strict error reporting for debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli("localhost", "root", "", "school_system");
} catch (Exception $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";

// --- HANDLE PHOTO & PASSWORD UPDATE ---
if (isset($_POST['update_profile'])) {
    try {
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];
        $photo_name = $_POST['current_photo'];

        // 1. Handle File Upload
        if (!empty($_FILES['profile_pic']['name'])) {
            $target_dir = "uploads/profiles/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

            $ext = strtolower(pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION));
            $new_photo = "user_" . $user_id . "_" . time() . "." . $ext;
            
            if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_dir . $new_photo)) {
                if ($photo_name != 'default_user.png' && file_exists($target_dir . $photo_name)) {
                    unlink($target_dir . $photo_name);
                }
                $photo_name = $new_photo;
            }
        }

        // 2. Update Database
        if (!empty($new_pass)) {
            if ($new_pass === $confirm_pass) {
                $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ?, profile_pic = ? WHERE id = ?");
                $stmt->bind_param("ssi", $hashed_pass, $photo_name, $user_id);
                $stmt->execute();
                $msg = "<div class='alert success'>Profile and Security updated!</div>";
            } else {
                $msg = "<div class='alert danger'>Passwords do not match.</div>";
            }
        } else {
            $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $stmt->bind_param("si", $photo_name, $user_id);
            $stmt->execute();
            $msg = "<div class='alert success'>Profile picture updated!</div>";
        }
    } catch (Exception $e) {
        $msg = "<div class='alert danger'>Update Error: " . $e->getMessage() . "</div>";
    }
}

// Fetch user data
$user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
$display_pic = !empty($user['profile_pic']) ? $user['profile_pic'] : 'default_user.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile | Secure E-Vote</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary: #1a237e; --bg: #f8fafc; }
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg); display: flex; }
        .sidebar { width: 260px; height: 100vh; background: var(--primary); color: white; position: fixed; display: flex; flex-direction: column; }
        .sidebar-menu a { padding: 15px 25px; display: flex; align-items: center; color: rgba(255,255,255,0.7); text-decoration: none; transition: 0.3s; }
        .sidebar-menu a.active { color: white; background: rgba(255,255,255,0.1); border-left: 4px solid #ff4081; }
        .sidebar-menu i { margin-right: 15px; }

        .main-content { margin-left: 260px; padding: 40px; width: calc(100% - 260px); }
        .profile-container { max-width: 650px; margin: auto; background: white; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); overflow: hidden; }
        .header-gradient { background: linear-gradient(135deg, #1a237e, #3949ab); color: white; padding: 50px 20px; text-align: center; }
        
        .avatar-wrapper { position: relative; width: 130px; height: 130px; margin: 0 auto 20px; }
        .avatar-img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 5px solid rgba(255,255,255,0.2); background: #eee; }
        
        .form-section { padding: 40px; }
        .input-box { width: 100%; padding: 12px 15px; margin: 8px 0 20px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; box-sizing: border-box; }
        .label-text { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .btn-save { background: var(--primary); color: white; border: none; padding: 15px; border-radius: 10px; cursor: pointer; width: 100%; font-weight: 700; transition: 0.3s; }
        .btn-save:hover { background: #0d1440; transform: translateY(-2px); }

        .alert { padding: 15px; border-radius: 10px; margin-bottom: 25px; text-align: center; font-weight: 500; }
        .success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div style="padding: 30px; text-align: center;"><h2>E-VOTE</h2></div>
        <nav class="sidebar-menu">
            <a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
            <a href="receipt.php"><i class="fas fa-ticket-alt"></i> Voting Receipt</a>
            <a href="profile.php" class="active"><i class="fas fa-user-circle"></i> Profile Settings</a>
            <a href="logout.php" style="margin-top: auto; color: #fda4af;"><i class="fas fa-power-off"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="profile-container">
            <form method="POST" enctype="multipart/form-data">
                <div class="header-gradient">
                    <div class="avatar-wrapper">
                        <img src="uploads/profiles/<?php echo $display_pic; ?>" class="avatar-img" id="imgPreview">
                    </div>
                    <h2 style="margin:0;"><?php echo htmlspecialchars($user['first_name'] . " " . $user['last_name']); ?></h2>
                    <p style="opacity: 0.8; font-size: 0.9em;"><?php echo htmlspecialchars($user['email']); ?></p>
                    <input type="hidden" name="current_photo" value="<?php echo $display_pic; ?>">
                </div>

                <div class="form-section">
                    <?php echo $msg; ?>

                    <div class="label-text">Profile Picture</div>
                    <input type="file" name="profile_pic" class="input-box" accept="image/*" onchange="updatePreview(this)">

                    <div style="margin: 20px 0; border-top: 1px solid #f1f5f9; padding-top: 20px;">
                        <div class="label-text">Change Password</div>
                        <input type="password" name="new_password" class="input-box" placeholder="Leave blank to keep current">
                        
                        <div class="label-text">Confirm Password</div>
                        <input type="password" name="confirm_password" class="input-box" placeholder="Confirm your new password">
                    </div>

                    <button type="submit" name="update_profile" class="btn-save">Update Profile Information</button>
                </div>
            </form>
        </div>
    </main>

    <script>
    function updatePreview(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('imgPreview').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    </script>
</body>
</html>

