<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once 'dompdf/vendor/autoload.php';
use Dompdf\Dompdf;

try {
    $conn = new mysqli("localhost", "root", "", "school_system");
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 1. Fetch Election Settings
$settings = $conn->query("SELECT * FROM election_settings WHERE id = 1")->fetch_assoc();

// 2. Fetch User Details
$user_stmt = $conn->prepare("SELECT first_name, last_name, id_number FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

// 3. Fetch Votes - Robust Query
$sql = "SELECT v.position, c.name as candidate_name 
        FROM votes v 
        JOIN candidates c ON v.candidate_id = c.id 
        WHERE v.user_id = ? 
        ORDER BY v.position ASC";

$votes_query = $conn->prepare($sql);
$votes_query->bind_param("i", $user_id);
$votes_query->execute();
$result = $votes_query->get_result();
$votes = [];
while ($row = $result->fetch_assoc()) {
    $votes[] = $row;
}

if (isset($_GET['download'])) {
    $filename = 'vote_receipt_' . preg_replace('/[^a-z0-9_-]/', '', strtolower($user['id_number'])) . '.pdf';
    $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Voting Receipt</title>';
    $html .= '<style>body{font-family:Arial,Helvetica,sans-serif;background:#f4f4f7;padding:20px;} .receipt{max-width:700px;margin:auto;background:#fff;padding:30px;border:1px solid #ccc;} .header{text-align:center;border-bottom:1px dashed #000;margin-bottom:20px;padding-bottom:10px;} .row{display:flex;justify-content:space-between;margin:10px 0;border-bottom:1px dotted #ccc;padding-bottom:8px;} .footer{text-align:center;margin-top:30px;font-size:0.85em;color:#555;border-top:1px solid #000;padding-top:10px;}</style>';
    $html .= '</head><body><div class="receipt"><div class="header"><h2 style="margin:0;">' . htmlspecialchars(strtoupper($settings['election_title'])) . '</h2><p>OFFICIAL VOTE CONFIRMATION</p></div>';
    $html .= '<p><strong>VOTER:</strong> ' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . '</p>';
    $html .= '<p><strong>ID:</strong> ' . htmlspecialchars($user['id_number']) . '</p>';
    $html .= '<p><strong>DATE:</strong> ' . date('Y-m-d H:i') . '</p><hr><h3 style="text-align:center;">BALLOT SELECTIONS</h3>';
    foreach ($votes as $row) {
        $html .= '<div class="row"><span><strong>' . htmlspecialchars($row['position']) . ':</strong></span><span>' . htmlspecialchars($row['candidate_name']) . '</span></div>';
    }
    $html .= '<div class="footer"><p>Verification Hash: ' . md5($user_id . time()) . '</p><p>Thank you for voting!</p></div></div></body></html>';

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream($filename, ['Attachment' => true]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Voting Receipt - <?php echo htmlspecialchars($user['id_number']); ?></title>
    <style>
        body { font-family: 'Courier New', monospace; background: #f4f4f7; padding: 40px 20px; }
        .receipt { 
            max-width: 500px; margin: auto; background: white; padding: 30px; 
            border: 2px solid #000; box-shadow: 8px 8px 0px #ddd;
        }
        .header { text-align: center; border-bottom: 2px dashed #000; margin-bottom: 20px; padding-bottom: 10px; }
        .row { display: flex; justify-content: space-between; margin: 10px 0; border-bottom: 1px dotted #ccc; }
        .btn-box { text-align: center; margin-top: 20px; }
        .btn { padding: 10px 20px; text-decoration: none; font-weight: bold; font-family: sans-serif; cursor: pointer; }
        @media print { .btn-box { display: none; } }
    </style>
</head>
<body>

    <div class="receipt">
        <div class="header">
            <h2 style="margin:0;"><?php echo strtoupper($settings['election_title']); ?></h2>
            <p>OFFICIAL VOTE CONFIRMATION</p>
        </div>

        <p><strong>VOTER:</strong> <?php echo htmlspecialchars($user['first_name'] . " " . $user['last_name']); ?></p>
        <p><strong>ID:</strong> <?php echo htmlspecialchars($user['id_number']); ?></p>
        <p><strong>DATE:</strong> <?php echo date('Y-m-d H:i'); ?></p>
        <hr>

        <h3 style="text-align:center;">BALLOT SELECTIONS</h3>

        <?php while($row = $result->fetch_assoc()): ?>
            <div class="row">
                <span><strong><?php echo htmlspecialchars($row['position']); ?>:</strong></span>
                <span><?php echo htmlspecialchars($row['candidate_name']); ?></span>
            </div>
        <?php endwhile; ?>

        <div style="margin-top: 30px; text-align: center; font-size: 0.8em; border-top: 1px solid #000; padding-top: 10px;">
            <p>Verification Hash: <?php echo md5($user_id . time()); ?></p>
            <p>Thank you for voting!</p>
        </div>
    </div>

    <div class="btn-box">
        <button onclick="window.print()" class="btn" style="background:#1a237e; color:white; border:none;">Print Receipt</button>
        <a href="?download=1" class="btn" style="background:#10b981; color:white; margin-left:10px;">Download PDF</a>
        <a href="dashboard.php" class="btn" style="background:#eee; color:#333; margin-left:10px;">Back to Home</a>
    </div>

</body>
</html>

