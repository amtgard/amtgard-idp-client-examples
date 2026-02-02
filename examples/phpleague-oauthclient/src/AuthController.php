<?php

namespace App;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

class AuthController
{
    private $db;
    private $provider;

    public function __construct(Database $db)
    {
        $this->db = $db;

        // Configuration - Hardcoded for example, typically env vars
        $this->provider = new GenericProvider([
            'clientId' => 'test_phpleague_oauth_client',    // The client ID assigned to you by the provider
            'clientSecret' => 'secret',                           // The client password assigned to you by the provider
            'redirectUri' => 'http://localhost:37181', // Root of localhost:37181
            'urlAuthorize' => 'http://localhost:37080/oauth/authorize',
            'urlAccessToken' => 'http://host.docker.internal:37080/oauth/token', // Internal Docker networking
            'urlResourceOwnerDetails' => 'http://host.docker.internal:37080/resources/userinfo',
            'verify' => false, // self-signed certs
            'scopes' => 'profile email'
        ]);

        // Note: urlAccessToken/ResourceOwnerDetails use host.docker.internal because PHP container calls them.
        // urlAuthorize is browser-side, so localhost.
    }

    public function login(Request $request, Response $response)
    {
        // seamless login check logic
        $user = $this->db->getLastUser();
        if ($user && $user['refresh_token']) {
            if (time() < ($user['access_expires_at'] - 10)) {
                $_SESSION['user_id'] = $user['id'];
                return $response->withHeader('Location', '/')->withStatus(302);
            }

            // Try refresh
            try {
                $newAccessToken = $this->provider->getAccessToken('refresh_token', [
                    'refresh_token' => $user['refresh_token']
                ]);

                // Update DB
                $this->updateUserToken($user, $newAccessToken);
                $_SESSION['user_id'] = $user['id'];
                return $response->withHeader('Location', '/')->withStatus(302);

            } catch (IdentityProviderException $e) {
                // Refresh failed
            }
        }

        // PKCE Setup
        $verifier = $this->generateCodeVerifier();
        $challenge = $this->generateCodeChallenge($verifier);
        $_SESSION['oauth2pkceCode'] = $verifier;

        $authUrl = $this->provider->getAuthorizationUrl([
            'scope' => 'profile email',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256'
        ]);

        $_SESSION['oauth2state'] = $this->provider->getState();

        return $response->withHeader('Location', $authUrl)->withStatus(302);
    }

    public function callback(Request $request, Response $response)
    {
        $params = $request->getQueryParams();

        if (empty($params['state']) || ($params['state'] !== $_SESSION['oauth2state'])) {
            unset($_SESSION['oauth2state']);
            $response->getBody()->write('Invalid state');
            return $response->withStatus(400);
        }

        try {
            $accessToken = $this->provider->getAccessToken('authorization_code', [
                'code' => $params['code'],
                'code_verifier' => $_SESSION['oauth2pkceCode'] ?? null
            ]);

            // Clean up session
            unset($_SESSION['oauth2pkceCode']);

            // We have an access token, which we may use in authenticated
            // requests against the service provider.
            $resourceOwner = $this->provider->getResourceOwner($accessToken);
            $userArray = $resourceOwner->toArray();

            // Save to DB
            $userId = $this->db->upsertUser(
                $userArray['id'] ?? 'unknown',
                $userArray['email'] ?? 'unknown',
                $accessToken->getToken(),
                $accessToken->getRefreshToken(),
                $accessToken->getExpires(),
                null // Refresh expiry not always standard in league access token object without custom handling
            );

            $_SESSION['user_id'] = $userId;

            return $response->withHeader('Location', '/')->withStatus(302);

        } catch (IdentityProviderException $e) {
            $response->getBody()->write($e->getMessage());
            return $response->withStatus(500);
        }
    }

    public function logout(Request $request, Response $response)
    {
        session_destroy();
        return $response->withHeader('Location', '/')->withStatus(302);
    }

    public function validate(Request $request, Response $response)
    {
        if (!isset($_SESSION['user_id'])) {
            return $this->jsonResponse($response, ['success' => false, 'message' => 'Not logged in']);
        }

        $user = $this->db->getUser($_SESSION['user_id']);
        if (!$user) {
            return $this->jsonResponse($response, ['success' => false, 'message' => 'User not found']);
        }

        $token = $user['access_token'];
        if (time() >= ($user['access_expires_at'] - 10)) {
            // Refresh
            try {
                $newAccessToken = $this->provider->getAccessToken('refresh_token', [
                    'refresh_token' => $user['refresh_token']
                ]);
                $this->updateUserToken($user, $newAccessToken);
                $token = $newAccessToken->getToken();
            } catch (\Exception $e) {
                return $this->jsonResponse($response, ['success' => false, 'message' => 'Refresh failed: ' . $e->getMessage()]);
            }
        }

        // Validate by fetching resource owner
        try {
            // Create a dummy AccessToken object to pass to getResourceOwner
            // We can just pass the string if the provider supports it? 
            // GenericProvider::getResourceOwner expects an AccessTokenInterface.
            // We can reconstruct it.
            $accessTokenObj = new \League\OAuth2\Client\Token\AccessToken(['access_token' => $token]);

            $resourceOwner = $this->provider->getResourceOwner($accessTokenObj);
            return $this->jsonResponse($response, ['success' => true, 'data' => $resourceOwner->toArray()]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'message' => 'Validation error: ' . $e->getMessage()]);
        }
    }

    private function updateUserToken($user, $accessToken)
    {
        $this->db->upsertUser(
            $user['amtgard_sub'],
            $user['email'],
            $accessToken->getToken(),
            $accessToken->getRefreshToken() ?? $user['refresh_token'],
            $accessToken->getExpires(),
            null
        );
    }

    private function jsonResponse($response, $data)
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function generateCodeVerifier()
    {
        return $this->base64UrlEncode(random_bytes(32));
    }

    private function generateCodeChallenge($verifier)
    {
        return $this->base64UrlEncode(hash('sha256', $verifier, true));
    }

    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
