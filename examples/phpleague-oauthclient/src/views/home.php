<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Amtgard IDP Client (Slim/PHPLeague)</title>
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
            width: 350px;
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

        #validation-result {
            margin-bottom: 1rem;
            padding: 1rem;
            border-radius: 4px;
            display: none;
        }
    </style>
    <script>
        async function doValidate() {
            const resultDiv = document.getElementById('validation-result');
            resultDiv.style.display = 'none';

            try {
                const response = await fetch('/validate', { method: 'POST' });
                const data = await response.json();

                resultDiv.style.display = 'block';
                if (data.success) {
                    resultDiv.style.backgroundColor = '#e6ffe6';
                    resultDiv.style.border = '1px solid #ccffcc';
                    resultDiv.innerHTML = '<strong>Validation Result:</strong> Success<br><pre style="font-size: 0.8rem; overflow-x: auto;">' + JSON.stringify(data.data, null, 2) + '</pre>';
                } else {
                    resultDiv.style.backgroundColor = '#ffe6e6';
                    resultDiv.style.border = '1px solid #ffcccc';
                    resultDiv.innerHTML = '<strong>Validation Result:</strong> Failed<br><span style="color: red;">' + data.message + '</span>';
                }
            } catch (e) {
                console.error(e);
            }
        }
    </script>
</head>

<body>
    <div class="container">
        <h1>IDP Client (Slim)</h1>

        <div class="status-line">
            Registered? <span class="status-value <?= $isRegistered ? 'yes' : 'no' ?>">
                <?= $isRegistered ? 'Yes' : 'No' ?>
            </span>
        </div>
        <div class="status-line">
            Logged In? <span class="status-value <?= $isLoggedIn ? 'yes' : 'no' ?>">
                <?= $isLoggedIn ? 'Yes' : 'No' ?>
            </span>
        </div>

        <div id="validation-result"></div>

        <?php if ($isLoggedIn && $user): ?>
            <div style="margin-top: 1rem; font-size: 0.9rem; color: #666;">
                Welcome,
                <?= htmlspecialchars($user['email']) ?>
                <br>
                Access Token Expires:
                <?= ($user['access_expires_at'] ?? null) ? date('Y-m-d H:i:s', $user['access_expires_at']) : 'Unknown' ?>
                <?php if ($user['refresh_token']): ?>
                    <br>
                    Refresh Token Expires:
                    <?= ($user['refresh_expires_at'] ?? null) ? date('Y-m-d H:i:s', $user['refresh_expires_at']) : 'Unknown' ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="controls">
            <?php if (!$isLoggedIn): ?>
                <form action="/login" method="get" style="display:contents">
                    <button type="submit">Register</button>
                    <button type="submit">Login</button>
                </form>
            <?php else: ?>
                <?php if ($user && $user['refresh_token']): ?>
                    <button type="button" onclick="doValidate()"
                        style="background-color: #e6f7ff; color: #005580; border: 1px solid #005580;">Validate</button>
                    <!-- Refresh is implicit in Validate or next Login, but we can add explicit refresh if needed. 
                         The plain-php example had explicit refresh. Let's rely on validate for now or add a route. -->
                    <form action="/login" method="get" style="display:contents">
                        <!-- Re-hitting login with a refresh token stored will refresh it or just redirect back -->
                        <button type="submit"
                            style="background-color: #e0f7fa; color: #006064; border: 1px solid #006064;">Refresh</button>
                    </form>
                <?php endif; ?>
                <form action="/logout" method="post" style="display:contents">
                    <button type="submit">Logout</button>
                </form>
            <?php endif; ?>

            <form action="/clear_db" method="post" style="grid-column: span 2;">
                <button type="submit"
                    style="background-color: #ffcccc; color: #cc0000; border: 1px solid #cc0000;">Clear DB</button>
            </form>
        </div>
    </div>
</body>

</html>