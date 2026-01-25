export class OAuthClient {
    constructor(config) {
        this.clientId = config.clientId;
        this.redirectUri = config.redirectUri;
        this.authorizationEndpoint = config.authorizationEndpoint;
        this.tokenEndpoint = config.tokenEndpoint;
        this.scopes = config.scopes;
    }

    async login() {
        const state = this.generateRandomString(16);
        const codeVerifier = this.generateRandomString(64);
        const codeChallenge = await this.generateCodeChallenge(codeVerifier);

        localStorage.setItem('pkce_code_verifier', codeVerifier);
        localStorage.setItem('oauth_state', state);

        const params = new URLSearchParams({
            response_type: 'code',
            client_id: this.clientId,
            redirect_uri: this.redirectUri,
            scope: this.scopes,
            state: state,
            code_challenge: codeChallenge,
            code_challenge_method: 'S256'
        });

        window.location.href = `${this.authorizationEndpoint}?${params.toString()}`;
    }

    async handleCallback() {
        const params = new URLSearchParams(window.location.search);
        const code = params.get('code');
        const state = params.get('state');
        const storedState = localStorage.getItem('oauth_state');

        if (!code) return null;

        if (state !== storedState) {
            throw new Error('Invalid state');
        }

        const codeVerifier = localStorage.getItem('pkce_code_verifier');
        if (!codeVerifier) {
            throw new Error('No code verifier found');
        }

        // Clean up URL
        window.history.replaceState({}, document.title, "/");
        localStorage.removeItem('oauth_state');
        localStorage.removeItem('pkce_code_verifier');

        return await this.exchangeCodeForToken(code, codeVerifier);
    }

    async exchangeCodeForToken(code, codeVerifier) {
        const body = new URLSearchParams({
            grant_type: 'authorization_code',
            client_id: this.clientId,
            redirect_uri: this.redirectUri,
            code: code,
            code_verifier: codeVerifier
        });

        // Use relative path for proxy
        const response = await fetch(this.tokenEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: body
        });

        if (!response.ok) {
            throw new Error('Token exchange failed');
        }

        const data = await response.json();
        this.saveTokens(data);
        return data;
    }

    saveTokens(tokenData) {
        const now = Math.floor(Date.now() / 1000);
        localStorage.setItem('access_token', tokenData.access_token);
        if (tokenData.refresh_token) {
            localStorage.setItem('refresh_token', tokenData.refresh_token);
        }
        localStorage.setItem('expires_at', now + tokenData.expires_in);
    }

    getAccessToken() {
        return localStorage.getItem('access_token');
    }

    isLoggedIn() {
        const token = this.getAccessToken();
        const expiresAt = localStorage.getItem('expires_at');
        if (!token || !expiresAt) return false;

        return Math.floor(Date.now() / 1000) < (parseInt(expiresAt) - 10);
    }

    logout() {
        localStorage.removeItem('access_token');
        localStorage.removeItem('refresh_token');
        localStorage.removeItem('expires_at');
        window.location.reload();
    }

    // Helpers
    generateRandomString(length) {
        const array = new Uint8Array(length);
        window.crypto.getRandomValues(array);
        return Array.from(array, dec => ('0' + dec.toString(16)).substr(-2)).join('');
    }

    async generateCodeChallenge(codeVerifier) {
        const encoder = new TextEncoder();
        const data = encoder.encode(codeVerifier);
        const digest = await window.crypto.subtle.digest('SHA-256', data);
        return this.base64UrlEncode(new Uint8Array(digest));
    }

    base64UrlEncode(buffer) {
        let binary = '';
        const bytes = new Uint8Array(buffer);
        const len = bytes.byteLength;
        for (let i = 0; i < len; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary)
            .replace(/\+/g, '-')
            .replace(/\//g, '_')
            .replace(/=+$/, '');
    }
}
