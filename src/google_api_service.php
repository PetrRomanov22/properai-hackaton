<?php
/**
 * Google API Service
 * This file handles the creation of Google Maps API keys for users
 */

require_once __DIR__ . '/vendor/autoload.php';

use Google\Client;
use Google\Service\ApiKeysService;
use Google\Service\ApiKeysService\V2Key;
use Google\Service\ApiKeysService\V2Restrictions;
use Google\Service\ApiKeysService\V2BrowserKeyRestrictions;
use Google\Service\ApiKeysService\V2ApiTarget;

/**
 * Create a new Google Maps API key for a user
 * 
 * @param int $userId The user ID
 * @param string $userEmail The user's email
 * @return array Array containing 'success' status and either 'key' or 'error'
 */
function createGoogleMapsApiKey($userId, $userEmail) {
    try {
        // Path to service account credentials - set this to your actual service account key file
        $keyFilePath = __DIR__ . '/keys/your-service-account-key.json';
        
        // Create the client
        $client = new Client();
        $client->setAuthConfig($keyFilePath);
        $client->addScope('https://www.googleapis.com/auth/cloud-platform');
        
        // Set up API keys service
        $apiKeys = new ApiKeysService($client);
        
        // Your Google Cloud project ID (from service account)
        $projectId = $_ENV['GOOGLE_CLOUD_PROJECT_ID'] ?? 'your-google-cloud-project-id';
        
        // Create a new API key
        $key = new V2Key();
        $key->setDisplayName("ProperAI-UserKey-{$userId}");
        
        // Set restrictions for the key
        $restrictions = new V2Restrictions();
        
        // Set browser key restrictions
        $browserKeyRestrictions = new V2BrowserKeyRestrictions();
        $browserKeyRestrictions->setAllowedReferrers(['https://properai.info/*', 'https://www.properai.info/*', 'https://properai.pro/*', 'https://www.properai.pro/*']);
        $restrictions->setBrowserKeyRestrictions($browserKeyRestrictions);
        
        // Set API targets (Maps API)
        $apiTarget = new V2ApiTarget();
        $apiTarget->setService('maps-backend.googleapis.com');
        $apiTarget->setMethods(['*']);
        $restrictions->setApiTargets([$apiTarget]);
        
        $key->setRestrictions($restrictions);
        
        // Create the key - this returns an operation
        $operation = $apiKeys->projects_locations_keys->create(
            "projects/{$projectId}/locations/global",
            $key
        );
        
        // Wait until the operation is complete
        $operationName = $operation->getName();
        $maxAttempts = 10;
        $delay = 1;
        
        while (!$operation->getDone() && $maxAttempts-- > 0) {
            sleep($delay);
            $operation = $apiKeys->operations->get($operationName);
        }
        
        if (!$operation->getDone()) {
            throw new \Exception("Key creation timed out.");
        }
        
        // Extract the API key from the response
        $response = $operation->getResponse();
        $createdKey = new V2Key();
        $createdKey->setKeyString($response['keyString']);
        $apiKey = $createdKey->getKeyString();
        
        return [
            'success' => true,
            'key' => $apiKey
        ];
    } catch (\Exception $e) {
        // Log the error (consider using a proper logging system)
        error_log("Error creating Google Maps API key: " . $e->getMessage());
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
} 