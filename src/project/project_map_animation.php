<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require '../db.php';

// Define secure access constant
define('SECURE_ACCESS', true);
// API key is now fetched from users table instead of api_config.php

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$project_id = $_GET["id"] ?? 0;

// Fetch Google Maps API key from users table
$api_key_stmt = $conn->prepare("SELECT google_maps_api_key FROM users WHERE id = ?");
$api_key_stmt->bind_param("i", $user_id);
$api_key_stmt->execute();
$api_key_result = $api_key_stmt->get_result();
$user_data = $api_key_result->fetch_assoc();
$GOOGLE_MAPS_API_KEY = $user_data['google_maps_api_key'] ?? '';

// Handle project update if form is submitted
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_project"])) {
    $name = $_POST["name"];
    $description = $_POST["description"];
    $address = $_POST["address"];
    $country = $_POST["country"];
    $city = $_POST["city"];
    $lat = $_POST["lat"];
    $lng = $_POST["lng"];
    $altitude = $_POST["altitude"];
    $camera_range = $_POST["camera_range"];
    $camera_tilt = $_POST["camera_tilt"];
    $camera_heading = $_POST["camera_heading"];
    $size = $_POST["size"];
    $size_unit = $_POST["size_unit"];
    $price = $_POST["price"];
    $currency = $_POST["currency"];
    $floor = $_POST["floor"];
    $bedrooms = $_POST["bedrooms"];
    $type = $_POST["type"];

    $stmt = $conn->prepare("UPDATE projects SET 
        name=?, description=?, address=?, country=?, city=?, 
        lat=?, lng=?, altitude=?, camera_range=?, camera_tilt=?, camera_heading=?,
        size=?, size_unit=?, price=?, currency=?, floor=?, bedrooms=?, type=?
        WHERE id=? AND user_id=?");
    $stmt->bind_param("sssssdddddssssiisii", $name, $description, $address, $country, $city,
        $lat, $lng, $altitude, $camera_range, $camera_tilt, $camera_heading,
        $size, $size_unit, $price, $currency, $floor, $bedrooms, $type, 
        $project_id, $user_id);
    $stmt->execute();
}

// Handle active model update via AJAX
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_active_model"])) {
    $active_model_id = $_POST["model_id"];
    
    $stmt = $conn->prepare("UPDATE projects SET active_model_id=? WHERE id=? AND user_id=?");
    $stmt->bind_param("iii", $active_model_id, $project_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => $conn->error]);
    }
    exit;
}

// Handle save viewpoint via AJAX
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_viewpoint"])) {
    $name = $_POST["name"] ?? "Viewpoint " . time();
    $lat = $_POST["lat"];
    $lng = $_POST["lng"];
    $altitude = $_POST["altitude"];
    $tilt = $_POST["tilt"];
    $heading = $_POST["heading"];
    $range = $_POST["range"];
    
    $stmt = $conn->prepare("INSERT INTO viewpoints (project_id, name, lat, lng, altitude, tilt, heading, range_value) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isdddddd", $project_id, $name, $lat, $lng, $altitude, $tilt, $heading, $range);
    
    if ($stmt->execute()) {
        $viewpoint_id = $conn->insert_id;
        echo json_encode([
            "success" => true, 
            "viewpoint" => [
                "id" => $viewpoint_id,
                "name" => $name,
                "lat" => $lat,
                "lng" => $lng,
                "altitude" => $altitude,
                "tilt" => $tilt,
                "heading" => $heading,
                "range" => $range
            ]
        ]);
    } else {
        echo json_encode(["success" => false, "error" => $conn->error]);
    }
    exit;
}

// Handle delete viewpoint via AJAX
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_viewpoint"])) {
    $viewpoint_id = $_POST["viewpoint_id"];
    
    $stmt = $conn->prepare("DELETE FROM viewpoints WHERE id=? AND project_id=?");
    $stmt->bind_param("ii", $viewpoint_id, $project_id);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => $conn->error]);
    }
    exit;
}

// Handle update viewpoint via AJAX
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_viewpoint"])) {
    $viewpoint_id = $_POST["viewpoint_id"];
    $name = $_POST["name"] ?? "Viewpoint " . time();
    $lat = $_POST["lat"];
    $lng = $_POST["lng"];
    $altitude = $_POST["altitude"];
    $tilt = $_POST["tilt"];
    $heading = $_POST["heading"];
    $range = $_POST["range"];
    
    $stmt = $conn->prepare("UPDATE viewpoints SET name=?, lat=?, lng=?, altitude=?, tilt=?, heading=?, range_value=? 
                           WHERE id=? AND project_id=?");
    $stmt->bind_param("sddddddii", $name, $lat, $lng, $altitude, $tilt, $heading, $range, $viewpoint_id, $project_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            "success" => true, 
            "viewpoint" => [
                "id" => $viewpoint_id,
                "name" => $name,
                "lat" => $lat,
                "lng" => $lng,
                "altitude" => $altitude,
                "tilt" => $tilt,
                "heading" => $heading,
                "range" => $range
            ]
        ]);
    } else {
        echo json_encode(["success" => false, "error" => $conn->error]);
    }
    exit;
}

// Fetch the project
$stmt = $conn->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $project_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$project = $result->fetch_assoc();

if (!$project) {
    echo "Project not found.";
    exit;
}

// Fetch models for this project
$model_stmt = $conn->prepare("SELECT * FROM models WHERE project_id = ?");
$model_stmt->bind_param("i", $project_id);
$model_stmt->execute();
$models = $model_stmt->get_result();
$modelsList = [];
while ($model = $models->fetch_assoc()) {
    $modelsList[] = $model;
}
// Reset the pointer for later use
$models->data_seek(0);

// Fetch viewpoints for this project
$viewpoint_stmt = $conn->prepare("SELECT * FROM viewpoints WHERE project_id = ? ORDER BY created_at DESC");
$viewpoint_stmt->bind_param("i", $project_id);
$viewpoint_stmt->execute();
$viewpoints_result = $viewpoint_stmt->get_result();
$viewpointsList = [];
while ($viewpoint = $viewpoints_result->fetch_assoc()) {
    $viewpointsList[] = $viewpoint;
}
// Reset the pointer for later use
$viewpoints_result->data_seek(0);

