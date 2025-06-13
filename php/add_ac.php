<?php

    header("Content-Type: application/json");
    include 'database.php';

    if(isset($_POST['id']) && isset($_POST['room_name']) && isset($_POST['floor']) && isset($_POST['status']) && isset($_POST['system']) && isset($_POST['created_at'])){
        $id = $_POST['id'];
        $room_name = $_POST['room_name'];
        $floor = $_POST['floor'];
        $status = $_POST['status'];
        $system = $_POST['system'];
        $created_at_raw = $_POST['created_at'];
        $dt = new DateTime($created_at_raw);
        $created_at =$dt->format('Y-m-d H:i:s');

        $sql = "INSERT INTO ac_units (id, room_name, floor, status, system, created_at) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt -> bind_param("ssssss", $id, $room_name, $floor, $status, $system, $created_at);
        
        // if ($stmt->execute()) {
        //     echo json_encode(["success" => true]);
        // } else {
        //     http_response_code(500);
        //     echo json_encode(["success" => false, "error" => "Insert failed"]);
        // }
        
        $stmt->close();

    }else{
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "SQL prepare failed"]);
    }

    $conn->close();
?>