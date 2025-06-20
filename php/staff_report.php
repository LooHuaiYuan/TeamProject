<?php
header('Content-Type: application/json');

include 'database.php'; // external connection file

// Handle different actions
$action = $_GET['action'] ?? '';

switch ($action) {
case 'get_report':
        handleGetReport($conn);
        break;
    case 'get_logs':
        handleGetLogs($conn);
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}

// Get Logs Function
function handleGetLogs($conn) {
    $username = $_GET['user'] ?? '';

    if (empty($username)) {
        echo json_encode(['error' => 'Missing username']);
        return;
    }

    // Get user ID based on full_name
    $stmt = $conn->prepare("SELECT id FROM users WHERE full_name = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        echo json_encode(['error' => 'User not found']);
        return;
    }

    $userId = $user['id'];

    // Now get logs for the user
    $stmt = $conn->prepare("SELECT access_time, action FROM user_access_logs WHERE user_id = ? ORDER BY access_time DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = [
            'datetime' => $row['access_time'],
            'action' => $row['action'] ?? 'Accessed system'
        ];
    }

    echo json_encode(['logs' => $logs]);
}

// Get Report Function
function handleGetReport($conn) {
    $year = isset($_GET['year']) ? intval($_GET['year']) : 0;
    $month = isset($_GET['month']) ? trim($_GET['month']) : '';
    $departmentsParam = isset($_GET['departments']) ? $_GET['departments'] : '';

    $departments = explode(',', urldecode($departmentsParam));
    $departments = array_map('trim', $departments);

    if ($year < 2024 || $year > 2025) {
        echo json_encode(['error' => 'Invalid year']);
        return;
    }

    $validMonths = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    if (!in_array($month, $validMonths)) {
        echo json_encode(['error' => 'Invalid month']);
        return;
    }

    if (empty($departments)) {
        echo json_encode(['error' => 'No departments selected']);
        return;
    }

    $monthNum = date('m', strtotime("1 $month $year"));

    $response = [
        'all_department' => [],
        'by_department' => [],
        'active_users' => [],
        'inactive_users' => []
    ];

    $allDeptSelected = in_array('All Department', $departments);

    if ($allDeptSelected) {
        $response['all_department'] = getAllDepartmentData($conn, $year, $monthNum);
    }

    foreach ($departments as $dept) {
        if ($dept === 'All Department') continue;
        $response['by_department'][$dept] = getDepartmentData($conn, $dept, $year, $monthNum);
    }

    list($active, $inactive) = getActiveInactiveUsers($conn, $year, $monthNum, $departments, $allDeptSelected);
    $response['active_users'] = $active;
    $response['inactive_users'] = $inactive;

    echo json_encode($response);
}

function getAllDepartmentData($conn, $year, $monthNum) {
    $startDate = "$year-$monthNum-01";
    $sql = "SELECT u.id, u.full_name AS user, COUNT(l.id) AS total_visits,
                   DENSE_RANK() OVER (ORDER BY COUNT(l.id) DESC) AS rank
            FROM users u
            LEFT JOIN user_access_logs l ON u.id = l.user_id
                AND l.access_time >= ?
                AND l.access_time < DATE_ADD(?, INTERVAL 1 MONTH)
            GROUP BY u.id, u.full_name
            ORDER BY total_visits DESC, u.full_name";

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
    $departmentMapping = [
        'F&B' => 'F&B Department', 'F&amp;B' => 'F&B Department',
        'F%26B' => 'F&B Department', 'Admin' => 'Admin',
        'Maintenance' => 'Maintenance Department',
        'Fuze' => 'Fuze Department', 'Sales' => 'Sales Department',
        'House Keeping' => 'House Keeping Department',
        'View Only' => 'View Only'
    ];
    $dbDepartmentName = $departmentMapping[$department] ?? $department;

    $sql = "SELECT u.id, u.full_name AS user, COUNT(l.id) AS total_visits,
                   ROW_NUMBER() OVER (ORDER BY COUNT(l.id) DESC) AS no
            FROM users u
            JOIN departments d ON u.department_id = d.id
            LEFT JOIN user_access_logs l ON u.id = l.user_id
                AND l.access_time >= ?
                AND l.access_time < DATE_ADD(?, INTERVAL 1 MONTH)
            WHERE d.name = ?
            GROUP BY u.id, u.full_name
            ORDER BY total_visits DESC, u.full_name";

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
    $deptFilter = '';
    $params = [$startDate, $startDate];
    $types = 'ss';

    if (!$allDeptSelected) {
        $placeholders = [];
        foreach ($departments as $dept) {
            if ($dept === 'All Department') continue;
            $dbDept = [
                'F&B' => 'F&B Department', 'F&amp;B' => 'F&B Department',
                'F%26B' => 'F&B Department', 'Admin' => 'Admin',
                'Maintenance' => 'Maintenance Department',
                'Fuze' => 'Fuze Department', 'Sales' => 'Sales Department',
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

    $sql = "SELECT u.id, u.full_name AS user, COUNT(l.id) AS total_visits,
                   ROW_NUMBER() OVER () AS no
            FROM users u
            JOIN departments d ON u.department_id = d.id
            LEFT JOIN user_access_logs l ON u.id = l.user_id
                AND l.access_time >= ?
                AND l.access_time < DATE_ADD(?, INTERVAL 1 MONTH)
            WHERE 1=1 $deptFilter
            GROUP BY u.id, u.full_name
            ORDER BY u.full_name";

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