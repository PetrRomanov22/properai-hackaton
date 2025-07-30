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

if (!isset($_FILES["model_file"])) {
    echo "No file uploaded.";
    exit;
}

// Get project details to use as default values
$project_query = $conn->prepare("SELECT lat, lng, altitude FROM projects WHERE id = ?");
$project_query->bind_param("i", $project_id);
$project_query->execute();
$project_result = $project_query->get_result();
$project = $project_result->fetch_assoc();

$default_lat = $project["lat"] ?? 0;
$default_lng = $project["lng"] ?? 0;
$default_altitude_show = $project["altitude"] ?? 0;
$default_altitude_fact = ($project["altitude"] ?? 0) - 10; // Default real altitude a bit lower

$upload_dir = "users/$user_id/$project_id/models/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$filename = basename($_FILES["model_file"]["name"]);
$filepath = $upload_dir . $filename;

// Get file format and size
$file_info = pathinfo($filename);
$format = strtolower($file_info['extension'] ?? '');
$size_mb = $_FILES["model_file"]["size"] / (1024 * 1024); // Convert bytes to MB

if (move_uploaded_file($_FILES["model_file"]["tmp_name"], $filepath)) {
    $stmt = $conn->prepare("INSERT INTO models (
        project_id, name, file_path, format, size_mb, tilt, roll, scale, 
        lat, lng, altitude_show, altitude_fact, uploaded_at
    ) VALUES (?, ?, ?, ?, ?, 0, 0, 1, ?, ?, ?, ?, NOW())");
    
    $stmt->bind_param(
        "isssddddd", 
        $project_id, $filename, $filepath, $format, $size_mb,
        $default_lat, $default_lng, $default_altitude_show, $default_altitude_fact
    );
    $stmt->execute();

    header("Location: project_map.php?id=" . $project_id);
} else {
    echo "Failed to upload file.";
}
?>
