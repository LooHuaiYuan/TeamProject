<?php
header('Content-Type: application/json');
include 'database.php'; // Only need to include once


$sql = "SELECT * FROM plate_data";
$result = $conn->query($sql);

$plateData = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $plateData[] = $row;
    }
}

echo json_encode($plateData);

$conn->close();
?>
