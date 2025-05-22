<?php
header('Content-Type: application/json');
require_once 'db_config.php';

// Create database connection
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_report':
        handleGetReport($pdo);
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}

function handleGetReport($pdo) {
    // Validate inputs
    $year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT, ['options' => ['min_range' => 2024, 'max_range' => 2025]]);
    $month = filter_input(INPUT_GET, 'month', FILTER_SANITIZE_STRING);
    $departments = isset($_GET['departments']) ? explode(',', $_GET['departments']) : [];
    
    if (!$year || !$month || empty($departments)) {
        echo json_encode(['error' => 'Invalid parameters']);
        return;
    }
    
    // Convert month name to month number
    $monthNumber = date('m', strtotime("1 $month $year"));
    if (!$monthNumber) {
        echo json_encode(['error' => 'Invalid month']);
        return;
    }
    
    $startDate = "$year-$monthNumber-01";
    $endDate = date('Y-m-t', strtotime($startDate));
    
    $response = [
        'all_department' => [],
        'by_department' => [],
        'active_users' => [],
        'inactive_users' => []
    ];
    
    // Check if "All Department" is selected
    $allDepartmentsSelected = in_array('All Department', $departments);
    
    // Get all departments data if "All Department" is selected
    if ($allDepartmentsSelected) {
        $response['all_department'] = getAllDepartmentStats($pdo, $startDate, $endDate);
        
        // Get active/inactive users for all departments
        list($active, $inactive) = getActiveInactiveUsers($pdo, $startDate, $endDate);
        $response['active_users'] = $active;
        $response['inactive_users'] = $inactive;
    }
    
    // Get data for each selected department (excluding "All Department")
    $selectedDepts = array_diff($departments, ['All Department']);
    
    foreach ($selectedDepts as $deptName) {
        $deptData = getDepartmentStats($pdo, $deptName, $startDate, $endDate);
        $response['by_department'][$deptName] = $deptData;
        
        // If not "All Department" selected, collect active/inactive users from selected departments
        if (!$allDepartmentsSelected) {
            list($deptActive, $deptInactive) = getActiveInactiveUsers($pdo, $startDate, $endDate, $deptName);
            
            // Merge with existing active/inactive users, avoiding duplicates
            $response['active_users'] = array_merge($response['active_users'], $deptActive);
            $response['inactive_users'] = array_merge($response['inactive_users'], $deptInactive);
        }
    }
    
    // Remove duplicate users from active/inactive lists
    $response['active_users'] = array_unique($response['active_users'], SORT_REGULAR);
    $response['inactive_users'] = array_unique($response['inactive_users'], SORT_REGULAR);
    
    // Add ranking numbers
    addRankingNumbers($response);
    
    echo json_encode($response);
}

function getAllDepartmentStats($pdo, $startDate, $endDate) {
    $sql = "SELECT 
                u.user_id,
                u.full_name AS user,
                COUNT(v.visit_id) AS total_visits,
                s.department_id,
                d.department_name
            FROM users u
            JOIN staff s ON u.user_id = s.user_id
            JOIN departments d ON s.department_id = d.department_id
            LEFT JOIN staff_visits v ON s.staff_id = v.staff_id 
                AND v.visit_date BETWEEN :start_date AND :end_date
            WHERE u.user_type = 'staff'
            GROUP BY u.user_id, u.full_name, s.department_id, d.department_name
            ORDER BY total_visits DESC, u.full_name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $results;
}

function getDepartmentStats($pdo, $departmentName, $startDate, $endDate) {
    $sql = "SELECT 
                u.user_id,
                u.full_name AS user,
                COUNT(v.visit_id) AS total_visits,
                s.department_id,
                d.department_name
            FROM users u
            JOIN staff s ON u.user_id = s.user_id
            JOIN departments d ON s.department_id = d.department_id
            LEFT JOIN staff_visits v ON s.staff_id = v.staff_id 
                AND v.visit_date BETWEEN :start_date AND :end_date
            WHERE u.user_type = 'staff' AND d.department_name = :dept_name
            GROUP BY u.user_id, u.full_name, s.department_id, d.department_name
            ORDER BY total_visits DESC, u.full_name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'start_date' => $startDate,
        'end_date' => $endDate,
        'dept_name' => $departmentName
    ]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $results;
}

function getActiveInactiveUsers($pdo, $startDate, $endDate, $departmentName = null) {
    $active = [];
    $inactive = [];
    
    $sql = "SELECT 
                u.user_id,
                u.full_name AS user,
                COUNT(v.visit_id) AS total_visits,
                d.department_name
            FROM users u
            JOIN staff s ON u.user_id = s.user_id
            JOIN departments d ON s.department_id = d.department_id
            LEFT JOIN staff_visits v ON s.staff_id = v.staff_id 
                AND v.visit_date BETWEEN :start_date AND :end_date
            WHERE u.user_type = 'staff'";
    
    if ($departmentName) {
        $sql .= " AND d.department_name = :dept_name";
    }
    
    $sql .= " GROUP BY u.user_id, u.full_name, d.department_name";
    
    $params = ['start_date' => $startDate, 'end_date' => $endDate];
    if ($departmentName) {
        $params['dept_name'] = $departmentName;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        if ($user['total_visits'] > 0) {
            $active[] = ['user' => $user['user'], 'department' => $user['department_name']];
        } else {
            $inactive[] = ['user' => $user['user'], 'department' => $user['department_name']];
        }
    }
    
    return [$active, $inactive];
}

function addRankingNumbers(&$response) {
    // Add ranking to all department data
    $rank = 1;
    foreach ($response['all_department'] as &$row) {
        $row['rank'] = $rank++;
    }
    
    // Add numbering to department-specific data
    foreach ($response['by_department'] as &$deptData) {
        $no = 1;
        foreach ($deptData as &$row) {
            $row['no'] = $no++;
        }
    }
    
    // Add numbering to active/inactive users
    $no = 1;
    foreach ($response['active_users'] as &$user) {
        $user['no'] = $no++;
    }
    
    $no = 1;
    foreach ($response['inactive_users'] as &$user) {
        $user['no'] = $no++;
    }
}
?>