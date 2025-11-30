<?php
// ===============================================
// Course Microservice API Entry Point
// ===============================================

// 1. CONFIGURATION
// !!! IMPORTANT: CHANGE THESE DATABASE CREDENTIALS !!!
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // <-- UPDATE THIS
define('DB_NAME', 'course_db');

// Set the API base URL for internal routing (assuming /api/v1 is the start)
$base_uri = '/api/v1';

// 2. SET HEADERS
// Allow cross-origin requests (crucial for microservices and frontend interaction)
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Handle pre-flight OPTIONS requests often sent by browsers/Postman
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
// Get the requested URI path
$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// Remove the base path (/course-service/api/v1)
$base_path = 'course-service/api/v1';
if (strpos($uri, $base_path) === 0) {
    $uri = substr($uri, strlen($base_path));
}

$uri = trim($uri, '/');
$uri_segments = array_filter(explode('/', $uri));
$uri_segments = array_values($uri_segments);

// Determine the primary resource (e.g., 'courses', 'schedules')
$resource = $uri_segments[0] ?? '';
$id = $uri_segments[1] ?? null;


// ===============================================
// RESOURCE: /courses (tbl_courses)
// ===============================================

if ($resource === 'courses') {
    if ($request_method === 'GET') {
        // GET /courses -> Get all courses
        if ($id === null) {
            handle_get_all_courses($conn);
        } 
        // GET /courses/{id} -> Get specific course
        else {
            handle_get_single_course($conn, $id);
        }
    } 
    
    else if ($request_method === 'POST') {
        // POST /courses -> Create a new course
        handle_create_course($conn);
    }
    
    // --- Placeholder for other methods ---
    
    else if ($request_method === 'PUT' && $id !== null) {
        // PUT /courses/{id} -> Update a course
        http_response_code(501); // Not Implemented
        echo json_encode(["message" => "PUT /courses/{id} not yet implemented."]);
    }
    
    else if ($request_method === 'DELETE' && $id !== null) {
        // DELETE /courses/{id} -> Delete a course
        http_response_code(501); // Not Implemented
        echo json_encode(["message" => "DELETE /courses/{id} not yet implemented."]);
    }

} 

// --- Placeholder for other resources ---
else if ($resource === 'schedules') {
    http_response_code(501);
    echo json_encode(["message" => "Schedule resource routing not yet implemented."]);
}

else {
    // If no resource matches
    http_response_code(404);
    echo json_encode(["error" => "Resource not found"]);
}

$conn->close();


// ===============================================
// API FUNCTION IMPLEMENTATIONS
// ===============================================

/**
 * Handles GET /courses
 * Fetches all courses from tbl_courses.
 */
function handle_get_all_courses($conn) {
    $sql = "SELECT course_id, course_name, units, departments FROM tbl_courses";
    $result = $conn->query($sql);
    
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
 * Fetches a single course from tbl_courses.
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
 * Creates a new course in tbl_courses.
 */
function handle_create_course($conn) {
    // Read JSON data from the request body
    $data = json_decode(file_get_contents("php://input"), true);

    // Basic input validation
    $course_name = $data['course_name'] ?? null;
    $units = $data['units'] ?? null;
    $departments = $data['departments'] ?? null;

    if (!$course_name || !is_numeric($units) || !$departments) {
        http_response_code(400); // Bad Request
        echo json_encode(["error" => "Missing or invalid data: course_name, units (number), and departments are required."]);
        return;
    }

    // Use prepared statements to prevent SQL injection (ALWAYS use this!)
    $stmt = $conn->prepare("INSERT INTO tbl_courses (course_name, units, departments) VALUES (?, ?, ?)");
    $stmt->bind_param("sis", $course_name, $units, $departments); // s=string, i=integer

    if ($stmt->execute()) {
        http_response_code(201); // 201 Created
        echo json_encode([
            "message" => "Course created successfully",
            "course_id" => $conn->insert_id,
            "course_name" => $course_name
        ]);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(["error" => "Failed to create course: " . $stmt->error]);
    }
}
?>