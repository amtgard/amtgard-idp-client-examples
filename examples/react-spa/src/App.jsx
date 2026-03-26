import { useState, useEffect } from 'react'
import { OAuthClient } from './utils/oauth'
import './index.css'

const config = {
    clientId: 'test_react_spa',
    redirectUri: 'http://localhost:37183',
    authorizationEndpoint: 'https://idp.amtgard.com/oauth/authorize',
    tokenEndpoint: '/api/oauth/token', // Proxy
    scopes: 'profile email'
};

const auth = new OAuthClient(config);

function App() {
    const [isLoggedIn, setIsLoggedIn] = useState(false);
    const [validationResult, setValidationResult] = useState(null);
    const [error, setError] = useState(null);

    useEffect(() => {
        // Check for callback
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('code')) {
            auth.handleCallback()
                .then(() => {
                    setIsLoggedIn(true);
                })
                .catch(err => {
                    console.error("Login Error", err);
                    setError(err.message);
                });
        } else {
            setIsLoggedIn(auth.isLoggedIn());
        }
    }, []);

    const handleLogin = () => auth.login();
    const handleLogout = () => auth.logout();

    const handleValidate = async () => {
        setValidationResult(null);
        setError(null);
        const token = auth.getAccessToken();

        try {
            const response = await fetch('/api/resources/userinfo', { // Proxy
                headers: {
                    'Authorization': `Bearer ${token}`
                }
            });

            if (!response.ok) {
                const text = await response.text();
                throw new Error(response.statusText + ": " + text);
            }

            const data = await response.json();
            setValidationResult({ success: true, data });

        } catch (err) {
            setValidationResult({ success: false, message: err.message });
        }
    };

    return (
        <div className="container">
            <h1>IDP Client (React)</h1>

            <div className="status-line">
                Logged In? <span className={`status-value ${isLoggedIn ? 'yes' : 'no'}`}>{isLoggedIn ? 'Yes' : 'No'}</span>
            </div>

            {error && (
                <div className="validation-result validation-error">
                    <strong>Error:</strong> {error}
                </div>
            )}

            {validationResult && (
                <div className={`validation-result ${validationResult.success ? 'validation-success' : 'validation-error'}`}>
                    <strong>Validation Result:</strong> {validationResult.success ? 'Success' : 'Failed'}
                    <br />
                    {validationResult.success ? (
                        <pre style={{ fontSize: '0.8rem', overflowX: 'auto' }}>
                            {JSON.stringify(validationResult.data, null, 2)}
                        </pre>
                    ) : (
                        <span style={{ color: 'red' }}>{validationResult.message}</span>
                    )}
                </div>
            )}

            {isLoggedIn && (
                <div style={{ marginTop: '1rem', fontSize: '0.9rem', color: '#666' }}>
                    Welcome! <br />
                    Access Token Stored locally.
                </div>
            )}

            <div className="controls">
                {!isLoggedIn ? (
                    <button onClick={handleLogin}>Login</button>
                ) : (
                    <>
                        <button onClick={handleValidate} style={{ backgroundColor: '#e6f7ff', color: '#005580', border: '1px solid #005580' }}>Validate</button>
                        <button onClick={handleLogout}>Logout</button>
                    </>
                )}
            </div>
        </div>
    )
}

export default App
