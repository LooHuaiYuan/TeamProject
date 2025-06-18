<?php
header('Content-Type: application/json');
include 'database.php';

$category = $_GET['category']; // 'ac_system', 'water_heater', etc.
$timeframe = $_GET['timeframe']; // 'day', 'week', 'month'

$validCategories = ['ac_system', 'water_heater', 'chiller_freezer', 'plate_pickup'];
$validTimeframes = ['day', 'week', 'month'];

if (!in_array($category, $validCategories) || !in_array($timeframe, $validTimeframes)) {
    echo json_encode([]);
    exit;
}

// Define how many entries to return
$counts = [
    'day' => 7,     // day 1–7
    'week' => 4,    // week 1–4
    'month' => 5    // month 1–5
];

// Prepare zeroed arrays
$dayData = array_fill(0, 7, 0);
$weekData = array_fill(0, 4, 0);
$monthData = array_fill(0, 5, 0);

// Build query for the selected timeframe
$sql = "SELECT $timeframe, time FROM $category ORDER BY $timeframe ASC";
$result = $conn->query($sql);

// Fill only the selected timeframe
while ($row = $result->fetch_assoc()) {
    $index = intval($row[$timeframe]) - 1; // zero-based index
    $timeVal = floatval($row['time']);
    
    if ($timeframe === 'day' && $index >= 0 && $index < 7) {
        $dayData[$index] = $timeVal;
    } elseif ($timeframe === 'week' && $index >= 0 && $index < 4) {
        $weekData[$index] = $timeVal;
    } elseif ($timeframe === 'month' && $index >= 0 && $index < 5) {
        $monthData[$index] = $timeVal;
    }
}

// Send all arrays to keep chart format consistent
echo json_encode([
    'day' => $dayData,
    'week' => $weekData,
    'month' => $monthData
]);

$conn->close();
?>
