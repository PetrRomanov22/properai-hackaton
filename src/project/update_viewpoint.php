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

if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_POST["viewpoint_id"])) {
    header("Location: ../account.php");
    exit;
}

$viewpoint_id = $_POST["viewpoint_id"];

// Verify that the viewpoint belongs to a project owned by the current user
$verify_stmt = $conn->prepare("
    SELECT v.* 
    FROM viewpoints v
    JOIN projects p ON v.project_id = p.id
    WHERE v.id = ? AND p.user_id = ?
");
$verify_stmt->bind_param("ii", $viewpoint_id, $user_id);
$verify_stmt->execute();
$result = $verify_stmt->get_result();

if ($result->num_rows === 0) {
    echo "Unauthorized access or viewpoint not found.";
    exit;
}

$viewpoint = $result->fetch_assoc();
$project_id = $viewpoint["project_id"];

// Get the updated values from form
$lat = $_POST["lat"];
$lng = $_POST["lng"];
$altitude = $_POST["altitude"];
$tilt = $_POST["tilt"];
$heading = $_POST["heading"];
$range = $_POST["range"];

// Update the viewpoint in the database
$update_stmt = $conn->prepare("
    UPDATE viewpoints
    SET lat = ?, lng = ?, altitude = ?, tilt = ?, heading = ?, range_value = ?
    WHERE id = ?
");
$update_stmt->bind_param("ddddddi", $lat, $lng, $altitude, $tilt, $heading, $range, $viewpoint_id);

if ($update_stmt->execute()) {
    header("Location: project_map.php?id=$project_id");
} else {
    echo "Error updating viewpoint: " . $conn->error;
}

$conn->close();
?> 