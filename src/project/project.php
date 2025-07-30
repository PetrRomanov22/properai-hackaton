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
$project_id = $_GET["id"] ?? 0;

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
    $size = $_POST["size"];
    $size_unit = $_POST["size_unit"];
    $price = $_POST["price"];
    $currency = $_POST["currency"];
    $floor = $_POST["floor"];
    $bedrooms = $_POST["bedrooms"];
    $type = $_POST["type"];

    $stmt = $conn->prepare("UPDATE projects SET 
        name=?, description=?, address=?, country=?, city=?, 
        lat=?, lng=?, altitude=?, camera_range=?, camera_tilt=?,
        size=?, size_unit=?, price=?, currency=?, floor=?, bedrooms=?, type=?
        WHERE id=? AND user_id=?");
    $stmt->bind_param("sssssddddddsssiisii", $name, $description, $address, $country, $city,
        $lat, $lng, $altitude, $camera_range, $camera_tilt, 
        $size, $size_unit, $price, $currency, $floor, $bedrooms, $type, 
        $project_id, $user_id);
    $stmt->execute();
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
</head>
<body>
    <div class="container">
        <div class="project-header">
            <h2>Project: <?= htmlspecialchars($project["name"]) ?></h2>
            <p><a href="../account.php" class="sidebar-nav-btn">← Back to My Projects</a></p>
            <br>
            <h2>Project</h2>
            <p><a href="project.php?id=<?= $project_id ?>" class="sidebar-nav-btn active">Project →</a></p>
            <br>
            <h2>3D map</h2>
            <p><a href="project_map.php?id=<?= $project_id ?>" class="sidebar-nav-btn">🗺️ Edit 3d map →</a></p>
            <p><a href="project_map_animation.php?id=<?= $project_id ?>" class="sidebar-nav-btn">🎬 Animation →</a></p>
            <br>
            <h2>Share your apartment</h2>
            <p><a href="project_share.php?id=<?= $project_id ?>" class="sidebar-nav-btn">Preview</a></p>
        </div>
        
        <div class="project-content">
            
            <div class="project-details">
                <!-- Photo Gallery Section -->
                <div style="margin-bottom: 20px;">
                    <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 15px;">Photo Gallery</h3>
                    
                    <!-- Photo Upload Form -->
                    <form action="upload_photo.php" method="POST" enctype="multipart/form-data" class="model-form" style="margin-bottom: 15px;">
                        <input type="hidden" name="project_id" value="<?= $project["id"] ?>">
                        <div style="display: flex; align-items: center;">
                            <input type="file" name="photo_file" accept="image/*" required style="flex: 1;">
                            <button type="submit" style="margin-left: 10px;" class="btn btn-primary">Upload Photo</button>
                        </div>
                    </form>
                    
                    <!-- Photo Gallery Display -->
                    <div class="photo-gallery" style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 15px;">
                        <?php
                        // Get photos for this project
                        $photos_dir = "users/{$user_id}/{$project_id}/images";
                        
                        // Create directory if it doesn't exist
                        if (!file_exists($photos_dir)) {
                            mkdir($photos_dir, 0777, true);
                        }
                        
                        $photos = [];
                        if (is_dir($photos_dir)) {
                            $photos = array_filter(scandir($photos_dir), function($item) use ($photos_dir) {
                                return !is_dir($photos_dir . '/' . $item) && 
                                       in_array(strtolower(pathinfo($item, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif']);
                            });
                        }
                        
                        if (count($photos) > 0) {
                            foreach ($photos as $photo) {
                                $secure_photo_url = 'serve_photo.php?project_id=' . $project["id"] . '&photo=' . urlencode($photo);
                                echo '<div class="photo-item" style="position: relative; width: 150px; height: 150px; overflow: hidden; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">';
                                echo '<img src="' . $secure_photo_url . '" style="width: 100%; height: 100%; object-fit: cover; cursor: pointer;" onclick="openLightbox(\'' . $secure_photo_url . '\');">';
                                echo '<form action="delete_photo.php" method="POST" style="position: absolute; top: 5px; right: 5px;">';
                                echo '<input type="hidden" name="project_id" value="' . $project["id"] . '">';
                                echo '<input type="hidden" name="photo_name" value="' . $photo . '">';
                                echo '<button type="submit" style="background-color: rgba(220, 53, 69, 0.8); color: white; border: none; border-radius: 50%; width: 24px; height: 24px; padding: 0; cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: bold;">×</button>';
                                echo '</form>';
                                echo '</div>';
                            }
                        } else {
                            echo '<p>No photos uploaded yet.</p>';
                        }
                        ?>
                    </div>
                    
                    <!-- Lightbox Modal -->
                    <div id="photoLightbox" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.9); overflow: hidden;">
                        <span id="lightboxClose" style="position: absolute; top: 15px; right: 25px; color: white; font-size: 35px; font-weight: bold; cursor: pointer;">&times;</span>
                        <img id="lightboxImage" style="display: block; max-width: 90%; max-height: 90%; margin: auto; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
                    </div>
                </div>

                <!-- Project Edit Form -->
                <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 15px;">Project Details</h3>
                <form method="POST" class="model-form">
                    <input type="hidden" name="update_project" value="1">
                    
                    <!-- Main Info Section -->
                    <div style="margin-bottom: 20px;">
                        <h4 style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 15px;">Main Information</h4>
                        <div>
                            <label>Name:</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($project["name"]) ?>">
                        </div>
                        
                        <div style="display: flex; align-items: center; margin-top: 10px;">
                            <label style="margin-right: 10px;">Price: <input type="number" name="price" value="<?= htmlspecialchars($project["price"] ?? '') ?>" style="width: 120px;"></label>
                            <label style="display: flex; align-items: center;">
                                <span style="margin-right: 10px;">Currency:</span>
                                <select name="currency">
                                    <option value="USD" <?= ($project["currency"] ?? 'USD') == "USD" ? "selected" : "" ?>>USD</option>
                                    <option value="EUR" <?= ($project["currency"] ?? '') == "EUR" ? "selected" : "" ?>>EUR</option>
                                    <option value="GBP" <?= ($project["currency"] ?? '') == "GBP" ? "selected" : "" ?>>GBP</option>
                                    <option value="JPY" <?= ($project["currency"] ?? '') == "JPY" ? "selected" : "" ?>>JPY</option>
                                    <option value="CNY" <?= ($project["currency"] ?? '') == "CNY" ? "selected" : "" ?>>CNY</option>
                                </select>
                            </label>
                        </div>
                        
                        <div style="margin-top: 10px;">
                            <label>Address:</label>
                            <input type="text" name="address" value="<?= htmlspecialchars($project["address"]) ?>">
                        </div>
                        
                        <div style="display: flex; align-items: center; margin-top: 10px;">
                            <label style="margin-right: 10px;">Size: <input type="number" name="size" value="<?= htmlspecialchars($project["size"] ?? '') ?>" style="width: 80px;"></label>
                            <label style="display: flex; align-items: center;">
                                <span style="margin-right: 10px;">Unit:</span>
                                <select name="size_unit">
                                    <option value="sq.m" <?= ($project["size_unit"] ?? 'sq.m') == "sq.m" ? "selected" : "" ?>>sq.m</option>
                                    <option value="sq.ft" <?= ($project["size_unit"] ?? '') == "sq.ft" ? "selected" : "" ?>>sq.ft</option>
                                    <option value="acre" <?= ($project["size_unit"] ?? '') == "acre" ? "selected" : "" ?>>acre</option>
                                    <option value="hectare" <?= ($project["size_unit"] ?? '') == "hectare" ? "selected" : "" ?>>hectare</option>
                                </select>
                            </label>
                        </div>
                        
                        <div style="margin-top: 10px;"><label>Bedrooms: <input type="number" name="bedrooms" value="<?= htmlspecialchars($project["bedrooms"] ?? '') ?>"></label></div>
                        <div style="margin-top: 10px;"><label>Floor: <input type="number" name="floor" value="<?= htmlspecialchars($project["floor"] ?? '') ?>"></label></div>
                        <div style="margin-top: 10px;"><label>Type: 
                            <select name="type">
                                <option value="apartment" <?= ($project["type"] ?? '') == "apartment" ? "selected" : "" ?>>Apartment</option>
                                <option value="house" <?= ($project["type"] ?? '') == "house" ? "selected" : "" ?>>House</option>
                                <option value="commercial" <?= ($project["type"] ?? '') == "commercial" ? "selected" : "" ?>>Commercial</option>
                                <option value="land" <?= ($project["type"] ?? '') == "land" ? "selected" : "" ?>>Land</option>
                                <option value="other" <?= ($project["type"] ?? '') == "other" ? "selected" : "" ?>>Other</option>
                            </select>
                        </label></div>
                        <div style="margin-top: 10px;">
                            <label>Description:</label>
                            <textarea name="description" rows="4" class="form-textarea"><?= htmlspecialchars($project["description"]) ?></textarea>
                        </div>
                    </div>
                    
                    <!-- General Location Section -->
                    <div style="margin-bottom: 20px;">
                        <h4 style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 15px;">General Location</h4>
                        <div>
                            <label>Country:</label>
                            <input type="text" name="country" value="<?= htmlspecialchars($project["country"]) ?>">
                        </div>
                        <div style="margin-top: 10px;">
                            <label>City:</label>
                            <input type="text" name="city" value="<?= htmlspecialchars($project["city"]) ?>">
                        </div>
                    </div>
                    
                    <!-- Hidden 3D Map Parameters -->
                    <input type="hidden" name="lat" value="<?= htmlspecialchars($project["lat"] ?? 0) ?>">
                    <input type="hidden" name="lng" value="<?= htmlspecialchars($project["lng"] ?? 0) ?>">
                    <input type="hidden" name="altitude" value="<?= htmlspecialchars($project["altitude"] ?? 0) ?>">
                    <input type="hidden" name="camera_range" value="<?= htmlspecialchars($project["camera_range"] ?? 200) ?>">
                    <input type="hidden" name="camera_tilt" value="<?= htmlspecialchars($project["camera_tilt"] ?? 45) ?>">
                    
                    <div><button type="submit" class="btn btn-primary">Save Changes</button></div>
                </form>

            </div>
        </div>
    </div>

    
    <script>
        // Lightbox functionality
        function openLightbox(imageSrc) {
            const lightbox = document.getElementById('photoLightbox');
            const lightboxImage = document.getElementById('lightboxImage');
            
            lightboxImage.src = imageSrc;
            lightbox.style.display = 'block';
            
            // Prevent scrolling when lightbox is open
            document.body.style.overflow = 'hidden';
        }
        
        // Close lightbox when clicking the x button or outside the image
        document.getElementById('lightboxClose').addEventListener('click', function() {
            document.getElementById('photoLightbox').style.display = 'none';
            document.body.style.overflow = 'auto';
        });
        
        // Close lightbox when clicking outside the image
        document.getElementById('photoLightbox').addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
        
        // Add event listener for notification handling
        document.addEventListener('DOMContentLoaded', function() {
            // Check for success or error messages in URL
            const urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.has('success')) {
                const successType = urlParams.get('success');
                let message = '';
                
                if (successType === 'photo_uploaded') {
                    message = 'Photo uploaded successfully!';
                } else if (successType === 'photo_deleted') {
                    message = 'Photo deleted successfully!';
                }
                
                if (message) {
                    showNotification(message, 'success');
                    
                    // Remove the success parameter from URL to prevent notification on refresh
                    let newUrl = new URL(window.location.href);
                    newUrl.searchParams.delete('success');
                    window.history.replaceState({}, document.title, newUrl.toString());
                }
            }
            
            if (urlParams.has('error')) {
                const errorType = urlParams.get('error');
                let message = '';
                
                if (errorType === 'upload_failed') {
                    message = 'Failed to upload photo. Please try again.';
                } else if (errorType === 'invalid_file_type') {
                    message = 'Invalid file type. Please upload a valid image file.';
                } else if (errorType === 'delete_failed') {
                    message = 'Failed to delete photo. Please try again.';
                } else if (errorType === 'file_not_found') {
                    message = 'Photo not found.';
                }
                
                if (message) {
                    showNotification(message, 'error');
                    
                    // Remove the error parameter from URL to prevent notification on refresh
                    let newUrl = new URL(window.location.href);
                    newUrl.searchParams.delete('error');
                    window.history.replaceState({}, document.title, newUrl.toString());
                }
            }
        });
        
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
    </script>
</body>
</html>
