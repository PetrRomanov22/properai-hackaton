<?php
session_start();
require '../db.php';

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

// Check if project_id and photo_name are provided
if (!isset($_POST["project_id"]) || !isset($_POST["photo_name"])) {
    header("Location: ../account.php");
    exit;
}

$project_id = $_POST["project_id"];
$photo_name = $_POST["photo_name"];

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

// Sanitize filename for security (basic sanitization)
$photo_name = basename($photo_name);

// Construct the file path
$target_file = "users/{$user_id}/{$project_id}/images/{$photo_name}";

// Check if file exists
if (file_exists($target_file)) {
    // Delete the file
    if (unlink($target_file)) {
        // Successfully deleted the file
        header("Location: project.php?id=" . $project_id . "&success=photo_deleted");
    } else {
        // Failed to delete the file
        header("Location: project.php?id=" . $project_id . "&error=delete_failed");
    }
} else {
    // File not found
    header("Location: project.php?id=" . $project_id . "&error=file_not_found");
}
?> 