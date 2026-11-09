<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli("localhost", "root", "", "school_system");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$settings = $conn->query("SELECT * FROM election_settings WHERE id = 1")->fetch_assoc();
$now = new DateTime();
$start = new DateTime($settings['start_time']);
$end = new DateTime($settings['end_time']);

$is_open = ($now >= $start && $now <= $end);

// Fetch User Info
$user = $conn->query("SELECT * FROM users WHERE id = $current_user_id")->fetch_assoc();
$pic = !empty($user['profile_pic']) ? $user['profile_pic'] : 'default_user.png';

$vote_error = isset($_GET['vote_error']) ? $_GET['vote_error'] : '';

// Fetch already voted positions for this user
$voted_positions = [];
$vote_stmt = $conn->prepare("SELECT position FROM votes WHERE user_id = ?");
$vote_stmt->bind_param("i", $current_user_id);
$vote_stmt->execute();
$vote_result = $vote_stmt->get_result();
while ($vp = $vote_result->fetch_assoc()) {
    $voted_positions[] = $vp['position'];
}

$hasVoted = count($voted_positions) > 0;

// Get all unique positions and show only the ones the student still needs to vote for
$positions_query = $conn->query("SELECT DISTINCT position FROM candidates ORDER BY position ASC");
$positions = [];
while($p = $positions_query->fetch_assoc()) {
    if (!in_array($p['position'], $voted_positions, true)) {
        $positions[] = $p['position'];
    }
}

$hasCompletedAll = $hasVoted && count($positions) === 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Voting Dashboard | <?php echo htmlspecialchars($settings['election_title']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary: #1a237e; --accent: #ff4081; --bg: #f0f2f5; }
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg); display: flex; }

        /* Sidebar */
        .sidebar { width: 260px; height: 100vh; background: var(--primary); color: white; position: fixed; display: flex; flex-direction: column; }
        .sidebar-header { padding: 30px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .nav-item { padding: 15px 25px; display: block; color: rgba(255,255,255,0.7); text-decoration: none; transition: 0.3s; }
        .nav-item.active { background: rgba(255,255,255,0.1); color: white; border-left: 4px solid var(--accent); }

        /* Content */
        .main-content { margin-left: 260px; padding: 40px; width: calc(100% - 260px); }
        .card { background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px; }
        
        /* Voting Slots */
        .position-group { margin-bottom: 40px; padding-bottom: 20px; border-bottom: 1px dashed #e2e8f0; }
        .position-title { color: var(--primary); font-size: 1.4em; font-weight: 800; margin-bottom: 20px; display: flex; align-items: center; }
        .position-title::after { content: ''; flex-grow: 1; height: 2px; background: #eee; margin-left: 20px; }
        
        .candidate-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .candidate-card { 
            border: 2px solid #f1f5f9; border-radius: 12px; padding: 15px; display: flex; align-items: center; 
            cursor: pointer; transition: 0.3s; position: relative;
        }
        .candidate-card:hover { border-color: var(--primary); background: #f8faff; }
        
        /* Checked State */
        input[type="radio"] { display: none; }
        input[type="radio"]:checked + .candidate-card { border-color: var(--primary); background: #e8eaf6; box-shadow: 0 4px 10px rgba(26,35,126,0.1); }
        input[type="radio"]:checked + .candidate-card::after {
            content: '\f058'; font-family: "Font Awesome 5 Free"; font-weight: 900;
            position: absolute; top: 10px; right: 10px; color: var(--primary); font-size: 1.2em;
        }

        .candidate-img { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; margin-right: 15px; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .submit-btn { background: var(--primary); color: white; border: none; padding: 20px; border-radius: 12px; width: 100%; font-size: 1.1em; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .submit-btn:hover { background: #0d1440; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="uploads/profiles/<?php echo $pic; ?>" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent);">
            <h4 style="margin: 10px 0 5px;"><?php echo htmlspecialchars($user['first_name']); ?></h4>
            <small style="opacity: 0.6;">STUDENT VOTER</small>
        </div>
        <nav style="margin-top: 20px;">
            <a href="dashboard.php" class="nav-item active"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="profile.php" class="nav-item"><i class="fas fa-user"></i> My Profile</a>
            <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="card">
            <h1 style="margin:0;"><?php echo htmlspecialchars($settings['election_title']); ?></h1>
            <p style="color: #64748b;">Please select one candidate for each available position below.</p>
        </div>

        <?php if (!empty($vote_error)): ?>
            <div class="card" style="border: 1px solid #fee2e2; background: #fff1f2; color: #991b1b;">
                <strong>Oops!</strong> <?php echo htmlspecialchars($vote_error); ?>
            </div>
        <?php endif; ?>

        <?php if ($hasCompletedAll): ?>
            <div class="card" style="text-align: center; padding: 60px;">
                <i class="fas fa-check-circle" style="font-size: 5em; color: #10b981; margin-bottom: 20px;"></i>
                <h2>Vote Recorded Successfully!</h2>
                <p>Thank you for participating. Your ballots for all positions have been cast and encrypted.</p>
                <a href="receipt.php" class="submit-btn" style="text-decoration:none; display:inline-block; width:auto; margin-top:20px;">View My Receipt</a>
            </div>
        <?php elseif (!$is_open): ?>
            <div class="card" style="text-align: center; padding: 60px;">
                <i class="fas fa-lock" style="font-size: 4em; color: #64748b; margin-bottom: 20px;"></i>
                <h2>Voting is Currently Closed</h2>
                <p>Scheduled Start: <?php echo date('M d, Y h:i A', strtotime($settings['start_time'])); ?></p>
            </div>
        <?php else: ?>
            <?php if ($hasVoted && !$hasCompletedAll): ?>
                <div class="card" style="border: 1px solid #fde68a; background: #fffbeb; color: #92400e;">
                    <strong>Partial ballot detected:</strong> you can still vote for the following remaining positions.
                </div>
            <?php endif; ?>
            <form action="cast_vote.php" method="POST">
                <?php foreach ($positions as $pos): ?>
                    <div class="position-group">
                        <div class="position-title">
                            <i class="fas fa-user-tag" style="margin-right: 15px; font-size: 0.8em;"></i>
                            FOR <?php echo strtoupper($pos); ?>
                        </div>
                        <div class="candidate-grid">
                            <?php 
                            $stmt = $conn->prepare("SELECT * FROM candidates WHERE position = ?");
                            $stmt->bind_param("s", $pos);
                            $stmt->execute();
                            $candidates = $stmt->get_result();
                            while($c = $candidates->fetch_assoc()):
                            ?>
                                <label>
                                    <input type="radio" name="votes[<?php echo htmlspecialchars($pos); ?>]" value="<?php echo $c['id']; ?>" required>
                                    <div class="candidate-card">
                                        <img src="uploads/<?php echo $c['photo']; ?>" class="candidate-img">
                                        <div>
                                            <div style="font-weight: 700;"><?php echo htmlspecialchars($c['name']); ?></div>
                                            <small style="color: #64748b;">Official Nominee</small>
                                        </div>
                                    </div>
                                </label>
                            <?php endwhile; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div style="padding: 20px 0;">
                    <button type="submit" name="cast_multi_vote" class="submit-btn" onclick="return confirm('Submit selected ballots?')">
                        <i class="fas fa-paper-plane" style="margin-right: 10px;"></i> CAST VOTES
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </main>

</body>
</html>

