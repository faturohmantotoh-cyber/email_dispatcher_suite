<?php
// analytics.php - Real-time email analytics dashboard
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/util.php';

ensure_dirs();
$pdo = DB::conn();

// Date range filter
$range = $_GET['range'] ?? '7d';
$jobId = isset($_GET['job_id']) ? intval($_GET['job_id']) : null;

// Calculate date range
$ranges = [
    '24h' => 'INTERVAL 24 HOUR',
    '7d' => 'INTERVAL 7 DAY',
    '30d' => 'INTERVAL 30 DAY',
    '90d' => 'INTERVAL 90 DAY'
];
$dateFilter = $ranges[$range] ?? $ranges['7d'];

// Get statistics
$stats = [];

// Overall stats
$overallStats = $pdo->query("
    SELECT 
        COUNT(DISTINCT mail_job_id) as total_jobs,
        COUNT(*) as total_events,
        SUM(CASE WHEN event_type = 'sent' THEN 1 ELSE 0 END) as sent_count,
        SUM(CASE WHEN event_type = 'delivered' THEN 1 ELSE 0 END) as delivered_count,
        SUM(CASE WHEN event_type = 'open' THEN 1 ELSE 0 END) as open_count,
        SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as click_count,
        SUM(CASE WHEN event_type = 'bounce' THEN 1 ELSE 0 END) as bounce_count,
        COUNT(DISTINCT CASE WHEN event_type = 'open' THEN email END) as unique_opens,
        COUNT(DISTINCT CASE WHEN event_type = 'click' THEN email END) as unique_clicks
    FROM email_analytics
    WHERE created_at >= DATE_SUB(NOW(), $dateFilter)
" . ($jobId ? " AND mail_job_id = $jobId" : ""));
$overallStats = $overallStats->fetch(PDO::FETCH_ASSOC);

// Calculate rates
$sent = max(1, $overallStats['sent_count'] ?? 0);
$overallStats['delivery_rate'] = round(($overallStats['delivered_count'] / $sent) * 100, 1);
$overallStats['open_rate'] = round(($overallStats['unique_opens'] / $sent) * 100, 1);
$overallStats['click_rate'] = round(($overallStats['unique_clicks'] / $sent) * 100, 1);
$overallStats['bounce_rate'] = round(($overallStats['bounce_count'] / $sent) * 100, 1);
$overallStats['ctr'] = $overallStats['unique_opens'] > 0 ? round(($overallStats['unique_clicks'] / $overallStats['unique_opens']) * 100, 1) : 0;

// Hourly breakdown
$hourlyStats = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%H:00') as hour,
        COUNT(*) as events,
        SUM(CASE WHEN event_type = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN event_type = 'open' THEN 1 ELSE 0 END) as opens
    FROM email_analytics
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
" . ($jobId ? " AND mail_job_id = $jobId" : "") . "
    GROUP BY hour
    ORDER BY hour
")->fetchAll(PDO::FETCH_ASSOC);

// Top performing links
$topLinks = $pdo->query("
    SELECT 
        link_url,
        COUNT(*) as clicks,
        COUNT(DISTINCT email) as unique_clicks
    FROM email_analytics
    WHERE event_type = 'click' AND created_at >= DATE_SUB(NOW(), $dateFilter)
" . ($jobId ? " AND mail_job_id = $jobId" : "") . "
    GROUP BY link_url
    ORDER BY clicks DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Device breakdown
$deviceStats = $pdo->query("
    SELECT 
        device_type,
        COUNT(*) as count,
        COUNT(DISTINCT email) as unique_users
    FROM email_analytics
    WHERE device_type IS NOT NULL AND created_at >= DATE_SUB(NOW(), $dateFilter)
" . ($jobId ? " AND mail_job_id = $jobId" : "") . "
    GROUP BY device_type
")->fetchAll(PDO::FETCH_ASSOC);

// Geographic stats (simplified)
$geoStats = $pdo->query("
    SELECT 
        country,
        COUNT(*) as count
    FROM email_analytics
    WHERE country IS NOT NULL AND created_at >= DATE_SUB(NOW(), $dateFilter)
" . ($jobId ? " AND mail_job_id = $jobId" : "") . "
    GROUP BY country
    ORDER BY count DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Recent jobs with stats
$recentJobs = $pdo->query("
    SELECT 
        j.id,
        j.subject,
        j.created_at,
        COUNT(DISTINCT i.id) as total_recipients,
        SUM(CASE WHEN i.status = 'sent' THEN 1 ELSE 0 END) as sent_count,
        j.open_count,
        j.click_count,
        j.bounce_count
    FROM mail_jobs j
    LEFT JOIN mail_job_items i ON i.mail_job_id = j.id
    WHERE j.created_at >= DATE_SUB(NOW(), $dateFilter)
    GROUP BY j.id
    ORDER BY j.created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Analytics Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Inter', sans-serif;
            background: #f5f7fa;
            color: #1a1a2e;
            line-height: 1.6;
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        header { 
            background: linear-gradient(135deg, #0052CC 0%, #003d99 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        header h1 { font-size: 28px; margin-bottom: 8px; }
        .nav-back { 
            display: inline-flex; 
            align-items: center; 
            gap: 8px;
            color: white;
            text-decoration: none;
            margin-top: 15px;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
        }
        
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            align-items: center;
        }
        .filters select, .filters a {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            background: white;
            color: #333;
            text-decoration: none;
            font-weight: 500;
        }
        .filters a.active {
            background: #0052CC;
            color: white;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        .stat-card.sent::before { background: #0052CC; }
        .stat-card.open::before { background: #28a745; }
        .stat-card.click::before { background: #17a2b8; }
        .stat-card.bounce::before { background: #dc3545; }
        .stat-card.rate::before { background: #ffc107; }
        
        .stat-card h3 {
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .stat-card .number {
            font-size: 36px;
            font-weight: 700;
            color: #333;
        }
        .stat-card .change {
            font-size: 13px;
            margin-top: 5px;
        }
        .stat-card .change.positive { color: #28a745; }
        .stat-card .change.negative { color: #dc3545; }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .card h2 {
            font-size: 18px;
            margin-bottom: 20px;
            color: #333;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e1e8ed;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            color: #666;
        }
        .bar {
            background: #e9ecef;
            border-radius: 4px;
            height: 8px;
            overflow: hidden;
        }
        .bar-fill {
            background: #0052CC;
            height: 100%;
            border-radius: 4px;
        }
        .rate {
            font-weight: 600;
            color: #0052CC;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📊 Email Analytics</h1>
            <p>Real-time insights into your email campaigns</p>
            <a href="index.php" class="nav-back">← Back to Dashboard</a>
        </header>
        
        <div class="filters">
            <span style="font-weight:600;">Time Range:</span>
            <a href="?range=24h" class="<?= $range === '24h' ? 'active' : '' ?>">Last 24 Hours</a>
            <a href="?range=7d" class="<?= $range === '7d' ? 'active' : '' ?>">Last 7 Days</a>
            <a href="?range=30d" class="<?= $range === '30d' ? 'active' : '' ?>">Last 30 Days</a>
            <a href="?range=90d" class="<?= $range === '90d' ? 'active' : '' ?>">Last 90 Days</a>
        </div>
        
        <!-- Key Metrics -->
        <div class="stats-grid">
            <div class="stat-card sent">
                <h3>Emails Sent</h3>
                <div class="number"><?= number_format($overallStats['sent_count']) ?></div>
            </div>
            <div class="stat-card open">
                <h3>Unique Opens</h3>
                <div class="number"><?= number_format($overallStats['unique_opens']) ?></div>
                <div class="change <?= $overallStats['open_rate'] > 20 ? 'positive' : '' ?>">
                    <?= $overallStats['open_rate'] ?>% open rate
                </div>
            </div>
            <div class="stat-card click">
                <h3>Unique Clicks</h3>
                <div class="number"><?= number_format($overallStats['unique_clicks']) ?></div>
                <div class="change">
                    <?= $overallStats['click_rate'] ?>% CTR
                </div>
            </div>
            <div class="stat-card bounce">
                <h3>Bounces</h3>
                <div class="number"><?= number_format($overallStats['bounce_count']) ?></div>
                <div class="change <?= $overallStats['bounce_rate'] > 2 ? 'negative' : 'positive' ?>">
                    <?= $overallStats['bounce_rate'] ?>% bounce rate
                </div>
            </div>
            <div class="stat-card rate">
                <h3>Click-to-Open Rate</h3>
                <div class="number"><?= $overallStats['ctr'] ?>%</div>
                <div class="change">
                    Of opened emails
                </div>
            </div>
        </div>
        
        <div class="charts-grid">
            <!-- Hourly Activity Chart -->
            <div class="card">
                <h2>📈 Hourly Activity (Last 24h)</h2>
                <canvas id="hourlyChart" height="200"></canvas>
            </div>
            
            <!-- Device Breakdown -->
            <div class="card">
                <h2>📱 Device Breakdown</h2>
                <canvas id="deviceChart" height="200"></canvas>
            </div>
        </div>
        
        <!-- Top Links -->
        <?php if (!empty($topLinks)): ?>
        <div class="card">
            <h2>🔗 Top Performing Links</h2>
            <table>
                <thead>
                    <tr>
                        <th>Link URL</th>
                        <th>Total Clicks</th>
                        <th>Unique Clicks</th>
                        <th>Click Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topLinks as $link): 
                        $linkRate = $overallStats['unique_opens'] > 0 ? round(($link['unique_clicks'] / $overallStats['unique_opens']) * 100, 1) : 0;
                    ?>
                    <tr>
                        <td style="max-width:400px; overflow:hidden; text-overflow:ellipsis;">
                            <a href="<?= e($link['link_url']) ?>" target="_blank"><?= e($link['link_url']) ?></a>
                        </td>
                        <td><?= number_format($link['clicks']) ?></td>
                        <td><?= number_format($link['unique_clicks']) ?></td>
                        <td>
                            <div class="bar" style="display:inline-block; width:100px; vertical-align:middle; margin-right:10px;">
                                <div class="bar-fill" style="width:<?= min(100, $linkRate * 5) ?>%"></div>
                            </div>
                            <span class="rate"><?= $linkRate ?>%</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Recent Jobs -->
        <div class="card">
            <h2>📧 Recent Campaigns</h2>
            <table>
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Sent</th>
                        <th>Opens</th>
                        <th>Clicks</th>
                        <th>Bounces</th>
                        <th>Open Rate</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentJobs as $job):
                        $jobSent = max(1, $job['sent_count'] ?? 0);
                        $jobOpenRate = round(($job['open_count'] / $jobSent) * 100, 1);
                    ?>
                    <tr>
                        <td><?= e(substr($job['subject'], 0, 50)) ?><?= strlen($job['subject']) > 50 ? '...' : '' ?></td>
                        <td><?= number_format($job['sent_count']) ?></td>
                        <td><?= number_format($job['open_count']) ?></td>
                        <td><?= number_format($job['click_count']) ?></td>
                        <td><?= number_format($job['bounce_count']) ?></td>
                        <td>
                            <span class="rate"><?= $jobOpenRate ?>%</span>
                        </td>
                        <td>
                            <a href="logs.php?job=<?= $job['id'] ?>" style="color:#0052CC;">Details</a>
                            | <a href="?job_id=<?= $job['id'] ?>" style="color:#0052CC;">Analytics</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        // Hourly Chart
        const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
        new Chart(hourlyCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($hourlyStats, 'hour')) ?>,
                datasets: [{
                    label: 'Opens',
                    data: <?= json_encode(array_column($hourlyStats, 'opens')) ?>,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Sent',
                    data: <?= json_encode(array_column($hourlyStats, 'sent')) ?>,
                    borderColor: '#0052CC',
                    backgroundColor: 'rgba(0, 82, 204, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
        
        // Device Chart
        const deviceCtx = document.getElementById('deviceChart').getContext('2d');
        new Chart(deviceCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_map(function($d) { return ucfirst($d['device_type']); }, $deviceStats)) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($deviceStats, 'count')) ?>,
                    backgroundColor: ['#0052CC', '#28a745', '#ffc107', '#6c757d']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    </script>
</body>
</html>
