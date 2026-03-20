<?php
session_start();
header('Content-Type: application/json');

// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'You must be logged in to enroll.']);
    exit();
}

// 2. Get input from the POST request
$input = json_decode(file_get_contents('php://input'), true);
$courseSlug = $input['course'] ?? null;

if (!$courseSlug) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Course not specified.']);
    exit();
}

// 3. Connect to the database
require_once 'db_connect.php';

$user_id = $_SESSION['user_id'];

// 4. Check if the user is already enrolled in this course
$stmt = $conn->prepare("SELECT id FROM user_courses WHERE user_id = ? AND course_slug = ?");
$stmt->bind_param("is", $user_id, $courseSlug);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();
    $conn->close();
    http_response_code(409); // Conflict
    echo json_encode(['success' => false, 'message' => 'You are already enrolled in this course.']);
    exit();
}
$stmt->close();

// 5. Insert the new enrollment record
$status = 'in-progress';
$progress = 0;
$stmt = $conn->prepare("INSERT INTO user_courses (user_id, course_slug, status, progress) VALUES (?, ?, ?, ?)");
$stmt->bind_param("issi", $user_id, $courseSlug, $status, $progress);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Successfully enrolled! You can start learning now.']);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Failed to enroll due to a server error.']);
}

$stmt->close();
$conn->close();
?>