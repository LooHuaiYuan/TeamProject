<?php
header('Content-Type: application/json');
include 'database.php';

$category = $_GET['category'] ?? '';
$timeframe = $_GET['timeframe'] ?? '';

// Whitelisted valid values
$validCategories = ['ac_system', 'water_heater', 'chiller_freezer', 'plate_pickup'];
$validTimeframes = ['day', 'week', 'month'];

if (!in_array($category, $validCategories) || !in_array($timeframe, $validTimeframes)) {
    echo json_encode(['error' => 'Invalid category or timeframe']);
    exit;
}

// Determine the correct value column
$valueColumn = ($category === 'plate_pickup') ? 'time' : 'temperature';

$dayData = array_fill(0, 7, 0);
$weekData = array_fill(0, 4, 0);
$monthData = array_fill(0, 5, 0);

$sql = "SELECT `$timeframe`, `$valueColumn` FROM `$category` ORDER BY `$timeframe` ASC";
$result = $conn->query($sql);

// Error check
if (!$result) {
    echo json_encode(['error' => 'SQL Error: ' . $conn->error]);
    exit;
}

// Populate selected timeframe only
while ($row = $result->fetch_assoc()) {
    $index = intval($row[$timeframe]) - 1;
    $value = floatval($row[$valueColumn]);

    if ($timeframe === 'day' && $index >= 0 && $index < 7) {
        $dayData[$index] = $value;
    } elseif ($timeframe === 'week' && $index >= 0 && $index < 4) {
        $weekData[$index] = $value;
    } elseif ($timeframe === 'month' && $index >= 0 && $index < 5) {
        $monthData[$index] = $value;
    }
}

// Output all arrays to keep chart formatting consistent
echo json_encode([
    'day' => $dayData,
    'week' => $weekData,
    'month' => $monthData
]);

$conn->close();
?>
