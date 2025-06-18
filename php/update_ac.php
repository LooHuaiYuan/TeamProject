<?php

    header("Content-Type: application/json");
    include 'database.php';

    // $data = json_decode(file_get_contents("php://input"), true);
    
    // if (!$data) {
    //     http_response_code(400);
    //     echo json_encode(["success" => false, "error" => "Invalid or missing required fields"]);
    //     exit();
    // }

    if (isset($_POST['id']) && isset($_POST['room_name']) && isset($_POST['floor']) && isset($_POST['status']) && isset($_POST['system']) && isset($_POST['created_at'])) {
        $id = $_POST['id'];
        $room_name = $_POST['room_name'];
        $floor = $_POST['floor'];
        $status = $_POST['status'];
        $system = $_POST['system'];
        $created_at = $_POST['created_at'];

        $sql = "UPDATE ac_units SET room_name = ?, floor = ?, status = ?, system = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $room_name, $floor, $status, $system, $id);

        if ($stmt->execute()) {
            echo json_encode('Update Successfully');
        } else {
            http_response_code(500);
            echo json_encode('Update Failed');
        }
    
        $stmt->close();

    }else {
        echo json_encode('SQL prepare failed');
    }

    $conn->close();
?>