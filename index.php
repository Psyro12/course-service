<?php
// ===============================================
// Course Microservice API Entry Point
// ===============================================

// 1. CONFIGURATION
// !!! IMPORTANT: CHANGE THESE DATABASE CREDENTIALS !!!
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // <-- Your Password
define('DB_NAME', 'course_db');

// Set the API base URL for internal routing (assuming /api/v1 is the start)
$base_uri = '/api/v1';

// 2. SET HEADERS
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Handle pre-flight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(200);
    exit();
}

// 3. DATABASE CONNECTION
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

// 4. ROUTING LOGIC
$request_method = $_SERVER['REQUEST_METHOD'];

// --- [ADDED] METHOD SPOOFING FOR INFINITYFREE ---
// This fixes the 404 error on PUT requests by checking for ?_method=PUT
if ($request_method === 'POST' && isset($_GET['_method'])) {
    $request_method = strtoupper($_GET['_method']);
}
// ------------------------------------------------

$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// Extract resource and parameters from URI
// Expected format: /api/v1/courses or /api/v1/courses/123
$parts = explode('/', $uri);

// Find the position of 'api' in the parts
$api_index = array_search('api', $parts);
if ($api_index !== false && isset($parts[$api_index + 1]) && $parts[$api_index + 1] === 'v1') {
    // Extract everything after 'v1'
    $resource_parts = array_slice($parts, $api_index + 2);
    $resource_parts = array_values(array_filter($resource_parts)); // Remove empty values
} else {
    $resource_parts = [];
}

$resource = $resource_parts[0] ?? '';
$id = $resource_parts[1] ?? null;
$sub_resource = $resource_parts[2] ?? null;


// ===============================================
// ROUTING SECTION
// ===============================================

if ($resource === 'courses') {
    
    // Endpoint: /courses/{user_id}/schedules
    if ($id !== null && $sub_resource === 'schedules') {
        if ($request_method === 'GET') {
            handle_get_user_schedule($conn, $id);
        } else if ($request_method === 'POST') {
            handle_create_schedule($conn, $id); 
        } else {
            http_response_code(405);
            echo json_encode(["error" => "Method not allowed for /courses/{user_id}/schedules"]);
        }
    }

    // Endpoint: /courses, /courses/{course_id}
    else {
        if ($request_method === 'GET') {
            if ($id === null) {
                // GET /courses OR GET /courses?department={name}
                handle_get_all_courses($conn);
            } else {
                // GET /courses/{course_id}
                handle_get_single_course($conn, $id);
            }
        } 
        
        else if ($request_method === 'POST') {
            // POST /courses -> Create a new course
            handle_create_course($conn);
        }
        
        else if ($request_method === 'PUT' && $id !== null) {
            // PUT /courses/{course_id} -> Update a course
            handle_update_course($conn, $id);
        }
        
        else if ($request_method === 'DELETE' && $id !== null) {
            // DELETE /courses/{course_id} -> Delete a course
            http_response_code(501); // Not Implemented
            echo json_encode(["message" => "DELETE /courses/{id} not yet implemented."]);
        }
    }

} else {
    // If no resource matches
    http_response_code(404);
    echo json_encode(["error" => "Resource not found"]);
}

$conn->close();


// ===============================================
// API FUNCTION IMPLEMENTATIONS
// ===============================================

/**
 * Handles GET /courses and GET /courses?department={name}
 */
function handle_get_all_courses($conn) {
    $department = $_GET['department'] ?? null;
    $sql = "SELECT course_id, course_name, units, departments FROM tbl_courses";
    $params = [];
    $types = '';

    if ($department) {
        $sql .= " WHERE departments = ?";
        $params[] = $department;
        $types .= 's';
    }

    $stmt = $conn->prepare($sql);
    
    if ($department) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        $courses = [];
        while($row = $result->fetch_assoc()) {
            $courses[] = $row;
        }
        http_response_code(200);
        echo json_encode($courses);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Error retrieving courses: " . $conn->error]);
    }
}

/**
 * Handles GET /courses/{id}
 */
function handle_get_single_course($conn, $id) {
    $stmt = $conn->prepare("SELECT course_id, course_name, units, departments FROM tbl_courses WHERE course_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["error" => "Course with ID $id not found."]);
        return;
    }

    $course = $result->fetch_assoc();
    http_response_code(200);
    echo json_encode($course);
}

/**
 * Handles POST /courses
 * Creates a new course AND adds prerequisites if provided.
 */
