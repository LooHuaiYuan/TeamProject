<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
include 'database.php';

$floor = $_GET['floor'] ?? 'b2';

$sql = "SELECT * FROM ac_units WHERE floor = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $floor);
$stmt->execute();
$result = $stmt->get_result();

$ac_units = [];

while ($row = $result->fetch_assoc()) {
    $ac_units[] = $row;
}

echo json_encode($ac_units);
$conn->close();
?>