// Get active model ID from project
$active_model_id = $project["active_model_id"];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Project: <?= htmlspecialchars($project["name"]) ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="project_map.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>
<body>
    <div class="container">
        
        <div class="project-content">
            <!-- Form that wraps both sidebar and 3D Map Parameters -->
            <!-- Left sidebar for navigation only -->
            <div class="project-sidebar">
                <h2>Project: <?= htmlspecialchars($project["name"]) ?></h2>
                <p><a href="../account.php" class="sidebar-nav-btn">← Back to My Projects</a></p>
                <br>
                <h2>Project</h2>
                <p><a href="project.php?id=<?= $project_id ?>" class="sidebar-nav-btn">Project →</a></p>
                <br>
                <h2>3D map</h2>
                <p><a href="project_map.php?id=<?= $project_id ?>" class="sidebar-nav-btn">🗺️ Edit 3d map →</a></p>
                <p><a href="project_map_animation.php?id=<?= $project_id ?>" class="sidebar-nav-btn active">🎬 Animation →</a></p>
                <br>
                <h2>Share your apartment</h2>
                <p><a href="project_share.php?id=<?= $project_id ?>" class="sidebar-nav-btn">Preview</a></p>
            </div>
            
            <!-- Hidden form for project updates (separate from sidebar) -->
            <form method="POST" id="updateProjectForm" style="display: none;">
                <input type="hidden" name="update_project" value="1">
                <input type="hidden" name="name" value="<?= htmlspecialchars($project["name"]) ?>">
                <input type="hidden" name="description" value="<?= htmlspecialchars($project["description"]) ?>">
                <input type="hidden" name="address" value="<?= htmlspecialchars($project["address"]) ?>">
                <input type="hidden" name="country" value="<?= htmlspecialchars($project["country"]) ?>">
                <input type="hidden" name="city" value="<?= htmlspecialchars($project["city"]) ?>">
                <input type="hidden" name="size" value="<?= htmlspecialchars($project["size"] ?? '') ?>">
                <input type="hidden" name="size_unit" value="<?= htmlspecialchars($project["size_unit"] ?? 'sq.m') ?>">
                <input type="hidden" name="price" value="<?= htmlspecialchars($project["price"] ?? '') ?>">
                <input type="hidden" name="currency" value="<?= htmlspecialchars($project["currency"] ?? 'USD') ?>">
                <input type="hidden" name="floor" value="<?= htmlspecialchars($project["floor"] ?? '') ?>">
                <input type="hidden" name="bedrooms" value="<?= htmlspecialchars($project["bedrooms"] ?? '') ?>">
                <input type="hidden" name="type" value="<?= htmlspecialchars($project["type"] ?? '') ?>">
            </form>
            
            <div class="map-container">
                <div id="map"></div>
                
                <button class="map-control-btn fly-around-btn" id="flyAroundBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24">
                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                    </svg>
                    Fly Around
                </button>
                <button class="map-control-btn reset-tilt-btn" id="resetTiltBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24">
                        <path d="M12 5.83L15.17 9l1.41-1.41L12 3 7.41 7.59 8.83 9 12 5.83zm0 12.34L8.83 15l-1.41 1.41L12 21l4.59-4.59L15.17 15 12 18.17z"/>
                    </svg>
                    Reset Tilt
                </button>
                <button class="map-control-btn return-to-model-btn" id="returnToModelBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                    </svg>
                    Center on Model
                </button>
                
                <button id="place" class="control-button" style="left: 10px; top: 10px;">Place 🏠</button>
                <button id="up" class="control-button" style="left: 10px; top: 50px;">Up 🔼</button>
                <button id="down" class="control-button" style="left: 10px; top: 90px;">Down 🔽</button>
                <button id="forward" class="control-button" style="left: 10px; top: 130px;">Forward ⬆</button>
                <button id="back" class="control-button" style="left: 10px; top: 170px;">Back ⬇</button>
                <button id="left" class="control-button" style="left: 10px; top: 210px;">Left ⬅</button>
                <button id="right" class="control-button" style="left: 10px; top: 250px;">Right ➡</button>
                <button id="roll1" class="control-button" style="left: 10px; top: 290px;">Roll+ ↩</button>
                <button id="roll2" class="control-button" style="left: 10px; top: 330px;">Roll- ↪</button>
                <button id="tilt1" class="control-button" style="left: 10px; top: 370px;">Tilt+ ⤴</button>
                <button id="tilt2" class="control-button" style="left: 10px; top: 410px;">Tilt- ⤵</button>
                <button id="addViewpoint" class="control-button add-viewpoint-btn">Add Viewpoint 📸</button>
                
                <!-- Mobile Recording Frame Overlay -->
                <div id="mobileRecordingFrame" style="
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    width: 475px;
                    height: 844px;
                    border: 3px solid #007bff;
                    border-radius: 25px;
                    pointer-events: none;
                    z-index: 1000;
                    display: block;
                    box-shadow: 0 0 20px rgba(0, 123, 255, 0.5);
                    opacity: 0.7;
                    transition: opacity 0.3s ease, box-shadow 0.3s ease;
                ">
                    <div style="
                        position: absolute;
                        top: -35px;
                        left: 50%;
                        transform: translateX(-50%);
                        background: #007bff;
                        color: white;
                        padding: 5px 15px;
                        border-radius: 15px;
                        font-size: 12px;
                        font-weight: bold;
                    ">Mobile Recording Area</div>
                </div>
                <!-- Standard Recording Frame Overlay -->
                <div id="standardRecordingFrame" style="
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    width: 848px;
                    height: 636px;
                    border: 3px solid #007bff;
                    border-radius: 25px;
                    pointer-events: none;
                    z-index: 1000;
                    display: block;
                    box-shadow: 0 0 20px rgba(0, 123, 255, 0.5);
                    opacity: 0.7;
                    transition: opacity 0.3s ease, box-shadow 0.3s ease;
                ">
                    <div style="
                        position: absolute;
                        top: -35px;
                        left: 50%;
                        transform: translateX(-50%);
                        background: #007bff;
                        color: white;
                        padding: 5px 15px;
                        border-radius: 15px;
                        font-size: 12px;
                        font-weight: bold;
                    ">Standard Recording Area</div>
                </div>
            </div>
            
            <div class="project-details">
                <!-- Frame Rate Section -->
                <div class="model-form">
                    <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 15px;">Frame Rate</h3>
                    <div class="frame-selection-container">
                        <button id="mobileFrame" class="frame-button">Mobile frame (16:9)</button>
                        <button id="standardFrame" class="frame-button">Standard frame (4:3)</button>
                    </div>
                </div>
                <!-- Video Recording Section -->
                <div class="model-form">
                    <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 15px;">Video Recording</h3>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <button id="screenShot" class="recording-button">Screen Shot 📸</button>
                        <button id="startRecording" class="recording-button">Start Mobile Recording 🔴</button>
                        <button id="stopRecording" class="recording-button" style="display: none;">Stop Recording ⏹️</button>
                        <a href="list_videos.php?project_id=<?= $project_id ?>" class="recording-button" style="text-decoration: none;">View Videos & Screenshots 📹</a>
                        <div id="recordingIndicator" style="display: none;">
                            🔴 RECORDING
                        </div>
                        
                    </div>
                </div>

                <!-- 3D Map Parameters Section - now separate from the form -->
                <div class="model-form">
                    <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 15px;">3D Map Parameters</h3>
                    <div><label>Latitude: <input type="number" class="model-form" id="map-lat" style="width: 100%;" value="<?= $project["lat"] ?>"></label></div>
                    <div style="margin-top: 10px;"><label>Longitude: <input type="number" class="model-form" id="map-lng" style="width: 100%;" value="<?= $project["lng"] ?>"></label></div>
                    <div style="margin-top: 10px;"><label>Altitude: <input type="number" class="model-form" id="map-altitude" style="width: 100%;" value="<?= $project["altitude"] ?>"></label></div>
                    <div style="margin-top: 10px;"><label>Camera Range: <input type="number" class="model-form" id="map-camera-range" style="width: 100%;" value="<?= $project["camera_range"] ?>"></label></div>
                    <div style="margin-top: 10px;"><label>Camera Tilt: <input type="number" class="model-form" id="map-camera-tilt" style="width: 100%;" value="<?= $project["camera_tilt"] ?>"></label></div>
                    <div style="margin-top: 10px;"><label>Camera Heading: <input type="number" class="model-form" id="map-camera-heading" style="width: 100%;" value="<?= $project["camera_heading"] ?? 0 ?>"></label></div>
                    <div style="margin-top: 15px;">
                        <button type="button" id="save-map-params" class="btn btn-primary">Save to Project</button>
                        <button type="button" id="use-current-camera" class="btn btn-primary">Use Current Camera</button>
                    </div>
                </div>

                <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 15px;">Models</h3>
                <div class="models-section">
                    <?php if(mysqli_num_rows($models) == 0): ?>
                    <p>No models uploaded yet.</p>
                    <?php else: ?>
                        <?php foreach ($modelsList as $model): ?>
                        <div class="model-item">
                            <form action="update_model.php" method="POST" class="model-form model-update-form">
                                <input type="hidden" name="id" value="<?= $model['id'] ?>">
                                <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                    <input type="checkbox" class="model-selector" id="model_<?= $model['id'] ?>" 
                                           data-model-id="<?= $model['id'] ?>" 
                                           data-file-path="<?= htmlspecialchars($model['file_path']) ?>"
                                           data-lat="<?= $model['lat'] ? $model['lat'] : $project['lat'] ?>"
                                           data-lng="<?= $model['lng'] ? $model['lng'] : $project['lng'] ?>"
                                           data-altitude-show="<?= $model['altitude_show'] ? $model['altitude_show'] : $project['altitude'] ?>"
                                           data-altitude-fact="<?= $model['altitude_fact'] ? $model['altitude_fact'] : ($project['altitude'] - 10) ?>"
                                           data-roll="<?= $model['roll'] ?>"
                                           data-tilt="<?= $model['tilt'] ?>"
                                           data-scale="<?= $model['scale'] ?>"
                                           data-marker-width="<?= $model['marker_width'] ? $model['marker_width'] : '0.00007' ?>"
                                           data-marker-length="<?= $model['marker_length'] ? $model['marker_length'] : '0.00007' ?>"
                                           data-marker-height="<?= $model['marker_height'] ? $model['marker_height'] : '3.5' ?>"
                                           <?= ($active_model_id && $active_model_id == $model['id']) || (!$active_model_id && $model === reset($modelsList)) ? 'checked' : '' ?>>
                                    <h4 style="margin-left: 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 15px;"><?= htmlspecialchars($model['name'] ?: 'Unnamed Model') ?></h4>
                                </div>
                                <p><small><?= htmlspecialchars($model['file_path']) ?></small></p>
                                <div><label>Roll: <input type="number" name="roll" style="width: 100%;" value="<?= $model['roll'] ?>"></label></div>
                                <div style="margin-top: 10px;"><label>Tilt: <input type="number" name="tilt" style="width: 100%;" value="<?= $model['tilt'] ?>"></label></div>
                                <div style="margin-top: 10px;"><label>Scale: <input type="number" name="scale" step="0.01" style="width: 100%;" value="<?= $model['scale'] ?>"></label></div>
                                <div style="margin-top: 10px;"><label>Latitude: <input type="number" name="lat" step="any" style="width: 100%;" value="<?= $model['lat'] ? $model['lat'] : $project['lat'] ?>"></label></div>
                                <div style="margin-top: 10px;"><label>Longitude: <input type="number" name="lng" step="any" style="width: 100%;" value="<?= $model['lng'] ? $model['lng'] : $project['lng'] ?>"></label></div>
                                <div style="margin-top: 10px;"><label>Altitude (shown): <input type="number" name="altitude_show" step="0.01" style="width: 100%;" value="<?= $model['altitude_show'] ? $model['altitude_show'] : $project['altitude'] ?>"></label></div>
                                <div style="margin-top: 10px;"><label>Altitude (real): <input type="number" name="altitude_fact" step="0.01" style="width: 100%;" value="<?= $model['altitude_fact'] ? $model['altitude_fact'] : ($project['altitude'] - 10) ?>"></label></div>
                                
                                <h4 style="margin-top: 15px; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Marker Size Adjustments</h4>
                                <div style="margin-top: 10px;"><label>Marker Width: <input type="number" name="marker_width" step="0.00001" style="width: 100%;" value="<?= $model['marker_width'] ? $model['marker_width'] : '0.00007' ?>"></label></div>
                                <div style="margin-top: 10px;"><label>Marker Length: <input type="number" name="marker_length" step="0.00001" style="width: 100%;" value="<?= $model['marker_length'] ? $model['marker_length'] : '0.00007' ?>"></label></div>
                                <div style="margin-top: 10px;"><label>Marker Height: <input type="number" name="marker_height" step="0.5" style="width: 100%;" value="<?= $model['marker_height'] ? $model['marker_height'] : '3.5' ?>"></label></div>
                                
                                <div style="margin-top: 15px;">
                                    <button type="submit" class="btn btn-primary save-model-btn">Save</button>
                                    <button type="button" class="delete-model-btn" data-model-id="<?= $model['id'] ?>" data-file-path="<?= htmlspecialchars($model['file_path']) ?>">Delete Model</button>
                                </div>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 15px;">Viewpoints</h3>
                <div class="viewpoints-section">
                    <?php if(mysqli_num_rows($viewpoints_result) == 0): ?>
                    <p>No viewpoints saved yet. Click the "Add Viewpoint" button to save the current camera position.</p>
                    <?php else: ?>
                        <?php while ($viewpoint = $viewpoints_result->fetch_assoc()): ?>
                        <div class="viewpoint-item">
                            <form>
                                <input type="hidden" name="viewpoint_id" value="<?= $viewpoint['id'] ?>">
                                <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                    <input type="checkbox" class="viewpoint-selector" id="viewpoint_<?= $viewpoint['id'] ?>" 
                                           data-viewpoint-id="<?= $viewpoint['id'] ?>" 
                                           data-lat="<?= $viewpoint['lat'] ?>"
                                           data-lng="<?= $viewpoint['lng'] ?>"
                                           data-altitude="<?= $viewpoint['altitude'] ?>"
                                           data-tilt="<?= $viewpoint['tilt'] ?>"
                                           data-heading="<?= $viewpoint['heading'] ?>"
                                           data-range="<?= $viewpoint['range_value'] ?>"
                                           <?= ($active_model_id && $active_model_id == $viewpoint['id']) || (!$active_model_id && $viewpoint === reset($viewpointsList)) ? 'checked' : '' ?>>
                                    <h4 style="margin-left: 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 15px;"><?= htmlspecialchars($viewpoint['name'] ?: 'Unnamed Viewpoint') ?></h4>
                                </div>
                                <div><label>Latitude: <input name="lat" style="width: 100%;" value="<?= $viewpoint['lat'] ?>"></label></div>
                                <div style="margin-top: 10px;"><label>Longitude: <input name="lng" style="width: 100%;" value="<?= $viewpoint['lng'] ?>"></label></div>
                                <div style="margin-top: 10px;"><label>Altitude: <input name="altitude" style="width: 100%;" value="<?= $viewpoint['altitude'] ?>"></label></div>
                                <div style="margin-top: 10px;"><label>Tilt: <input name="tilt" style="width: 100%;" value="<?= $viewpoint['tilt'] ?>"></label></div>
                                <div style="margin-top: 10px;"><label>Heading: <input name="heading" style="width: 100%;" value="<?= $viewpoint['heading'] ?>"></label></div>
                                <div style="margin-top: 10px;"><label>Range: <input name="range" style="width: 100%;" value="<?= $viewpoint['range_value'] ?>"></label></div>
                                <div style="margin-top: 15px;">
                                    <button type="button" class="go-to-viewpoint-btn" data-viewpoint-id="<?= $viewpoint['id'] ?>">Go to View</button>
                                    <button type="button" class="use-current-camera-btn" data-viewpoint-id="<?= $viewpoint['id'] ?>">Use Current Camera</button>
                                    <button type="button" class="delete-viewpoint-btn" data-viewpoint-id="<?= $viewpoint['id'] ?>">Delete Viewpoint</button>
                                </div>
                            </form>
                        </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script async defer>
        (g => { var h, a, k, p = "The Google Maps JavaScript API", c = "google", l = "importLibrary", q = "__ib__", m = document, b = window; b = b[c] || (b[c] = {}); var d = b.maps || (b.maps = {}), r = new Set, e = new URLSearchParams, u = () => h || (h = new Promise(async (f, n) => { await (a = m.createElement("script")); e.set("libraries", [...r] + ""); for (k in g) e.set(k.replace(/[A-Z]/g, t => "_" + t[0].toLowerCase()), g[k]); e.set("callback", c + ".maps." + q); a.src = `https://maps.${c}apis.com/maps/api/js?` + e; d[q] = f; a.onerror = () => h = n(Error(p + " could not load.")); a.nonce = m.querySelector("script[nonce]")?.nonce || ""; m.head.append(a) })); d[l] ? console.warn(p + " only loads once. Ignoring:", g) : d[l] = (f, ...n) => r.add(f) && u().then(() => d[l](f, ...n)) })({
            key: atob("<?php echo base64_encode($GOOGLE_MAPS_API_KEY); ?>"),
            v: "beta",
        });
    </script>
    
    <script>
        // Project data from PHP
        const projectData = {
            lat: <?= $project["lat"] ?? 0 ?>,
            lng: <?= $project["lng"] ?? 0 ?>,
            altitude: <?= $project["altitude"] ?? 0 ?>,
            camera_range: <?= $project["camera_range"] ?? 200 ?>,
            camera_tilt: <?= $project["camera_tilt"] ?? 45 ?>,
            camera_heading: <?= $project["camera_heading"] ?? 0 ?>,
            price: <?= $project["price"] ?? 0 ?>,
            currency: "<?= $project["currency"] ?? 'USD' ?>"
        };
        
        // Function to format price for display
        function formatPrice(currency, price) {
            const currencySymbols = {
                'USD': '$',
                'EUR': '€',
                'GBP': '£',
                'JPY': '¥',
                'CNY': '¥'
            };
            
            // Get symbol if available, otherwise use the currency code
            const symbol = currencySymbols[currency] || currency;
            
            // Format large numbers more compactly
            if (price >= 1000000) {
                return `${symbol}${(price / 1000000).toFixed(1)}M`;
            } else if (price >= 1000) {
                return `${symbol}${(price / 1000).toFixed(0)}K`;
            } else {
                return `${symbol}${price}`;
            }
        }
        
        // Models data from PHP
        const modelsData = <?= json_encode($modelsList) ?>;
        
        // Viewpoints data from PHP
        const viewpointsData = <?= json_encode($viewpointsList) ?>;
        
        // Initialize global variables
        let map;
        let model;
        let viewOrientation;
        let modelPosition;
        let altitudeFact;
        let altitudeShow;
        let modelOrientation;
        let isAnimating = false;
        let animationFrameId = null;
        let isFlyingThrough = false;
        let flyThroughTimeoutId = null;
        let savedViewpoints = [];
        let viewpointCount = 0;
        let activeModel = null;
        let loadedModels = {}; // Store references to loaded models
        let highlightPolygon = null; // Store reference to highlight polygon
        
        async function init() {
            const { Map3DElement, MapMode, Model3DElement, Marker3DInteractiveElement } = await google.maps.importLibrary("maps3d");
            const { PinElement } = await google.maps.importLibrary("marker");
            
            // Initialize map view based on project data
            viewOrientation = {
                lat: projectData.lat, 
                lng: projectData.lng, 
                altitude: projectData.altitude
            };
            
            // Create the map
            map = new Map3DElement({
                center: viewOrientation,
                range: projectData.camera_range,
                tilt: projectData.camera_tilt,
                heading: projectData.camera_heading,
                mode: MapMode.SATELLITE
            });
            
            document.getElementById('map').appendChild(map);
            
            // Add event listeners to model checkboxes
            setupModelSelectors(Model3DElement);
            
            // Setup control buttons
            setupControlButtons();
            
            // Add click event listener to the map to stop animation
            map.addEventListener('click', () => {
                if (isAnimating) {
                    stopAnimation();
                }
            });
            
            // Add touch event listeners for mobile devices
            map.addEventListener('touchstart', () => {
                if (isAnimating) {
                    stopAnimation();
                }
                
                if (isFlyingThrough) {
                    isFlyingThrough = false;
                    if (flyThroughTimeoutId) {
                        clearTimeout(flyThroughTimeoutId);
                        flyThroughTimeoutId = null;
                    }
                }
            });
            
            // Load viewpoints from database
            loadViewpointsFromDatabase();
        }
        
        // Function to set up model selector checkboxes
        function setupModelSelectors(Model3DElement) {
            const modelSelectors = document.querySelectorAll('.model-selector');
            
            // Ensure only one checkbox is checked at a time
            modelSelectors.forEach(selector => {
                selector.addEventListener('change', function() {
                    if (this.checked) {
                        // Uncheck all other checkboxes
                        modelSelectors.forEach(otherSelector => {
                            if (otherSelector !== this) {
                                otherSelector.checked = false;
                            }
                        });
                        
                        // Load or display the selected model
                        loadSelectedModel(this, Model3DElement);
                        
                        // Save active model ID to database
                        saveActiveModelId(this.dataset.modelId);
                    } else {
                        // If unchecked, check if there are any other checked boxes
                        const anyChecked = Array.from(modelSelectors).some(s => s.checked);
                        if (!anyChecked) {
                            // If none are checked, recheck this one
                            this.checked = true;
                        } else {
                            // If this is unchecked and another is checked, hide this model
                            hideModel(this.dataset.modelId);
                        }
                    }
                });
            });
            
            // Load the initially checked model
            const initialModel = document.querySelector('.model-selector:checked');
            if (initialModel) {
                loadSelectedModel(initialModel, Model3DElement);
            }
        }
        
        // Function to save active model ID to database
        function saveActiveModelId(modelId) {
            const formData = new FormData();
            formData.append('update_active_model', '1');
            formData.append('model_id', modelId);
            
            fetch('project_map.php?id=<?= $project_id ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Error saving active model:', data.error);
                }
            })
            .catch(error => {
                console.error('Error saving active model:', error);
            });
        }
        
        // Function to load a selected model
        function loadSelectedModel(selector, Model3DElement) {
            const modelId = selector.dataset.modelId;
            
            // Update active model if it's already loaded
            if (loadedModels[modelId]) {
                // Hide all other models
                for (const id in loadedModels) {
                    if (id !== modelId) {
                        loadedModels[id].style.display = 'none';
                    }
                }
                
                // Show this model
                loadedModels[modelId].style.display = '';
                activeModel = loadedModels[modelId];
                
                // Update model data
                updateModelData(selector);
                
                return;
            }
            
            // Hide all existing models
            for (const id in loadedModels) {
                loadedModels[id].style.display = 'none';
            }
            
            // Load model data from the data attributes
            modelPosition = {
                lat: parseFloat(selector.dataset.lat),
                lng: parseFloat(selector.dataset.lng),
                altitude: parseFloat(selector.dataset.altitudeShow)
            };
            
            altitudeShow = parseFloat(selector.dataset.altitudeShow);
            altitudeFact = parseFloat(selector.dataset.altitudeFact);
            
            modelOrientation = {
                tilt: parseFloat(selector.dataset.tilt || 0),
                roll: parseFloat(selector.dataset.roll || 0)
            };
            
            // Create new model
            const newModel = new Model3DElement({
                src: selector.dataset.filePath,
                position: modelPosition,
                altitudeMode: 'RELATIVE_TO_GROUND',
                orientation: modelOrientation,
                scale: parseFloat(selector.dataset.scale || 1)
            });
            
            // Add to map
            map.append(newModel);
            activeModel = newModel;
            
            // Store reference to this model
            loadedModels[modelId] = newModel;
            
            // Update interactive marker position
            updateInteractiveMarker();
            
            // Update or create highlight polygon
            if (highlightPolygon) {
                updateHighlightPolygon();
            } else {
                createHighlightPolygon(modelPosition, modelOrientation, altitudeFact);
            }
            
            // Center map on this model
            map.center = modelPosition;
        }
        
        // Function to hide a model
        function hideModel(modelId) {
            if (loadedModels[modelId]) {
                loadedModels[modelId].style.display = 'none';
                
                // If this was the active model, set active model to null
                if (activeModel === loadedModels[modelId]) {
                    activeModel = null;
                    
                    // Also hide the red cube marker
                    if (redCubeMarker.length > 0) {
                        try {
                            redCubeMarker.forEach(face => {
                                map.removeChild(face);
                            });
                            redCubeMarker = [];
                        } catch (e) {
                            console.error('Error removing red cube marker:', e);
                        }
                    }
                }
            }
        }
        
        // Function to update model data from selector
        function updateModelData(selector) {
            modelPosition = {
                lat: parseFloat(selector.dataset.lat),
                lng: parseFloat(selector.dataset.lng),
                altitude: parseFloat(selector.dataset.altitudeShow)
            };
            
            altitudeShow = parseFloat(selector.dataset.altitudeShow);
            altitudeFact = parseFloat(selector.dataset.altitudeFact);
            
            modelOrientation = {
                tilt: parseFloat(selector.dataset.tilt || 0),
                roll: parseFloat(selector.dataset.roll || 0)
            };
            
            // Update the active model with new data
            if (activeModel) {
                activeModel.position = modelPosition;
                activeModel.orientation = modelOrientation;
                activeModel.scale = parseFloat(selector.dataset.scale || 1);
            }
            
            // Update interactive marker position
            updateInteractiveMarker();
            
            // Update or create highlight polygon
            if (highlightPolygon) {
                updateHighlightPolygon();
            } else {
                createHighlightPolygon(modelPosition, modelOrientation, altitudeFact);
            }
            
            // Update form values
            updateFormValues();
        }
        
        // Function to update form values based on current model data
        function updateFormValues() {
            // Find the checked model selector
            const checkedSelector = document.querySelector('.model-selector:checked');
            if (!checkedSelector) return;
            
            // Find the associated form
            const form = checkedSelector.closest('form');
            if (!form) return;
            
            // Round the values to 14 decimal places to avoid validation issues
            const roundedLat = parseFloat(modelPosition.lat.toFixed(14));
            const roundedLng = parseFloat(modelPosition.lng.toFixed(14));
            
            // Update form input values
            form.querySelector('input[name="lat"]').value = roundedLat;
            form.querySelector('input[name="lng"]').value = roundedLng;
            form.querySelector('input[name="altitude_show"]').value = altitudeShow;
            form.querySelector('input[name="altitude_fact"]').value = altitudeFact;
            form.querySelector('input[name="roll"]').value = modelOrientation.roll;
            form.querySelector('input[name="tilt"]').value = modelOrientation.tilt;
            
            // Also update dataset attributes
            checkedSelector.dataset.lat = roundedLat;
            checkedSelector.dataset.lng = roundedLng;
            checkedSelector.dataset.altitudeShow = altitudeShow;
            checkedSelector.dataset.altitudeFact = altitudeFact;
            checkedSelector.dataset.roll = modelOrientation.roll;
            checkedSelector.dataset.tilt = modelOrientation.tilt;
        }
        
        // Function to update the interactive marker position
        function updateInteractiveMarker() {
            if (!modelPosition || !map) return;
            
            if (window.interactiveMarker) {
                // Update existing marker position
                try {
                    window.interactiveMarker.position = {
                        lat: modelPosition.lat, 
                        lng: modelPosition.lng, 
                        altitude: altitudeShow + 2.7
                    };
                    return; // Successfully updated, exit the function
                } catch (e) {
                    console.log('Error updating marker position, recreating marker:', e);
                    try {
                        map.removeChild(window.interactiveMarker);
                    } catch (removeError) {
                        // Ignore errors if marker can't be removed
                    }
                }
            }
            
            // Create new marker only if needed (if it doesn't exist or update failed)
            const createMarker = async () => {
                const { PinElement } = await google.maps.importLibrary("marker");
                const { Marker3DInteractiveElement } = await google.maps.importLibrary("maps3d");
                
                const pinScaled = new PinElement({
                    scale: 2.2,
                });
                
                const marker = new Marker3DInteractiveElement ({
                    position: {
                        lat: modelPosition.lat, 
                        lng: modelPosition.lng, 
                        altitude: altitudeShow + 2.7
                    },
                    label: formatPrice(projectData.currency, projectData.price),
                    altitudeMode: 'RELATIVE_TO_GROUND',
                    extruded: true,
                });
                
                marker.append(pinScaled);
                map.append(marker);
                
                // Store reference
                window.interactiveMarker = marker;
                
                // Add click event listener for altitude animation
                marker.addEventListener('gmp-click', (event) => {
                    console.log('Marker clicked (gmp-click), animating altitude change');
                    const currentAlt = modelPosition.altitude;
                    const targetAlt = Math.abs(currentAlt - altitudeShow) < 1 ? altitudeFact : altitudeShow;
                    console.log(`Changing altitude from ${currentAlt} to ${targetAlt}`);
                    animateAltitudeChange(currentAlt, targetAlt);
                });
            };
            
            createMarker();
        }
        
        // Function to animate altitude change
        let isAltitudeAnimating = false;
        let altitudeAnimationFrameId = null;

        function animateAltitudeChange(startAlt, endAlt) {
            if (isAltitudeAnimating) return;
            isAltitudeAnimating = true;
            console.log(`Starting altitude animation from ${startAlt} to ${endAlt}`);

            const startTime = Date.now();
            const duration = 1200; // 1.2 seconds for the animation

            function animate() {
                const currentTime = Date.now();
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);

                // Easing function for smooth animation
                const easeProgress = progress < 0.5
                    ? 2 * progress * progress
                    : 1 - Math.pow(-2 * progress + 2, 2) / 2;

                // Calculate new altitude
                const newAltitude = startAlt + (endAlt - startAlt) * easeProgress;
                modelPosition.altitude = newAltitude;
                
                // Update model position
                activeModel.position = {
                    lat: modelPosition.lat,
                    lng: modelPosition.lng,
                    altitude: newAltitude
                };
                
                // Update interactive marker and form values
                updateInteractiveMarker();
                
                // Only update the highlight polygon, NOT the red cube which should stay at altitudeFact
                if (highlightPolygon) {
                    highlightPolygon.outerCoordinates = calculateRotatedPolygon(
                        modelPosition.lat,
                        modelPosition.lng,
                        altitudeFact,
                        0.0002,
                        180 - modelOrientation.roll
                    );
                }
                
                updateFormValues();

                if (progress < 1) {
                    altitudeAnimationFrameId = requestAnimationFrame(animate);
                } else {
                    console.log(`Animation complete, final altitude: ${newAltitude}`);
                    isAltitudeAnimating = false;
                    altitudeAnimationFrameId = null;
                }
            }

            animate();
        }
        
        function setupControlButtons() {
            // Get all the control buttons
            const place = document.getElementById('place');
            const up = document.getElementById('up');
            const down = document.getElementById('down');
            const forward = document.getElementById('forward');
            const back = document.getElementById('back');
            const left = document.getElementById('left');
            const right = document.getElementById('right');
            const roll1 = document.getElementById('roll1');
            const roll2 = document.getElementById('roll2');
            const tilt1 = document.getElementById('tilt1');
            const tilt2 = document.getElementById('tilt2');
            const addViewpoint = document.getElementById('addViewpoint');
            
            // Function to return to model view
            function returnToModelView() {
                if (!activeModel || !modelPosition) return;
                
                const mapAltitude = parseFloat(document.getElementById('map-altitude').value) || projectData.altitude;
                const mapRange = parseFloat(document.getElementById('map-camera-range').value) || projectData.camera_range;
                const mapTilt = parseFloat(document.getElementById('map-camera-tilt').value) || projectData.camera_tilt;
                const mapHeading = parseFloat(document.getElementById('map-camera-heading').value) || projectData.camera_heading;
                
                map.center = {
                    lat: modelPosition.lat,
                    lng: modelPosition.lng,
                    altitude: mapAltitude
                };
                map.range = mapRange;
                map.tilt = mapTilt;
                map.heading = mapHeading;
            }
            
            // Function to reset map tilt
            function resetMapTilt() {
                const mapTilt = parseFloat(document.getElementById('map-camera-tilt').value) || projectData.camera_tilt;
                map.tilt = mapTilt;
            }
            
            // Add event listeners to the control buttons
            document.getElementById('returnToModelBtn').addEventListener('click', returnToModelView);
            document.getElementById('resetTiltBtn').addEventListener('click', resetMapTilt);
            document.getElementById('flyAroundBtn').addEventListener('click', flyAround);
            
            // Add checkbox change event listeners to update form data when model is selected
            const modelSelectors = document.querySelectorAll('.model-selector');
            modelSelectors.forEach(selector => {
                selector.addEventListener('change', function() {
                    if (this.checked) {
                        // Get the form associated with this checkbox
                        const form = this.closest('form');
                        if (!form) return;
                        
                        // Ensure model position and data are synchronized with form values
                        const lat = parseFloat(form.querySelector('input[name="lat"]').value);
                        const lng = parseFloat(form.querySelector('input[name="lng"]').value);
                        const altitude_show = parseFloat(form.querySelector('input[name="altitude_show"]').value);
                        const altitude_fact = parseFloat(form.querySelector('input[name="altitude_fact"]').value);
                        const roll = parseFloat(form.querySelector('input[name="roll"]').value);
                        const tilt = parseFloat(form.querySelector('input[name="tilt"]').value);
                        const scale = parseFloat(form.querySelector('input[name="scale"]').value);
                        const markerWidth = parseFloat(form.querySelector('input[name="marker_width"]').value) || 0.00007;
                        const markerLength = parseFloat(form.querySelector('input[name="marker_length"]').value) || 0.00007;
                        const markerHeight = parseFloat(form.querySelector('input[name="marker_height"]').value) || 3.5;
                        
                        // Update dataset attributes to match form values
                        this.dataset.lat = lat;
                        this.dataset.lng = lng;
                        this.dataset.altitudeShow = altitude_show;
                        this.dataset.altitudeFact = altitude_fact;
                        this.dataset.roll = roll;
                        this.dataset.tilt = tilt;
                        this.dataset.scale = scale;
                        this.dataset.markerWidth = markerWidth;
                        this.dataset.markerLength = markerLength;
                        this.dataset.markerHeight = markerHeight;
                    }
                });
            });
            
            // Model position and orientation controls
            if (activeModel) {
                place.addEventListener('click', () => {
                    if (!activeModel) return;
                    
                    // Ensure consistent precision
                    const lat = parseFloat(map.center.lat.toFixed(14));
                    const lng = parseFloat(map.center.lng.toFixed(14));
                    
                    activeModel.position = {
                        lat: lat,
                        lng: lng,
                        altitude: altitudeShow
                    };
                    
                    modelPosition = {
                        lat: lat,
                        lng: lng, 
                        altitude: altitudeShow
                    };
                    
                    updateHighlightPolygon();
                    updateInteractiveMarker();
                    updateFormValues();
                });
                
                up.addEventListener('click', () => {
                    if (!activeModel) return;
                    
                    altitudeFact += 1;
                    modelPosition.altitude = altitudeFact;
                    activeModel.position = {
                        lat: modelPosition.lat,
                        lng: modelPosition.lng,
                        altitude: altitudeFact
                    };
                    
                    updateHighlightPolygon();
                    updateInteractiveMarker();
                    updateFormValues();
                });
                
                down.addEventListener('click', () => {
                    if (!activeModel) return;
                    
                    altitudeFact -= 1;
                    modelPosition.altitude = altitudeFact;
                    activeModel.position = {
                        lat: modelPosition.lat,
                        lng: modelPosition.lng,
                        altitude: altitudeFact
                    };
                    
                    updateHighlightPolygon();
                    updateInteractiveMarker();
                    updateFormValues();
                });
                
                const moveStep = 0.00001; // Adjust as needed
                
                forward.addEventListener('click', () => {
                    if (!activeModel) return;
                    
                    const heading = map.heading || 0;
                    const radians = heading * Math.PI / 180;
                    
                    modelPosition.lat += moveStep * Math.cos(radians);
                    modelPosition.lng += moveStep * Math.sin(radians);
                    
                    // Ensure consistent precision
                    modelPosition.lat = parseFloat(modelPosition.lat.toFixed(14));
                    modelPosition.lng = parseFloat(modelPosition.lng.toFixed(14));
                    
                    activeModel.position = {
                        lat: modelPosition.lat,
                        lng: modelPosition.lng,
                        altitude: modelPosition.altitude
                    };
                    
                    updateHighlightPolygon();
                    updateInteractiveMarker();
                    updateFormValues();
                });
                
                back.addEventListener('click', () => {
                    if (!activeModel) return;
                    
                    const heading = map.heading || 0;
                    const radians = heading * Math.PI / 180;
                    
                    modelPosition.lat -= moveStep * Math.cos(radians);
                    modelPosition.lng -= moveStep * Math.sin(radians);
                    
                    // Ensure consistent precision
                    modelPosition.lat = parseFloat(modelPosition.lat.toFixed(14));
                    modelPosition.lng = parseFloat(modelPosition.lng.toFixed(14));
                    
                    activeModel.position = {
                        lat: modelPosition.lat,
                        lng: modelPosition.lng,
                        altitude: modelPosition.altitude
                    };
                    
                    updateHighlightPolygon();
                    updateInteractiveMarker();
                    updateFormValues();
                });
                
                left.addEventListener('click', () => {
                    if (!activeModel) return;
                    
                    const heading = map.heading || 0;
                    const radians = (heading - 90) * Math.PI / 180;
                    
                    modelPosition.lat += moveStep * Math.cos(radians);
                    modelPosition.lng += moveStep * Math.sin(radians);
                    
                    // Ensure consistent precision
                    modelPosition.lat = parseFloat(modelPosition.lat.toFixed(14));
                    modelPosition.lng = parseFloat(modelPosition.lng.toFixed(14));
                    
                    activeModel.position = {
                        lat: modelPosition.lat,
                        lng: modelPosition.lng,
                        altitude: modelPosition.altitude
                    };
                    
                    updateHighlightPolygon();
                    updateInteractiveMarker();
                    updateFormValues();
                });
                
                right.addEventListener('click', () => {
                    if (!activeModel) return;
                    
                    const heading = map.heading || 0;
                    const radians = (heading + 90) * Math.PI / 180;
                    
                    modelPosition.lat += moveStep * Math.cos(radians);
                    modelPosition.lng += moveStep * Math.sin(radians);
                    
                    // Ensure consistent precision
                    modelPosition.lat = parseFloat(modelPosition.lat.toFixed(14));
                    modelPosition.lng = parseFloat(modelPosition.lng.toFixed(14));
                    
                    activeModel.position = {
                        lat: modelPosition.lat,
                        lng: modelPosition.lng,
                        altitude: modelPosition.altitude
                    };
                    
                    updateHighlightPolygon();
                    updateInteractiveMarker();
                    updateFormValues();
                });
                
                roll1.addEventListener('click', () => {
                    if (!activeModel) return;
                    
                    modelOrientation.roll = (modelOrientation.roll + 5) % 360;
                    activeModel.orientation = modelOrientation;
                    
                    updateHighlightPolygon();
                    updateFormValues();
                });
                
                roll2.addEventListener('click', () => {
                    if (!activeModel) return;
                    
                    modelOrientation.roll = (modelOrientation.roll - 5 + 360) % 360;
                    activeModel.orientation = modelOrientation;
                    
                    updateHighlightPolygon();
                    updateFormValues();
                });
                
                tilt1.addEventListener('click', () => {
                    if (!activeModel) return;
                    
                    modelOrientation.tilt = Math.min(modelOrientation.tilt + 5, 360);
                    activeModel.orientation = modelOrientation;
                    updateFormValues();
                });
                
                tilt2.addEventListener('click', () => {
                    if (!activeModel) return;
                    
                    modelOrientation.tilt = Math.max(modelOrientation.tilt - 5, 0);
                    activeModel.orientation = modelOrientation;
                    updateFormValues();
                });
                
                addViewpoint.addEventListener('click', () => {
                    saveViewpoint();
                });
            }
        }
        
        // Function to create highlight polygon for the model
        function createHighlightPolygon(position, orientation, altitude) {
            if (!position || !orientation || !map) return;
            
            // Remove existing polygon if any
            if (highlightPolygon) {
                try {
                    map.removeChild(highlightPolygon);
                } catch (e) {
                    // Ignore errors if polygon doesn't exist or can't be removed
                }
            }
            
            const createPolygon = async () => {
                const { Polygon3DElement } = await google.maps.importLibrary("maps3d");
                
                const polygonOptions = {
                    strokeColor: "#EA4335",
                    strokeWidth: 4,
                    fillColor: "#e61d164f",
                    altitudeMode: 'RELATIVE_TO_GROUND',
                    extruded: false,
                    drawsOccludedSegments: true,
                };
                
                const polygon = new Polygon3DElement(polygonOptions);
                
                // Calculate polygon coordinates based on model position and orientation
                polygon.outerCoordinates = calculateRotatedPolygon(
                    position.lat,
                    position.lng,
                    altitude,
                    0.0002,
                    180 - orientation.roll
                );
                
                // Add polygon to map
                map.append(polygon);
                
                // Store reference to polygon
                highlightPolygon = polygon;
                
                // Create a red cube to mark the model's position
                createRedCubeMarker(position);
            };
            
            createPolygon();
        }
        
        // Function to create a red cube marker at the model position
        let redCubeMarker = [];
        async function createRedCubeMarker(position) {
            // Remove existing cube faces if any
            if (redCubeMarker.length > 0) {
                try {
                    redCubeMarker.forEach(face => {
                        map.removeChild(face);
                    });
                } catch (e) {
                    // Ignore errors if markers can't be removed
                }
                redCubeMarker = [];
            }
            
            const { Polygon3DElement } = await google.maps.importLibrary("maps3d");
            
            // Get the currently selected model form
            const checkedSelector = document.querySelector('.model-selector:checked');
            let sizeWidth = 0.00007; // Default width
            let sizeLength = 0.00007; // Default length
            let height = 3.5; // Default height
            
            if (checkedSelector) {
                const form = checkedSelector.closest('form');
                if (form) {
                    // Get marker dimension values from the form
                    const markerWidthInput = form.querySelector('input[name="marker_width"]');
                    const markerLengthInput = form.querySelector('input[name="marker_length"]');
                    const markerHeightInput = form.querySelector('input[name="marker_height"]');
                    
                    sizeWidth = markerWidthInput && !isNaN(parseFloat(markerWidthInput.value)) ? 
                                parseFloat(markerWidthInput.value) : sizeWidth;
                    sizeLength = markerLengthInput && !isNaN(parseFloat(markerLengthInput.value)) ? 
                                 parseFloat(markerLengthInput.value) : sizeLength;
                    height = markerHeightInput && !isNaN(parseFloat(markerHeightInput.value)) ? 
                             parseFloat(markerHeightInput.value) : height;
                }
            }
            
            // Use the same angle as in calculateRotatedPolygon
            const angleDegrees = modelOrientation ? (180 - modelOrientation.roll) : 0;
            const angleRad = angleDegrees * Math.PI / 180;
            
            // Convert sizes to meters (same conversion as in calculateRotatedPolygon)
            const widthMeters = sizeWidth * 111320; // ~1 degree longitude ≈ 111.32 km * cos(lat)
            const lengthMeters = sizeLength * 111320; // ~1 degree latitude ≈ 111.32 km
            const halfWidth = widthMeters / 2;
            const halfLength = lengthMeters / 2;
            
            // Use altitudeFact instead of position.altitude to position the cube at the real altitude
            const cubeAltitude = altitudeFact;
            
            // Define cube vertices using the exact same approach as in calculateRotatedPolygon
            // Bottom face vertices (z=0)
            const bottomVertices = [
                { x: -halfWidth, y: -halfLength },
                { x: halfWidth, y: -halfLength },
                { x: halfWidth, y: halfLength },
                { x: -halfWidth, y: halfLength }
            ].map(point => {
                const rotatedX = point.x * Math.cos(angleRad) - point.y * Math.sin(angleRad);
                const rotatedY = point.x * Math.sin(angleRad) + point.y * Math.cos(angleRad);
                
                return {
                    lat: position.lat + (rotatedY / 111320),
                    lng: position.lng + (rotatedX / (111320 * Math.cos(position.lat * Math.PI / 180))),
                    altitude: cubeAltitude
                };
            });
            
            // Top face vertices (z=height)
            const topVertices = [
                { x: -halfWidth, y: -halfLength },
                { x: halfWidth, y: -halfLength },
                { x: halfWidth, y: halfLength },
                { x: -halfWidth, y: halfLength }
            ].map(point => {
                const rotatedX = point.x * Math.cos(angleRad) - point.y * Math.sin(angleRad);
                const rotatedY = point.x * Math.sin(angleRad) + point.y * Math.cos(angleRad);
                
                return {
                    lat: position.lat + (rotatedY / 111320),
                    lng: position.lng + (rotatedX / (111320 * Math.cos(position.lat * Math.PI / 180))),
                    altitude: cubeAltitude + height
                };
            });
            
            // Create bottom face
            const bottomFace = new Polygon3DElement({
                strokeColor: "#DD0000",
                strokeWidth: 2,
                fillColor: "#FF0000B0",
                altitudeMode: 'RELATIVE_TO_GROUND',
                extruded: false,
                drawsOccludedSegments: true
            });
            bottomFace.outerCoordinates = bottomVertices;
            map.append(bottomFace);
            redCubeMarker.push(bottomFace);
            
            // Create top face
            const topFace = new Polygon3DElement({
                strokeColor: "#DD0000",
                strokeWidth: 2,
                fillColor: "#FF0000B0",
                altitudeMode: 'RELATIVE_TO_GROUND',
                extruded: false,
                drawsOccludedSegments: true
            });
            topFace.outerCoordinates = topVertices;
            map.append(topFace);
            redCubeMarker.push(topFace);
            
            // Create side faces
            for (let i = 0; i < 4; i++) {
                const nextI = (i + 1) % 4;
                const sideFace = new Polygon3DElement({
                    strokeColor: "#DD0000",
                    strokeWidth: 2,
                    fillColor: "#FF0000B0",
                    altitudeMode: 'RELATIVE_TO_GROUND',
                    extruded: false,
                    drawsOccludedSegments: true
                });
                sideFace.outerCoordinates = [
                    bottomVertices[i],
                    bottomVertices[nextI],
                    topVertices[nextI],
                    topVertices[i]
                ];
                map.append(sideFace);
                redCubeMarker.push(sideFace);
            }
        }
        
        // Function to update the highlight polygon
        function updateHighlightPolygon() {
            if (!highlightPolygon) return;
            
            highlightPolygon.outerCoordinates = calculateRotatedPolygon(
                modelPosition.lat,
                modelPosition.lng,
                altitudeFact,
                0.0002,
                180 - modelOrientation.roll
            );
            
            // Update the red cube marker position
            if (redCubeMarker.length > 0) {
                // Remove existing cube and create a new one at updated position
                createRedCubeMarker(modelPosition);
            } else {
                // Create red cube if it doesn't exist
                createRedCubeMarker(modelPosition);
            }
        }
        
        // Function to calculate rotated polygon coordinates
        function calculateRotatedPolygon(lat, lng, altitude, d, angleDegrees) {
            const angleRad = angleDegrees * Math.PI / 180; // Convert angle to radians
            const offsetMeters = d * 111320; // ~1 degree latitude ≈ 111.32 km
            const halfD = offsetMeters / 2;
            
            // Calculate rectangle corners relative to the center (in meters)
            const points = [
                { x: halfD, y: halfD },    // Top-right
                { x: halfD, y: -halfD },   // Bottom-right
                { x: -halfD, y: -halfD },  // Bottom-left
                { x: -halfD, y: halfD }    // Top-left
            ];
            
            // Rotation function for geodesic coordinates
            const rotatedPoints = points.map(point => {
                const rotatedX = point.x * Math.cos(angleRad) - point.y * Math.sin(angleRad);
                const rotatedY = point.x * Math.sin(angleRad) + point.y * Math.cos(angleRad);
                
                // Convert meters back to lat/lng
                return {
                    lat: lat + (rotatedY / 111320), 
                    lng: lng + (rotatedX / (111320 * Math.cos(lat * Math.PI / 180))),
                    altitude: altitude
                };
            });
            
            return rotatedPoints;
        }
        
        // Function to stop any ongoing animation
        function stopAnimation() {
            isAnimating = false;
            if (animationFrameId) {
                cancelAnimationFrame(animationFrameId);
                animationFrameId = null;
                if (map && map.stopCameraAnimation) {
                    map.stopCameraAnimation();
                }
            }
            
            // Also stop altitude animation if it's running
            if (isAltitudeAnimating && altitudeAnimationFrameId) {
                isAltitudeAnimating = false;
                cancelAnimationFrame(altitudeAnimationFrameId);
                altitudeAnimationFrameId = null;
            }
        }
        
        // Function to perform a 360-degree fly-around animation
        function flyAround() {
            if (isAnimating) {
                stopAnimation();
                return;
            }
            
            if (!activeModel || !modelPosition) return;
            
            isAnimating = true;
            
            // Center on model and set fixed parameters for the animation
            const mapTilt = parseFloat(document.getElementById('map-camera-tilt').value) || projectData.camera_tilt;
            const mapRange = parseFloat(document.getElementById('map-camera-range').value) || projectData.camera_range;
            const mapAltitude = parseFloat(document.getElementById('map-altitude').value) || projectData.altitude;
            const mapHeading = parseFloat(document.getElementById('map-camera-heading').value) || projectData.camera_heading;
            
            map.center = modelPosition;
            map.tilt = mapTilt;
            map.range = mapRange;
            map.heading = mapHeading;

            const camera = {
                center: {
                    lat: modelPosition.lat,
                    lng: modelPosition.lng,
                    altitude: mapAltitude
                },
                tilt: map.tilt,
                range: map.range,
                heading: map.heading
            };
            
            // Ensure we start from altitudeShow
            modelPosition.altitude = altitudeShow;
            activeModel.position = {
                lat: modelPosition.lat,
                lng: modelPosition.lng,
                altitude: altitudeShow
            };
            
            const startTime = Date.now();
            const duration = 20000; // 20 seconds for a complete rotation
            const startHeading = map.heading || 0;
            const startAltitude = altitudeShow;
            
            map.flyCameraAround({
                camera,
                durationMillis: duration,
                rounds: 1
            });
            
            function animate() {
                const currentTime = Date.now();
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                
                // Calculate altitude animation with four equal sections
                let newAltitude;
                
                if (progress < 0.25) {
                    // First 5 seconds: descend from altitudeShow to altitudeFact
                    const sectionProgress = progress / 0.25; // normalize to 0-1 for this section
                    const oscillationValue = Math.sin(sectionProgress * Math.PI / 2);
                    newAltitude = altitudeShow - oscillationValue * (altitudeShow - altitudeFact);
                } else if (progress < 0.5) {
                    // Second 5 seconds: ascend from altitudeFact back to altitudeShow
                    const sectionProgress = (progress - 0.25) / 0.25;
                    const oscillationValue = Math.sin(sectionProgress * Math.PI / 2);
                    newAltitude = altitudeFact + oscillationValue * (altitudeShow - altitudeFact);
                } else if (progress < 0.75) {
                    // Third 5 seconds: descend again from altitudeShow to altitudeFact
                    const sectionProgress = (progress - 0.5) / 0.25;
                    const oscillationValue = Math.sin(sectionProgress * Math.PI / 2);
                    newAltitude = altitudeShow - oscillationValue * (altitudeShow - altitudeFact);
                } else {
                    // Final 5 seconds: ascend from altitudeFact back to altitudeShow
                    const sectionProgress = (progress - 0.75) / 0.25;
                    const oscillationValue = Math.sin(sectionProgress * Math.PI / 2);
                    newAltitude = altitudeFact + oscillationValue * (altitudeShow - altitudeFact);
                }
                
                // Update model position with new altitude
                modelPosition.altitude = newAltitude;
                activeModel.position = {
                    lat: modelPosition.lat,
                    lng: modelPosition.lng,
                    altitude: newAltitude
                };
                
                // Update interactive marker
                updateInteractiveMarker();
                
                // Only update the highlight polygon, NOT the red cube which should stay at altitudeFact
                if (highlightPolygon) {
                    highlightPolygon.outerCoordinates = calculateRotatedPolygon(
                        modelPosition.lat,
                        modelPosition.lng,
                        altitudeFact,
                        0.0002,
                        180 - modelOrientation.roll
                    );
                }
                
                if (progress < 1 && isAnimating) {
                    animationFrameId = requestAnimationFrame(animate);
                } else {
                    isAnimating = false;
                    animationFrameId = null;
                    
                    // Ensure model returns to original altitude at the end
                    modelPosition.altitude = startAltitude;
                    activeModel.position = {
                        lat: modelPosition.lat,
                        lng: modelPosition.lng,
                        altitude: startAltitude
                    };
                    
                    // Update markers and form values one final time
                    updateInteractiveMarker();
                    updateHighlightPolygon();
                    updateFormValues();
                }
            }
            
            animate();
        }
        
        // Function to save a viewpoint
        function saveViewpoint() {
            if (!map) return;
            
            // Default viewpoint name
            const name = `Viewpoint ${new Date().toLocaleString()}`;
            
            const viewpoint = {
                name: name,
                center: {
                    lat: map.center.lat,
                    lng: map.center.lng,
                    altitude: map.center.altitude || 0
                },
                tilt: map.tilt,
                heading: map.heading || 0,
                range: map.range
            };
            
            // Save viewpoint to database
            const formData = new FormData();
            formData.append('save_viewpoint', '1');
            formData.append('name', viewpoint.name);
            formData.append('lat', viewpoint.center.lat);
            formData.append('lng', viewpoint.center.lng);
            formData.append('altitude', viewpoint.center.altitude);
            formData.append('tilt', viewpoint.tilt);
            formData.append('heading', viewpoint.heading);
            formData.append('range', viewpoint.range);
            
            fetch('project_map.php?id=<?= $project_id ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Add the new viewpoint to the savedViewpoints array
                    viewpoint.id = data.viewpoint.id;
                    savedViewpoints.push(viewpoint);
                    
                    // Create and add the new viewpoint element to the viewpoints section
                    addViewpointToPage(viewpoint);
                } else {
                    console.error('Error saving viewpoint:', data.error);
                    alert('Error saving viewpoint. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving viewpoint. Please try again.');
            });
        }
        
        // Function to add a viewpoint to the page without refreshing
        function addViewpointToPage(viewpoint) {
            const viewpointsSection = document.querySelector('.viewpoints-section');
            
            // Remove "no viewpoints" message if it exists
            const noViewpointsMsg = viewpointsSection.querySelector('p');
            if (noViewpointsMsg && noViewpointsMsg.textContent.includes('No viewpoints')) {
                noViewpointsMsg.remove();
            }
            
            // Create the viewpoint item container
            const viewpointItem = document.createElement('div');
            viewpointItem.className = 'viewpoint-item';
            
            // Create the form
            const form = document.createElement('form');
            
            // Set the HTML content
            form.innerHTML = `
                <input type="hidden" name="viewpoint_id" value="${viewpoint.id}">
                <div style="display: flex; align-items: center; margin-bottom: 10px;">
                    <input type="checkbox" class="viewpoint-selector" id="viewpoint_${viewpoint.id}" 
                           data-viewpoint-id="${viewpoint.id}" 
                           data-lat="${viewpoint.center.lat}"
                           data-lng="${viewpoint.center.lng}"
                           data-altitude="${viewpoint.center.altitude}"
                           data-tilt="${viewpoint.tilt}"
                           data-heading="${viewpoint.heading || 0}"
                           data-range="${viewpoint.range}">
                    <h4 style="margin-left: 10px;">${viewpoint.name || 'Unnamed Viewpoint'}</h4>
                </div>
                <div><label>Latitude: <input name="lat" value="${viewpoint.center.lat}"></label></div>
                <div><label>Longitude: <input name="lng" value="${viewpoint.center.lng}"></label></div>
                <div><label>Altitude: <input name="altitude" value="${viewpoint.center.altitude}"></label></div>
                <div><label>Tilt: <input name="tilt" value="${viewpoint.tilt}"></label></div>
                <div><label>Heading: <input name="heading" value="${viewpoint.heading || 0}"></label></div>
                <div><label>Range: <input name="range" value="${viewpoint.range}"></label></div>
                <div>
                    <button type="button" class="go-to-viewpoint-btn" data-viewpoint-id="${viewpoint.id}">Go to View</button>
                    <button type="button" class="use-current-camera-btn" data-viewpoint-id="${viewpoint.id}">Use Current Camera</button>
                    <button type="button" class="delete-viewpoint-btn" data-viewpoint-id="${viewpoint.id}">Delete Viewpoint</button>
                </div>
            `;
            
            // Add the form to the viewpoint item
            viewpointItem.appendChild(form);
            
            // Add the viewpoint item to the viewpoints section
            viewpointsSection.appendChild(viewpointItem);
            
            // Set up event listeners for the new buttons
            const goToViewBtn = form.querySelector('.go-to-viewpoint-btn');
            goToViewBtn.addEventListener('click', function() {
                goToViewpoint(viewpoint.id);
            });
            
            const useCurrentCameraBtn = form.querySelector('.use-current-camera-btn');
            useCurrentCameraBtn.addEventListener('click', function() {
                useCurrentCamera(viewpoint.id, form);
            });
            
            const deleteViewpointBtn = form.querySelector('.delete-viewpoint-btn');
            deleteViewpointBtn.addEventListener('click', function() {
                deleteViewpoint(viewpoint.id);
            });
        }
        
        // Function to update a viewpoint with current camera position
        function useCurrentCamera(viewpointId, form) {
            if (!map) return;
            
            // Get current camera parameters
            const currentCamera = {
                lat: map.center.lat,
                lng: map.center.lng,
                altitude: map.center.altitude || 0,
                tilt: map.tilt,
                heading: map.heading || 0,
                range: map.range
            };
            
            // Update form values with current camera
            form.querySelector('input[name="lat"]').value = currentCamera.lat;
            form.querySelector('input[name="lng"]').value = currentCamera.lng;
            form.querySelector('input[name="altitude"]').value = currentCamera.altitude;
            form.querySelector('input[name="tilt"]').value = currentCamera.tilt;
            form.querySelector('input[name="heading"]').value = currentCamera.heading;
            form.querySelector('input[name="range"]').value = currentCamera.range;
            
            // Automatically save these changes
            updateViewpoint(viewpointId, form);
        }
        
        // Function to update a viewpoint without reloading the page
        function updateViewpoint(viewpointId, form) {
            const formData = new FormData();
            formData.append('update_viewpoint', '1');
            formData.append('viewpoint_id', viewpointId);
            formData.append('name', form.querySelector('h4').textContent);
            formData.append('lat', form.querySelector('input[name="lat"]').value);
            formData.append('lng', form.querySelector('input[name="lng"]').value);
            formData.append('altitude', form.querySelector('input[name="altitude"]').value);
            formData.append('tilt', form.querySelector('input[name="tilt"]').value);
            formData.append('heading', form.querySelector('input[name="heading"]').value);
            formData.append('range', form.querySelector('input[name="range"]').value);
            
            fetch('project_map.php?id=<?= $project_id ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the viewpoint in the savedViewpoints array
                    const updatedViewpoint = savedViewpoints.find(vp => vp.id === viewpointId);
                    if (updatedViewpoint) {
                        updatedViewpoint.name = data.viewpoint.name;
                        updatedViewpoint.center = {
                            lat: parseFloat(data.viewpoint.lat),
                            lng: parseFloat(data.viewpoint.lng),
                            altitude: parseFloat(data.viewpoint.altitude)
                        };
                        updatedViewpoint.tilt = parseFloat(data.viewpoint.tilt);
                        updatedViewpoint.heading = parseFloat(data.viewpoint.heading);
                        updatedViewpoint.range = parseFloat(data.viewpoint.range);
                    }
                    
                    // Update the viewpoint selector
                    const selector = document.getElementById(`viewpoint_${viewpointId}`);
                    if (selector) {
                        selector.dataset.lat = data.viewpoint.lat;
                        selector.dataset.lng = data.viewpoint.lng;
                        selector.dataset.altitude = data.viewpoint.altitude;
                        selector.dataset.tilt = data.viewpoint.tilt;
                        selector.dataset.heading = data.viewpoint.heading;
                        selector.dataset.range = data.viewpoint.range;
                    }
                    
                    // Show success notification
                    const notification = document.createElement('div');
                    notification.style.position = 'fixed';
                    notification.style.top = '20px';
                    notification.style.right = '20px';
                    notification.style.backgroundColor = '#28a745';
                    notification.style.color = 'white';
                    notification.style.padding = '10px 20px';
                    notification.style.borderRadius = '5px';
                    notification.style.zIndex = '9999';
                    notification.style.opacity = '0';
                    notification.style.transition = 'opacity 0.3s ease';
                    notification.textContent = 'Viewpoint updated successfully';
                    document.body.appendChild(notification);
                    
                    // Fade in
                    setTimeout(() => { notification.style.opacity = '1'; }, 10);
                    
                    // Fade out after 2 seconds
                    setTimeout(() => {
                        notification.style.opacity = '0';
                        setTimeout(() => {
                            document.body.removeChild(notification);
                        }, 300);
                    }, 2000);
                } else {
                    console.error('Error updating viewpoint:', data.error);
                    alert('Error updating viewpoint. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating viewpoint. Please try again.');
            });
        }
        
        // Function to load viewpoints from database data
        function loadViewpointsFromDatabase() {
            if (!viewpointsData || viewpointsData.length === 0) {
                return;
            }
            
            // Convert database viewpoints to the format used by the application
            viewpointsData.forEach(vpData => {
                const viewpoint = {
                    id: parseInt(vpData.id),
                    name: vpData.name,
                    center: {
                        lat: parseFloat(vpData.lat),
                        lng: parseFloat(vpData.lng),
                        altitude: parseFloat(vpData.altitude)
                    },
                    tilt: parseFloat(vpData.tilt),
                    heading: parseFloat(vpData.heading),
                    range: parseFloat(vpData.range_value)
                };
                
                // Add to savedViewpoints array
                savedViewpoints.push(viewpoint);
                
                // We don't need to manually add these to the page as they're already rendered by PHP
            });
            
            // Set viewpointCount to the maximum id to ensure new viewpoints get a higher id
            if (savedViewpoints.length > 0) {
                viewpointCount = Math.max(...savedViewpoints.map(vp => vp.id));
            }
            
            // Set up event listeners for viewpoint buttons
            setupViewpointButtons();
        }
        
        // Function to set up event listeners for viewpoint buttons
        function setupViewpointButtons() {
            // Add event listeners to "Go to View" buttons
            const goToViewButtons = document.querySelectorAll('.go-to-viewpoint-btn');
            goToViewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const viewpointId = parseInt(this.dataset.viewpointId);
                    goToViewpoint(viewpointId);
                });
            });
            
            // Add event listeners to "Use Current Camera" buttons
            const useCurrentCameraButtons = document.querySelectorAll('.use-current-camera-btn');
            useCurrentCameraButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const viewpointId = parseInt(this.dataset.viewpointId);
                    const form = this.closest('form');
                    useCurrentCamera(viewpointId, form);
                });
            });
            
            // Add event listeners to "Delete Viewpoint" buttons
            const deleteViewpointButtons = document.querySelectorAll('.delete-viewpoint-btn');
            deleteViewpointButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const viewpointId = parseInt(this.dataset.viewpointId);
                    deleteViewpoint(viewpointId);
                });
            });
            
            // Set up delete model buttons
            const deleteModelButtons = document.querySelectorAll('.delete-model-btn');
            deleteModelButtons.forEach(button => {
                button.addEventListener('click', function() {
                    if (confirm('Are you sure you want to delete this model? This cannot be undone.')) {
                        deleteModel(this.dataset.modelId);
                    }
                });
            });
        }
        
        // Function to delete a viewpoint
        function deleteViewpoint(viewpointId) {
            if (!confirm('Are you sure you want to delete this viewpoint?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('delete_viewpoint', '1');
            formData.append('viewpoint_id', viewpointId);
            
            fetch('project_map.php?id=<?= $project_id ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove from savedViewpoints array
                    savedViewpoints = savedViewpoints.filter(vp => vp.id !== viewpointId);
                    
                    // Remove the viewpoint element from the page
                    removeViewpointFromPage(viewpointId);
                } else {
                    console.error('Error deleting viewpoint:', data.error);
                    alert('Error deleting viewpoint. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting viewpoint. Please try again.');
            });
        }
        
        // Function to remove a viewpoint from the page without refreshing
        function removeViewpointFromPage(viewpointId) {
            // Find the viewpoint item with the matching ID
            const viewpointSelector = document.getElementById(`viewpoint_${viewpointId}`);
            if (!viewpointSelector) return;
            
            // Get the parent viewpoint-item div and remove it
            const viewpointItem = viewpointSelector.closest('.viewpoint-item');
            if (viewpointItem) {
                viewpointItem.remove();
                
                // If no viewpoints left, add back the "no viewpoints" message
                const viewpointsSection = document.querySelector('.viewpoints-section');
                if (!viewpointsSection.querySelector('.viewpoint-item')) {
                    const noViewpointsMsg = document.createElement('p');
                    noViewpointsMsg.textContent = 'No viewpoints saved yet. Click the "Add Viewpoint" button to save the current camera position.';
                    viewpointsSection.appendChild(noViewpointsMsg);
                }
            }
        }
        
        // Function to go to a saved viewpoint
        function goToViewpoint(viewpointId) {
            const viewpoint = savedViewpoints.find(vp => vp.id === viewpointId);
            if (!viewpoint || !map) return;
            
            // Stop any ongoing animations
            stopAnimation();
            
            try {
                // Create a camera object with the saved viewpoint properties
                const endCamera = {
                    center: viewpoint.center,
                    tilt: viewpoint.tilt,
                    range: viewpoint.range,
                    heading: viewpoint.heading || 0
                };
                
                // Explicitly set the tilt first
                map.tilt = viewpoint.tilt;
                
                // Then fly to the position
                map.flyCameraTo({
                    endCamera: endCamera,
                    durationMillis: 2000
                });
                
                // Double-check that tilt is applied correctly after animation
                setTimeout(() => {
                    map.tilt = viewpoint.tilt;
                    map.heading = viewpoint.heading || 0;
                }, 2100);
            } catch (error) {
                console.error('Error during camera animation:', error);
                
                // Fallback to direct setting if animation fails
                try {
                    map.center = viewpoint.center;
                    map.tilt = viewpoint.tilt;
                    map.heading = viewpoint.heading || 0;
                    map.range = viewpoint.range;
                } catch (fallbackError) {
                    console.error('Fallback also failed:', fallbackError);
                }
            }
        }
        
        // Function to delete a model
        function deleteModel(modelId) {
            // Create FormData object for the request
            const formData = new FormData();
            formData.append('model_id', modelId);
            
            // Show loading notification
            showNotification('Deleting model...', 'info');
            
            // Find the model element before sending the request
            const modelSelector = document.getElementById('model_' + modelId);
            let modelElement = null;
            if (modelSelector) {
                modelElement = modelSelector.closest('.model-item');
            }
            
            // Log the request data for debugging
            console.log('Sending delete request for model ID:', modelId);
            
            // Send the delete request to the server
            fetch('delete_model.php', {
                method: 'POST',
                body: formData,
                credentials: 'include' // Use 'include' instead of 'same-origin' to ensure cookies are sent
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json().catch(error => {
                    console.error('Error parsing JSON response:', error);
                    return response.text().then(text => {
                        console.error('Server responded with non-JSON content:', text);
                        
                        // Check if the model element no longer exists in the DOM
                        const stillExists = document.getElementById('model_' + modelId);
                        
                        // If the model element is gone, the deletion was probably successful despite the error
                        if (!stillExists && modelElement) {
                            console.log('Model appears to be deleted despite error');
                            return { success: true, warning: "Possible race condition - model appears to be deleted" };
                        }
                        
                        throw new Error('Server responded with invalid format: ' + text);
                    });
                });
            })
            .then(data => {
                console.log('Delete response:', data);
                
                // Check for unauthorized error but the model element doesn't exist anymore
                // This handles the case where the model was deleted but the response indicates an error
                if (!data.success && data.error === "Model not found or unauthorized") {
                    // Check if the model is actually gone from the UI
                    const stillExists = document.getElementById('model_' + modelId);
                    if (!stillExists && modelElement) {
                        console.log('Model appears to be deleted despite unauthorized error');
                        // Set success to true but without showing the technical warning to the user
                        data = { success: true };
                    }
                }
                
                if (data.success) {
                    // Find the model element and remove it (if not already done)
                    if (modelElement && modelElement.parentNode) {
                        modelElement.remove();
                    }
                    
                    // If this was the active model, remove it from the map
                    if (loadedModels[modelId]) {
                        if (activeModel === loadedModels[modelId]) {
                            activeModel = null;
                        }
                        
                        try {
                            if (map && loadedModels[modelId]) {
                                map.removeChild(loadedModels[modelId]);
                            }
                        } catch (e) {
                            console.error('Error removing model from map:', e);
                        }
                        
                        delete loadedModels[modelId];
                    }
                    
                    // Load a different model if available
                    const remainingSelectors = document.querySelectorAll('.model-selector');
                    if (remainingSelectors.length > 0) {
                        remainingSelectors[0].checked = true;
                        const event = new Event('change');
                        remainingSelectors[0].dispatchEvent(event);
                    } else {
                        // No models left, clear the map
                        if (highlightPolygon) {
                            try {
                                map.removeChild(highlightPolygon);
                                highlightPolygon = null;
                            } catch (e) {
                                console.error('Error removing polygon:', e);
                            }
                        }
                        
                        if (window.interactiveMarker) {
                            try {
                                map.removeChild(window.interactiveMarker);
                                window.interactiveMarker = null;
                            } catch (e) {
                                console.error('Error removing marker:', e);
                            }
                        }
                        
                        // Add "no models" message if all models are deleted
                        const modelsSection = document.querySelector('.models-section');
                        if (!modelsSection.querySelector('.model-item')) {
                            const noModelsMsg = document.createElement('p');
                            noModelsMsg.textContent = 'No models uploaded yet.';
                            modelsSection.appendChild(noModelsMsg);
                        }
                    }
                    
                    // Show success notification
                    showNotification('Model deleted successfully', 'success');
                    
                    // If there's a warning, show it as a separate notification
                    if (data.warning) {
                        console.warn(data.warning);
                        setTimeout(() => {
                            showNotification(data.warning, 'warning');
                        }, 500);
                    }
                } else {
                    // Check if the model might have been deleted anyway
                    setTimeout(() => {
                        const stillExists = document.getElementById('model_' + modelId);
                        if (!stillExists && modelElement) {
                            console.log('Model appears to be deleted despite error response');
                            
                            // Remove the model from the map if it's loaded
                            if (loadedModels[modelId]) {
                                if (activeModel === loadedModels[modelId]) {
                                    activeModel = null;
                                }
                                
                                try {
                                    if (map && loadedModels[modelId]) {
                                        map.removeChild(loadedModels[modelId]);
                                    }
                                } catch (e) {
                                    console.error('Error removing model from map:', e);
                                }
                                
                                delete loadedModels[modelId];
                            }
                            
                            showNotification('Model appears to be deleted successfully despite error', 'success');
                        } else {
                            // Show error notification only if the model still exists
                            showNotification('Error deleting model: ' + (data.error || 'Unknown error'), 'error');
                        }
                    }, 500); // Wait a moment to check if the DOM has been updated
                }
            })
            .catch(error => {
                console.error('Error:', error);
                
                // Check if the model might have been deleted anyway
                setTimeout(() => {
                    const stillExists = document.getElementById('model_' + modelId);
                    if (!stillExists && modelElement) {
                        console.log('Model appears to be deleted despite error');
                        showNotification('Model appears to be deleted successfully despite error', 'success');
                    } else {
                        showNotification('Error deleting model: ' + error.message, 'error');
                    }
                }, 500); // Wait a moment to check if the DOM has been updated
            });
        }
        
        // Initialize the map when the page loads
        window.addEventListener('load', init);
        
        
        

        
        // Function to show notifications
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.textContent = message;
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.padding = '15px 20px';
            notification.style.borderRadius = '5px';
            notification.style.color = 'white';
            notification.style.zIndex = '9999';
            notification.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
            notification.style.opacity = '0';
            notification.style.transition = 'opacity 0.3s ease';
            
            // Set color based on notification type
            if (type === 'success') {
                notification.style.backgroundColor = '#28a745';
            } else if (type === 'error') {
                notification.style.backgroundColor = '#dc3545';
            } else if (type === 'warning') {
                notification.style.backgroundColor = '#ffc107';
                notification.style.color = '#212529';
            } else if (type === 'info') {
                notification.style.backgroundColor = '#17a2b8';
            }
            
            document.body.appendChild(notification);
            
            // Fade in
            setTimeout(() => {
                notification.style.opacity = '1';
            }, 10);
            
            // Fade out and remove after 5 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 5000);
        }
        
        // Handle Map Parameters
        document.addEventListener('DOMContentLoaded', function() {
            // Get the map parameter elements
            const mapLatInput = document.getElementById('map-lat');
            const mapLngInput = document.getElementById('map-lng');
            const mapAltitudeInput = document.getElementById('map-altitude');
            const mapCameraRangeInput = document.getElementById('map-camera-range');
            const mapCameraTiltInput = document.getElementById('map-camera-tilt');
            const mapCameraHeadingInput = document.getElementById('map-camera-heading');
            const saveMapParamsBtn = document.getElementById('save-map-params');
            const useCurrentCameraBtn = document.getElementById('use-current-camera');
            

            // Add event listener for the save button
            saveMapParamsBtn.addEventListener('click', function() {
                try {
                    // Get the form values
                    const lat = mapLatInput.value;
                    const lng = mapLngInput.value;
                    const altitude = mapAltitudeInput.value;
                    const camera_range = mapCameraRangeInput.value;
                    const camera_tilt = mapCameraTiltInput.value;
                    const camera_heading = mapCameraHeadingInput.value;
                    
                    // Create FormData object for AJAX request
                    const formData = new FormData();
                    formData.append('update_project', '1');
                    
                    // Get all values from the main form to include them
                    const mainForm = document.getElementById('updateProjectForm');
                    const formElements = mainForm.elements;
                    
                    // Append all form fields to formData (except hidden update_project which we already added)
                    for (let i = 0; i < formElements.length; i++) {
                        const field = formElements[i];
                        if (field.name && field.name !== 'update_project') {
                            formData.append(field.name, field.value);
                        }
                    }
                    
                    // Add the map parameters
                    formData.append('lat', lat);
                    formData.append('lng', lng);
                    formData.append('altitude', altitude);
                    formData.append('camera_range', camera_range);
                    formData.append('camera_tilt', camera_tilt);
                    formData.append('camera_heading', camera_heading);
                    
                    // Send AJAX request
                    fetch('project_map.php?id=<?= $project_id ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (response.ok) {
                            // Show success notification
                            showNotification('Map parameters saved successfully', 'success');
                            
                            // Update project data
                            projectData.lat = parseFloat(lat);
                            projectData.lng = parseFloat(lng);
                            projectData.altitude = parseFloat(altitude);
                            projectData.camera_range = parseFloat(camera_range);
                            projectData.camera_tilt = parseFloat(camera_tilt);
                            projectData.camera_heading = parseFloat(camera_heading);
                        } else {
                            // Show error notification
                            showNotification('Error saving map parameters', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error saving map parameters:', error);
                        showNotification('Error: ' + error.message, 'error');
                    });
                } catch (error) {
                    console.error('Error saving map parameters:', error);
                    showNotification('Error saving map parameters: ' + error.message, 'error');
                }
            });
            
            // Add event listener for the "Use Current Camera" button
            useCurrentCameraBtn.addEventListener('click', function() {
                if (!map) return;
                
                try {
                    // Get current camera values from the map
                    const currentLat = map.center.lat;
                    const currentLng = map.center.lng;
                    const currentAltitude = map.center.altitude || projectData.altitude;
                    const currentRange = map.range;
                    const currentTilt = map.tilt;
                    const currentHeading = map.heading || 0;
                    
                    // Update form inputs with current values
                    mapLatInput.value = currentLat;
                    mapLngInput.value = currentLng;
                    mapAltitudeInput.value = currentAltitude;
                    mapCameraRangeInput.value = currentRange;
                    mapCameraTiltInput.value = currentTilt;
                    mapCameraHeadingInput.value = currentHeading;
                    
                    // Show success notification
                    showNotification('Camera parameters updated with current values', 'success');
                } catch (error) {
                    console.error('Error getting current camera parameters:', error);
                    showNotification('Error getting current camera parameters: ' + error.message, 'error');
                }
            });
        });
        
        // Handle Main Information Form Submission
        document.addEventListener('DOMContentLoaded', function() {
            // Get the form
            const updateProjectForm = document.getElementById('updateProjectForm');
            
            // Add submit event listener
            updateProjectForm.addEventListener('submit', function(event) {
                // Prevent default form submission
                event.preventDefault();
                
                // Create FormData object
                const formData = new FormData(this);
                
                // Send AJAX request
                fetch('project_map.php?id=<?= $project_id ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.ok) {
                        // Show success notification
                        showNotification('Project updated successfully', 'success');
                        
                        // Update project data object with new values
                        updateProjectDataObject();
                    } else {
                        // Show error notification
                        showNotification('Error updating project', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error updating project: ' + error.message, 'error');
                });
            });
            
            // Function to update project data object with form values
            function updateProjectDataObject() {
                const priceInput = updateProjectForm.querySelector('input[name="price"]');
                const currencySelect = updateProjectForm.querySelector('select[name="currency"]');
                
                if (priceInput && currencySelect) {
                    projectData.price = parseFloat(priceInput.value) || 0;
                    projectData.currency = currencySelect.value;
                    
                    // Update marker label if it exists
                    if (window.interactiveMarker) {
                        try {
                            window.interactiveMarker.label = formatPrice(projectData.currency, projectData.price);
                        } catch (e) {
                            console.error('Error updating marker label:', e);
                        }
                    }
                }
            }
        });

        // Function to handle model form submissions via AJAX
        function setupModelUpdateForms() {
            const modelUpdateForms = document.querySelectorAll('.model-form.model-update-form');
            
            modelUpdateForms.forEach(form => {
                form.addEventListener('submit', function(event) {
                    // Prevent default form submission
                    event.preventDefault();
                    
                    // Show loading notification
                    showNotification('Saving model data...', 'info');
                    
                    // Get model ID - only proceed if this input exists
                    const idInput = form.querySelector('input[name="id"]');
                    if (!idInput) return;
                    
                    const modelId = idInput.value;
                    
                    // Create FormData object
                    const formData = new FormData(this);
                    
                    // Send AJAX request
                    fetch('update_model.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(data => {
                        try {
                            // Try to parse as JSON first (in case update_model.php returns JSON)
                            const jsonData = JSON.parse(data);
                            if (jsonData.success) {
                                showNotification('Model saved successfully', 'success');
                                
                                // Update model selector data attributes
                                updateModelSelectorAttributes(form);
                                
                                // If this model is currently active, update it
                                updateActiveModelIfSelected(modelId, form);
                            } else {
                                showNotification('Error saving model: ' + jsonData.error, 'error');
                            }
                        } catch (e) {
                            // If not JSON, assume success (since the original script doesn't return JSON)
                            showNotification('Model saved successfully', 'success');
                            
                            // Update model selector data attributes
                            updateModelSelectorAttributes(form);
                            
                            // If this model is currently active, update it
                            updateActiveModelIfSelected(modelId, form);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('Error saving model: ' + error.message, 'error');
                    });
                });
            });
        }
        
        // Function to update model selector data attributes
        function updateModelSelectorAttributes(form) {
            const modelId = form.querySelector('input[name="id"]').value;
            const selector = document.getElementById('model_' + modelId);
            
            if (selector) {
                selector.dataset.lat = form.querySelector('input[name="lat"]').value;
                selector.dataset.lng = form.querySelector('input[name="lng"]').value;
                selector.dataset.altitudeShow = form.querySelector('input[name="altitude_show"]').value;
                selector.dataset.altitudeFact = form.querySelector('input[name="altitude_fact"]').value;
                selector.dataset.roll = form.querySelector('input[name="roll"]').value;
                selector.dataset.tilt = form.querySelector('input[name="tilt"]').value;
                selector.dataset.scale = form.querySelector('input[name="scale"]').value;
                
                // Update marker dimension attributes
                const markerWidthInput = form.querySelector('input[name="marker_width"]');
                const markerLengthInput = form.querySelector('input[name="marker_length"]');
                const markerHeightInput = form.querySelector('input[name="marker_height"]');
                
                if (markerWidthInput) selector.dataset.markerWidth = markerWidthInput.value;
                if (markerLengthInput) selector.dataset.markerLength = markerLengthInput.value;
                if (markerHeightInput) selector.dataset.markerHeight = markerHeightInput.value;
            }
        }
        
        // Function to update the active model if the updated model is currently selected
        function updateActiveModelIfSelected(modelId, form) {
            const selector = document.getElementById('model_' + modelId);
            
            if (selector && selector.checked && activeModel) {
                // Get updated values
                const lat = parseFloat(form.querySelector('input[name="lat"]').value);
                const lng = parseFloat(form.querySelector('input[name="lng"]').value);
                const altitudeShow = parseFloat(form.querySelector('input[name="altitude_show"]').value);
                const altitudeFact = parseFloat(form.querySelector('input[name="altitude_fact"]').value);
                const roll = parseFloat(form.querySelector('input[name="roll"]').value);
                const tilt = parseFloat(form.querySelector('input[name="tilt"]').value);
                const scale = parseFloat(form.querySelector('input[name="scale"]').value);
                
                // Update model position and orientation
                modelPosition = {
                    lat: lat,
                    lng: lng,
                    altitude: selector.dataset.altitudeShow === altitudeShow.toString() ? altitudeShow : altitudeFact
                };
                
                window.altitudeShow = altitudeShow;
                window.altitudeFact = altitudeFact;
                
                modelOrientation = {
                    tilt: tilt,
                    roll: roll
                };
                
                // Update the active model with new data
                activeModel.position = modelPosition;
                activeModel.orientation = modelOrientation;
                activeModel.scale = scale;
                
                // Update interactive marker and highlight polygon
                updateInteractiveMarker();
                updateHighlightPolygon();
            }
        }
        
        // Initialize the map when the page loads
        window.addEventListener('load', function() {
            setupModelUpdateForms();
        });

        // Screen Recording Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const startRecordingBtn = document.getElementById('startRecording');
            const stopRecordingBtn = document.getElementById('stopRecording');
            const recordingIndicator = document.getElementById('recordingIndicator');
            const screenShotBtn = document.getElementById('screenShot');
            const mapElement = document.getElementById('map');
            const mobileFrame = document.getElementById('mobileRecordingFrame');
            const standardFrame = document.getElementById('standardRecordingFrame');
            const mobileFrameBtn = document.getElementById('mobileFrame');
            const standardFrameBtn = document.getElementById('standardFrame');
            
            let mediaRecorder;
            let recordedChunks = [];
            let recordingStream;
            let canvas;
            let ctx;
            let video;
            let currentFrameType = 'mobile'; // Default to mobile frame
            
            // Initialize recording timer variables
            let recordingTimer;
            let recordingSeconds = 0;
            let recordingMinutes = 0;
            
            // Screenshot functionality
            screenShotBtn.addEventListener('click', async function() {
                try {
                    const activeFrame = currentFrameType === 'mobile' ? mobileFrame : standardFrame;
                    const frameLabel = currentFrameType === 'mobile' ? 'Mobile' : 'Standard';
                    
                    // Enhance active frame appearance during screenshot
                    activeFrame.style.opacity = '1';
                    activeFrame.style.boxShadow = '0 0 30px rgba(40, 167, 69, 0.8)';
                    activeFrame.style.borderColor = '#28a745';
                    
                    // Request screen capture with optimized settings for stability
                    const screenshotStream = await navigator.mediaDevices.getDisplayMedia({
                        video: {
                            cursor: "never",
                            displaySurface: "browser",
                            width: { ideal: 2560 }, // Keep high capture resolution
                            height: { ideal: 1440 }
                        },
                        audio: false,
                        preferCurrentTab: true
                    });
                    
                    // Wait 1 second after screen sharing permission to allow browser notification bar to appear
                    // This ensures frame position is measured after page layout shifts
                    showNotification('Screen sharing enabled. Waiting for layout to stabilize...', 'info');
                    await new Promise(resolve => setTimeout(resolve, 1500));
                    
                    // Store frame position after browser notification bar has appeared
                    const frameRect = activeFrame.getBoundingClientRect();
                    const screenshotFrameRect = {
                        left: frameRect.left,
                        top: frameRect.top,
                        width: frameRect.width,
                        height: frameRect.height
                    };
                    const screenshotDevicePixelRatio = window.devicePixelRatio || 1;
                    const screenshotViewportWidth = window.innerWidth;
                    const screenshotViewportHeight = window.innerHeight;

                    // Create video element to capture frame
                    const screenshotVideo = document.createElement('video');
                    screenshotVideo.srcObject = screenshotStream;
                    screenshotVideo.muted = true;
                    
                    // Wait for video to be ready
                    await new Promise((resolve) => {
                        screenshotVideo.onloadedmetadata = resolve;
                    });
                    
                    // Start playing and wait for it to actually start
                    screenshotVideo.play();
                    await new Promise((resolve) => {
                        screenshotVideo.onplay = resolve;
                    });
                    
                    // Wait for first frame to be available
                    if ('requestVideoFrameCallback' in screenshotVideo) {
                        // Use requestVideoFrameCallback if supported (more reliable)
                        await new Promise((resolve) => {
                            screenshotVideo.requestVideoFrameCallback(resolve);
                        });
                    } else {
                        // Fallback: wait for timeupdate event (indicates frame is ready)
                        await new Promise((resolve) => {
                            const timeUpdateHandler = () => {
                                screenshotVideo.removeEventListener('timeupdate', timeUpdateHandler);
                                resolve();
                            };
                            screenshotVideo.addEventListener('timeupdate', timeUpdateHandler);
                        });
                    }
                    
                    // Create canvas for screenshot processing
                    const screenshotCanvas = document.createElement('canvas');
                    const screenshotCtx = screenshotCanvas.getContext('2d');
                    
                    // Get dimensions based on current frame type (same as video recording)
                    const frameWidth = currentFrameType === 'mobile' ? 475 * 2 : 848 * 2; // 2x resolution
                    const frameHeight = currentFrameType === 'mobile' ? 844 * 2 : 636 * 2; // 2x resolution
                    
                    // Set canvas size to frame dimensions
                    screenshotCanvas.width = frameWidth;
                    screenshotCanvas.height = frameHeight;
                    
                    // Enable high-quality rendering
                    screenshotCtx.imageSmoothingEnabled = true;
                    screenshotCtx.imageSmoothingQuality = 'high';
                    
                    // Calculate the actual dimensions of the captured screen
                    const sourceWidth = screenshotVideo.videoWidth;
                    const sourceHeight = screenshotVideo.videoHeight;
                    
                    // Calculate scaling factor between video capture and viewport (using stored values)
                    const scale = sourceWidth / (screenshotViewportWidth * screenshotDevicePixelRatio);
                    
                    // Calculate the frame position in video coordinates using stored frame position
                    const frameVideoX = screenshotFrameRect.left * screenshotDevicePixelRatio * scale;
                    const frameVideoY = screenshotFrameRect.top * screenshotDevicePixelRatio * scale;
                    const frameVideoWidth = screenshotFrameRect.width * screenshotDevicePixelRatio * scale;
                    const frameVideoHeight = screenshotFrameRect.height * screenshotDevicePixelRatio * scale;
                    
                    // Debug information
                    console.log('Screenshot Debug Info:', {
                        frameRect: screenshotFrameRect,
                        devicePixelRatio: screenshotDevicePixelRatio,
                        sourceWidth: sourceWidth,
                        sourceHeight: sourceHeight,
                        viewportWidth: screenshotViewportWidth,
                        viewportHeight: screenshotViewportHeight,
                        scale: scale,
                        frameVideoX: frameVideoX,
                        frameVideoY: frameVideoY,
                        frameVideoWidth: frameVideoWidth,
                        frameVideoHeight: frameVideoHeight
                    });
                    
                    // Ensure we don't go outside video bounds
                    const cropX = Math.max(0, Math.min(frameVideoX, sourceWidth - frameVideoWidth));
                    const cropY = Math.max(0, Math.min(frameVideoY, sourceHeight - frameVideoHeight));
                    const cropWidth = Math.min(frameVideoWidth, sourceWidth - cropX);
                    const cropHeight = Math.min(frameVideoHeight, sourceHeight - cropY);
                    
                    // Draw the cropped and scaled screenshot
                    screenshotCtx.drawImage(
                        screenshotVideo, 
                        cropX, cropY, cropWidth, cropHeight,
                        0, 0, frameWidth, frameHeight
                    );
                    
                    // Stop the screen capture stream
                    screenshotStream.getTracks().forEach(track => track.stop());
                    
                    // Convert canvas to blob
                    screenshotCanvas.toBlob(async function(blob) {
                        if (blob) {
                            // Upload screenshot to server
                            await uploadScreenshotToServer(blob, frameLabel);
                        } else {
                            showNotification('Error creating screenshot', 'error');
                        }
                        
                        // Reset active frame to default appearance
                        activeFrame.style.opacity = '0.7';
                        activeFrame.style.boxShadow = '0 0 20px rgba(0, 123, 255, 0.5)';
                        activeFrame.style.borderColor = '#007bff';
                    }, 'image/png', 0.95); // High quality PNG
                    
                } catch (error) {
                    console.error('Error taking screenshot:', error);
                    showNotification('Error taking screenshot: ' + error.message, 'error');
                    
                    // Reset active frame to default appearance on error
                    const activeFrame = currentFrameType === 'mobile' ? mobileFrame : standardFrame;
                    activeFrame.style.opacity = '0.7';
                    activeFrame.style.boxShadow = '0 0 20px rgba(0, 123, 255, 0.5)';
                    activeFrame.style.borderColor = '#007bff';
                }
            });
            
            // Function to upload screenshot to server
            async function uploadScreenshotToServer(blob, frameLabel) {
                const formData = new FormData();
                const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
                const projectName = '<?= htmlspecialchars($project["name"]) ?>';
                const safeProjectName = projectName.replace(/[^a-z0-9]/gi, '_').toLowerCase();
                const frameType = currentFrameType === 'mobile' ? 'mobile' : 'standard';
                const dimensions = currentFrameType === 'mobile' ? '780x1688' : '1696x1272';
                const filename = `${safeProjectName}_${frameType}_hd_screenshot_${timestamp}.png`;
                
                formData.append('screenshot', blob, filename);
                formData.append('project_id', '<?= $project_id ?>');
                
                // Show upload progress notification
                showNotification('Uploading screenshot to server...', 'info');
                
                try {
                    const response = await fetch('upload_screenshot.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        showNotification(`High-resolution ${frameType} screenshot (${dimensions}) saved successfully!`, 'success');
                    } else {
                        showNotification('Error uploading screenshot: ' + data.error, 'error');
                    }
                } catch (error) {
                    console.error('Upload error:', error);
                    showNotification('Error uploading screenshot: ' + error.message, 'error');
                }
            }
            

            
            // Frame switching functionality
            mobileFrameBtn.addEventListener('click', function() {
                currentFrameType = 'mobile';
                mobileFrame.style.display = 'block';
                standardFrame.style.display = 'none';
                
                // Update button states
                mobileFrameBtn.classList.add('active');
                standardFrameBtn.classList.remove('active');
                
                // Update recording button text
                startRecordingBtn.textContent = 'Start Mobile Recording 🔴';
                
                showNotification('Mobile frame (16:9) selected', 'info');
            });
            
            standardFrameBtn.addEventListener('click', function() {
                currentFrameType = 'standard';
                mobileFrame.style.display = 'none';
                standardFrame.style.display = 'block';
                
                // Update button states
                standardFrameBtn.classList.add('active');
                mobileFrameBtn.classList.remove('active');
                
                // Update recording button text
                startRecordingBtn.textContent = 'Start Standard Recording 🔴';
                
                showNotification('Standard frame (4:3) selected', 'info');
            });
            
            // Initialize with mobile frame active
            mobileFrameBtn.click();
            
            // Mobile recording dimensions (iPhone 14 Pro size at 2x resolution for high quality)
            const mobileWidth = 475 * 2; // 780px - 2x resolution
            const mobileHeight = 844 * 2; // 1688px - 2x resolution
            const mobileDisplayWidth = 475; // Visual frame size stays the same
            const mobileDisplayHeight = 844; // Visual frame size stays the same
            
            // Standard recording dimensions (4:3 aspect ratio at 2x resolution)
            const standardWidth = 848 * 2; // 1696px - 2x resolution
            const standardHeight = 636 * 2; // 1272px - 2x resolution
            const standardDisplayWidth = 848; // Visual frame size stays the same
            const standardDisplayHeight = 636; // Visual frame size stays the same
            
            // Start recording function
            startRecordingBtn.addEventListener('click', async function() {
                try {
                    const activeFrame = currentFrameType === 'mobile' ? mobileFrame : standardFrame;
                    const frameLabel = currentFrameType === 'mobile' ? 'Mobile' : 'Standard';
                    
                    // Enhance active frame appearance during recording
                    activeFrame.style.opacity = '1';
                    activeFrame.style.boxShadow = '0 0 30px rgba(220, 53, 69, 0.8)';
                    activeFrame.style.borderColor = '#dc3545';
                    
                    // Request screen capture with optimized settings for stability
                    recordingStream = await navigator.mediaDevices.getDisplayMedia({
                        video: {
                            cursor: "never",
                            displaySurface: "browser",
                            width: { ideal: 2560 }, // Keep high capture resolution
                            height: { ideal: 1440 },
                            frameRate: { ideal: 60 } // Reduced from 60fps to 30fps for stability
                        },
                        audio: false,
                        preferCurrentTab: true
                    });
                    
                    // Wait 1 second after screen sharing permission to allow browser notification bar to appear
                    // This ensures frame position is measured after page layout shifts
                    showNotification('Screen sharing enabled. Waiting for layout to stabilize...', 'info');
                    await new Promise(resolve => setTimeout(resolve, 1500));
                    
                    // Store frame position after browser notification bar has appeared
                    const frameRect = activeFrame.getBoundingClientRect();
                    window.recordingFrameRect = {
                        left: frameRect.left,
                        top: frameRect.top,
                        width: frameRect.width,
                        height: frameRect.height
                    };
                    window.recordingDevicePixelRatio = window.devicePixelRatio || 1;
                    window.recordingViewportWidth = window.innerWidth;
                    window.recordingViewportHeight = window.innerHeight;
                    
                    // Create MediaRecorder with optimized bitrate for better performance
                    mediaRecorder = new MediaRecorder(recordingStream, {
                        videoBitsPerSecond: 5000000 // Reduced from 8Mbps to 5Mbps for stability
                    });
                    
                    // Handle data available event
                    mediaRecorder.ondataavailable = function(event) {
                        if (event.data.size > 0) {
                            recordedChunks.push(event.data);
                        }
                    };
                    
                    // Handle recording stop
                    mediaRecorder.onstop = function() {
                        // Create blob from recorded chunks
                        const blob = new Blob(recordedChunks, {
                            type: 'video/webm'
                        });
                        
                        // Process the video to crop to selected frame size
                        processAndDownloadVideo(blob);
                        
                        // Reset recording
                        recordedChunks = [];
                        
                        // Reset timer
                        clearInterval(recordingTimer);
                        recordingSeconds = 0;
                        recordingMinutes = 0;
                        recordingIndicator.textContent = "🔴 RECORDING";
                        
                        // Reset active frame to default appearance
                        activeFrame.style.opacity = '0.7';
                        activeFrame.style.boxShadow = '0 0 20px rgba(0, 123, 255, 0.5)';
                        activeFrame.style.borderColor = '#007bff';
                        
                        // Clear stored frame position
                        delete window.recordingFrameRect;
                        delete window.recordingDevicePixelRatio;
                        delete window.recordingViewportWidth;
                        delete window.recordingViewportHeight;
                    };
                    
                    // Start recording with increased chunk interval for more stable recording
                    mediaRecorder.start(1000); // Increased from 500ms to 1000ms for stability
                    
                    // Update UI
                    startRecordingBtn.style.display = 'none';
                    stopRecordingBtn.style.display = 'block';
                    recordingIndicator.style.display = 'block';
                    
                    // Start recording timer
                    recordingTimer = setInterval(function() {
                        recordingSeconds++;
                        if (recordingSeconds >= 60) {
                            recordingMinutes++;
                            recordingSeconds = 0;
                        }
                        
                        // Update recording indicator with time
                        const formattedMinutes = recordingMinutes.toString().padStart(2, '0');
                        const formattedSeconds = recordingSeconds.toString().padStart(2, '0');
                        recordingIndicator.textContent = `🔴 HD ${frameLabel.toUpperCase()} REC ${formattedMinutes}:${formattedSeconds}`;
                    }, 1000);
                    
                    // Add event listener for track ended (user cancels)
                    recordingStream.getVideoTracks()[0].onended = function() {
                        stopRecording();
                    };
                    
                    // Show notification with instructions
                    const dimensions = currentFrameType === 'mobile' ? '780x1688' : '1696x1272';
                    showNotification(`Recording started! Frame position stabilized after browser notification. Recording at 2x resolution (${dimensions}) @ 30fps for optimized ${frameLabel.toLowerCase()} video.`, 'success');
                } catch (error) {
                    console.error('Error starting recording:', error);
                    showNotification('Error starting recording: ' + error.message, 'error');
                    // Reset active frame to default appearance on error
                    const activeFrame = currentFrameType === 'mobile' ? mobileFrame : standardFrame;
                    activeFrame.style.opacity = '0.7';
                    activeFrame.style.boxShadow = '0 0 20px rgba(0, 123, 255, 0.5)';
                    activeFrame.style.borderColor = '#007bff';
                }
            });
            
            // Function to process and crop video to selected frame size
            function processAndDownloadVideo(blob) {
                // Create video element to process the recorded content
                video = document.createElement('video');
                video.src = URL.createObjectURL(blob);
                video.muted = true;
                
                video.onloadedmetadata = function() {
                    // Get dimensions based on current frame type
                    const frameWidth = currentFrameType === 'mobile' ? mobileWidth : standardWidth;
                    const frameHeight = currentFrameType === 'mobile' ? mobileHeight : standardHeight;
                    
                    // Create canvas for processing at 2x resolution
                    canvas = document.createElement('canvas');
                    canvas.width = frameWidth;
                    canvas.height = frameHeight;
                    ctx = canvas.getContext('2d');
                    
                    // Enable high-quality rendering
                    ctx.imageSmoothingEnabled = true;
                    ctx.imageSmoothingQuality = 'high';
                    
                    // Use stored frame position for stability (captured at recording start)
                    let frameRect = window.recordingFrameRect;
                    let devicePixelRatio = window.recordingDevicePixelRatio;
                    let viewportWidth = window.recordingViewportWidth;
                    let viewportHeight = window.recordingViewportHeight;
                    
                    // Fallback if stored values are missing
                    if (!frameRect || !devicePixelRatio || !viewportWidth || !viewportHeight) {
                        console.warn('Stored frame position missing, using current frame position');
                        const activeFrame = currentFrameType === 'mobile' ? mobileFrame : standardFrame;
                        const currentFrameRect = activeFrame.getBoundingClientRect();
                        frameRect = {
                            left: currentFrameRect.left,
                            top: currentFrameRect.top,
                            width: currentFrameRect.width,
                            height: currentFrameRect.height
                        };
                        devicePixelRatio = window.devicePixelRatio || 1;
                        viewportWidth = window.innerWidth;
                        viewportHeight = window.innerHeight;
                    }
                    
                    // Calculate the actual dimensions of the recorded video
                    const sourceWidth = video.videoWidth;
                    const sourceHeight = video.videoHeight;
                    
                    // Calculate scaling factor between video capture and viewport (using stored values)
                    const scale = sourceWidth / (viewportWidth * devicePixelRatio);
                    
                    // Calculate the frame position in video coordinates using stored frame position
                    const frameVideoX = frameRect.left * devicePixelRatio * scale;
                    const frameVideoY = frameRect.top * devicePixelRatio * scale;
                    const frameVideoWidth = frameRect.width * devicePixelRatio * scale;
                    const frameVideoHeight = frameRect.height * devicePixelRatio * scale;
                    
                    // Debug information
                    console.log('Video Recording Debug Info:', {
                        usingStoredValues: !!window.recordingFrameRect,
                        frameRect: frameRect,
                        devicePixelRatio: devicePixelRatio,
                        sourceWidth: sourceWidth,
                        sourceHeight: sourceHeight,
                        viewportWidth: viewportWidth,
                        viewportHeight: viewportHeight,
                        scale: scale,
                        frameVideoX: frameVideoX,
                        frameVideoY: frameVideoY,
                        frameVideoWidth: frameVideoWidth,
                        frameVideoHeight: frameVideoHeight
                    });
                    
                    // Ensure we don't go outside video bounds
                    const cropX = Math.max(0, Math.min(frameVideoX, sourceWidth - frameVideoWidth));
                    const cropY = Math.max(0, Math.min(frameVideoY, sourceHeight - frameVideoHeight));
                    const cropWidth = Math.min(frameVideoWidth, sourceWidth - cropX);
                    const cropHeight = Math.min(frameVideoHeight, sourceHeight - cropY);
                    
                    // Set up video processing
                    video.currentTime = 0;
                    
                    // Create new MediaRecorder for the processed video with optimized settings
                    const processedStream = canvas.captureStream(60); // Reduced from 60fps to 30fps for stability
                    const processedRecorder = new MediaRecorder(processedStream, {
                        videoBitsPerSecond: 8000000 // Reduced from 12Mbps to 8Mbps for better performance
                    });
                    
                    const processedChunks = [];
                    
                    processedRecorder.ondataavailable = function(event) {
                        if (event.data.size > 0) {
                            processedChunks.push(event.data);
                        }
                    };
                    
                    processedRecorder.onstop = function() {
                        const processedBlob = new Blob(processedChunks, {
                            type: 'video/webm'
                        });
                        
                        // Upload the processed video to server
                        uploadVideoToServer(processedBlob);
                    };
                    
                    // Start processing
                    processedRecorder.start();
                    video.play();
                    
                    // Draw frames to canvas with high quality
                    function drawFrame() {
                        if (video.currentTime < video.duration && !video.ended) {
                            // Clear canvas
                            ctx.clearRect(0, 0, frameWidth, frameHeight);
                            
                            // Draw video frame scaled to 2x resolution using calculated crop coordinates
                            ctx.drawImage(video, cropX, cropY, cropWidth, cropHeight, 0, 0, frameWidth, frameHeight);
                            
                            requestAnimationFrame(drawFrame);
                        } else {
                            // End processing
                            processedRecorder.stop();
                            video.pause();
                        }
                    }
                    
                    // Start drawing frames
                    requestAnimationFrame(drawFrame);
                };
                
                video.onerror = function() {
                    console.error('Error processing video');
                    showNotification('Error processing video. Uploading original...', 'warning');
                    uploadVideoToServer(blob);
                };
            }
            
            // Function to upload video to server
            function uploadVideoToServer(blob) {
                const formData = new FormData();
                const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
                const projectName = '<?= htmlspecialchars($project["name"]) ?>';
                const safeProjectName = projectName.replace(/[^a-z0-9]/gi, '_').toLowerCase();
                const frameType = currentFrameType === 'mobile' ? 'mobile' : 'standard';
                const dimensions = currentFrameType === 'mobile' ? '780x1688' : '1696x1272';
                const filename = `${safeProjectName}_${frameType}_hd_recording_${timestamp}.webm`;
                
                formData.append('video', blob, filename);
                formData.append('project_id', '<?= $project_id ?>');
                
                // Show upload progress notification
                showNotification('Uploading video to server...', 'info');
                
                fetch('upload_video.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(`High-resolution ${frameType} recording (${dimensions}) uploaded successfully!`, 'success');
                    } else {
                        showNotification('Error uploading video: ' + data.error, 'error');
                    }
                })
                .catch(error => {
                    console.error('Upload error:', error);
                    showNotification('Error uploading video: ' + error.message, 'error');
                });
            }
            
            // Stop recording function
            stopRecordingBtn.addEventListener('click', stopRecording);
            
            function stopRecording() {
                if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                    mediaRecorder.stop();
                    
                    // Stop all tracks
                    if (recordingStream) {
                        recordingStream.getTracks().forEach(track => track.stop());
                    }
                    
                    // Update UI
                    startRecordingBtn.style.display = 'block';
                    stopRecordingBtn.style.display = 'none';
                    recordingIndicator.style.display = 'none';
                    
                    // Reset active frame to default appearance
                    const activeFrame = currentFrameType === 'mobile' ? mobileFrame : standardFrame;
                    activeFrame.style.opacity = '0.7';
                    activeFrame.style.boxShadow = '0 0 20px rgba(0, 123, 255, 0.5)';
                    activeFrame.style.borderColor = '#007bff';
                    
                    // Show notification
                    const frameType = currentFrameType === 'mobile' ? 'mobile' : 'standard';
                    showNotification(`Processing high-resolution ${frameType} recording...`, 'info');
                }
            }
        });
    </script>
</body>
</html>
