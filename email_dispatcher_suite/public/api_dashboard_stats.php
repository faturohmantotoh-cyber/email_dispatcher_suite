<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';

header('Content-Type: application/json');

// Check user auth
$user = $_SESSION['user'] ?? null;
if (!$user) {
  http_response_code(401);
  exit(json_encode(['error' => 'Unauthorized']));
}

try {
  $pdo = DB::conn();
  
  // Get emails sent this month
  $month_sent = $pdo->query("
    SELECT COUNT(*) as count 
    FROM mail_job_items 
    WHERE status = 'sent' 
    AND MONTH(sent_at) = MONTH(NOW()) 
    AND YEAR(sent_at) = YEAR(NOW())
  ")->fetch(PDO::FETCH_ASSOC);
  
  // Get pending task count
  $pending_tasks = $pdo->query("
    SELECT COUNT(*) as count 
    FROM mail_job_items 
    WHERE status = 'pending'
  ")->fetch(PDO::FETCH_ASSOC);
  
  // Get activity data (last 7 days)
  $activity_data = $pdo->query("
    SELECT DATE(sent_at) as date, COUNT(*) as count 
    FROM mail_job_items 
    WHERE status = 'sent' 
    AND sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(sent_at)
    ORDER BY date ASC
  ")->fetchAll(PDO::FETCH_ASSOC);
  
  // Fill missing dates with 0
  $dates = [];
  for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dates[$date] = 0;
  }
  
  foreach ($activity_data as $item) {
    $dates[$item['date']] = (int)$item['count'];
  }
  
  // Format for chart (last 7 days)
  $chart_data = [
    'dates' => array_keys($dates),
    'values' => array_values($dates),
    'total' => array_sum($dates)
  ];
  
  // Get contact count
  $contacts_count = $pdo->query("SELECT COUNT(*) FROM contacts")->fetchColumn();
  
  echo json_encode([
    'success' => true,
    'this_month' => (int)($month_sent['count'] ?? 0),
    'pending' => (int)($pending_tasks['count'] ?? 0),
    'contacts' => (int)$contacts_count,
    'activity' => $chart_data
  ]);
  
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => 'Database error'
  ]);
}
?>
