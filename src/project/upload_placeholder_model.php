<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require '../db.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$project_id = $_POST["project_id"] ?? 0;

// Get project details to use as default values
$project_query = $conn->prepare("SELECT lat, lng, altitude FROM projects WHERE id = ? AND user_id = ?");
$project_query->bind_param("ii", $project_id, $user_id);
$project_query->execute();
$project_result = $project_query->get_result();
$project = $project_result->fetch_assoc();

if (!$project) {
    echo "Project not found or access denied.";
    exit;
}

$default_lat = $project["lat"] ?? 0;
$default_lng = $project["lng"] ?? 0;
$default_altitude_show = $project["altitude"] ?? 0;
$default_altitude_fact = ($project["altitude"] ?? 0) - 10; // Default real altitude a bit lower

// Path to the placeholder model
$placeholder_path = "users/null.glb";

// Check if placeholder model exists
if (!file_exists($placeholder_path)) {
    echo "Placeholder model not found at: " . $placeholder_path;
    exit;
}

// Create user's project model directory
$upload_dir = "users/$user_id/$project_id/models/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename for the copied placeholder
$timestamp = date('Y-m-d_H-i-s');
$filename = "placeholder_model_" . $timestamp . ".glb";
$filepath = $upload_dir . $filename;

// Copy the placeholder model to user's directory
if (copy($placeholder_path, $filepath)) {
    // Insert model record into database
    $stmt = $conn->prepare("INSERT INTO models (
        project_id, name, file_path, tilt, roll, scale, 
        lat, lng, altitude_show, altitude_fact, uploaded_at
    ) VALUES (?, ?, ?, 0, 0, 1, ?, ?, ?, ?, NOW())");
    
    $model_name = "Placeholder Model";
    $stmt->bind_param(
        "issdddd", 
        $project_id, $model_name, $filepath, 
        $default_lat, $default_lng, $default_altitude_show, $default_altitude_fact
    );
    
    if ($stmt->execute()) {
        header("Location: project_map.php?id=" . $project_id . "&success=placeholder_uploaded");
    } else {
        echo "Failed to save model to database: " . $conn->error;
    }
} else {
    echo "Failed to copy placeholder model.";
}
?> 