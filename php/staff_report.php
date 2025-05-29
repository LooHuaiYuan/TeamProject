<?php
header('Content-Type: application/json');

// Database configuration
$db_host = 'localhost';
$db_user = 'root'; // Change to your database username
$db_pass = '';     // Change to your database password
$db_name = 'everly_iot';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}

// Handle different actions
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_report':
        handleGetReport($conn);
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}

function handleGetReport($conn) {
    // Validate and sanitize inputs
    $year = isset($_GET['year']) ? intval($_GET['year']) : 0;
    $month = isset($_GET['month']) ? trim($_GET['month']) : '';
    $departmentsParam = isset($_GET['departments']) ? $_GET['departments'] : '';
    
    // Properly decode and split departments
    $departments = explode(',', urldecode($departmentsParam));
    $departments = array_map('trim', $departments);
    
    // Validate inputs
    if ($year < 2024 || $year > 2025) {
        echo json_encode(['error' => 'Invalid year']);
        return;
    }
    
    $validMonths = ['January', 'February', 'March', 'April', 'May', 'June', 
                   'July', 'August', 'September', 'October', 'November', 'December'];
    if (!in_array($month, $validMonths)) {
        echo json_encode(['error' => 'Invalid month']);
        return;
    }
    
    if (empty($departments)) {
        echo json_encode(['error' => 'No departments selected']);
        return;
    }
    
    // Convert month name to numeric value
    $monthNum = date('m', strtotime("1 $month $year"));
    
    // Prepare response data
    $response = [
        'all_department' => [],
        'by_department' => [],
        'active_users' => [],
        'inactive_users' => []
    ];
    
    // Check if "All Department" is selected
    $allDeptSelected = in_array('All Department', $departments);
    
    // Get data for all departments if selected
    if ($allDeptSelected) {
        $response['all_department'] = getAllDepartmentData($conn, $year, $monthNum);
    }
    
    // Get data for individual departments
    foreach ($departments as $dept) {
        if ($dept === 'All Department') continue;
        
        $response['by_department'][$dept] = getDepartmentData($conn, $dept, $year, $monthNum);
    }
    
    // Get active/inactive users
    list($active, $inactive) = getActiveInactiveUsers($conn, $year, $monthNum, $departments, $allDeptSelected);
    $response['active_users'] = $active;
    $response['inactive_users'] = $inactive;
    
    echo json_encode($response);
}

function getAllDepartmentData($conn, $year, $monthNum) {
    $startDate = "$year-$monthNum-01";
    
    $sql = "SELECT 
                u.id,
                u.full_name AS user,
                COUNT(l.id) AS total_visits,
                DENSE_RANK() OVER (ORDER BY COUNT(l.id) DESC) AS rank
            FROM 
                users u
            LEFT JOIN 
                user_access_logs l ON u.id = l.user_id 
                AND l.access_time >= ? 
                AND l.access_time < DATE_ADD(?, INTERVAL 1 MONTH)
            GROUP BY 
                u.id, u.full_name
            ORDER BY 
                total_visits DESC, u.full_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $startDate, $startDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'rank' => $row['rank'],
            'user' => $row['user'],
            'total_visits' => (int)$row['total_visits']
        ];
    }
    
    return $data;
}

function getDepartmentData($conn, $department, $year, $monthNum) {
    $startDate = "$year-$monthNum-01";
    
    // Create mapping for department names
    $departmentMapping = [
        'F&B' => 'F&B Department',
        'F&amp;B' => 'F&B Department',
        'F%26B' => 'F&B Department',
        'Admin' => 'Admin',
        'Maintenance' => 'Maintenance Department',
        'Fuze' => 'Fuze Department',
        'Sales' => 'Sales Department',
        'House Keeping' => 'House Keeping Department',
        'View Only' => 'View Only'
    ];
    
    // Use mapped name if available, otherwise use original
    $dbDepartmentName = $departmentMapping[$department] ?? $department;
    
    $sql = "SELECT 
                u.id,
                u.full_name AS user,
                COUNT(l.id) AS total_visits,
                ROW_NUMBER() OVER (ORDER BY COUNT(l.id) DESC) AS no
            FROM 
                users u
            JOIN 
                departments d ON u.department_id = d.id
            LEFT JOIN 
                user_access_logs l ON u.id = l.user_id 
                AND l.access_time >= ? 
                AND l.access_time < DATE_ADD(?, INTERVAL 1 MONTH)
            WHERE 
                d.name = ?
            GROUP BY 
                u.id, u.full_name
            ORDER BY 
                total_visits DESC, u.full_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $startDate, $startDate, $dbDepartmentName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'no' => $row['no'],
            'user' => $row['user'],
            'total_visits' => (int)$row['total_visits']
        ];
    }
    
    return $data;
}

function getActiveInactiveUsers($conn, $year, $monthNum, $departments, $allDeptSelected) {
    $startDate = "$year-$monthNum-01";
    
    // Build department filter
    $deptFilter = '';
    $params = [$startDate, $startDate];
    $types = 'ss';
    
    if (!$allDeptSelected) {
        $placeholders = [];
        foreach ($departments as $dept) {
            if ($dept === 'All Department') continue;
            
            // Map to database names
            $dbDept = [
                'F&B' => 'F&B Department',
                'F&amp;B' => 'F&B Department',
                'F%26B' => 'F&B Department',
                'Admin' => 'Admin',
                'Maintenance' => 'Maintenance Department',
                'Fuze' => 'Fuze Department',
                'Sales' => 'Sales Department',
                'House Keeping' => 'House Keeping Department',
                'View Only' => 'View Only'
            ][$dept] ?? $dept;
            
            $placeholders[] = '?';
            $params[] = $dbDept;
            $types .= 's';
        }
        
        if (!empty($placeholders)) {
            $deptFilter = "AND d.name IN (" . implode(',', $placeholders) . ")";
        }
    }
    
    $sql = "SELECT 
                u.id,
                u.full_name AS user,
                COUNT(l.id) AS total_visits,
                ROW_NUMBER() OVER () AS no
            FROM 
                users u
            JOIN 
                departments d ON u.department_id = d.id
            LEFT JOIN 
                user_access_logs l ON u.id = l.user_id 
                AND l.access_time >= ? 
                AND l.access_time < DATE_ADD(?, INTERVAL 1 MONTH)
            WHERE 
                1=1
                $deptFilter
            GROUP BY 
                u.id, u.full_name
            ORDER BY 
                u.full_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $active = [];
    $inactive = [];
    $noActive = 1;
    $noInactive = 1;
    
    while ($row = $result->fetch_assoc()) {
        $userData = [
            'no' => $row['total_visits'] > 0 ? $noActive++ : $noInactive++,
            'user' => $row['user'],
            'total_visits' => (int)$row['total_visits']
        ];
        
        if ($row['total_visits'] > 0) {
            $active[] = $userData;
        } else {
            $inactive[] = $userData;
        }
    }
    
    return [$active, $inactive];
}

$conn->close();
?>