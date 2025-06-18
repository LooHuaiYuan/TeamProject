<?php

    header("Content-Type: application/json");
    include 'database.php';

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing ID"]);
        exit();
    }

    $id = $data['id'];

    $sql = "DELETE FROM ac_units WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Delete failed"]);
    }

    $stmt->close();
    $conn->close();
?>