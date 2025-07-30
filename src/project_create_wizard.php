<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require 'db.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$step = $_GET['step'] ?? 1;

// Fetch Google Maps API key from users table
$api_key_stmt = $conn->prepare("SELECT google_maps_api_key FROM users WHERE id = ?");
$api_key_stmt->bind_param("i", $user_id);
$api_key_stmt->execute();
$api_key_result = $api_key_stmt->get_result();
$user_data = $api_key_result->fetch_assoc();
$GOOGLE_MAPS_API_KEY = $user_data['google_maps_api_key'] ?? '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step1_submit'])) {
        // Store step 1 data in session
        $_SESSION['project_wizard'] = [
            'address' => $_POST['address'],
            'country' => $_POST['country'],
            'city' => $_POST['city'],
            'lat' => $_POST['lat'],
            'lng' => $_POST['lng']
        ];
        header("Location: project_create_wizard.php?step=2");
        exit;
    }
    
    if (isset($_POST['step2_submit'])) {
        // Add step 2 data to session
        $_SESSION['project_wizard']['floor'] = $_POST['floor'];
        $_SESSION['project_wizard']['price'] = $_POST['price'];
        $_SESSION['project_wizard']['currency'] = $_POST['currency'];
        header("Location: project_create_wizard.php?step=3");
        exit;
    }
    
    if (isset($_POST['step3_submit'])) {
        // Create the project with all collected data
        $wizard_data = $_SESSION['project_wizard'];
        
        // Generate name from city, street and date
        $address_parts = explode(',', $wizard_data['address']);
        $street = trim($address_parts[0]);
        $city = $wizard_data['city'];
        $date = date('Y-m-d');
        $name = $city . ' - ' . $street . ' - ' . $date;
        
        // Calculate altitude_fact (floor * 3)
        $altitude_fact = intval($wizard_data['floor']) * 3;
        
        // Insert project
        $stmt = $conn->prepare("INSERT INTO projects (user_id, name, description, address, country, city, lat, lng, altitude, camera_range, camera_tilt, price, currency, floor, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $description = "Project created on " . $date;
        $altitude = 50; // Default camera altitude
        $camera_range = 250; // Default camera range
        $camera_tilt = 45; // Default camera tilt
        
        // Extract values into variables for bind_param
        $address = $wizard_data['address'];
        $country = $wizard_data['country'];
        $city = $wizard_data['city'];
        $lat = $wizard_data['lat'];
        $lng = $wizard_data['lng'];
        $price = $wizard_data['price'];
        $currency = $wizard_data['currency'];
        $floor = $wizard_data['floor'];
        
        $stmt->bind_param(
            "isssssdddddssi",
            $user_id,
            $name,
            $description,
            $address,
            $country,
            $city,
            $lat,
            $lng,
            $altitude,
            $camera_range,
            $camera_tilt,
            $price,
            $currency,
            $floor
        );
        
        if ($stmt->execute()) {
            $project_id = $stmt->insert_id;
            
            // Create necessary folders for project
            $projectFolder = "project/users/$user_id/$project_id";
            if (!is_dir($projectFolder)) {
                mkdir($projectFolder, 0775, true);
            }
            
            // Create required subfolders
            $subfolders = ["images", "videos", "models"];
            foreach ($subfolders as $folder) {
                $folderPath = $projectFolder . "/" . $folder;
                if (!is_dir($folderPath)) {
                    if (!mkdir($folderPath, 0775, true)) {
                        die("Failed to create folder: " . $folderPath);
                    }
                }
            }
            
            // Handle model selection
            if ($_POST['model_choice'] === 'placeholder') {
                // Create placeholder model with altitude_fact (same approach as upload_placeholder_model.php)
                $placeholder_path = "project/users/null.glb";
                
                // Generate unique filename for the copied placeholder
                $timestamp = date('Y-m-d_H-i-s');
                $filename = "placeholder_model_" . $timestamp . ".glb";
                $filepath = "users/$user_id/$project_id/models/" . $filename;
                
                // Copy the placeholder model to user's directory (or create record even if file doesn't exist)
                if (file_exists($placeholder_path)) {
                    copy($placeholder_path, "project/" . $filepath);
                }
                
                $model_stmt = $conn->prepare("INSERT INTO models (project_id, name, file_path, tilt, roll, scale, lat, lng, altitude_show, altitude_fact, uploaded_at) 
                                            VALUES (?, ?, ?, 0, 0, 1, ?, ?, ?, ?, NOW())");
                $model_name = "Placeholder Model";
                $model_lat = $wizard_data['lat'];
                $model_lng = $wizard_data['lng'];
                
                $model_stmt->bind_param(
                    "issdddd",
                    $project_id,
                    $model_name,
                    $filepath,
                    $model_lat,
                    $model_lng,
                    $altitude_fact, // altitude_show
                    $altitude_fact  // altitude_fact (floor * 3)
                );
                
                if ($model_stmt->execute()) {
                    $model_id = $model_stmt->insert_id;
                    // Set as active model
                    $update_stmt = $conn->prepare("UPDATE projects SET active_model_id = ? WHERE id = ?");
                    $update_stmt->bind_param("ii", $model_id, $project_id);
                    $update_stmt->execute();
                }
            } elseif ($_POST['model_choice'] === 'upload' && isset($_FILES['model_file']) && $_FILES['model_file']['error'] === UPLOAD_ERR_OK) {
                // Handle file upload
                $upload_dir = "project/users/$user_id/$project_id/models/";
                $filename = basename($_FILES["model_file"]["name"]);
                $filepath = "users/$user_id/$project_id/models/" . $filename;
                
                // Get file format and size
                $file_info = pathinfo($filename);
                $format = strtolower($file_info['extension'] ?? '');
                $size_mb = $_FILES["model_file"]["size"] / (1024 * 1024); // Convert bytes to MB
                
                if (move_uploaded_file($_FILES["model_file"]["tmp_name"], $upload_dir . $filename)) {
                    $model_stmt = $conn->prepare("INSERT INTO models (project_id, name, file_path, format, size_mb, lat, lng, altitude_show, altitude_fact, tilt, roll, scale, uploaded_at) 
                                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    
                    $upload_lat = $wizard_data['lat'];
                    $upload_lng = $wizard_data['lng'];
                    $tilt = 270;
                    $roll = 0;
                    $scale = 1;
                    $altitude_show = $altitude_fact + 10; // altitude shown is 10 meters more than altitude_fact
                    
                    $model_stmt->bind_param(
                        "issssddddddd",
                        $project_id,
                        $filename,
                        $filepath,
                        $format,
                        $size_mb,
                        $upload_lat,
                        $upload_lng,
                        $altitude_show, // altitude_show (altitude_fact + 10)
                        $altitude_fact, // altitude_fact (floor * 3)
                        $tilt,
                        $roll,
                        $scale
                    );
                    
                    if ($model_stmt->execute()) {
                        $model_id = $model_stmt->insert_id;
                        // Set as active model
                        $update_stmt = $conn->prepare("UPDATE projects SET active_model_id = ? WHERE id = ?");
                        $update_stmt->bind_param("ii", $model_id, $project_id);
                        $update_stmt->execute();
                    }
                } else {
                    $error = "Failed to upload model file.";
                }
            }
            
            // Clear wizard data from session
            unset($_SESSION['project_wizard']);
            
            // Redirect to project_map.php
            header("Location: project/project_map.php?id=" . $project_id);
            exit;
        } else {
            $error = "Database error: " . $stmt->error;
        }
    }
}

// Check if we have wizard data for later steps
if ($step > 1 && !isset($_SESSION['project_wizard'])) {
    header("Location: project_create_wizard.php?step=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Project - Step <?= $step ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .wizard-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 800px;
            overflow: hidden;
            position: relative;
        }

        .wizard-header {
            background: linear-gradient(135deg, #6a5acd 0%, #9370db 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .wizard-header h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .step.active {
            background: white;
            color: #6a5acd;
            transform: scale(1.2);
        }

        .step.completed {
            background: #4CAF50;
            color: white;
        }

        .wizard-content {
            padding: 40px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #6a5acd;
            box-shadow: 0 0 0 3px rgba(106, 90, 205, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .map-container {
            width: 100%;
            height: 300px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            margin-top: 15px;
        }

        .wizard-navigation {
            background: #f8f9fa;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            border-top: 1px solid #e0e0e0;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #6a5acd 0%, #9370db 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(106, 90, 205, 0.3);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #666;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .model-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }

        .model-option {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .model-option:hover {
            border-color: #6a5acd;
            background: #f8f7ff;
        }

        .model-option.selected {
            border-color: #6a5acd;
            background: #6a5acd;
            color: white;
        }

        .model-option input[type="radio"] {
            display: none;
        }

        .model-option h3 {
            margin-bottom: 10px;
            font-family: 'Montserrat', sans-serif;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="wizard-container">
        <div class="wizard-header">
            <h1>Create New Project</h1>
            <div class="step-indicator">
                <div class="step <?= $step >= 1 ? ($step == 1 ? 'active' : 'completed') : '' ?>">1</div>
                <div class="step <?= $step >= 2 ? ($step == 2 ? 'active' : 'completed') : '' ?>">2</div>
                <div class="step <?= $step >= 3 ? ($step == 3 ? 'active' : 'completed') : '' ?>">3</div>
            </div>
        </div>

        <div class="wizard-content">
            <?php if (isset($error)): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($step == 1): ?>
                <!-- Step 1: Address with Map -->
                <h2>Step 1: Project Location</h2>
                <p style="margin-bottom: 30px; color: #666;">Enter the project address and select the location on the map.</p>
                
                <form method="POST" id="step1Form">
                    <div class="form-group">
                        <label for="address">Address *</label>
                        <input type="text" id="address" name="address" required placeholder="Enter project address">
                    </div>
                    
                    <div id="map" class="map-container"></div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="country">Country</label>
                            <input type="text" id="country" name="country" readonly>
                        </div>
                        <div class="form-group">
                            <label for="city">City</label>
                            <input type="text" id="city" name="city" readonly>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="lat">Latitude</label>
                            <input type="number" id="lat" name="lat" step="any" readonly>
                        </div>
                        <div class="form-group">
                            <label for="lng">Longitude</label>
                            <input type="number" id="lng" name="lng" step="any" readonly>
                        </div>
                    </div>
                    
                    <input type="hidden" name="step1_submit" value="1">
                </form>

            <?php elseif ($step == 2): ?>
                <!-- Step 2: Floor and Price -->
                <h2>Step 2: Property Details</h2>
                <p style="margin-bottom: 30px; color: #666;">Enter the floor level and pricing information.</p>
                
                <form method="POST" id="step2Form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="floor">Floor Level *</label>
                            <input type="number" id="floor" name="floor" required min="0" placeholder="Floor number (e.g., 5)">
                            <small style="color: #666;">This will determine the 3D model height (floor × 3 meters)</small>
                        </div>
                        <div class="form-group">
                            <label for="price">Price</label>
                            <input type="number" id="price" name="price" step="0.01" placeholder="Property price">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="currency">Currency</label>
                        <select id="currency" name="currency">
                            <option value="EUR">EUR - Euro</option>
                            <option value="USD">USD - US Dollar</option>
                            <option value="GBP">GBP - British Pound</option>
                            <option value="JPY">JPY - Japanese Yen</option>
                            <option value="CNY">CNY - Chinese Yuan</option>
                        </select>
                    </div>
                    
                    <input type="hidden" name="step2_submit" value="1">
                </form>
                
                <script>
                    // Form validation for step 2
                    document.getElementById('step2Form').addEventListener('submit', function(e) {
                        const floor = document.getElementById('floor').value;
                        
                        if (!floor || floor < 0) {
                            e.preventDefault();
                            alert('Please enter a valid floor number (0 or higher).');
                            return false;
                        }
                    });
                </script>

            <?php elseif ($step == 3): ?>
                <!-- Step 3: Model Selection -->
                <h2>Step 3: 3D Model</h2>
                <p style="margin-bottom: 30px; color: #666;">Choose to upload a custom 3D model or use a placeholder.</p>
                
                <form method="POST" id="step3Form" enctype="multipart/form-data">
                    <div class="model-options">
                        <label class="model-option" onclick="selectModel('upload')">
                            <input type="radio" name="model_choice" value="upload">
                            <h3>📁 Upload Model</h3>
                            <p>Upload your own 3D model file (.glb, .gltf)</p>
                        </label>
                        
                        <label class="model-option selected" onclick="selectModel('placeholder')">
                            <input type="radio" name="model_choice" value="placeholder" checked>
                            <h3>🏢 Use Placeholder</h3>
                            <p>Start with a default building model</p>
                        </label>
                    </div>
                    
                    <div id="uploadSection" style="display: none; margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 2px dashed #dee2e6;">
                        <div class="form-group">
                            <label for="model_file">Select 3D Model File</label>
                            <input type="file" id="model_file" name="model_file" accept=".glb,.gltf" style="padding: 10px; border: 2px dashed #6a5acd; background: white;">
                            <small style="display: block; margin-top: 5px; color: #666;">Supported formats: .glb, .gltf (recommended: .glb for better performance)</small>
                        </div>
                    </div>
                    
                    <input type="hidden" name="step3_submit" value="1">
                </form>
            <?php endif; ?>
        </div>

        <div class="wizard-navigation">
            <?php if ($step > 1): ?>
                <a href="project_create_wizard.php?step=<?= $step - 1 ?>" class="btn btn-secondary">← Previous</a>
            <?php else: ?>
                <a href="account.php" class="btn btn-secondary">← Cancel</a>
            <?php endif; ?>
            
            <button type="submit" form="step<?= $step ?>Form" class="btn btn-primary">
                <?= $step == 3 ? 'Create Project' : 'Next →' ?>
            </button>
        </div>
    </div>

    <?php if ($step == 1): ?>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($GOOGLE_MAPS_API_KEY) ?>&libraries=places&language=en&region=US"></script>
    <script>
        let map, marker, autocomplete;

        function initMap() {
            // Default location (Barcelona)
            const defaultLocation = { lat: 41.3851, lng: 2.1734 };
            
            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 15,
                center: defaultLocation
            });

            marker = new google.maps.Marker({
                position: defaultLocation,
                map: map,
                draggable: true
            });

            // Set up autocomplete
            const addressInput = document.getElementById('address');
            autocomplete = new google.maps.places.Autocomplete(addressInput);
            autocomplete.bindTo('bounds', map);

            // Listen for place selection
            autocomplete.addListener('place_changed', function() {
                const place = autocomplete.getPlace();
                if (!place.geometry) return;

                const location = place.geometry.location;
                map.setCenter(location);
                marker.setPosition(location);

                updateLocationFields(location.lat(), location.lng(), place);
            });

            // Listen for marker drag
            marker.addListener('dragend', function() {
                const position = marker.getPosition();
                updateLocationFields(position.lat(), position.lng());
                
                // Reverse geocode to get address
                const geocoder = new google.maps.Geocoder();
                geocoder.geocode({ location: position }, function(results, status) {
                    if (status === 'OK' && results[0]) {
                        document.getElementById('address').value = results[0].formatted_address;
                        updateLocationFields(position.lat(), position.lng(), results[0]);
                    }
                });
            });

            // Listen for map clicks
            map.addListener('click', function(event) {
                marker.setPosition(event.latLng);
                updateLocationFields(event.latLng.lat(), event.latLng.lng());
                
                // Reverse geocode
                const geocoder = new google.maps.Geocoder();
                geocoder.geocode({ location: event.latLng }, function(results, status) {
                    if (status === 'OK' && results[0]) {
                        document.getElementById('address').value = results[0].formatted_address;
                        updateLocationFields(event.latLng.lat(), event.latLng.lng(), results[0]);
                    }
                });
            });
        }

        function updateLocationFields(lat, lng, place = null) {
            document.getElementById('lat').value = lat;
            document.getElementById('lng').value = lng;

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

                document.getElementById('country').value = country;
                document.getElementById('city').value = city;
            }
        }

        // Initialize map when page loads
        window.onload = initMap;
        
        // Form validation for step 1
        document.getElementById('step1Form').addEventListener('submit', function(e) {
            const lat = document.getElementById('lat').value;
            const lng = document.getElementById('lng').value;
            
            if (!lat || !lng || lat == 0 || lng == 0) {
                e.preventDefault();
                alert('Please select a location on the map or enter a valid address.');
                return false;
            }
        });
    </script>
    <?php endif; ?>

    <?php if ($step == 3): ?>
    <script>
        function selectModel(choice) {
            // Remove selected class from all options
            document.querySelectorAll('.model-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            event.currentTarget.classList.add('selected');
            
            // Check the radio button
            document.querySelector(`input[value="${choice}"]`).checked = true;
            
            // Show/hide upload section
            const uploadSection = document.getElementById('uploadSection');
            if (choice === 'upload') {
                uploadSection.style.display = 'block';
                document.getElementById('model_file').required = true;
            } else {
                uploadSection.style.display = 'none';
                document.getElementById('model_file').required = false;
            }
        }
        
        // Form validation for step 3
        document.getElementById('step3Form').addEventListener('submit', function(e) {
            const modelChoice = document.querySelector('input[name="model_choice"]:checked').value;
            const fileInput = document.getElementById('model_file');
            
            if (modelChoice === 'upload' && (!fileInput.files || fileInput.files.length === 0)) {
                e.preventDefault();
                alert('Please select a 3D model file to upload.');
                return false;
            }
            
            // Show loading state
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.textContent = 'Creating Project...';
            submitBtn.disabled = true;
        });
    </script>
    <?php endif; ?>
</body>
</html> 