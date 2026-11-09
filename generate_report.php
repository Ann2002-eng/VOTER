<?php
session_start();
require_once 'dompdf/vendor/autoload.php';
use Dompdf\Dompdf;

$conn = new mysqli("localhost", "root", "", "school_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    die("Unauthorized Access.");
}

$settings = $conn->query("SELECT * FROM election_settings WHERE id = 1")->fetch_assoc();
$total_voters = $conn->query("SELECT COUNT(*) FROM users WHERE user_role = 'Student'")->fetch_row()[0];
$total_votes = $conn->query("SELECT COUNT(DISTINCT user_id) FROM votes")->fetch_row()[0];

$query = "SELECT c.position, c.name, COUNT(v.id) as vote_count FROM candidates c LEFT JOIN votes v ON c.id = v.candidate_id GROUP BY c.position, c.id ORDER BY c.position ASC, vote_count DESC";

$results = $conn->query($query);
if (!$results) {
    die("Error in query: " . $conn->error);
}

$html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Election Results Report</title>';
$html .= '<style>body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:0;background:#f4f4f7;}';
$html .= '.page{max-width:900px;margin:30px auto;background:#fff;padding:40px;border:1px solid #d1d5db;}';
$html .= 'h1,h2,h3{margin:0 0 12px; color:#111827;}';
$html .= 'p{margin:6px 0;color:#374151;}';
$html .= '.meta{margin-bottom:24px;padding-bottom:12px;border-bottom:1px solid #e5e7eb;}';
$html .= 'table{width:100%;border-collapse:collapse;margin-top:20px;}';
$html .= 'th,td{padding:12px 10px;border:1px solid #e5e7eb;text-align:left;}';
$html .= 'th{background:#f3f4f6;color:#111827;}';
$html .= '.summary{display:flex;gap:20px;margin-top:20px;}';
$html .= '.summary div{flex:1;padding:16px;border:1px solid #e5e7eb;background:#f9fafb;border-radius:10px;}';
$html .= '.summary strong{display:block;font-size:1.4rem;margin-bottom:6px;color:#111827;}</style>';
$html .= '</head><body><div class="page">';
$html .= '<h1>Election Results Report</h1>';
$html .= '<div class="meta">';
$html .= '<p><strong>Election:</strong> ' . htmlspecialchars($settings['election_title'] ?? 'School Election') . '</p>';
$html .= '<p><strong>Generated:</strong> ' . date('Y-m-d H:i:s') . '</p>';
$html .= '</div>';
$html .= '<div class="summary">';
$html .= '<div><strong>' . intval($total_voters) . '</strong> Registered Voters</div>';
$html .= '<div><strong>' . intval($total_votes) . '</strong> Completed Ballots</div>';
$html .= '</div>';
$html .= '<h2 style="margin-top:30px;">Vote Breakdown</h2>';
$html .= '<table><thead><tr><th>Position</th><th>Candidate</th><th>Total Votes</th></tr></thead><tbody>';
while ($row = $results->fetch_assoc()) {
    $html .= '<tr><td>' . htmlspecialchars($row['position']) . '</td><td>' . htmlspecialchars($row['name']) . '</td><td>' . intval($row['vote_count']) . '</td></tr>';
}
$html .= '</tbody></table>';
$html .= '<p style="margin-top:30px;font-size:0.9rem;color:#6b7280;">This report reflects the current election results snapshot at the time of generation.</p>';
$html .= '</div></body></html>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$filename = 'Election_Report_' . date('Y-m-d_H-i-s') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit();
