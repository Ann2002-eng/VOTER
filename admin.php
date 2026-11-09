<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli("localhost", "root", "", "school_system");
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Ensure `positions` table exists so admin UI doesn't fatal when not migrated yet
try {
    $conn->query("CREATE TABLE IF NOT EXISTS positions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $cnt = $conn->query("SELECT COUNT(*) as c FROM positions")->fetch_assoc();
    if (intval(
        $cnt['c']
    ) === 0) {
        $defaults = ['President','Vice President','Academics Minister','Sports Minister','Welfare Minister'];
        $ins = $conn->prepare("INSERT INTO positions (name) VALUES (?)");
        foreach ($defaults as $p) {
            $ins->bind_param('s', $p);
            $ins->execute();
        }
    }
} catch (Exception $e) {
    // If creation fails (insufficient privileges), continue — UI will show empty dropdown instead of fatal error
}

// Security Check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// --- 1. CORE LOGIC (HANDLING ALL POST ACTIONS) ---
$msg = "";

// A. RESET SYSTEM
if (isset($_POST['reset_system'])) {
    $conn->query("TRUNCATE TABLE votes");
    $msg = "<div class='alert'>Success: Election data wiped.</div>";
}

// A2. UPDATE ELECTION TIME WINDOW
if (isset($_POST['save_election_time'])) {
    $start = $_POST['start_time'];
    $end = $_POST['end_time'];

    if (strtotime($end) <= strtotime($start)) {
        $msg = "<div class='alert' style='background:#fee2e2;color:#991b1b;'>End time must be later than start time.</div>";
    } else {
        $stmt = $conn->prepare("UPDATE election_settings SET start_time = ?, end_time = ? WHERE id = 1");
        $stmt->bind_param('ss', $start, $end);
        if ($stmt->execute()) {
            if ($conn->affected_rows === 0) {
                $insert = $conn->prepare("INSERT INTO election_settings (id, election_title, start_time, end_time) VALUES (1, 'School Election', ?, ?)");
                $insert->bind_param('ss', $start, $end);
                $insert->execute();
            }
            $msg = "<div class='alert'>Election time window updated successfully.</div>";
        } else {
            $msg = "<div class='alert' style='background:#fee2e2;color:#991b1b;'>Unable to save election times.</div>";
        }
    }
}

// B. CANDIDATE ACTIONS (ADD/DELETE)
if (isset($_POST['add_candidate'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $pos = mysqli_real_escape_string($conn, $_POST['position']);
    $photo = time() . "_" . $_FILES['photo']['name'];
    if (!is_dir('uploads')) mkdir('uploads');
    move_uploaded_file($_FILES['photo']['tmp_name'], "uploads/" . $photo);
    
    $stmt = $conn->prepare("INSERT INTO candidates (name, position, photo) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $pos, $photo);
    $stmt->execute();
    header("Location: admin.php?msg=cand_added");
}

if (isset($_GET['delete_candidate'])) {
    $id = intval($_GET['delete_candidate']);
    $conn->query("DELETE FROM candidates WHERE id = $id");
    header("Location: admin.php?msg=cand_deleted");
}

// C. STUDENT ACTIONS (ADD/UPDATE/DELETE)
if (isset($_POST['add_student'])) {
    $fname = mysqli_real_escape_string($conn, $_POST['first_name']);
    $lname = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $id_num = mysqli_real_escape_string($conn, $_POST['id_number']);
    $default_password = $id_num;
    $pass = password_hash($default_password, PASSWORD_DEFAULT);

    $dup_check = $conn->prepare("SELECT email, id_number FROM users WHERE email = ? OR id_number = ? LIMIT 1");
    $dup_check->bind_param("ss", $email, $id_num);
    $dup_check->execute();
    $dup_result = $dup_check->get_result()->fetch_assoc();

    if ($dup_result) {
        if ($dup_result['email'] === $email && $dup_result['id_number'] === $id_num) {
            $msg = "<div class='alert' style='background:#fee2e2;color:#991b1b;'>A student already exists with that email and ID number.</div>";
        } elseif ($dup_result['email'] === $email) {
            $msg = "<div class='alert' style='background:#fee2e2;color:#991b1b;'>That email is already in use.</div>";
        } else {
            $msg = "<div class='alert' style='background:#fee2e2;color:#991b1b;'>That ID number is already in use.</div>";
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, id_number, password, user_role) VALUES (?, ?, ?, ?, ?, 'Student')");
        $stmt->bind_param("sssss", $fname, $lname, $email, $id_num, $pass);
        $stmt->execute();
        header("Location: admin.php?msg=stud_added");
        exit();
    }
}

if (isset($_GET['reset_password'])) {
    $id = intval($_GET['reset_password']);
    $student = $conn->query("SELECT id_number FROM users WHERE id = $id AND user_role = 'Student'")->fetch_assoc();
    if ($student) {
        $default_password = $student['id_number'];
        $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password = '$hashed_password' WHERE id = $id");
    }
    header("Location: admin.php?msg=password_reset");
    exit();
}

if (isset($_POST['update_student'])) {
    $id = intval($_POST['student_id']);
    $fname = mysqli_real_escape_string($conn, $_POST['first_name']);
    $lname = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $id_num = mysqli_real_escape_string($conn, $_POST['id_number']);

    $dup_check = $conn->prepare("SELECT email, id_number FROM users WHERE (email = ? OR id_number = ?) AND id != ? LIMIT 1");
    $dup_check->bind_param("ssi", $email, $id_num, $id);
    $dup_check->execute();
    $dup_result = $dup_check->get_result()->fetch_assoc();

    if ($dup_result) {
        if ($dup_result['email'] === $email && $dup_result['id_number'] === $id_num) {
            $msg = "<div class='alert' style='background:#fee2e2;color:#991b1b;'>Another user already exists with that email and ID number.</div>";
        } elseif ($dup_result['email'] === $email) {
            $msg = "<div class='alert' style='background:#fee2e2;color:#991b1b;'>That email is already in use.</div>";
        } else {
            $msg = "<div class='alert' style='background:#fee2e2;color:#991b1b;'>That ID number is already in use.</div>";
        }
    } else {
        $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, email=?, id_number=? WHERE id=?");
        $stmt->bind_param("ssssi", $fname, $lname, $email, $id_num, $id);
        $stmt->execute();
        header("Location: admin.php?msg=stud_updated");
        exit();
    }
}

if (isset($_GET['delete_student'])) {
    $id = intval($_GET['delete_student']);
    $conn->query("DELETE FROM users WHERE id = $id");
    header("Location: admin.php?msg=stud_deleted");
}

// --- 2. DATA PREPARATION ---
$total_voters = $conn->query("SELECT COUNT(*) FROM users WHERE user_role = 'Student'")->fetch_row()[0];
$total_votes = $conn->query("SELECT COUNT(DISTINCT user_id) FROM votes")->fetch_row()[0];

$results_query = $conn->query("SELECT c.id, c.name, c.position, c.photo, COUNT(v.id) as vote_count FROM candidates c LEFT JOIN votes v ON c.id = v.candidate_id GROUP BY c.id ORDER BY c.position ASC");

$chart_labels = []; $chart_votes = []; $tally_rows = [];
while($r = $results_query->fetch_assoc()) {
    $chart_labels[] = $r['name'];
    $chart_votes[] = $r['vote_count'];
    $tally_rows[] = $r;
}
$students = $conn->query("SELECT * FROM users WHERE user_role = 'Student' ORDER BY last_name ASC");
$positions = $conn->query("SELECT name FROM positions ORDER BY name ASC");
$election_settings = $conn->query("SELECT * FROM election_settings WHERE id = 1")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Command Center</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --sidebar-w: 260px; --primary: #0f172a; --accent: #3b82f6; --text-muted: #94a3b8; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; margin: 0; display: flex; scroll-behavior: smooth; }
        
        .sidebar { width: var(--sidebar-w); background: var(--primary); color: white; height: 100vh; position: fixed; padding: 20px 0; z-index: 100; }
        .sidebar-brand { padding: 0 25px 30px; font-size: 1.5rem; font-weight: 800; color: var(--accent); }
        .nav-link { padding: 15px 25px; display: flex; align-items: center; color: var(--text-muted); text-decoration: none; transition: 0.2s; border-left: 4px solid transparent; cursor: pointer; }
        .nav-link:hover { background: #1e293b; color: white; }
        .nav-link i { margin-right: 15px; width: 20px; }

        .main { margin-left: var(--sidebar-w); padding: 40px; width: calc(100% - var(--sidebar-w)); }
        .card { background: white; padding: 30px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); margin-bottom: 30px; }
        
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal-content { background: white; margin: 5% auto; padding: 30px; width: 450px; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        .form-control { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #e2e8f0; border-radius: 8px; box-sizing: border-box; }
        
        .grid-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-box { background: white; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; padding: 15px; border-bottom: 2px solid #f1f5f9; }
        td { padding: 15px; border-bottom: 1px solid #f1f5f9; }
        
        .btn { padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; text-decoration: none; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .alert { padding: 15px; background: #dcfce7; color: #166534; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-brand">VOTER.SYS</div>
    <nav>
        <a href="#overview" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
        <a href="#tally" class="nav-link"><i class="fas fa-poll"></i> Live Tally</a>
        <a onclick="openModal('candModal')" class="nav-link"><i class="fas fa-plus-circle"></i> Add Candidate</a>
        <a href="#students" class="nav-link"><i class="fas fa-users-cog"></i> Manage Students</a>
        <a href="#settings" class="nav-link"><i class="fas fa-tools"></i> Maintenance</a>
        <a href="logout.php" class="nav-link" style="color: #f87171; margin-top: 50px;"><i class="fas fa-power-off"></i> Logout</a>
    </nav>
</aside>

<main class="main">
    <div id="overview">
        <h1 style="margin:0;">Dashboard Overview</h1>
        <?php
            if (isset($_GET['msg'])) {
                if ($_GET['msg'] === 'stud_added') {
                    echo "<div class='alert'>Student registered successfully. Default login password is the student&#39;s ID Number.</div>";
                } elseif ($_GET['msg'] === 'password_reset') {
                    echo "<div class='alert'>Student password reset successfully. Default login password is the student&#39;s ID Number.</div>";
                } else {
                    echo "<div class='alert'>Update Successful.</div>";
                }
            }
            echo $msg;
        ?>
        <div class="grid-stats">
            <div class="stat-box"><h3>Voters</h3><h2><?php echo $total_voters; ?></h2></div>
            <div class="stat-box"><h3>Votes</h3><h2><?php echo $total_votes; ?></h2></div>
            <div class="stat-box"><h3>Turnout</h3><h2><?php echo ($total_voters > 0) ? round(($total_votes/$total_voters)*100, 1) : 0; ?>%</h2></div>
        </div>
    </div>

    <div class="card">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:18px;">
            <h3 style="margin:0;">Distribution</h3>
            <div>
                <a href="export_results.php" class="btn btn-primary" style="text-decoration:none;">Export CSV</a>
                <a href="generate_report.php" class="btn" style="background:#10b981; color:white; text-decoration:none;">Generate PDF Report</a>
            </div>
        </div>
        <canvas id="voteChart" height="80"></canvas>
    </div>

    <div class="card" id="tally">
        <h3>Live Standings</h3>
        <table>
            <thead><tr><th>Photo</th><th>Name</th><th>Position</th><th>Votes</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach($tally_rows as $row): ?>
                <tr>
                    <td><img src="uploads/<?php echo $row['photo']; ?>" width="40" height="40" style="border-radius:50%; object-fit:cover;"></td>
                    <td><strong><?php echo $row['name']; ?></strong></td>
                    <td><?php echo $row['position']; ?></td>
                    <td><span style="background: #eff6ff; color: #1e40af; padding: 4px 12px; border-radius: 20px;"><?php echo $row['vote_count']; ?></span></td>
                    <td><a href="?delete_candidate=<?php echo $row['id']; ?>" style="color:#ef4444;" onclick="return confirm('Remove?')"><i class="fas fa-trash"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card" id="students">
        <button onclick="openModal('addStudModal')" class="btn btn-primary" style="float:right;">Add New Student</button>
        <h3>Student Registry</h3>
        <input type="text" id="studentSearch" placeholder="Search..." class="form-control" style="margin-bottom: 20px;">
        <table id="studentTable">
            <thead><tr><th>Name</th><th>Email</th><th>ID Number</th><th>Action</th></tr></thead>
            <tbody>
                <?php while($s = $students->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $s['first_name'] . " " . $s['last_name']; ?></td>
                    <td><?php echo $s['email']; ?></td>
                    <td><?php echo $s['id_number']; ?></td>
                    <td>
                        <button class="btn" style="background:#f1f5f9; font-size:0.8rem;" onclick='openEditModal(<?php echo json_encode($s); ?>)'><i class="fas fa-edit"></i></button>
                        <a href="?reset_password=<?php echo $s['id']; ?>" style="color:#2563eb; margin-left:10px;" onclick="return confirm('Reset this student\'s password to their ID Number?')"><i class="fas fa-key"></i></a>
                        <a href="?delete_student=<?php echo $s['id']; ?>" style="color:#ef4444; margin-left:10px;" onclick="return confirm('Delete Student?')"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="card" id="settings" style="border-left: 5px solid #ef4444;">
        <h3 style="color:#ef4444;">Maintenance</h3>
        <form method="POST" style="margin-bottom:24px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px;">
                <div>
                    <label for="start_time" style="display:block;font-weight:700;margin-bottom:8px;color:#475569;">Election Start</label>
                    <input type="datetime-local" id="start_time" name="start_time" class="form-control" value="<?php echo htmlspecialchars(date('Y-m-d\TH:i', strtotime($election_settings['start_time'] ?? 'now'))); ?>" required>
                </div>
                <div>
                    <label for="end_time" style="display:block;font-weight:700;margin-bottom:8px;color:#475569;">Election End</label>
                    <input type="datetime-local" id="end_time" name="end_time" class="form-control" value="<?php echo htmlspecialchars(date('Y-m-d\TH:i', strtotime($election_settings['end_time'] ?? '+1 day'))); ?>" required>
                </div>
            </div>
            <button type="submit" name="save_election_time" class="btn btn-primary">Update Election Schedule</button>
        </form>
        <form method="POST" onsubmit="return confirm('Wipe all results?');">
            <button type="submit" name="reset_system" class="btn btn-danger">Reset Election Results</button>
        </form>
    </div>
</main>

<div id="candModal" class="modal">
    <div class="modal-content">
        <h2>Add Candidate</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="text" name="name" class="form-control" placeholder="Name" required>
            <select name="position" class="form-control" required>
                <option value="">Select Position</option>
                <?php while($p = $positions->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($p['name']); ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                <?php endwhile; ?>
            </select>
            <input type="file" name="photo" class="form-control" required>
            <button type="submit" name="add_candidate" class="btn btn-primary" style="width:100%;">Save</button>
            <button type="button" onclick="closeModals()" class="btn" style="width:100%; margin-top:10px;">Cancel</button>
        </form>
    </div>
</div>

<div id="addStudModal" class="modal">
    <div class="modal-content">
        <h2>Register Student</h2>
        <form method="POST">
            <input type="text" name="first_name" class="form-control" placeholder="First Name" required>
            <input type="text" name="last_name" class="form-control" placeholder="Last Name" required>
            <input type="email" name="email" class="form-control" placeholder="Email" required>
            <input type="text" name="id_number" class="form-control" placeholder="ID Number" required>
            <p style="margin: 0 0 12px; color: #475569; font-size: 0.9rem;">Default password on first login: student ID number.</p>
            <button type="submit" name="add_student" class="btn btn-primary" style="width:100%;">Register</button>
            <button type="button" onclick="closeModals()" class="btn" style="width:100%; margin-top:10px;">Cancel</button>
        </form>
    </div>
</div>

<div id="editStudModal" class="modal">
    <div class="modal-content">
        <h2>Edit Student</h2>
        <form method="POST">
            <input type="hidden" name="student_id" id="edit_sid">
            <input type="text" name="first_name" id="edit_fn" class="form-control">
            <input type="text" name="last_name" id="edit_ln" class="form-control">
            <input type="email" name="email" id="edit_em" class="form-control">
            <input type="text" name="id_number" id="edit_in" class="form-control">
            <button type="submit" name="update_student" class="btn btn-primary" style="width:100%;">Update</button>
            <button type="button" onclick="closeModals()" class="btn" style="width:100%; margin-top:10px;">Cancel</button>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).style.display = 'block'; }
function closeModals() { 
    document.querySelectorAll('.modal').forEach(m => m.style.display = 'none'); 
}

function openEditModal(s) {
    document.getElementById('edit_sid').value = s.id;
    document.getElementById('edit_fn').value = s.first_name;
    document.getElementById('edit_ln').value = s.last_name;
    document.getElementById('edit_em').value = s.email;
    document.getElementById('edit_in').value = s.id_number;
    openModal('editStudModal');
}

window.onclick = function(e) { if(e.target.className === 'modal') closeModals(); }

document.getElementById('studentSearch').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    document.querySelectorAll('#studentTable tbody tr').forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
    });
});

new Chart(document.getElementById('voteChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [{
            label: 'Votes',
            data: <?php echo json_encode($chart_votes); ?>,
            backgroundColor: [
                '#3b82f6', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#14b8a6', '#f97316', '#0ea5e9', '#e11d48', '#22c55e'
            ],
            borderColor: [
                '#1d4ed8', '#b45309', '#047857', '#b91c1c', '#5b21b6', '#0f766e', '#c2410c', '#0369a1', '#9d174d', '#15803d'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: { precision: 0 }
            }
        }
    }
});
</script>
</body>
</html>

