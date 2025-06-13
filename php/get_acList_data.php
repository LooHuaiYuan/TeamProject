<?php

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    header("Content-Type: application/json");
    include 'database.php';

    //optional GET parameters
    $id = $_GET['id']??null;
    $system = $_GET['system']??null;
    $floor = $_GET['floor']??null;

    $where = [];
    $params = [];
    $types = "";

    //Dynamically build where clause
    if ($id !==null){
        $where[] = "id = ?";
        $params[] = $id;
        $types .= "s";
    }

    if ($system !==null){
        $where[] = "system = ?";
        $params[] = $system;
        $types .= "s";
    }

    if ($floor !==null){
        $where[] = "floor = ?";
        $params[] = $floor;
        $types .= "s";
    }

    $sql = "SELECT * FROM ac_units";
    if (!empty($where)){
        $sql .= " WHERE ". implode(" AND ", $where);
    }
    $stmt = $conn-> prepare($sql);

    if(!$stmt){
        http_response_code(500);
        echo json_encode(["error" => "SQL prepare failed"]);
        exit();
    }

    if(!empty($params)){
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $ac_units = [];

    while ($row = $result->fetch_assoc()) {
        $ac_units[] = $row;
    } 

    echo json_encode($ac_units);
    $conn->close();
?>
