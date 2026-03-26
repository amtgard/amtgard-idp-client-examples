<?php
// public/index.php

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/oauth.php';

// Start session to store tokens/state
session_start();

// Initialize DB
$db = getDb();
initDb($db);

// Handle OAuth Callback
if (isset($_GET['code'])) {
    handleCallback($db, $_GET);
    header('Location: /');
    exit;
}

// Handle Login Action
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    // Check if we have a user with a refresh token
    $lastUser = getLastUser($db);
    if ($lastUser && $lastUser['refresh_token']) {
        // Attempt to just restore session if access token is valid
        // Give 10s buffer
        if (time() < ($lastUser['access_expires_at'] - 10)) {
            $_SESSION['user_id'] = $lastUser['id'];
            header('Location: /');
            exit;
        }

        // Attempt refresh
        $refreshed = refreshAccessToken($db, $lastUser['id']);
        if ($refreshed) {
            $_SESSION['user_id'] = $lastUser['id'];
            header('Location: /');
            exit;
        }
        // If refresh failed (e.g. revoked), fall through to normal login
    }

    $url = getLoginUrl();
    header('Location: ' . $url);
    exit;
}

// Handle Register Action - same as login for this flow, but semantically strict
if (isset($_POST['action']) && $_POST['action'] === 'register') {
    $url = getLoginUrl();
    header('Location: ' . $url);
    exit;
}

// Handle Clear DB Action
if (isset($_POST['action']) && $_POST['action'] === 'clear_db') {
    clearUsers($db);
    session_destroy();
    header('Location: /');
    exit;
}

// Handle Logout Action
if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_destroy();
    header('Location: /');
    exit;
}

// Handle Refresh Action
if (isset($_POST['action']) && $_POST['action'] === 'refresh') {
    if (isset($_SESSION['user_id'])) {
        refreshAccessToken($db, $_SESSION['user_id']);
    }
    header('Location: /');
    exit;
}

// Handle User Info Action
$userInfoResult = null;
if (isset($_POST['action']) && $_POST['action'] === 'user_info') {
    if (isset($_SESSION['user_id'])) {
        $user = getUser($db, $_SESSION['user_id']);
        if ($user) {
            // Check expiry (give it a 10 second buffer)
            if (time() >= ($user['access_expires_at'] - 10)) {
                // Token expired or about to expire, try to refresh
                $refreshed = refreshAccessToken($db, $_SESSION['user_id']);
                if (!$refreshed) {
                    $userInfoResult = ['success' => false, 'message' => 'Token expired and refresh failed (revoked or expired).'];
                } else {
                    // Reload user to get new token
                    $user = getUser($db, $_SESSION['user_id']);
                }
            }

            if (!$userInfoResult) { // If we haven't failed yet
                $res = fetchUserProfile($user['access_token']);
                if ($res['success']) {
                    $userInfoResult = ['success' => true, 'data' => $res['data']];
                } else {
                    $userInfoResult = ['success' => false, 'message' => 'User Info failed: ' . $res['error']];
                }
            }
        } else {
            $userInfoResult = ['success' => false, 'message' => 'User not found in DB.'];
        }
    } else {
        $userInfoResult = ['success' => false, 'message' => 'Not logged in.'];
    }
}

// Handle Validate Action
$validationResult = null;
if (isset($_POST['action']) && $_POST['action'] === 'validate') {
    if (isset($_SESSION['user_id'])) {
        $user = getUser($db, $_SESSION['user_id']);
        if ($user) {
            // Check expiry (give it a 10 second buffer)
            if (time() >= ($user['access_expires_at'] - 10)) {
                $refreshed = refreshAccessToken($db, $_SESSION['user_id']);
                if (!$refreshed) {
                    $validationResult = ['success' => false, 'message' => 'Token expired and refresh failed.'];
                } else {
                    $user = getUser($db, $_SESSION['user_id']);
                }
            }

            if (!$validationResult) {
                $res = validateToken($user['access_token']);
                if ($res['success']) {
                    $validationResult = ['success' => true, 'data' => $res['data']];
                } else {
                    $validationResult = ['success' => false, 'message' => 'Validation failed: ' . $res['error']];
                }
            }
        } else {
            $validationResult = ['success' => false, 'message' => 'User not found.'];
        }
    } else {
        $validationResult = ['success' => false, 'message' => 'Not logged in.'];
    }
}

// Check status
// Check status
$isLoggedIn = isset($_SESSION['user_id']);
$isRegistered = hasAnyUser($db);

