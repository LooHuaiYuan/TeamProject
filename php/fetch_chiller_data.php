<?php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root"; // default XAMPP username
$password = ""; // default XAMPP password
$dbname = "sse3400";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['error' => "Connection failed: " . $conn->connect_error]));
}

$freezer_name = $_GET['freezer_name'] ?? null;
$date = $_GET['date'] ?? null;

if (!$freezer_name || !$date) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

// List of valid chiller names (security measure)
$validChillers = [
    "Dairy Chiller",
    "Holding Chiller",
    "Holding Freezer",
    "Meat Chiller",
    "Vegetable Chiller",
    "General Freezer",
    "Fuze Chiller",
    "Fuze Freezer",
    "Banquet Chiller",
    "Banquet Freezer"
];

if (!in_array($freezer_name, $validChillers)) {
    echo json_encode(['error' => 'Invalid chiller name']);
    exit;
}

// Remove the time part if it exists in the date
$formatted_date = explode(' ', $date)[0];

// Prepare the SQL query to get hourly data
$sql = "SELECT record_time as hour, temperature 
        FROM freezer_monitoring 
        WHERE freezer_name = ? AND DATE(record_date) = ?
        ORDER BY hour";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $freezer_name, $formatted_date);
$stmt->execute();
$result = $stmt->get_result();

$data = array_fill(0, 24, null); // Initialize array for 24 hours

while ($row = $result->fetch_assoc()) {
    $hour = (int)$row['hour'];
    $data[$hour] = (float)$row['temperature'];
}

// Extract only the hours we need (6-11)
$filteredData = array_slice($data, 6, 6);

echo json_encode($filteredData);

$conn->close();
