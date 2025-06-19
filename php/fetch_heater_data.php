<?php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root"; // default XAMPP username
$password = ""; // default XAMPP password
$dbname = "team";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['error' => "Connection failed: " . $conn->connect_error]));
}

$heater_id = $_GET['heater_id'] ?? null;
$date = $_GET['date'] ?? null;

if (!$heater_id || !$date) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

// Remove the time part if it exists in the date
$formatted_date = explode(' ', $date)[0];

// Prepare the SQL query to get hourly data
$sql = "SELECT record_time as hour, temperature 
        FROM water_heater 
        WHERE heater_id = ? AND DATE(record_date) = ?
        ORDER BY hour";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => "Prepare failed: " . $conn->error]);
    exit;
}

$stmt->bind_param("ss", $heater_id, $formatted_date);
if (!$stmt->execute()) {
    echo json_encode(['error' => "Execute failed: " . $stmt->error]);
    exit;
}

$result = $stmt->get_result();
$data = array_fill(0, 24, null); // Initialize array for 24 hours

while ($row = $result->fetch_assoc()) {
    $hour = (int)$row['hour'];
    $data[$hour] = (float)$row['temperature'];
}

echo json_encode($data);

$conn->close();
