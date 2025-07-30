<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require '../db.php';

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$project_id = $_GET["project_id"] ?? 0;

// Validate project belongs to user
$stmt = $conn->prepare("SELECT name FROM projects WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $project_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$project = $result->fetch_assoc();

if (!$project) {
    echo "Project not found.";
    exit;
}

// Get videos from database if table exists
$videos = [];
$table_exists = false;

// Check if project_videos table exists
$check_table = $conn->query("SHOW TABLES LIKE 'project_videos'");
if ($check_table->num_rows > 0) {
    $table_exists = true;
    $stmt = $conn->prepare("SELECT * FROM project_videos WHERE project_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($video = $result->fetch_assoc()) {
        $videos[] = $video;
    }
}

// Only check filesystem for videos if no database records found
$all_videos = [];
if (!empty($videos)) {
    // Use database records
    $all_videos = $videos;
} else {
    // Fall back to filesystem scanning
    $video_dir = "users/$user_id/$project_id/videos";
    if (is_dir($video_dir)) {
        $files = scandir($video_dir);
        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            // Include both videos and images (screenshots)
            if (in_array($extension, ['webm', 'mp4', 'png', 'jpg', 'jpeg', 'webp'])) {
                $all_videos[] = [
                    'filename' => $file,
                    'file_path' => "$video_dir/$file",
                    'file_size' => filesize("$video_dir/$file"),
                    'created_at' => date('Y-m-d H:i:s', filemtime("$video_dir/$file")),
                    'file_type' => in_array($extension, ['webm', 'mp4']) ? 'video' : 'image'
                ];
            }
        }
        
        // Sort by creation date (newest first)
        usort($all_videos, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Videos & Screenshots - <?= htmlspecialchars($project['name']) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .video-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .video-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            background: #fafafa;
        }
        .video-card video {
            width: 100%;
            max-height: 200px;
            border-radius: 4px;
            transition: opacity 0.3s ease;
        }
        .video-card img {
            width: 100%;
            max-height: 200px;
            border-radius: 4px;
            object-fit: cover;
            transition: opacity 0.3s ease;
        }
        .video-card video:not(.loaded) {
            opacity: 0.7;
            background: #f0f0f0;
        }
        .video-card video.loaded {
            opacity: 1;
        }
        .video-card video.seekable {
            border: 2px solid #28a745;
            border-radius: 6px;
        }
        .video-info {
            margin-top: 10px;
        }
        .video-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .video-details {
            font-size: 0.9em;
            color: #666;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #007cba;
            text-decoration: none;
            padding: 8px 16px;
            border: 1px solid #007cba;
            border-radius: 4px;
        }
        .back-link:hover {
            background-color: #007cba;
            color: white;
        }
        .no-videos {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 40px;
        }
        .download-btn {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 16px;
            background-color: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.9em;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .download-btn:hover {
            background-color: #218838;
        }
        .delete-btn {
            display: inline-block;
            margin-top: 10px;
            margin-left: 10px;
            padding: 8px 16px;
            background-color: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.9em;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .delete-btn:hover {
            background-color: #c82333;
        }
        .media-type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .media-type-video {
            background-color: #007bff;
            color: white;
        }
        .media-type-image {
            background-color: #28a745;
            color: white;
        }
    </style>
    <script>
        function deleteMedia(projectId, filename, mediaType) {
            const mediaTypeName = mediaType === 'video' ? 'video' : 'screenshot';
            if (!confirm(`Are you sure you want to delete this ${mediaTypeName}? This action cannot be undone.`)) {
                return;
            }
            
            // Create form data
            const formData = new FormData();
            formData.append('project_id', projectId);
            formData.append('filename', filename);
            
            // Send AJAX request
            fetch('delete_video.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`${mediaTypeName.charAt(0).toUpperCase() + mediaTypeName.slice(1)} deleted successfully!`);
                    // Remove the media card from the page
                    const mediaCard = document.querySelector(`[data-filename="${filename}"]`);
                    if (mediaCard) {
                        mediaCard.remove();
                    }
                    
                    // Check if no media remains
                    const remainingCards = document.querySelectorAll('.video-card');
                    if (remainingCards.length === 0) {
                        document.querySelector('.video-grid').innerHTML = 
                            '<div class="no-videos">No videos or screenshots found for this project. Start recording or taking screenshots to see your media here!</div>';
                    }
                } else {
                    alert(`Error deleting ${mediaTypeName}: ` + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(`An error occurred while deleting the ${mediaTypeName}.`);
            });
        }
        
        // Keep the old function for backward compatibility
        function deleteVideo(projectId, filename) {
            deleteMedia(projectId, filename, 'video');
        }
    </script>
    <script>
        // Optimize video loading and seeking
        document.addEventListener('DOMContentLoaded', function() {
            const videos = document.querySelectorAll('video');
            
            videos.forEach(video => {
                // Force load video metadata for better seeking
                video.addEventListener('loadstart', function() {
                    this.preload = 'metadata';
                });
                
                // Handle seeking improvements
                video.addEventListener('loadedmetadata', function() {
                    // Enable seeking throughout the video
                    this.currentTime = 0.1; // Small seek to enable timeline
                    this.currentTime = 0;
                });
                
                // Prevent multiple loading of the same video
                video.addEventListener('loadeddata', function() {
                    this.classList.add('loaded');
                });
                
                // Handle seeking errors
                video.addEventListener('error', function(e) {
                    console.error('Video error:', e);
                    // Retry loading after a short delay
                    setTimeout(() => {
                        if (!this.classList.contains('loaded')) {
                            this.load();
                        }
                    }, 1000);
                });
                
                // Optimize for seeking by buffering more data
                video.addEventListener('progress', function() {
                    if (this.buffered.length > 0) {
                        const bufferedEnd = this.buffered.end(this.buffered.length - 1);
                        const duration = this.duration;
                        if (duration > 0) {
                            const percentBuffered = (bufferedEnd / duration) * 100;
                            // Enable seeking when we have enough buffer
                            if (percentBuffered > 10) {
                                this.classList.add('seekable');
                            }
                        }
                    }
                });
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="project_map_animation.php?id=<?= $project_id ?>" class="back-link">← Back to Project</a>
            <h1>Videos & Screenshots for "<?= htmlspecialchars($project['name']) ?>"</h1>
        </div>

        <?php if (empty($all_videos)): ?>
            <div class="no-videos">
                No videos or screenshots found for this project. Start recording or taking screenshots to see your media here!
            </div>
        <?php else: ?>
            <div class="video-grid">
                <?php foreach ($all_videos as $media): ?>
                    <?php 
                    $fileType = $media['file_type'] ?? (in_array(strtolower(pathinfo($media['filename'], PATHINFO_EXTENSION)), ['webm', 'mp4']) ? 'video' : 'image');
                    ?>
                    <div class="video-card" data-filename="<?= htmlspecialchars($media['filename']) ?>">
                        <div class="media-type-badge media-type-<?= $fileType ?>">
                            <?= $fileType === 'video' ? '🎥 VIDEO' : '📸 SCREENSHOT' ?>
                        </div>
                        
                        <?php if ($fileType === 'video'): ?>
                            <video controls preload="metadata" crossorigin="anonymous">
                                <source src="serve_video.php?project_id=<?= $project_id ?>&filename=<?= urlencode($media['filename']) ?>" type="video/webm">
                                Your browser does not support the video tag.
                            </video>
                        <?php else: ?>
                            <img src="serve_video.php?project_id=<?= $project_id ?>&filename=<?= urlencode($media['filename']) ?>" 
                                 alt="Screenshot: <?= htmlspecialchars($media['filename']) ?>"
                                 onclick="window.open(this.src, '_blank')" 
                                 style="cursor: pointer;" 
                                 title="Click to view full size">
                        <?php endif; ?>
                        
                        <div class="video-info">
                            <div class="video-name"><?= htmlspecialchars($media['filename']) ?></div>
                            <div class="video-details">
                                Size: <?= number_format($media['file_size'] / 1024 / 1024, 2) ?> MB<br>
                                Created: <?= $media['created_at'] ?>
                            </div>
                            <a href="serve_video.php?project_id=<?= $project_id ?>&filename=<?= urlencode($media['filename']) ?>&download=1" class="download-btn">
                                Download <?= $fileType === 'video' ? 'Video' : 'Screenshot' ?>
                            </a>
                            <a href="#" class="delete-btn" onclick="deleteMedia(<?= $project_id ?>, '<?= htmlspecialchars($media['filename']) ?>', '<?= $fileType ?>')">
                                Delete <?= $fileType === 'video' ? 'Video' : 'Screenshot' ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 