<?php
session_start();
require '../db.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$id = $_POST["id"];
$tilt = $_POST["tilt"] ?? 0;
$roll = $_POST["roll"] ?? 0;
$scale = $_POST["scale"] ?? 1;
$lat = $_POST["lat"] ?? null;
$lng = $_POST["lng"] ?? null;
$altitude_show = $_POST["altitude_show"] ?? null;
$altitude_fact = $_POST["altitude_fact"] ?? null;
$marker_width = $_POST["marker_width"] ?? 7;
$marker_length = $_POST["marker_length"] ?? 7;
$marker_height = $_POST["marker_height"] ?? 3.5;

// Convert user-friendly values back to actual stored values
$marker_width = $marker_width / 100000;
$marker_length = $marker_length / 100000;

$stmt = $conn->prepare("UPDATE models 
    SET tilt=?, roll=?, scale=?, lat=?, lng=?, altitude_show=?, altitude_fact=?, 
        marker_width=?, marker_length=?, marker_height=? 
    WHERE id=?");
$stmt->bind_param("ddddddddddi", $tilt, $roll, $scale, $lat, $lng, $altitude_show, $altitude_fact, 
                 $marker_width, $marker_length, $marker_height, $id);
$stmt->execute();

// Return JSON response for AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    echo json_encode(['success' => true]);
    exit;
}

// Find project_id to redirect back for traditional form submissions
$result = $conn->query("SELECT project_id FROM models WHERE id = $id");
$row = $result->fetch_assoc();

header("Location: project_map.php?id=" . $row["project_id"]);

