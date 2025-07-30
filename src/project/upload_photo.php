<?php
session_start();
require '../db.php';

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

// Check if project_id is provided
if (!isset($_POST["project_id"])) {
    header("Location: ../account.php");
    exit;
}

$project_id = $_POST["project_id"];

// Verify the project belongs to the user
$stmt = $conn->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $project_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Project doesn't exist or doesn't belong to this user
    header("Location: ../account.php");
    exit;
}

// Check if file was uploaded
if (!isset($_FILES["photo_file"]) || $_FILES["photo_file"]["error"] !== UPLOAD_ERR_OK) {
    header("Location: project.php?id=" . $project_id . "&error=upload_failed");
    exit;
}

// Create directory if it doesn't exist
$target_dir = "users/{$user_id}/{$project_id}/images";
if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

// Get file details
$file_tmp = $_FILES["photo_file"]["tmp_name"];
$file_name = $_FILES["photo_file"]["name"];
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

// Check if file is an image
$allowed_extensions = ["jpg", "jpeg", "png", "gif"];
if (!in_array($file_ext, $allowed_extensions)) {
    header("Location: project.php?id=" . $project_id . "&error=invalid_file_type");
    exit;
}

// Generate a unique filename to prevent overwriting
$new_file_name = uniqid() . "_" . $file_name;
$target_file = $target_dir . "/" . $new_file_name;

// Upload the file
if (move_uploaded_file($file_tmp, $target_file)) {
    // Successfully uploaded the file
    header("Location: project.php?id=" . $project_id . "&success=photo_uploaded");
} else {
    // Failed to upload the file
    header("Location: project.php?id=" . $project_id . "&error=upload_failed");
}
?> 