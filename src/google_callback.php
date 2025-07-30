<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'config.php';
require 'db.php';
// Add Google API service for API key creation
require_once 'google_api_service.php';

$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);

if (isset($_GET['code'])) {
    try {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        
        if (isset($token['error'])) {
            throw new Exception('Error fetching access token: ' . $token['error']);
        }
        
        $client->setAccessToken($token);
        $oauth = new Google_Service_Oauth2($client);
        $google_user = $oauth->userinfo->get();
        
        $google_id = $google_user->id;
        $name = $google_user->name;
        $email = $google_user->email;
        
        // Check if user exists
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE google_id = ?");
        $stmt->bind_param("s", $google_id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows === 0) {
            // Start transaction for new user creation
            $conn->begin_transaction();
            
            try {
                // Create new user
                $stmt = $conn->prepare("INSERT INTO users (name, email, google_id) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $name, $email, $google_id);
                $stmt->execute();
                $user_id = $stmt->insert_id;
                
                // Create Google Maps API key for the new user
                try {
                    $apiKeyResult = createGoogleMapsApiKey($user_id, $email);
                    
                    if ($apiKeyResult['success']) {
                        // Store the API key in the database
                        $apiKey = $apiKeyResult['key'];
                        $updateStmt = $conn->prepare("UPDATE users SET google_maps_api_key = ? WHERE id = ?");
                        $updateStmt->bind_param("si", $apiKey, $user_id);
                        $updateStmt->execute();
                    } else {
                        // API key creation failed, log the error
                        error_log("API key creation failed for Google user: " . $apiKeyResult['error']);
                    }
                } catch (Exception $e) {
                    // Log any exceptions during API key creation
                    error_log("Exception during API key creation for Google user: " . $e->getMessage());
                }
                
                // Create user directory regardless of API key creation success
                $userFolder = "project/users/{$user_id}";
                if (!is_dir($userFolder)) {
                    if (!mkdir($userFolder, 0775, true)) {
                        error_log("Failed to create user directory: {$userFolder}");
                    }
                }
                
                // Commit transaction
                $conn->commit();
                
            } catch (Exception $e) {
                // Rollback transaction in case of error
                $conn->rollback();
                throw $e;
            }
        } else {
            // Get existing user
            $stmt->bind_result($user_id, $name);
            $stmt->fetch();
        }
        
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        
        header('Location: account.php');
        exit;
        
    } catch (Exception $e) {
        error_log('Google OAuth Error: ' . $e->getMessage());
        echo "Login failed. Please try again.";
    }
} else {
    echo "Google login failed - no authorization code received.";
}