if ($isLoggedIn) {
    $user = getUser($db, $_SESSION['user_id']);
    if (!$user) {
        // Session invalid or user deleted
        session_destroy();
        $isLoggedIn = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Amtgard IDP Test Client</title>
    <style>
        body {
            font-family: sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f0f0f0;
        }

        .container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 300px;
        }

        .status-line {
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .status-value {
            font-weight: bold;
        }

        .controls {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 2rem;
        }

        button {
            padding: 0.5rem;
            cursor: pointer;
            width: 100%;
        }

        .yes {
            color: green;
        }

        .no {
            color: red;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>IDP Client</h1>

        <div class="status-line">
            Registered? <span
                class="status-value <?php echo $isRegistered ? 'yes' : 'no'; ?>"><?php echo $isRegistered ? 'Yes' : 'No'; ?></span>
        </div>
        <div class="status-line">
            Logged In? <span
                class="status-value <?php echo $isLoggedIn ? 'yes' : 'no'; ?>"><?php echo $isLoggedIn ? 'Yes' : 'No'; ?></span>
        </div>

        <?php if (isset($userInfoResult)): ?>
            <div
                style="margin-bottom: 1rem; padding: 1rem; border-radius: 4px; border: 1px solid <?php echo $userInfoResult['success'] ? '#ccffcc' : '#ffcccc'; ?>; background-color: <?php echo $userInfoResult['success'] ? '#e6ffe6' : '#ffe6e6'; ?>;">
                <strong>User Info Result:</strong> <?php echo $userInfoResult['success'] ? 'Success' : 'Failed'; ?><br>
                <?php if ($userInfoResult['success']): ?>
                    <pre
                        style="font-size: 0.8rem; overflow-x: auto;"><?php echo htmlspecialchars(json_encode($userInfoResult['data'], JSON_PRETTY_PRINT)); ?></pre>
                <?php else: ?>
                    <span style="color: red;"><?php echo htmlspecialchars($userInfoResult['message']); ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($validationResult)): ?>
            <div
                style="margin-bottom: 1rem; padding: 1rem; border-radius: 4px; border: 1px solid <?php echo $validationResult['success'] ? '#ccffcc' : '#ffcccc'; ?>; background-color: <?php echo $validationResult['success'] ? '#e6ffe6' : '#ffe6e6'; ?>;">
                <strong>Validation Result:</strong> <?php echo $validationResult['success'] ? 'Success' : 'Failed'; ?><br>
                <?php if ($validationResult['success']): ?>
                    <pre
                        style="font-size: 0.8rem; overflow-x: auto;"><?php echo htmlspecialchars(json_encode($validationResult['data'], JSON_PRETTY_PRINT)); ?></pre>
                <?php else: ?>
                    <span style="color: red;"><?php echo htmlspecialchars($validationResult['message']); ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($isLoggedIn && $user): ?>
            <div style="margin-top: 1rem; font-size: 0.9rem; color: #666;">
                Welcome, <?= htmlspecialchars($user['email']) ?>
                <br>
                Access Token Expires:
                <?= ($user['access_expires_at'] ?? null) ? date('Y-m-d H:i:s', $user['access_expires_at']) : 'Unknown' ?>
                <?php if ($user['refresh_token']): ?>
                    <br>
                    Refresh Token Expires:
                    <?= ($user['refresh_expires_at'] ?? null) ? date('Y-m-d H:i:s', $user['refresh_expires_at']) : 'Unknown (Not provided by IDP)' ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="controls">
            <?php if (!$isLoggedIn): ?>
                <button type="submit" name="action" value="register">Register</button>
                <button type="submit" name="action" value="login">Login</button>
            <?php else: ?>
                <?php if ($user && $user['refresh_token']): ?>
                    <button type="submit" name="action" value="user_info"
                        style="background-color: #e6f7ff; color: #005580; border: 1px solid #005580;">User Info</button>
                    <button type="submit" name="action" value="validate"
                        style="background-color: #e6ffe6; color: #006600; border: 1px solid #006600;">Validate</button>
                    <button type="submit" name="action" value="refresh"
                        style="background-color: #e0f7fa; color: #006064; border: 1px solid #006064;">Refresh</button>
                <?php endif; ?>
                <button type="submit" name="action" value="logout">Logout</button>
            <?php endif; ?>

            <button type="submit" name="action" value="clear_db"
                style="background-color: #ffcccc; color: #cc0000; border: 1px solid #cc0000;">Clear
                DB</button>
        </form>
    </div>
</body>

</html>