function handle_create_course($conn) {
    $data = json_decode(file_get_contents("php://input"), true);

    $course_name = $data['course_name'] ?? null;
    $units = $data['units'] ?? null;
    $departments = $data['departments'] ?? null;
    $prerequisites = $data['prerequisites'] ?? []; // New field

    if (!$course_name || !is_numeric($units) || !$departments) {
        http_response_code(400); 
        echo json_encode(["error" => "Missing or invalid data: course_name, units, and departments are required."]);
        return;
    }

    // 1. Start Transaction
    $conn->begin_transaction();

    try {
        // 2. Insert Course
        $stmt = $conn->prepare("INSERT INTO tbl_courses (course_name, units, departments) VALUES (?, ?, ?)");
        $stmt->bind_param("sis", $course_name, $units, $departments);

        if (!$stmt->execute()) {
            throw new Exception("Course insertion failed: " . $stmt->error);
        }
        
        $new_course_id = $conn->insert_id;

        // 3. Insert Prerequisites
        if (!empty($prerequisites) && is_array($prerequisites)) {
            $stmt_prereq = $conn->prepare("INSERT INTO tbl_courses_prerequisites (course_id, prerequisite_id) VALUES (?, ?)");
            foreach ($prerequisites as $prereq_id) {
                if (is_numeric($prereq_id)) {
                    $stmt_prereq->bind_param("ii", $new_course_id, $prereq_id);
                    if (!$stmt_prereq->execute()) {
                         throw new Exception("Failed to add prerequisite ID: $prereq_id");
                    }
                }
            }
        }

        // 4. Commit
        $conn->commit();

        http_response_code(201);
        echo json_encode([
            "message" => "Course created successfully",
            "course_id" => $new_course_id,
            "course_name" => $course_name,
            "prerequisites_count" => count($prerequisites)
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(["error" => "Transaction failed: " . $e->getMessage()]);
    }
}

/**
 * Handles PUT /courses/{course_id}
 */
function handle_update_course($conn, $course_id) {
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data)) {
        http_response_code(400);
        echo json_encode(["error" => "No data provided for update."]);
        return;
    }

    $set_clauses = [];
    $params = [];
    $types = '';

    if (isset($data['course_name'])) {
        $set_clauses[] = "course_name = ?";
        $params[] = $data['course_name'];
        $types .= 's';
    }
    if (isset($data['units']) && is_numeric($data['units'])) {
        $set_clauses[] = "units = ?";
        $params[] = $data['units'];
        $types .= 'i';
    }
    if (isset($data['departments'])) {
        $set_clauses[] = "departments = ?";
        $params[] = $data['departments'];
        $types .= 's';
    }

    if (empty($set_clauses)) {
        http_response_code(400);
        echo json_encode(["error" => "No valid fields to update."]);
        return;
    }

    $sql = "UPDATE tbl_courses SET " . implode(', ', $set_clauses) . " WHERE course_id = ?";
    
    $params[] = $course_id;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        http_response_code(200); 
        echo json_encode(["message" => "Course ID $course_id updated successfully."]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update course: " . $stmt->error]);
    }
}


/**
 * Handles GET /courses/{user_id}/schedules
 */
function handle_get_user_schedule($conn, $user_id) {
    $stmt_role = $conn->prepare("SELECT user_role FROM tbl_users WHERE user_id = ?");
    $stmt_role->bind_param("i", $user_id);
    $stmt_role->execute();
    $result_role = $stmt_role->get_result();

    if ($result_role->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["error" => "User ID $user_id not found."]);
        return;
    }
    
    $user = $result_role->fetch_assoc();
    $role = $user['user_role'];
    $schedule_table = "tbl_{$role}_schedules";
    
    $sql = "
        SELECT 
            s.schedule_time, 
            s.schedule_day, 
            s.schedule_date, 
            c.course_id, 
            c.course_name, 
            c.units,
            ? AS user_role
        FROM $schedule_table s
        JOIN tbl_courses c ON s.course_id = c.course_id
        WHERE s.user_id = ?
        ORDER BY s.schedule_day, s.schedule_time
    ";
    
    $stmt_schedule = $conn->prepare($sql);
    $stmt_schedule->bind_param("si", $role, $user_id);
    $stmt_schedule->execute();
    $result_schedule = $stmt_schedule->get_result();
    
    if ($result_schedule) {
        $schedule = [];
        while($row = $result_schedule->fetch_assoc()) {
            $schedule[] = $row;
        }
        http_response_code(200);
        echo json_encode($schedule);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Error retrieving schedule: " . $conn->error]);
    }
}

/**
 * Handles POST /courses/{user_id}/schedules
 */
function handle_create_schedule($conn, $user_id) {
    $data = json_decode(file_get_contents("php://input"), true);

    $course_id = $data['course_id'] ?? null;
    $schedule_time = $data['schedule_time'] ?? null;
    $schedule_day = $data['schedule_day'] ?? null;
    $schedule_date = $data['schedule_date'] ?? null;

    if (!$course_id || !$schedule_time || !$schedule_day) {
        http_response_code(400); 
        echo json_encode(["error" => "Missing required fields."]);
        return;
    }
    
    $stmt_role = $conn->prepare("SELECT user_role FROM tbl_users WHERE user_id = ?");
    $stmt_role->bind_param("i", $user_id);
    $stmt_role->execute();
    $result_role = $stmt_role->get_result();

    if ($result_role->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["error" => "User ID $user_id not found."]);
        return;
    }
    
    $user = $result_role->fetch_assoc();
    $role = $user['user_role'];
    $schedule_table = "tbl_{$role}_schedules";

    if ($schedule_date) {
        $sql = "INSERT INTO $schedule_table (course_id, user_id, schedule_time, schedule_day, schedule_date) VALUES (?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql);
        $stmt_insert->bind_param("iisss", $course_id, $user_id, $schedule_time, $schedule_day, $schedule_date);
    } else {
        $sql = "INSERT INTO $schedule_table (course_id, user_id, schedule_time, schedule_day) VALUES (?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql);
        $stmt_insert->bind_param("iiss", $course_id, $user_id, $schedule_time, $schedule_day);
    } 

    if ($stmt_insert->execute()) {
        http_response_code(201);
        $response = [
            "message" => "Schedule created successfully for $role",
            "schedule_id" => $conn->insert_id,
            "course_id" => $course_id
        ];
        if ($schedule_date) $response["schedule_date"] = $schedule_date;
        echo json_encode($response);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to create schedule: " . $stmt_insert->error]);
    }
}
?>