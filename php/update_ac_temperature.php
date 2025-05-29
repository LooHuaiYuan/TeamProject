<?php
require_once 'database.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["success" => false, "error" => "Invalid or missing JSON"]);
    exit;
}

if (isset($data['room_id']) && isset($data['temperature'])) {
    $roomId = $data['room_id']; // Keep as string like 'AC-001'
    $temperature = floatval($data['temperature']);

    // Debug
    // error_log(\"Room ID: $roomId, Temp: $temperature\");

    $stmt = $conn->prepare("UPDATE ac_units SET temperature = ? WHERE id = ?");
    $stmt->bind_param("ds", $temperature, $roomId);

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(["success" => false, "error" => "Missing room_id or temperature"]);
}

$conn->close();
?>
