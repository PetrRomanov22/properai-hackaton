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
    $camera_heading = $_POST["camera_heading"] ?? 0;
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
    $stmt->bind_param("sssssddddidssssiisii", $name, $description, $address, $country, $city,
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
    
    <style>
        @keyframes control-button-pulse {
            0% {
                opacity: 1;
                box-shadow: 0 0 20px rgba(255, 193, 7, 0.8);
            }
            50% {
                opacity: 0.7;
                box-shadow: 0 0 30px rgba(255, 193, 7, 1);
            }
            100% {
                opacity: 1;
                box-shadow: 0 0 20px rgba(255, 193, 7, 0.8);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <div class="project-content">
            <!-- Left sidebar for Main Info and General Location -->
            <div class="project-sidebar">
                <h2>Project: <?= htmlspecialchars($project["name"]) ?></h2>
                <p><a href="../account.php" class="sidebar-nav-btn">← Back to My Projects</a></p>
                <br>
                <h2>Project</h2>
                <p><a href="project.php?id=<?= $project_id ?>" class="sidebar-nav-btn">Project →</a></p>
                <br>
                <h2>3D map</h2>
                <p><a href="project_map.php?id=<?= $project_id ?>" class="sidebar-nav-btn active">🗺️ Edit 3d map →</a></p>
                <p><a href="project_map_animation.php?id=<?= $project_id ?>" class="sidebar-nav-btn">🎬 Animation →</a></p>
                <br>
                <h2>Share your apartment</h2>
                <p><a href="project_share.php?id=<?= $project_id ?>" class="sidebar-nav-btn">Preview</a></p>
            </div>
            
            <!-- Hidden form for project updates (separate from sidebar) -->
            <form method="POST" id="updateProjectForm" style="display: none;">
                <input type="hidden" name="update_project" value="1">
                <input type="hidden" name="name" value="<?= htmlspecialchars($project["name"]) ?>">
                <input type="hidden" name="price" value="<?= htmlspecialchars($project["price"] ?? '') ?>">
                <input type="hidden" name="currency" value="<?= htmlspecialchars($project["currency"] ?? 'USD') ?>">
                <input type="hidden" name="address" value="<?= htmlspecialchars($project["address"]) ?>">
                <input type="hidden" name="size" value="<?= htmlspecialchars($project["size"] ?? '') ?>">
                <input type="hidden" name="size_unit" value="<?= htmlspecialchars($project["size_unit"] ?? 'sq.m') ?>">
                <input type="hidden" name="bedrooms" value="<?= htmlspecialchars($project["bedrooms"] ?? '') ?>">
                <input type="hidden" name="floor" value="<?= htmlspecialchars($project["floor"] ?? '') ?>">
                <input type="hidden" name="type" value="<?= htmlspecialchars($project["type"] ?? '') ?>">
                <input type="hidden" name="description" value="<?= htmlspecialchars($project["description"]) ?>">
                <input type="hidden" name="country" value="<?= htmlspecialchars($project["country"]) ?>">
                <input type="hidden" name="city" value="<?= htmlspecialchars($project["city"]) ?>">
            </form>
            
            <div class="map-container">
                <div id="map"></div>
                
                <button class="map-control-btn popover-toggle-btn" id="popoverToggleBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15h2v-6h-2v6zm0-8h2V7h-2v2z"/>
                    </svg>
                    Popover: ON
                </button>
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
            </div>
            
            <div class="project-details">
                <!-- Save and Continue Button -->
                <div style="text-align: center; margin-bottom: 30px;">
                    <button type="button" id="saveContinueBtn"
                       class="save-continue-btn" 
                       style="display: inline-block; background: linear-gradient(135deg, #007bff, #0056b3); color: white; padding: 20px 40px; border: none; cursor: pointer; border-radius: 12px; font-size: 20px; font-weight: 600; box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3); transition: all 0.3s ease; text-transform: uppercase; letter-spacing: 1px;"
                       onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(0, 123, 255, 0.4)'"
                       onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(0, 123, 255, 0.3)'">
                        🎬 Save & Continue to Animation
                    </button>
                    <p style="margin-top: 10px; color: #666; font-size: 14px;">Continue to the next step to create animated camera sequences</p>
                </div>
                
                <!-- 3D Map Parameters Section - now separate from the form -->
                <div class="model-form">
                    <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 15px;">3D Map Parameters</h3>
                    
                    <!-- Address Selection -->
                    <div class="address-section">
                        <label for="current-address">Current Address:</label>
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                            <input type="text" id="current-address" class="model-form" style="width: 70%; cursor: default;" 
                                   value="<?= htmlspecialchars($project["address"]) ?>" readonly>
                            <button type="button" id="change-address-btn" class="btn btn-secondary" style="flex: 1;">Change Address</button>
                        </div>
                        
                        <!-- Country and City (readonly, auto-populated) -->
                        <div style="display: flex; gap: 10px;">
                            <div style="flex: 1;">
                                <label for="map-country">Country:</label>
                                <input type="text" id="map-country" class="model-form" style="width: 100%;" 
                                       value="<?= htmlspecialchars($project["country"]) ?>" readonly>
                            </div>
                            <div style="flex: 1;">
                                <label for="map-city">City:</label>
                                <input type="text" id="map-city" class="model-form" style="width: 100%;" 
                                       value="<?= htmlspecialchars($project["city"]) ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <!-- Address Change Overlay -->
                    <div id="address-overlay" class="address-overlay" style="display: none;">
                        <div class="address-modal">
                            <div class="address-modal-header">
                                <h3>Change Project Address</h3>
                                <button type="button" id="close-address-modal" class="close-btn">&times;</button>
                            </div>
                            <div class="address-modal-content">
                                <div class="address-input-section">
                                    <label for="map-address">Search Address:</label>
                                    <input type="text" id="map-address" class="model-form" style="width: 100%; margin-bottom: 15px;" 
                                           value="<?= htmlspecialchars($project["address"]) ?>" placeholder="Enter new project address">
                                </div>
                                
                                <!-- Mini Map Container -->
                                <div id="map-mini-map" style="width: 100%; height: 300px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px;"></div>
                                
                                <!-- Coordinates Display -->
                                <div class="coordinates-display" style="display: flex; gap: 10px; margin-bottom: 15px;">
                                    <div style="flex: 1;">
                                        <label>Latitude:</label>
                                        <input type="text" id="modal-lat" readonly style="width: 100%; background: #f5f5f5;">
                                    </div>
                                    <div style="flex: 1;">
                                        <label>Longitude:</label>
                                        <input type="text" id="modal-lng" readonly style="width: 100%; background: #f5f5f5;">
                                    </div>
                                </div>
                                
                                <!-- Modal Action Buttons -->
                                <div class="modal-actions" style="display: flex; gap: 10px; justify-content: flex-end;">
                                    <button type="button" id="cancel-address-change" class="btn btn-secondary">Cancel</button>
                                    <button type="button" id="apply-address-change" class="btn btn-primary">Apply Changes</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <style>
                        .address-overlay {
                            position: fixed;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background: rgba(0, 0, 0, 0.5);
                            z-index: 1000;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        }

                        .address-modal {
                            background: white;
                            width: 90%;
                            max-width: 600px;
                            max-height: 90%;
                            border-radius: 8px;
                            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
                            overflow: hidden;
                        }

                        .address-modal-header {
                            background: #f8f9fa;
                            padding: 15px 20px;
                            border-bottom: 1px solid #ddd;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                        }

                        .address-modal-header h3 {
                            margin: 0;
                            color: #333;
                        }

                        .close-btn {
                            background: none;
                            border: none;
                            font-size: 24px;
                            color: #666;
                            cursor: pointer;
                            padding: 0;
                            width: 30px;
                            height: 30px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        }

                        .close-btn:hover {
                            color: #000;
                            background: #e9ecef;
                            border-radius: 50%;
                        }

                        .address-modal-content {
                            padding: 20px;
                            max-height: calc(90vh - 80px);
                            overflow-y: auto;
                        }

                        .modal-actions {
                            margin-top: 20px;
                            padding-top: 15px;
                            border-top: 1px solid #ddd;
                        }

                        @media (max-width: 768px) {
                            .address-modal {
                                width: 95%;
                                margin: 10px;
                            }
                            
                            .coordinates-display {
                                flex-direction: column;
                            }
                            
                            .modal-actions {
                                flex-direction: column;
                            }
                            
                            .modal-actions button {
                                width: 100%;
                                margin-bottom: 10px;
                            }
                        }
                    </style>
                    
                    <!-- Coordinates -->
                    <div class="coordinates-section">
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
                    </div> <!-- Close coordinates-section -->
                </div>

                <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 15px;">Upload 3D Model</h3>
                <form action="upload_model.php" method="POST" enctype="multipart/form-data" class="model-form">
                    <input type="hidden" name="project_id" value="<?= $project["id"] ?>">
                    <div><input type="file" name="model_file" style="width: 100%; padding: 8px 0;" required></div>
                    <div style="margin-top: 15px;">
                        <button type="submit" class="btn btn-primary">Upload Model</button>
                    </div>
                </form>
                
                <!-- Use Placeholder Model Form -->
                <form action="upload_placeholder_model.php" method="POST" class="model-form" style="margin-top: 10px;">
                    <input type="hidden" name="project_id" value="<?= $project["id"] ?>">
                    <div>
                        <button type="submit" class="btn btn-secondary">Use Placeholder</button>
                        <small style="display: block; margin-top: 5px; color: #666;">Use a default 3D model for testing</small>
                    </div>
                </form>

                <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 15px;">Models</h3>
                <div class="models-section">
                    <?php if(mysqli_num_rows($models) == 0): ?>
                    <p>No models uploaded yet.</p>
                    <?php else: ?>
                        <?php foreach ($modelsList as $model): ?>
                        <div class="model-item">
                            <form action="update_model.php" method="POST" class="model-form model-update-form" novalidate>
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
                                           data-marker-width="<?= $model['marker_width'] ? ($model['marker_width'] * 100000) : '7' ?>"
                                           data-marker-length="<?= $model['marker_length'] ? ($model['marker_length'] * 100000) : '7' ?>"
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
                                <div style="margin-top: 10px;"><label>Altitude (shown): <input type="number" name="altitude_show" step="any" style="width: 100%;" value="<?= $model['altitude_show'] ? $model['altitude_show'] : $project['altitude'] ?>"></label></div>
                                <div style="margin-top: 10px;"><label>Altitude (real): <input type="number" name="altitude_fact" step="any" style="width: 100%;" value="<?= $model['altitude_fact'] ? $model['altitude_fact'] : ($project['altitude'] - 10) ?>"></label></div>
                                
                                <h4 style="margin-top: 15px; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Marker Size Adjustments</h4>
                                <div style="margin-top: 10px;"><label>Marker Width: <input type="number" name="marker_width" step="0.1" style="width: 100%;" value="<?= ($model['marker_width'] ? $model['marker_width'] * 100000 : 7) ?>"></label></div>
                                <div style="margin-top: 10px;"><label>Marker Length: <input type="number" name="marker_length" step="0.1" style="width: 100%;" value="<?= ($model['marker_length'] ? $model['marker_length'] * 100000 : 7) ?>"></label></div>
                                <div style="margin-top: 10px;"><label>Marker Height: <input type="number" name="marker_height" step="0.5" style="width: 100%;" value="<?= $model['marker_height'] ? $model['marker_height'] : '3.5' ?>"></label></div>
                                
                                <div style="margin-top: 15px;">
                                    <button type="submit" class="btn btn-primary save-model-btn">Save</button>
                                    <button type="button" class="btn btn-secondary replace-model-btn" data-model-id="<?= $model['id'] ?>">Replace Model</button>
                                    <button type="button" class="delete-model-btn" data-model-id="<?= $model['id'] ?>" data-file-path="<?= htmlspecialchars($model['file_path']) ?>">Delete Model</button>
                                </div>
                            </form>
                            
                            <!-- Hidden replace model form - MOVED OUTSIDE the main form -->
                            <div class="replace-model-form" id="replace-form-<?= $model['id'] ?>" style="display: none; margin-top: 15px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9;">
                                <h4>Replace Model File</h4>
                                <p><small>Upload a new 3D model file to replace the current one. All positioning and scaling parameters will be preserved.</small></p>
                                <form class="replace-model-form-element" enctype="multipart/form-data">
                                    <input type="hidden" name="model_id" value="<?= $model['id'] ?>">
                                    <input type="hidden" name="project_id" value="<?= $project['id'] ?>">
                                    <div style="margin-bottom: 10px;">
                                        <input type="file" name="model_file" accept=".glb,.gltf,.obj,.fbx,.dae,.3ds,.ply,.stl" style="width: 100%; padding: 8px;">
                                    </div>
                                    <div>
                                        <button type="submit" class="btn btn-primary">Upload Replacement</button>
                                        <button type="button" class="btn btn-secondary cancel-replace-btn">Cancel</button>
                                    </div>
                                </form>
                            </div>
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
        let currentPopover = null; // Store reference to current popover
        let popoverEnabled = true; // Track popover toggle state
        
        async function init() {
            const { Map3DElement, MapMode, Model3DElement, Model3DInteractiveElement, Marker3DInteractiveElement } = await google.maps.importLibrary("maps3d");
            const { PinElement } = await google.maps.importLibrary("marker");
            const { PopoverElement } = await google.maps.importLibrary("maps3d");
            
            // Check if there's an initially checked model to determine center position
            const initialModel = document.querySelector('.model-selector:checked');
            let initialCenter;
            
            // Altitude always comes from map altitude parameter
            const mapAltitude = parseFloat(document.getElementById('map-altitude').value) || projectData.altitude;
            
            if (initialModel) {
                // Use model position for lat/lng, map altitude for altitude
                initialCenter = {
                    lat: parseFloat(initialModel.dataset.lat), 
                    lng: parseFloat(initialModel.dataset.lng), 
                    altitude: mapAltitude
                };
            } else {
                // No model loaded, use 3D map parameters for lat/lng, map altitude for altitude
                initialCenter = {
                    lat: parseFloat(document.getElementById('map-lat').value) || projectData.lat,
                    lng: parseFloat(document.getElementById('map-lng').value) || projectData.lng,
                    altitude: mapAltitude
                };
            }
            
            viewOrientation = initialCenter;
            
            // Use 3D map parameter inputs for camera settings (same as fly around behavior)
            const mapTilt = parseFloat(document.getElementById('map-camera-tilt').value) || projectData.camera_tilt;
            const mapRange = parseFloat(document.getElementById('map-camera-range').value) || projectData.camera_range;
            const mapHeading = parseFloat(document.getElementById('map-camera-heading').value) || projectData.camera_heading;
            
            // Create the map
            map = new Map3DElement({
                center: initialCenter,
                range: mapRange,
                tilt: mapTilt,
                heading: mapHeading,
                mode: MapMode.SATELLITE
            });
            
            document.getElementById('map').appendChild(map);
            
            // Add event listeners to model checkboxes
            setupModelSelectors(Model3DInteractiveElement);
            
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
            
            // Set up delete model buttons
            setupDeleteModelButtons();
            
            // Initialize popover toggle button style
            initializePopoverToggleButton();
            
            // Show hint and highlight control buttons after a short delay
            setTimeout(() => {
                showControlButtonsHint();
                highlightControlButtons();
            }, 1000);
        }
        
        // Function to set up model selector checkboxes
        function setupModelSelectors(Model3DInteractiveElement) {
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
                        loadSelectedModel(this, Model3DInteractiveElement);
                        
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
                loadSelectedModel(initialModel, Model3DInteractiveElement);
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
        function loadSelectedModel(selector, Model3DInteractiveElement) {
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
                
                // Ensure the event listener is present on this model
                if (!activeModel.hasRandomScaleListener) {
                    // Store original scale if not already stored
                    if (!activeModel.originalScale) {
                        activeModel.originalScale = parseFloat(selector.dataset.scale || 1);
                    }
                    activeModel.addEventListener('gmp-click', (event) => {
                        console.log('Existing model clicked!');
                        console.log('Current scale:', activeModel.scale);
                        console.log('Current scale.x:', activeModel.scale.x);
                        console.log('Original scale:', activeModel.originalScale);
                        
                        // Check if current scale matches original scale (compare the x component)
                        if (activeModel.scale.x === activeModel.originalScale) {
                            activeModel.scale = 2;
                            console.log('Scaled to 2');
                        } else {
                            activeModel.scale = activeModel.originalScale;
                            console.log('Scaled back to original:', activeModel.originalScale);
                        }
                    });
                    activeModel.hasRandomScaleListener = true;
                }
                
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
            const newModel = new Model3DInteractiveElement({
                src: selector.dataset.filePath,
                position: modelPosition,
                altitudeMode: 'RELATIVE_TO_GROUND',
                orientation: modelOrientation,
                scale: parseFloat(selector.dataset.scale || 1)
            });
            
            // Store original scale and add click event listener to toggle scale
            newModel.originalScale = parseFloat(selector.dataset.scale || 1);
            newModel.addEventListener('gmp-click', (event) => {
                console.log('Model clicked!');
                console.log('Current scale:', newModel.scale);
                console.log('Current scale.x:', newModel.scale.x);
                console.log('Original scale:', newModel.originalScale);
                
                // Check if current scale matches original scale (compare the x component)
                if (newModel.scale.x === newModel.originalScale) {
                    newModel.scale = 2;
                    console.log('Scaled to 2');
                } else {
                    newModel.scale = newModel.originalScale;
                    console.log('Scaled back to original:', newModel.originalScale);
                }
            });
            newModel.hasRandomScaleListener = true;
            
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
            
            // Center map on this model (preserve camera altitude from map parameters)
            const mapAltitude = parseFloat(document.getElementById('map-altitude').value) || projectData.altitude;
            map.center = {
                lat: modelPosition.lat,
                lng: modelPosition.lng,
                altitude: mapAltitude
            };
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
        function updateInteractiveMarker(forceRecreate = false) {
            if (!modelPosition || !map) return;
            
            if (window.interactiveMarker && !forceRecreate) {
                // Update existing marker position
                try {
                    window.interactiveMarker.position = {
                        lat: modelPosition.lat, 
                        lng: modelPosition.lng, 
                        altitude: altitudeShow + 3.7
                    };
                    
                    // Also update the label with current price
                    window.interactiveMarker.label = formatPrice(projectData.currency, projectData.price);
                    
                    // Update popover position if it exists
                    if (currentPopover) {
                        currentPopover.positionAnchor = window.interactiveMarker;
                    }
                    
                    return; // Successfully updated, exit the function
                } catch (e) {
                    console.log('Error updating marker position, recreating marker:', e);
                    try {
                        map.removeChild(window.interactiveMarker);
                    } catch (removeError) {
                        // Ignore errors if marker can't be removed
                    }
                    window.interactiveMarker = null;
                }
            }
            
            // Remove existing marker if we're forcing recreation
            if (window.interactiveMarker && forceRecreate) {
                try {
                    map.removeChild(window.interactiveMarker);
                } catch (removeError) {
                    // Ignore errors if marker can't be removed
                }
                window.interactiveMarker = null;
                
                // Also remove the popover if it exists
                if (currentPopover) {
                    try {
                        map.removeChild(currentPopover);
                    } catch (removeError) {
                        // Ignore errors if popover can't be removed
                    }
                    currentPopover = null;
                }
            }
            
            // Create new marker only if needed (if it doesn't exist or update failed)
            const createMarker = async () => {
                const { PinElement } = await google.maps.importLibrary("marker");
                const { Marker3DInteractiveElement } = await google.maps.importLibrary("maps3d");
                const { PopoverElement } = await google.maps.importLibrary("maps3d");
                
                // Remove existing popover if it exists
                if (currentPopover) {
                    try {
                        map.removeChild(currentPopover);
                    } catch (e) {
                        console.error('Error removing existing popover:', e);
                    }
                    currentPopover = null;
                }
                
                const pinScaled = new PinElement({
                    scale: 2.2,
                });
                
                const marker = new Marker3DInteractiveElement ({
                    position: {
                        lat: modelPosition.lat, 
                        lng: modelPosition.lng, 
                        altitude: altitudeShow + 3.7
                    },
                    label: formatPrice(projectData.currency, projectData.price),
                    altitudeMode: 'RELATIVE_TO_GROUND',
                    extruded: true,
                    sizePreserved: true,
                });
                
                marker.append(pinScaled);
                map.append(marker);
                
                // Create popover with project information
                const popover = new PopoverElement({
                    open: false,
                    positionAnchor: marker,
                });
                
                // Create popover content with project information
                const popoverContent = document.createElement('div');
                popoverContent.style.padding = '10px';
                popoverContent.style.minWidth = '200px';
                popoverContent.style.fontFamily = 'Arial, sans-serif';
                popoverContent.style.fontSize = '14px';
                popoverContent.style.lineHeight = '1.4';
                
                // Add project information
                let contentHTML = `<strong><?= htmlspecialchars($project["name"]) ?></strong><br/>`;
                contentHTML += `<div style="margin-top: 8px;">`;
                
                <?php if (!empty($project["size"])): ?>
                contentHTML += `<div><strong>Size:</strong> <?= htmlspecialchars($project["size"]) ?> <?= htmlspecialchars($project["size_unit"] ?? 'sq.m') ?></div>`;
                <?php endif; ?>
                
                <?php if (!empty($project["floor"])): ?>
                contentHTML += `<div><strong>Floor:</strong> <?= htmlspecialchars($project["floor"]) ?></div>`;
                <?php endif; ?>
                
                <?php if (!empty($project["bedrooms"])): ?>
                contentHTML += `<div><strong>Bedrooms:</strong> <?= htmlspecialchars($project["bedrooms"]) ?></div>`;
                <?php endif; ?>
                
                <?php if (!empty($project["type"])): ?>
                contentHTML += `<div><strong>Type:</strong> <?= htmlspecialchars($project["type"]) ?></div>`;
                <?php endif; ?>
                
                contentHTML += `</div>`;
                
                popoverContent.innerHTML = contentHTML;
                popover.append(popoverContent);
                
                // Add popover to map
                map.append(popover);
                
                // Store reference to current popover
                currentPopover = popover;
                
                // Store reference to marker
                window.interactiveMarker = marker;
                
                // Add click event listener for altitude animation and popover toggle
                marker.addEventListener('gmp-click', (event) => {
                    console.log('Marker clicked (gmp-click)');
                    
                    // Only toggle the popover if popover functionality is enabled
                    if (popoverEnabled) {
                        popover.open = !popover.open;
                    }
                    
                    // Always animate altitude on each click
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
            document.getElementById('popoverToggleBtn').addEventListener('click', togglePopover);
            
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
                        const markerWidth = parseFloat(form.querySelector('input[name="marker_width"]').value) || 7;
                        const markerLength = parseFloat(form.querySelector('input[name="marker_length"]').value) || 7;
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
                    
                    // Convert user-friendly values back to actual values
                    sizeWidth = markerWidthInput && !isNaN(parseFloat(markerWidthInput.value)) ? 
                                (parseFloat(markerWidthInput.value) / 100000) : sizeWidth;
                    sizeLength = markerLengthInput && !isNaN(parseFloat(markerLengthInput.value)) ? 
                                 (parseFloat(markerLengthInput.value) / 100000) : sizeLength;
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
            
            map.center = {
                lat: modelPosition.lat,
                lng: modelPosition.lng,
                altitude: mapAltitude
            };
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
        
        // Function to toggle popover functionality
        function togglePopover() {
            popoverEnabled = !popoverEnabled;
            
            const popoverToggleBtn = document.getElementById('popoverToggleBtn');
            
            if (popoverEnabled) {
                popoverToggleBtn.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15h2v-6h-2v6zm0-8h2V7h-2v2z"/>
                    </svg>
                    Popover: ON
                `;
                popoverToggleBtn.style.backgroundColor = '#007bff';
                popoverToggleBtn.style.color = 'white';
            } else {
                popoverToggleBtn.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15h2v-6h-2v6zm0-8h2V7h-2v2z"/>
                    </svg>
                    Popover: OFF
                `;
                popoverToggleBtn.style.backgroundColor = '#6c757d';
                popoverToggleBtn.style.color = 'white';
                
                // Close any open popover when disabling
                if (currentPopover && currentPopover.open) {
                    currentPopover.open = false;
                }
            }
        }
        
        // Function to initialize popover toggle button style
        function initializePopoverToggleButton() {
            const popoverToggleBtn = document.getElementById('popoverToggleBtn');
            if (popoverToggleBtn) {
                // Set initial style based on popoverEnabled state
                if (popoverEnabled) {
                    popoverToggleBtn.style.backgroundColor = '#007bff';
                    popoverToggleBtn.style.color = 'white';
                } else {
                    popoverToggleBtn.style.backgroundColor = '#6c757d';
                    popoverToggleBtn.style.color = 'white';
                }
            }
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
        }
        
        // Function to set up delete model button event listeners
        function setupDeleteModelButtons() {
            const deleteModelButtons = document.querySelectorAll('.delete-model-btn');
            deleteModelButtons.forEach(button => {
                button.addEventListener('click', function() {
                    if (confirm('Are you sure you want to delete this model? This cannot be undone.')) {
                        deleteModel(this.dataset.modelId);
                    }
                });
            });
        }
        
        // Function to set up replace model button event listeners
        function setupReplaceModelButtons() {
            const replaceModelButtons = document.querySelectorAll('.replace-model-btn');
            replaceModelButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const modelId = this.dataset.modelId;
                    const replaceForm = document.getElementById('replace-form-' + modelId);
                    
                    // Show the replace form
                    replaceForm.style.display = 'block';
                    
                    // Add required attribute to the file input when form is shown
                    const fileInput = replaceForm.querySelector('input[name="model_file"]');
                    if (fileInput) {
                        fileInput.required = true;
                    }
                    
                    // Hide all other replace forms
                    document.querySelectorAll('.replace-model-form').forEach(form => {
                        if (form.id !== 'replace-form-' + modelId) {
                            form.style.display = 'none';
                            // Remove required attribute from hidden forms
                            const hiddenFileInput = form.querySelector('input[name="model_file"]');
                            if (hiddenFileInput) {
                                hiddenFileInput.required = false;
                            }
                        }
                    });
                });
            });
            
            // Set up cancel buttons
            const cancelReplaceButtons = document.querySelectorAll('.cancel-replace-btn');
            cancelReplaceButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const replaceForm = this.closest('.replace-model-form');
                    replaceForm.style.display = 'none';
                    
                    // Remove required attribute when form is hidden
                    const fileInput = replaceForm.querySelector('input[name="model_file"]');
                    if (fileInput) {
                        fileInput.required = false;
                    }
                });
            });
            
            // Set up replace form submissions
            const replaceFormElements = document.querySelectorAll('.replace-model-form-element');
            replaceFormElements.forEach(form => {
                form.addEventListener('submit', function(event) {
                    event.preventDefault();
                    
                    console.log('Replace model form submitted');
                    
                    const modelId = this.querySelector('input[name="model_id"]').value;
                    const projectId = this.querySelector('input[name="project_id"]').value;
                    const fileInput = this.querySelector('input[name="model_file"]');
                    
                    console.log('Form data:', {
                        modelId: modelId,
                        projectId: projectId,
                        fileSelected: fileInput.files.length > 0,
                        fileName: fileInput.files[0]?.name
                    });
                    
                    if (!fileInput.files.length) {
                        console.log('No file selected');
                        showNotification('Please select a file to upload', 'error');
                        return;
                    }
                    
                    const formData = new FormData();
                    formData.append('model_id', modelId);
                    formData.append('project_id', projectId);
                    formData.append('model_file', fileInput.files[0]);
                    
                    console.log('FormData created, starting upload...');
                    
                    // Show loading notification
                    showNotification('Replacing model...', 'info');
                    
                    fetch('replace_model.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        console.log('Response status:', response.status);
                        console.log('Response headers:', response.headers);
                        return response.text().then(text => {
                            console.log('Raw response:', text);
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                console.error('Failed to parse JSON:', e);
                                console.error('Response text:', text);
                                throw new Error('Invalid JSON response: ' + text);
                            }
                        });
                    })
                    .then(data => {
                        console.log('Parsed response:', data);
                        if (data.success) {
                            showNotification('Model replaced successfully', 'success');
                            
                            console.log('Starting UI update process...');
                            console.log('Old model ID:', modelId);
                            console.log('New model ID:', data.new_model_id);
                            
                            // Clean up old model from map and loadedModels
                            if (loadedModels[modelId]) {
                                try {
                                    console.log('Removing old model from map...');
                                    map.removeChild(loadedModels[modelId]);
                                    delete loadedModels[modelId];
                                    console.log('Old model removed successfully');
                                } catch (e) {
                                    console.error('Error removing old model:', e);
                                }
                            } else {
                                console.log('No loaded model found for ID:', modelId);
                            }
                            
                            // Update the old model element in the UI to reflect the new model
                            const oldModelSelector = document.getElementById('model_' + modelId);
                            console.log('Old model selector found:', oldModelSelector);
                            const oldModelItem = oldModelSelector ? oldModelSelector.closest('.model-item') : null;
                            console.log('Old model item found:', oldModelItem);
                            
                            if (oldModelItem) {
                                console.log('Updating UI elements...');
                                
                                // Update the selector ID and data attributes
                                console.log('Updating selector ID and data attributes...');
                                oldModelSelector.id = 'model_' + data.new_model_id;
                                oldModelSelector.dataset.modelId = data.new_model_id;
                                oldModelSelector.dataset.filePath = data.new_file_path;
                                
                                // Update the model's name display
                                const modelNameElement = oldModelItem.querySelector('h4');
                                if (modelNameElement) {
                                    console.log('Updating model name display...');
                                    modelNameElement.textContent = data.new_filename;
                                } else {
                                    console.log('Model name element not found');
                                }
                                
                                // Update the file path display
                                const filePathElement = oldModelItem.querySelector('p small');
                                if (filePathElement) {
                                    console.log('Updating file path display...');
                                    filePathElement.textContent = data.new_file_path;
                                } else {
                                    console.log('File path element not found');
                                }
                                
                                // Update all form inputs with the new model ID
                                const hiddenIdInput = oldModelItem.querySelector('input[name="id"]');
                                if (hiddenIdInput) {
                                    console.log('Updating hidden ID input...');
                                    hiddenIdInput.value = data.new_model_id;
                                } else {
                                    console.log('Hidden ID input not found');
                                }
                                
                                // Update button data attributes
                                const replaceBtn = oldModelItem.querySelector('.replace-model-btn');
                                if (replaceBtn) {
                                    console.log('Updating replace button...');
                                    replaceBtn.dataset.modelId = data.new_model_id;
                                } else {
                                    console.log('Replace button not found');
                                }
                                
                                const deleteBtn = oldModelItem.querySelector('.delete-model-btn');
                                if (deleteBtn) {
                                    console.log('Updating delete button...');
                                    deleteBtn.dataset.modelId = data.new_model_id;
                                    deleteBtn.dataset.filePath = data.new_file_path;
                                } else {
                                    console.log('Delete button not found');
                                }
                                
                                // Update the replace form ID
                                const replaceForm = oldModelItem.querySelector('.replace-model-form');
                                if (replaceForm) {
                                    console.log('Updating replace form...');
                                    replaceForm.id = 'replace-form-' + data.new_model_id;
                                    
                                    // Update hidden inputs in replace form
                                    const replaceModelIdInput = replaceForm.querySelector('input[name="model_id"]');
                                    if (replaceModelIdInput) {
                                        replaceModelIdInput.value = data.new_model_id;
                                    }
                                } else {
                                    console.log('Replace form not found');
                                }
                                
                                // Ensure the new model is checked and active
                                console.log('Setting model as checked and active...');
                                oldModelSelector.checked = true;
                                
                                // Load the new model
                                console.log('Dispatching change event to load new model...');
                                const changeEvent = new Event('change');
                                oldModelSelector.dispatchEvent(changeEvent);
                                
                                console.log('UI update completed successfully');
                            } else {
                                console.error('Old model item not found - UI update failed');
                            }
                            
                            // Hide the replace form
                            const replaceFormElement = document.getElementById('replace-form-' + data.new_model_id);
                            if (replaceFormElement) {
                                console.log('Hiding replace form...');
                                replaceFormElement.style.display = 'none';
                            } else {
                                console.log('Replace form element not found for hiding');
                            }
                            
                            // Clear the file input
                            console.log('Clearing file input...');
                            fileInput.value = '';
                            
                            console.log('Replace model process completed successfully');
                            
                        } else {
                            console.error('Replace model failed:', data);
                            showNotification('Error replacing model: ' + data.error, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        showNotification('Error replacing model: ' + error.message, 'error');
                    });
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
        
        // Function to show control buttons hint
        function showControlButtonsHint() {
            showNotification('💡 Use control buttons to move your model around the map!', 'info');
        }
        
        // Function to temporarily highlight control buttons
        function highlightControlButtons() {
            const controlButtons = document.querySelectorAll('.control-button');
            
            controlButtons.forEach(button => {
                // Store original style
                const originalBackground = button.style.backgroundColor;
                const originalBorder = button.style.border;
                const originalBoxShadow = button.style.boxShadow;
                const originalTransform = button.style.transform;
                
                // Apply highlight styles
                button.style.backgroundColor = '#fff3cd';
                button.style.border = '2px solid #ffc107';
                button.style.boxShadow = '0 0 20px rgba(255, 193, 7, 0.8)';
                button.style.transform = 'scale(1.1)';
                button.style.transition = 'all 0.3s ease';
                
                // Add pulsing animation
                button.style.animation = 'control-button-pulse 1.5s ease-in-out infinite';
                
                // Remove highlight after 5 seconds
                setTimeout(() => {
                    button.style.backgroundColor = originalBackground;
                    button.style.border = originalBorder;
                    button.style.boxShadow = originalBoxShadow;
                    button.style.transform = originalTransform;
                    button.style.animation = '';
                    button.style.transition = '';
                }, 5000);
            });
        }
        
        // Mini map variables
        let miniMap, miniMapMarker, miniMapGeocoder, miniMapAutocomplete;

        // Initialize mini map for address selection using importLibrary
        // Initialize address overlay functionality
        function initAddressOverlay() {
            const changeAddressBtn = document.getElementById('change-address-btn');
            const addressOverlay = document.getElementById('address-overlay');
            const closeModalBtn = document.getElementById('close-address-modal');
            const cancelBtn = document.getElementById('cancel-address-change');
            const applyBtn = document.getElementById('apply-address-change');

            // Open overlay
            changeAddressBtn.addEventListener('click', async function() {
                addressOverlay.style.display = 'flex';
                
                // Initialize mini map when overlay opens (lazy loading)
                if (!window.miniMapInitialized) {
                    await initMiniMap();
                    window.miniMapInitialized = true;
                }
            });

            // Close overlay functions
            function closeOverlay() {
                addressOverlay.style.display = 'none';
            }

            closeModalBtn.addEventListener('click', closeOverlay);
            cancelBtn.addEventListener('click', closeOverlay);

            // Close overlay when clicking outside the modal
            addressOverlay.addEventListener('click', function(event) {
                if (event.target === addressOverlay) {
                    closeOverlay();
                }
            });

            // Apply address changes
            applyBtn.addEventListener('click', function() {
                const newLat = parseFloat(document.getElementById('modal-lat').value);
                const newLng = parseFloat(document.getElementById('modal-lng').value);
                const newAddress = document.getElementById('map-address').value;
                const newCountry = window.selectedCountry || document.getElementById('map-country').value;
                const newCity = window.selectedCity || document.getElementById('map-city').value;

                if (isNaN(newLat) || isNaN(newLng)) {
                    showNotification('Please select a valid address first.', 'error');
                    return;
                }

                // Show loading notification and disable button
                showNotification('Saving address changes...', 'info');
                applyBtn.disabled = true;
                applyBtn.textContent = 'Saving...';

                // Create FormData object for AJAX request to save to database
                const formData = new FormData();
                formData.append('update_project', '1');
                
                // Get all current form values to preserve other project data
                const mainForm = document.getElementById('updateProjectForm');
                const formElements = mainForm.elements;
                
                // Append all form fields to formData (except hidden update_project which we already added)
                for (let i = 0; i < formElements.length; i++) {
                    const field = formElements[i];
                    if (field.name && field.name !== 'update_project') {
                        formData.append(field.name, field.value);
                    }
                }
                
                // Override with new address data
                formData.append('address', newAddress);
                formData.append('country', newCountry);
                formData.append('city', newCity);
                formData.append('lat', newLat);
                formData.append('lng', newLng);
                
                // Include current map parameters to preserve them
                formData.append('altitude', document.getElementById('map-altitude').value || projectData.altitude);
                formData.append('camera_range', document.getElementById('map-camera-range').value || projectData.camera_range);
                formData.append('camera_tilt', document.getElementById('map-camera-tilt').value || projectData.camera_tilt);
                formData.append('camera_heading', document.getElementById('map-camera-heading').value || projectData.camera_heading);
                
                // Send AJAX request to save to database
                fetch('project_map.php?id=<?= $project_id ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.ok) {
                        // Update the coordinate fields
                        document.getElementById('map-lat').value = newLat;
                        document.getElementById('map-lng').value = newLng;
                        document.getElementById('current-address').value = newAddress;

                        // Update country and city if available
                        if (newCountry) {
                            document.getElementById('map-country').value = newCountry;
                        }
                        if (newCity) {
                            document.getElementById('map-city').value = newCity;
                        }

                        // Update project data
                        projectData.lat = newLat;
                        projectData.lng = newLng;
                        projectData.address = newAddress;
                        if (newCountry) projectData.country = newCountry;
                        if (newCity) projectData.city = newCity;

                        // Update hidden form fields to preserve changes
                        const hiddenAddressField = mainForm.querySelector('input[name="address"]');
                        const hiddenCountryField = mainForm.querySelector('input[name="country"]');
                        const hiddenCityField = mainForm.querySelector('input[name="city"]');
                        
                        if (hiddenAddressField) hiddenAddressField.value = newAddress;
                        if (hiddenCountryField && newCountry) hiddenCountryField.value = newCountry;
                        if (hiddenCityField && newCity) hiddenCityField.value = newCity;

                        // Update active model position if there's one selected
                        if (activeModel && modelPosition) {
                            const altitudeOffset = modelPosition.altitude - (parseFloat(document.getElementById('map-altitude').value) || projectData.altitude);
                            
                            modelPosition.lat = newLat;
                            modelPosition.lng = newLng;
                            // Keep the relative altitude offset
                            modelPosition.altitude = (parseFloat(document.getElementById('map-altitude').value) || projectData.altitude) + altitudeOffset;

                            activeModel.position = {
                                lat: modelPosition.lat,
                                lng: modelPosition.lng,
                                altitude: modelPosition.altitude
                            };

                            // Update form values for the active model
                            updateFormValues();
                            
                            // Update the highlight polygon and interactive marker
                            updateHighlightPolygon();
                            updateInteractiveMarker();
                            
                            // Save updated model coordinates to database
                            const activeModelSelector = document.querySelector('.model-selector:checked');
                            if (activeModelSelector) {
                                const activeModelId = activeModelSelector.dataset.modelId;
                                const activeModelForm = activeModelSelector.closest('form');
                                
                                if (activeModelForm && activeModelId) {
                                    // Create FormData for the model update
                                    const modelFormData = new FormData();
                                    modelFormData.append('id', activeModelId);
                                    modelFormData.append('lat', modelPosition.lat);
                                    modelFormData.append('lng', modelPosition.lng);
                                    modelFormData.append('altitude_show', modelPosition.altitude);
                                    modelFormData.append('altitude_fact', altitudeFact);
                                    
                                    // Include other model data to preserve them
                                    const rollInput = activeModelForm.querySelector('input[name="roll"]');
                                    const tiltInput = activeModelForm.querySelector('input[name="tilt"]');
                                    const scaleInput = activeModelForm.querySelector('input[name="scale"]');
                                    const markerWidthInput = activeModelForm.querySelector('input[name="marker_width"]');
                                    const markerLengthInput = activeModelForm.querySelector('input[name="marker_length"]');
                                    const markerHeightInput = activeModelForm.querySelector('input[name="marker_height"]');
                                    
                                    if (rollInput) modelFormData.append('roll', rollInput.value);
                                    if (tiltInput) modelFormData.append('tilt', tiltInput.value);
                                    if (scaleInput) modelFormData.append('scale', scaleInput.value);
                                    if (markerWidthInput) modelFormData.append('marker_width', markerWidthInput.value);
                                    if (markerLengthInput) modelFormData.append('marker_length', markerLengthInput.value);
                                    if (markerHeightInput) modelFormData.append('marker_height', markerHeightInput.value);
                                    
                                    // Send AJAX request to save model coordinates
                                    fetch('update_model.php', {
                                        method: 'POST',
                                        body: modelFormData
                                    })
                                    .then(response => {
                                        if (response.ok) {
                                            console.log('Model coordinates saved to database successfully');
                                        } else {
                                            console.error('Error saving model coordinates to database');
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error saving model coordinates:', error);
                                    });
                                }
                            }
                        }

                        // Update main 3D map center
                        if (map) {
                            const currentRange = map.range;
                            const currentTilt = map.tilt;
                            const currentHeading = map.heading || 0;
                            
                            map.center = {
                                lat: newLat,
                                lng: newLng,
                                altitude: parseFloat(document.getElementById('map-altitude').value) || projectData.altitude
                            };
                            
                            // Preserve camera settings
                            map.range = currentRange;
                            map.tilt = currentTilt;
                            map.heading = currentHeading;
                        }

                        showNotification('Address saved to database successfully!', 'success');
                        closeOverlay();
                    } else {
                        showNotification('Error saving address to database', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error saving address:', error);
                    showNotification('Error saving address: ' + error.message, 'error');
                })
                .finally(() => {
                    // Re-enable button and restore text
                    applyBtn.disabled = false;
                    applyBtn.textContent = 'Apply Changes';
                });
            });

            // Escape key to close overlay
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && addressOverlay.style.display === 'flex') {
                    closeOverlay();
                }
            });
        }

        async function initMiniMap() {
            const miniMapDiv = document.getElementById('map-mini-map');
            if (!miniMapDiv) return;

            try {
                // Import required libraries
                const { Map } = await google.maps.importLibrary("maps");
                const { Marker } = await google.maps.importLibrary("marker");
                const { Geocoder } = await google.maps.importLibrary("geocoding");
                const { Autocomplete } = await google.maps.importLibrary("places");

                // Use current project coordinates as default
                const currentLocation = {
                    lat: parseFloat(document.getElementById('map-lat').value) || projectData.lat,
                    lng: parseFloat(document.getElementById('map-lng').value) || projectData.lng
                };

                miniMap = new Map(miniMapDiv, {
                    center: currentLocation,
                    zoom: 15,
                    mapId: "mini-map"
                });

                miniMapGeocoder = new Geocoder();

                // Create draggable marker
                miniMapMarker = new Marker({
                    position: currentLocation,
                    map: miniMap,
                    draggable: true
                });

                // Update modal coordinates initially
                document.getElementById('modal-lat').value = currentLocation.lat;
                document.getElementById('modal-lng').value = currentLocation.lng;

                // Set up autocomplete for address input
                const addressInput = document.getElementById('map-address');
                if (addressInput) {
                    miniMapAutocomplete = new Autocomplete(addressInput);
                    miniMapAutocomplete.bindTo('bounds', miniMap);

                    // Listen for place selection
                    miniMapAutocomplete.addListener('place_changed', function() {
                        const place = miniMapAutocomplete.getPlace();
                        if (!place.geometry) return;

                        const location = place.geometry.location;
                        miniMap.setCenter(location);
                        miniMapMarker.setPosition(location);

                        updateModalLocationFields(location.lat(), location.lng(), place);
                    });
                }

                // Listen for marker drag
                miniMapMarker.addListener('dragend', async function() {
                    const position = miniMapMarker.getPosition();
                    updateModalLocationFields(position.lat(), position.lng());
                    
                    // Reverse geocode to get address
                    try {
                        const response = await miniMapGeocoder.geocode({ location: position });
                        if (response.results && response.results[0]) {
                            document.getElementById('map-address').value = response.results[0].formatted_address;
                            updateModalLocationFields(position.lat(), position.lng(), response.results[0]);
                        }
                    } catch (error) {
                        console.error('Geocoding error:', error);
                    }
                });

                // Listen for map clicks
                miniMap.addListener('click', async function(event) {
                    miniMapMarker.setPosition(event.latLng);
                    updateModalLocationFields(event.latLng.lat(), event.latLng.lng());
                    
                    // Reverse geocode
                    try {
                        const response = await miniMapGeocoder.geocode({ location: event.latLng });
                        if (response.results && response.results[0]) {
                            document.getElementById('map-address').value = response.results[0].formatted_address;
                            updateModalLocationFields(event.latLng.lat(), event.latLng.lng(), response.results[0]);
                        }
                    } catch (error) {
                        console.error('Geocoding error:', error);
                    }
                });

                console.log('Mini map initialized successfully');
                
            } catch (error) {
                console.error('Error initializing mini map:', error);
            }
        }

        // Update modal location fields based on mini map selection
        function updateModalLocationFields(lat, lng, place = null) {
            document.getElementById('modal-lat').value = lat.toFixed(6);
            document.getElementById('modal-lng').value = lng.toFixed(6);

            if (place) {
                let country = '', city = '';
                
                if (place.address_components) {
                    place.address_components.forEach(component => {
                        if (component.types.includes('country')) {
                            country = component.long_name;
                        }
                        if (component.types.includes('locality') || component.types.includes('administrative_area_level_2')) {
                            city = component.long_name;
                        }
                    });
                }

                // Store country and city for later use when applying changes
                window.selectedCountry = country;
                window.selectedCity = city;
            }
        }

        // Handle Map Parameters
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize address overlay functionality
            initAddressOverlay();

            // Get the map parameter elements
            const mapLatInput = document.getElementById('map-lat');
            const mapLngInput = document.getElementById('map-lng');
            const mapAltitudeInput = document.getElementById('map-altitude');
            const mapCameraRangeInput = document.getElementById('map-camera-range');
            const mapCameraTiltInput = document.getElementById('map-camera-tilt');
            const mapCameraHeadingInput = document.getElementById('map-camera-heading');
            const mapAddressInput = document.getElementById('map-address');
            const mapCountryInput = document.getElementById('map-country');
            const mapCityInput = document.getElementById('map-city');
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
                    const address = mapAddressInput.value;
                    const country = mapCountryInput.value;
                    const city = mapCityInput.value;
                    
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
                    
                    // Add the map parameters (including address, country, city)
                    formData.append('address', address);
                    formData.append('country', country);
                    formData.append('city', city);
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
                            showNotification('Map parameters and address saved successfully', 'success');
                            
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
            useCurrentCameraBtn.addEventListener('click', async function() {
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
                    
                    // Update mini map marker position
                    if (miniMapMarker) {
                        const newPosition = { lat: currentLat, lng: currentLng };
                        miniMapMarker.setPosition(newPosition);
                        miniMap.setCenter(newPosition);
                        
                        // Reverse geocode to get address
                        try {
                            const response = await miniMapGeocoder.geocode({ location: newPosition });
                            if (response.results && response.results[0]) {
                                document.getElementById('map-address').value = response.results[0].formatted_address;
                                updateMiniMapLocationFields(currentLat, currentLng, response.results[0]);
                            }
                        } catch (error) {
                            console.error('Geocoding error:', error);
                        }
                    }
                    
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
                const currencyInput = updateProjectForm.querySelector('input[name="currency"]');
                
                if (priceInput && currencyInput) {
                    projectData.price = parseFloat(priceInput.value) || 0;
                    projectData.currency = currencyInput.value;
                    
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
                                showNotification('Model and interactive marker updated successfully', 'success');
                                
                                // Update model selector data attributes
                                updateModelSelectorAttributes(form);
                                
                                // If this model is currently active, update it
                                updateActiveModelIfSelected(modelId, form);
                            } else {
                                showNotification('Error saving model: ' + jsonData.error, 'error');
                            }
                        } catch (e) {
                            // If not JSON, assume success (since the original script doesn't return JSON)
                            showNotification('Model and interactive marker updated successfully', 'success');
                            
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
                
                // Store user-friendly values in data attributes
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
                const altitudeShowNew = parseFloat(form.querySelector('input[name="altitude_show"]').value);
                const altitudeFactNew = parseFloat(form.querySelector('input[name="altitude_fact"]').value);
                const roll = parseFloat(form.querySelector('input[name="roll"]').value);
                const tilt = parseFloat(form.querySelector('input[name="tilt"]').value);
                const scale = parseFloat(form.querySelector('input[name="scale"]').value);
                
                // Update global altitude variables
                altitudeShow = altitudeShowNew;
                altitudeFact = altitudeFactNew;
                
                // Update model position and orientation
                modelPosition = {
                    lat: lat,
                    lng: lng,
                    altitude: altitudeShow // Use current model altitude
                };
                
                modelOrientation = {
                    tilt: tilt,
                    roll: roll
                };
                
                // Update the active model with new data
                activeModel.position = modelPosition;
                activeModel.orientation = modelOrientation;
                activeModel.scale = scale;
                
                // Force update of interactive marker with new parameters
                updateInteractiveMarker(true); // Force recreation to ensure all parameters are updated
                updateHighlightPolygon();
                
                console.log('Interactive marker updated with new parameters:', {
                    lat: modelPosition.lat,
                    lng: modelPosition.lng,
                    altitude: altitudeShow + 2.7,
                    label: formatPrice(projectData.currency, projectData.price)
                });
            }
        }
        
        // Function to handle Save & Continue button
        function setupSaveContinueButton() {
            const saveContinueBtn = document.getElementById('saveContinueBtn');
            if (!saveContinueBtn) return;
            
            saveContinueBtn.addEventListener('click', async function() {
                // Disable button and show loading state
                this.disabled = true;
                this.style.opacity = '0.6';
                this.innerHTML = '💾 Saving...';
                
                try {
                    // First, save 3D map parameters
                    const mapSaveSuccess = await saveMapParameters();
                    
                    if (!mapSaveSuccess) {
                        throw new Error('Failed to save map parameters');
                    }
                    
                    // Then, save active model parameters if there's an active model
                    const modelSaveSuccess = await saveActiveModelParameters();
                    
                    if (!modelSaveSuccess) {
                        throw new Error('Failed to save model parameters');
                    }
                    
                    // Show success message
                    showNotification('All parameters saved successfully! Redirecting...', 'success');
                    
                    // Wait a moment for user to see the success message, then redirect
                    setTimeout(() => {
                        window.location.href = 'project_map_animation.php?id=<?= $project_id ?>';
                    }, 1000);
                    
                } catch (error) {
                    console.error('Error during save and continue:', error);
                    showNotification('Error saving parameters: ' + error.message, 'error');
                    
                    // Re-enable button
                    this.disabled = false;
                    this.style.opacity = '1';
                    this.innerHTML = '🎬 Save & Continue to Animation';
                }
            });
        }
        
        // Function to save 3D map parameters
        async function saveMapParameters() {
            return new Promise((resolve) => {
                try {
                    // Get the map parameter input values
                    const lat = document.getElementById('map-lat').value;
                    const lng = document.getElementById('map-lng').value;
                    const altitude = document.getElementById('map-altitude').value;
                    const camera_range = document.getElementById('map-camera-range').value;
                    const camera_tilt = document.getElementById('map-camera-tilt').value;
                    const camera_heading = document.getElementById('map-camera-heading').value;
                    const address = document.getElementById('current-address').value;
                    const country = document.getElementById('map-country').value;
                    const city = document.getElementById('map-city').value;
                    
                    // Create FormData object
                    const formData = new FormData();
                    formData.append('update_project', '1');
                    
                    // Get all values from the main form to include them
                    const mainForm = document.getElementById('updateProjectForm');
                    const formElements = mainForm.elements;
                    
                    // Append all form fields to formData
                    for (let i = 0; i < formElements.length; i++) {
                        const field = formElements[i];
                        if (field.name && field.name !== 'update_project') {
                            formData.append(field.name, field.value);
                        }
                    }
                    
                    // Add the map parameters
                    formData.append('address', address);
                    formData.append('country', country);
                    formData.append('city', city);
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
                            console.log('Map parameters saved successfully');
                            resolve(true);
                        } else {
                            console.error('Error saving map parameters');
                            resolve(false);
                        }
                    })
                    .catch(error => {
                        console.error('Error saving map parameters:', error);
                        resolve(false);
                    });
                } catch (error) {
                    console.error('Error preparing map parameters:', error);
                    resolve(false);
                }
            });
        }
        
        // Function to save active model parameters
        async function saveActiveModelParameters() {
            return new Promise((resolve) => {
                try {
                    // Find the currently checked model
                    const activeModelSelector = document.querySelector('.model-selector:checked');
                    
                    if (!activeModelSelector) {
                        console.log('No active model to save');
                        resolve(true); // No model to save is not an error
                        return;
                    }
                    
                    // Find the form associated with this model
                    const modelForm = activeModelSelector.closest('form');
                    if (!modelForm) {
                        console.error('No form found for active model');
                        resolve(false);
                        return;
                    }
                    
                    // Create FormData object from the model form
                    const formData = new FormData(modelForm);
                    
                    // Send AJAX request to update_model.php
                    fetch('update_model.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(data => {
                        try {
                            // Try to parse as JSON first
                            const jsonData = JSON.parse(data);
                            if (jsonData.success) {
                                console.log('Model parameters saved successfully');
                                resolve(true);
                            } else {
                                console.error('Error saving model parameters:', jsonData.error);
                                resolve(false);
                            }
                        } catch (e) {
                            // If not JSON, assume success (since update_model.php might not return JSON)
                            console.log('Model parameters saved successfully');
                            resolve(true);
                        }
                    })
                    .catch(error => {
                        console.error('Error saving model parameters:', error);
                        resolve(false);
                    });
                } catch (error) {
                    console.error('Error preparing model parameters:', error);
                    resolve(false);
                }
            });
        }

        // Initialize the map when the page loads
        window.addEventListener('load', function() {
            setupModelUpdateForms();
            setupDeleteModelButtons();
            setupReplaceModelButtons();
            setupSaveContinueButton();
        });
    </script>
</body>
</html>
