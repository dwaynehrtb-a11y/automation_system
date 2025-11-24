<?php
require_once 'config/db.php';
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Live Grade Monitor</title>
    <meta http-equiv="refresh" content="2">
    <style>
        body { font-family: monospace; padding: 20px; background: #1e293b; color: #e2e8f0; }
        .timestamp { color: #94a3b8; font-size: 12px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th { background: #334155; padding: 12px; text-align: left; border: 1px solid #475569; }
        td { padding: 10px; border: 1px solid #475569; }
        .raw { background: #1e40af; color: white; font-weight: bold; font-size: 16px; }
        .pct { background: #059669; color: white; }
        .warning { background: #dc2626; color: white; animation: blink 1s infinite; }
        @keyframes blink { 50% { opacity: 0.5; } }
        .info { background: #334155; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>🔴 LIVE GRADE MONITOR - Auto-refresh every 2s</h1>
    <div class="timestamp">Last updated: <?php echo date('Y-m-d H:i:s'); ?></div>
    
    <div class="info">
        <strong>Monitoring:</strong> Student 2022-118764 | Class: 24_T1_CCDATRCL_INF221 | Component: Quiz<br>
        <strong>Purpose:</strong> Watch database values update in real-time when you input grades
    </div>
    
    <?php
    $query = "SELECT 
                gcc.column_name,
                gcc.max_score,
                sfg.raw_score,
                sfg.updated_at,
                CASE 
                    WHEN sfg.raw_score > gcc.max_score AND sfg.raw_score <= 100 THEN 'STORED AS PERCENTAGE!'
                    WHEN sfg.raw_score <= gcc.max_score THEN 'OK (Raw Score)'
                    ELSE 'Unusual Value'
                END as status,
                CASE 
                    WHEN sfg.raw_score > gcc.max_score AND sfg.raw_score <= 100 
                    THEN ROUND((sfg.raw_score / 100) * gcc.max_score, 2)
                    ELSE NULL
                END as corrected_value
              FROM grading_component_columns gcc
              INNER JOIN grading_components gc ON gcc.component_id = gc.id
              LEFT JOIN student_flexible_grades sfg ON sfg.column_id = gcc.id AND sfg.student_id = '2022-118764'
              WHERE gc.class_code = '24_T1_CCDATRCL_INF221'
              AND gc.component_name = 'Quiz'
              ORDER BY gcc.order_index";
    
    $result = $conn->query($query);
    ?>
    
    <table>
        <thead>
            <tr>
                <th>Quiz Item</th>
                <th>Max Score</th>
                <th>DB Raw Score</th>
                <th>Status</th>
                <th>Should Be</th>
                <th>As Percentage</th>
                <th>Last Updated</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): 
                $rawScore = $row['raw_score'];
                $maxScore = $row['max_score'];
                $percentage = $rawScore !== null ? round(($rawScore / $maxScore) * 100, 1) : '-';
                $isWarning = strpos($row['status'], 'PERCENTAGE') !== false;
            ?>
            <tr class="<?php echo $isWarning ? 'warning' : ''; ?>">
                <td><strong><?php echo $row['column_name']; ?></strong></td>
                <td><?php echo $maxScore; ?></td>
                <td class="raw"><?php echo $rawScore !== null ? $rawScore : 'NULL'; ?></td>
                <td><?php echo $row['status']; ?></td>
                <td><?php echo $row['corrected_value'] !== null ? $row['corrected_value'] : '-'; ?></td>
                <td class="pct"><?php echo $percentage; ?>%</td>
                <td><?php echo $row['updated_at'] ?? '-'; ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    
    <div style="margin-top: 20px; padding: 15px; background: #0f172a; border-radius: 8px;">
        <strong>🎯 How to use:</strong><br>
        1. Keep this page open<br>
        2. Go to your grading page and input a grade in Quiz 3<br>
        3. Watch this page auto-update every 2 seconds<br>
        4. Check if the database stores the value you typed or a percentage
    </div>
</body>
</html>
<?php
$conn->close();
?>
