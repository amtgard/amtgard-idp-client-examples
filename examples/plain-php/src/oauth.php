<?php
// src/oauth.php

// Configuration
define('CLIENT_ID', 'test_amtgard_idp_client');
define('CLIENT_SECRET', 'secret');
define('IDP_BASE_URL', 'http://host.docker.internal:37080'); // Use host.docker.internal to reach host from container
define('REDIRECT_URI', 'http://localhost:37180');

// PKCE Helpers
function base64UrlEncode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function generateCodeVerifier()
{
    return base64UrlEncode(random_bytes(32));
}

function generateCodeChallenge($verifier)
{
    return base64UrlEncode(hash('sha256', $verifier, true));
}

function getLoginUrl()
{
    $verifier = generateCodeVerifier();
    $challenge = generateCodeChallenge($verifier);
    $state = bin2hex(random_bytes(16));

    // Store verifier and state in session for later verification
    $_SESSION['oauth_verifier'] = $verifier;
    $_SESSION['oauth_state'] = $state;

    $params = [
        'response_type' => 'code',
        'client_id' => CLIENT_ID,
        'redirect_uri' => REDIRECT_URI,
        'scope' => 'profile email',
        'state' => $state,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
        'approval_prompt' => 'auto'
    ];

    $authUrl = 'https://idp.amtgard.com/oauth/authorize?' . http_build_query($params);

    return $authUrl;
}

function handleCallback($db, $params)
{
    if (!isset($params['code']) || !isset($params['state'])) {
        die('Invalid callback parameters');
    }

    if ($params['state'] !== $_SESSION['oauth_state']) {
        die('Invalid state');
    }

    $code = $params['code'];
    $verifier = $_SESSION['oauth_verifier'];

    // Exchange code for token
    // Exchange code for token
    $tokenUrl = IDP_BASE_URL . '/oauth/token';

    $postData = [
        'grant_type' => 'authorization_code',
        'client_id' => CLIENT_ID,
        'client_secret' => CLIENT_SECRET,
        'redirect_uri' => REDIRECT_URI,
        'code_verifier' => $verifier,
        'code' => $code,
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    // Since we might be using self-signed certs or local dev, disable verifying peer if needed.
    // Ideally we shouldn't on prod, but for local dev box:
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        die('Failed to get access token: ' . $response);
    }


    $tokenData = json_decode($response, true);

    $accessToken = $tokenData['access_token'];
    $refreshToken = $tokenData['refresh_token'] ?? null;
    $expiresIn = $tokenData['expires_in'] ?? 3600; // Default to 1 hour if missing
    $refreshExpiresIn = $tokenData['refresh_expires_in'] ?? null; // PHPLeague might send this?

    $accessExpiresAt = time() + $expiresIn;
    $refreshExpiresAt = $refreshExpiresIn ? (time() + $refreshExpiresIn) : null;

    $userProfileResult = fetchUserProfile($accessToken);

    if (!$userProfileResult['success']) {
        die("Error fetching user profile during callback: " . $userProfileResult['error']);
    }
    $userProfile = $userProfileResult['data'];

    // Save to DB
    $userId = upsertUser($db, $userProfile['id'] ?? 'unknown', $userProfile['email'] ?? 'unknown', $accessToken, $refreshToken, $accessExpiresAt, $refreshExpiresAt);

    $_SESSION['user_id'] = $userId;
}

function refreshAccessToken($db, $userId)
{
    $user = getUser($db, $userId);
    if (!$user || !$user['refresh_token']) {
        return false;
    }

    $tokenUrl = IDP_BASE_URL . '/oauth/token';

    $postData = [
        'grant_type' => 'refresh_token',
        'client_id' => CLIENT_ID,
        'client_secret' => CLIENT_SECRET,
        'refresh_token' => $user['refresh_token'],
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        // Refresh failed (revoked/expired)
        return false;
    }

    $tokenData = json_decode($response, true);
    $accessToken = $tokenData['access_token'];
    $refreshToken = $tokenData['refresh_token'] ?? $user['refresh_token']; // Keep old if not rotated
    $expiresIn = $tokenData['expires_in'] ?? 3600;
    $refreshExpiresIn = $tokenData['refresh_expires_in'] ?? null;

    $accessExpiresAt = time() + $expiresIn;
    $refreshExpiresAt = $refreshExpiresIn ? (time() + $refreshExpiresIn) : $user['refresh_expires_at'];

    // We need to upsert to update tokens. We need the original SUB and Email.
    // Ideally we update just tokens, but upsertUser works if we have the data.
    // But upsertUser requires sub/email which we might not want to fetch again?
    // Actually, upsertUser uses sub to find the user.
    // Let's create a specific updateTokens function in db.php? 
    // Or just reuse upsertUser since we have the user row.

    upsertUser($db, $user['amtgard_sub'], $user['email'], $accessToken, $refreshToken, $accessExpiresAt, $refreshExpiresAt);

    return true;
}

function fetchUserProfile($accessToken)
{
    // Attempting to hit the resource server
    // I will use a likely endpoint and we can debug if it fails.
    // Or I can read the IDP code in the next turn.
    // Let's assume /api/me for now.
    $url = IDP_BASE_URL . '/resources/userinfo';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Accept: application/json'
    ]);
    // Allow self-signed certs for local dev
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'error' => "Curl error: " . $curlError];
    }

    if ($httpCode !== 200) {
        $extra = '';
        if ($httpCode === 302) {
            // This really shouldn't happen for an API call, but good to know
            // $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL); // curl_close called already
            $extra = " -> Redirected (302)";
        }
        return ['success' => false, 'error' => 'Failed to fetch user profile (HTTP ' . $httpCode . ')' . $extra . ': ' . $response];
    }

    $data = json_decode($response, true);
    if (!$data) {
        return ['success' => false, 'error' => 'Failed to decode JSON response: ' . $response];
    }

    return ['success' => true, 'data' => $data];
}

function validateToken($accessToken)
{
    $url = IDP_BASE_URL . '/resources/validate';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return ['success' => false, 'error' => 'Validation failed (HTTP ' . $httpCode . '): ' . $response];
    }

    $data = json_decode($response, true);
    return ['success' => true, 'data' => $data];
}
