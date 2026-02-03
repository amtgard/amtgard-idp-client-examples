<script>
  import { onMount } from 'svelte';
  import { OAuthClient } from './utils/oauth';

  const config = {
      clientId: 'test_svelte_spa',
      redirectUri: 'http://localhost:37182',
      authorizationEndpoint: 'https://idp.amtgard.com/oauth/authorize',
      tokenEndpoint: '/api/oauth/token', // Proxy
      scopes: 'profile email'
  };

  const auth = new OAuthClient(config);

  let isLoggedIn = false;
  let validationResult = null;
  let error = null;

  onMount(async () => {
      // Check for callback
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('code')) {
          try {
              await auth.handleCallback();
              isLoggedIn = true;
          } catch (err) {
              console.error("Login Error", err);
              error = err.message;
          }
      } else {
          isLoggedIn = auth.isLoggedIn();
      }
  });

  const handleLogin = () => auth.login();
  const handleLogout = () => auth.logout();

  const handleValidate = async () => {
      validationResult = null;
      error = null;
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
          validationResult = { success: true, data };

      } catch (err) {
          validationResult = { success: false, message: err.message };
      }
  };
</script>

<div class="container">
  <h1>IDP Client (Svelte)</h1>

  <div class="status-line">
    Logged In? <span class="status-value {isLoggedIn ? 'yes' : 'no'}">{isLoggedIn ? 'Yes' : 'No'}</span>
  </div>

  {#if error}
      <div class="validation-result validation-error">
          <strong>Error:</strong> {error}
      </div>
  {/if}

  {#if validationResult}
      <div class="validation-result {validationResult.success ? 'validation-success' : 'validation-error'}">
          <strong>Validation Result:</strong> {validationResult.success ? 'Success' : 'Failed'}
          <br />
          {#if validationResult.success}
              <pre style="font-size: 0.8rem; overflow-x: auto;">
                  {JSON.stringify(validationResult.data, null, 2)}
              </pre>
          {:else}
              <span style="color: red;">{validationResult.message}</span>
          {/if}
      </div>
  {/if}

  {#if isLoggedIn}
       <div style="margin-top: 1rem; font-size: 0.9rem; color: #666;">
           Welcome! <br/>
           Access Token Stored locally.
       </div>
  {/if}

  <div class="controls">
      {#if !isLoggedIn}
          <button on:click={handleLogin}>Login</button>
      {:else}
          <button on:click={handleValidate} style="background-color: #e6f7ff; color: #005580; border: 1px solid #005580;">Validate</button>
          <button on:click={handleLogout}>Logout</button>
      {/if}
  </div>
</div>
