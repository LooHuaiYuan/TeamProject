<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$db_host = 'localhost';
$db_user = 'your_username';
$db_pass = 'your_password';
$db_name = 'everly_iot';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
}

// Handle different API endpoints
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_report':
        handleGetReport($pdo);
        break;
    case 'get_departments':
        handleGetDepartments($pdo);
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}

function handleGetReport($pdo)
{
    $year = $_GET['year'] ?? '';
    $month = $_GET['month'] ?? '';
    $departments = $_GET['departments'] ?? '';

    if (empty($year) || empty($month)) {
        echo json_encode(['error' => 'Year and month are required']);
        return;
    }

    $monthNumber = date('m', strtotime($month));
    $startDate = "$year-$monthNumber-01";
    $endDate = date("Y-m-t", strtotime($startDate));

    $departmentList = explode(',', $departments);
    $departmentList = array_map('trim', $departmentList);

    $response = [
        'all_department' => [],
        'by_department' => [],
        'active_users' => [],
        'inactive_users' => []
    ];

    // Get data for "All Department"
    if (in_array('All Department', $departmentList)) {
        $query = "
            SELECT 
                u.user_id,
                u.full_name AS user,
                COUNT(v.visit_id) AS total_visits
            FROM 
                users u
            LEFT JOIN 
                visits v ON u.user_id = v.user_id 
                AND v.visit_date BETWEEN :start_date AND :end_date
            WHERE 
                u.user_type = 'staff'
            GROUP BY 
                u.user_id, u.full_name
            ORDER BY 
                total_visits DESC
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
        $allDepartmentData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add ranking
        $rank = 1;
        foreach ($allDepartmentData as &$row) {
            $row['rank'] = $rank++;
        }

        $response['all_department'] = $allDepartmentData;
    }

    // Get data for each selected department
    foreach ($departmentList as $dept) {
        if ($dept === 'All Department' || $dept === 'Active' || $dept === 'Inactive') {
            continue;
        }

        $query = "
            SELECT 
                u.user_id,
                u.full_name AS user,
                COUNT(v.visit_id) AS total_visits
            FROM 
                users u
            JOIN 
                staff s ON u.user_id = s.user_id
            JOIN 
                departments d ON s.department_id = d.department_id
            LEFT JOIN 
                visits v ON u.user_id = v.user_id 
                AND v.visit_date BETWEEN :start_date AND :end_date
            WHERE 
                u.user_type = 'staff'
                AND d.department_name = :department
            GROUP BY 
                u.user_id, u.full_name
            ORDER BY 
                total_visits DESC
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate, 'department' => $dept]);
        $deptData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add numbering
        $number = 1;
        foreach ($deptData as &$row) {
            $row['no'] = $number++;
        }

        $response['by_department'][$dept] = $deptData;
    }

    // Get active users
    if (in_array('Active', $departmentList)) {
        $query = "
            SELECT 
                u.user_id,
                u.full_name AS user
            FROM 
                users u
            WHERE 
                u.is_active = TRUE
                AND u.user_type = 'staff'
            ORDER BY 
                u.full_name
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $activeUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add numbering
        $number = 1;
        foreach ($activeUsers as &$row) {
            $row['no'] = $number++;
        }

        $response['active_users'] = $activeUsers;
    }

    // Get inactive users
    if (in_array('Inactive', $departmentList)) {
        $query = "
            SELECT 
                u.user_id,
                u.full_name AS user
            FROM 
                users u
            WHERE 
                u.is_active = FALSE
                AND u.user_type = 'staff'
            ORDER BY 
                u.full_name
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $inactiveUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add numbering
        $number = 1;
        foreach ($inactiveUsers as &$row) {
            $row['no'] = $number++;
        }

        $response['inactive_users'] = $inactiveUsers;
    }

    echo json_encode($response);
}

function handleGetDepartments($pdo)
{
    $query = "SELECT department_name FROM departments";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode($departments);
}
