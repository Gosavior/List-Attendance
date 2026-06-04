<?php
 

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';


if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ../../dashboard.php');
    exit();
}


$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');


$stmt = $pdo->query("
    SELECT 
        table_name,
        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
        table_rows
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
    AND table_name IN ('location_tracking', 'tracking_sessions', 'geofence_alerts', 'attendances')
    ORDER BY (data_length + index_length) DESC
");
$tableSizes = $stmt->fetchAll(PDO::FETCH_ASSOC);


$stmt = $pdo->prepare("
    SELECT 
        DATE(tracked_at) as date,
        COUNT(*) as count,
        COUNT(DISTINCT user_id) as active_users
    FROM location_tracking
    WHERE tracked_at BETWEEN ? AND ?
    GROUP BY DATE(tracked_at)
    ORDER BY date DESC
");
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$dailyInserts = $stmt->fetchAll(PDO::FETCH_ASSOC);


$stmt = $pdo->prepare("
    SELECT 
        u.username,
        COUNT(lt.id) as total_locations,
        ROUND(COUNT(lt.id) / DATEDIFF(?, ?), 2) as avg_per_day
    FROM users u
    LEFT JOIN location_tracking lt ON u.id = lt.user_id 
        AND lt.tracked_at BETWEEN ? AND ?
    WHERE u.role = 'staff'
    GROUP BY u.id, u.username
    ORDER BY total_locations DESC
    LIMIT 10
");
$stmt->execute([$endDate, $startDate, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$topUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);


$stmt = $pdo->prepare("
    SELECT 
        user_id,
        tracked_at,
        LAG(tracked_at) OVER (PARTITION BY user_id ORDER BY tracked_at) as prev_tracked_at,
        TIMESTAMPDIFF(SECOND, LAG(tracked_at) OVER (PARTITION BY user_id ORDER BY tracked_at), tracked_at) as interval_seconds
    FROM location_tracking
    WHERE tracked_at BETWEEN ? AND ?
");
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$intervals = $stmt->fetchAll(PDO::FETCH_ASSOC);


$validIntervals = array_filter(array_column($intervals, 'interval_seconds'), function($v) {
    return $v > 0 && $v < 3600; 
});

$avgInterval = !empty($validIntervals) ? round(array_sum($validIntervals) / count($validIntervals)) : 0;
$minInterval = !empty($validIntervals) ? min($validIntervals) : 0;
$maxInterval = !empty($validIntervals) ? max($validIntervals) : 0;


$stmt = $pdo->prepare("
    SELECT 
        alert_type,
        COUNT(*) as count
    FROM geofence_alerts
    WHERE created_at BETWEEN ? AND ?
    GROUP BY alert_type
");
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$alertStats = $stmt->fetchAll(PDO::FETCH_ASSOC);


$stmt = $pdo->query("SELECT COUNT(*) FROM tracking_sessions WHERE is_active = 1");
$activeSessions = $stmt->fetchColumn();


$stmt = $pdo->query("SELECT COUNT(*) FROM location_tracking");
$totalLocations = $stmt->fetchColumn();


$avgInsertPerDay = !empty($dailyInserts) ? array_sum(array_column($dailyInserts, 'count')) / count($dailyInserts) : 0;
$projectedMonthly = $avgInsertPerDay * 30;
$projectedYearly = $avgInsertPerDay * 365;

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GPS Performance Monitoring</title>
    <link rel="stylesheet" href="../../src/output.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-chart-line mr-2"></i>GPS Performance Monitoring</h1>
                    <p class="text-gray-600 mt-1">Real-time database dan performance metrics</p>
                </div>
                <a href="../../dashboard.php" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">← Kembali</a>
            </div>
            
            
            <form method="GET" class="mt-4 flex gap-4 items-end">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Start Date</label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" 
                           class="px-3 py-2 border rounded-lg" required>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">End Date</label>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" 
                           class="px-3 py-2 border rounded-lg" required>
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Filter
                </button>
            </form>
        </div>

        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-lg">
                        <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-600 text-sm">Total Locations</p>
                        <p class="text-2xl font-bold text-gray-800"><?= number_format($totalLocations) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-lg">
                        <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-600 text-sm">Avg Interval</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $avgInterval ?> sec</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-yellow-100 rounded-lg">
                        <svg class="w-6 h-6 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-600 text-sm">Active Sessions</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $activeSessions ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-red-100 rounded-lg">
                        <svg class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-600 text-sm">Daily Inserts</p>
                        <p class="text-2xl font-bold text-gray-800"><?= number_format($avgInsertPerDay, 0) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4"><i class="fas fa-database mr-2"></i>Database Size</h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-2 text-sm text-gray-600">Table</th>
                                <th class="text-right py-2 text-sm text-gray-600">Size (MB)</th>
                                <th class="text-right py-2 text-sm text-gray-600">Rows</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tableSizes as $table): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-2 text-sm"><?= htmlspecialchars($table['table_name']) ?></td>
                                <td class="py-2 text-sm text-right font-semibold"><?= $table['size_mb'] ?></td>
                                <td class="py-2 text-sm text-right"><?= number_format($table['table_rows']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4"><i class="fas fa-chart-area mr-2"></i>Growth Projection</h2>
                <div class="space-y-3">
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="text-sm text-gray-600">Avg Per Day</span>
                        <span class="font-bold text-gray-800"><?= number_format($avgInsertPerDay, 0) ?> records</span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg">
                        <span class="text-sm text-blue-600">Projected Monthly</span>
                        <span class="font-bold text-blue-800"><?= number_format($projectedMonthly, 0) ?> records</span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-green-50 rounded-lg">
                        <span class="text-sm text-green-600">Projected Yearly</span>
                        <span class="font-bold text-green-800"><?= number_format($projectedYearly, 0) ?> records</span>
                    </div>
                    <div class="mt-4 p-4 bg-yellow-50 border-l-4 border-yellow-400">
                        <p class="text-sm text-yellow-800">
                            <strong><i class="fas fa-lightbulb mr-1"></i>Recommendation:</strong><br>
                            <?php if ($avgInsertPerDay > 5000): ?>
                            Increase tracking interval to 10 minutes untuk reduce load
                            <?php elseif ($avgInsertPerDay > 2000): ?>
                            Current 5-minute interval is optimal
                            <?php else: ?>
                            Load masih sangat ringan, interval bisa diturunkan jika perlu
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4"><i class="fas fa-chart-bar mr-2"></i>Daily Location Inserts (Last 7 Days)</h2>
            <canvas id="dailyInsertsChart" height="80"></canvas>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4"><i class="fas fa-users mr-2"></i>Top 10 Active Users</h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-2 text-sm text-gray-600">Username</th>
                                <th class="text-right py-2 text-sm text-gray-600">Total</th>
                                <th class="text-right py-2 text-sm text-gray-600">Avg/Day</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topUsers as $user): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-2 text-sm"><?= htmlspecialchars($user['username']) ?></td>
                                <td class="py-2 text-sm text-right font-semibold"><?= number_format($user['total_locations']) ?></td>
                                <td class="py-2 text-sm text-right"><?= $user['avg_per_day'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4"><i class="fas fa-triangle-exclamation mr-2"></i>Geofence Alert Statistics</h2>
                <?php if (!empty($alertStats)): ?>
                <div class="space-y-3">
                    <?php foreach ($alertStats as $stat): ?>
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="text-sm capitalize"><?= htmlspecialchars($stat['alert_type']) ?></span>
                        <span class="font-bold text-gray-800"><?= number_format($stat['count']) ?> alerts</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-gray-500 text-center py-8">No alerts in selected period</p>
                <?php endif; ?>
            </div>
        </div>

        
        <div class="bg-white rounded-lg shadow-md p-6 mt-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4"><i class="fas fa-stopwatch mr-2"></i>Tracking Interval Analysis</h2>
            <div class="grid grid-cols-3 gap-4">
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <p class="text-sm text-green-600 mb-1">Min Interval</p>
                    <p class="text-2xl font-bold text-green-800"><?= $minInterval ?> sec</p>
                </div>
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                    <p class="text-sm text-blue-600 mb-1">Average Interval</p>
                    <p class="text-2xl font-bold text-blue-800"><?= $avgInterval ?> sec</p>
                </div>
                <div class="text-center p-4 bg-red-50 rounded-lg">
                    <p class="text-sm text-red-600 mb-1">Max Interval</p>
                    <p class="text-2xl font-bold text-red-800"><?= $maxInterval ?> sec</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('dailyInsertsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_reverse(array_column($dailyInserts, 'date'))) ?>,
                datasets: [{
                    label: 'Location Inserts',
                    data: <?= json_encode(array_reverse(array_column($dailyInserts, 'count'))) ?>,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.3
                }, {
                    label: 'Active Users',
                    data: <?= json_encode(array_reverse(array_column($dailyInserts, 'active_users'))) ?>,
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
