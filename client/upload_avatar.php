<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'db_connect.php';
$user_id = $_SESSION['user_id'];
$message = '';
$message_type = 'error';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['avatar'])) {
    $target_dir = "../uploads/avatars/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $file = $_FILES['avatar'];
    $file_name = basename($file['name']);
    $image_file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Create a unique filename to prevent conflicts
    $new_file_name = "user_" . $user_id . "_" . time() . "." . $image_file_type;
    $target_file = $target_dir . $new_file_name;

    // 1. Check if image file is an actual image
    $check = getimagesize($file["tmp_name"]);
    if ($check === false) {
        $message = "File is not an image.";
    } 
    // 2. Check file size (e.g., 2MB limit)
    elseif ($file["size"] > 2000000) {
        $message = "Sorry, your file is too large. Max 2MB.";
    } 
    // 3. Allow certain file formats
    elseif (!in_array($image_file_type, ['jpg', 'png', 'jpeg', 'gif'])) {
        $message = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
    } 
    // If all checks pass, try to upload
    else {
        // Before uploading, remove the old avatar if it exists
        $stmt_old = $conn->prepare("SELECT avatar_path FROM users WHERE id = ?");
        $stmt_old->bind_param("i", $user_id);
        $stmt_old->execute();
        $result_old = $stmt_old->get_result();
        if ($row_old = $result_old->fetch_assoc()) {
            if (!empty($row_old['avatar_path']) && file_exists($row_old['avatar_path'])) {
                @unlink($row_old['avatar_path']);
            }
        }
        $stmt_old->close();

        if (move_uploaded_file($file["tmp_name"], $target_file)) {
            // Update database with new avatar path
            $stmt = $conn->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
            $stmt->bind_param("si", $target_file, $user_id);
            if ($stmt->execute()) {
                $message = "Your avatar has been updated successfully.";
                $message_type = 'success';
            } else {
                $message = "Failed to update avatar in database.";
            }
            $stmt->close();
        } else {
            $message = "Sorry, there was an error uploading your file.";
        }
    }
} else {
    $message = "No file was uploaded.";
}

$conn->close();

// Redirect back to profile page with a status message
$_SESSION['profile_message'] = $message;
$_SESSION['profile_message_type'] = $message_type;
header("Location: profile.php");
exit();
?>

