<?php
session_start();
header('Content-Type: application/json');

// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// 2. Get input
$input = json_decode(file_get_contents('php://input'), true);
$courseSlug = $input['course_slug'] ?? null;
$progress = $input['progress'] ?? null;

if (!$courseSlug || !is_numeric($progress)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
    exit();
}

// 3. Connect to DB
require_once 'db_connect.php';
$user_id = $_SESSION['user_id'];

// 4. Sanitize and validate progress value
$progress = max(0, min(100, intval($progress)));

// 5. Determine status based on progress
$status = ($progress >= 100) ? 'completed' : 'in-progress';

// 6. Update the database
if ($status === 'completed') {
    // Set completion_date only if it's not already set.
    // NOTE: This requires the `completion_date` DATE column in your `user_courses` table.
    $stmt = $conn->prepare(
        "UPDATE user_courses SET progress = ?, status = ?, completion_date = IF(completion_date IS NULL, CURDATE(), completion_date) WHERE user_id = ? AND course_slug = ?"
    );
} else {
    $stmt = $conn->prepare(
        "UPDATE user_courses SET progress = ?, status = ? WHERE user_id = ? AND course_slug = ?"
    );
}
$stmt->bind_param("isis", $progress, $status, $user_id, $courseSlug);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Progress updated.', 'new_status' => $status]);
    } else {
        // This can happen if the user isn't enrolled, but tried to update progress.
        echo json_encode(['success' => false, 'message' => 'No enrollment found to update.']);
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error while updating progress.']);
}

$stmt->close();
$conn->close();
?>