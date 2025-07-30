<?php
require_once 'vendor/autoload.php';
require_once 'config.php';

$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
$client->addScope('email');
$client->addScope('profile');

$loginUrl = $client->createAuthUrl();
header('Location: ' . filter_var($loginUrl, FILTER_SANITIZE_URL));
exit;