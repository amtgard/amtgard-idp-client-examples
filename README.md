# Amtgard IDP Client Examples

This repository contains various examples of clients connecting to the Amtgard IDP.

## Structure

-   `examples/plain-php`: A basic PHP implementation using raw cURL requests and manual OAuth flow handling.
-   `examples/phpleague-oauthclient`: Implementation using `thephpleague/oauth2-client` and Slim 4.
-   `examples/svelte-spa`: Single Page Application using Svelte and Vite.
-   `examples/react-spa`: Single Page Application using React and Vite.

## Modifying the Examples

To test against a local development instance of `amtgard-idp` or the production environment, you may need to change the IDP URL.

-   **Local Development**: To use a local instance of [`amtgard-idp`](https://github.com/amtgard/amtgard-bastion-idp) running on port 37080, change the endpoint to `http://localhost:37080`.
-   **Production**: To use the live Amtgard IDP, change the endpoint to `https://idp.amtgard.com`.

Each example has a different way of configuring the endpoint:
-   **plain-php**: Modify the `$idpUrl` variable in `examples/plain-php/src/oauth.php`.
-   **phpleague-oauthclient**: Modify the `idp_url` setting in the application's configuration.
-   **plain-jquery**: Update the `proxy_pass` directive in `examples/plain-jquery/nginx.conf`.
-   **react-spa**: Update the `proxy` target in `examples/react-spa/vite.config.js`.
-   **svelte-spa**: Update the `proxy` target in `examples/svelte-spa/vite.config.js`.

## Registering a Test Client with Amtgard

To get a `client_id` and `client_secret` for testing, you can request one by posting in the [Amtgard ORK Help](https://www.facebook.com/groups/189406377888489) group on Facebook.

## Running the Plain PHP Example

The plain PHP example includes a `docker-compose.yml` file for easy deployment.

1.  Navigate to the directory:
    ```bash
    cd examples/plain-php
    ```

2.  Start the container:
    ```bash
    docker-compose up -d
    ```

3.  Access the application at [http://localhost:37180](http://localhost:37180).

### Notes
-   The application uses a local SQLite database (`database.sqlite`) stored in the `examples/plain-php` directory.
-   Configuration (Client ID, IDP URL) is defined in `src/oauth.php`.

## Running the Slim + PHPLeague Example

This example uses Slim Framework 4 and `league/oauth2-client`.

1.  Navigate to the directory:
    ```bash
    cd examples/phpleague-oauthclient
    ```

2.  Start the container:
    ```bash
    docker-compose up -d
    ```

3.  Install dependencies (required first time):
    ```bash
    docker-compose exec php composer install
    ```

4.  Access the application at [http://localhost:37181](http://localhost:37181).

### Notes
-   Uses port **37181** and Client ID `test_phpleague_oauth_client`.
-   Authentication logic is in `src/AuthController.php`.

## Running the Plain jQuery SPA Example

This example is a Single Page Application using jQuery and standard browser APIs. It is hosted as static files via Nginx.

1.  Navigate to the directory:
    ```bash
    cd examples/plain-jquery
    ```

2.  Start the container:
    ```bash
    docker-compose up -d
    ```

3.  Access the application at [http://localhost:37184](http://localhost:37184).

### Notes
-   Uses port **37184** and Client ID `test_plain_jquery`.
-   Nginx is configured to proxy `/api/oauth` and `/api/resources` to the IDP to handle potential CORS issues during development.

## Running the React SPA Example

This example uses React and Vite. It runs locally via the Vite dev server.

1.  Navigate to the directory:
    ```bash
    cd examples/react-spa
    ```

2.  Install dependencies:
    ```bash
    npm install
    ```

3.  Start the dev server:
    ```bash
    npm run dev
    ```

4.  Access the application at [http://localhost:37183](http://localhost:37183).

### Notes
-   Uses port **37183** and Client ID `test_react_spa`.
-   Vite is configured to proxy `/api/oauth` and `/api/resources` to `https://idp.amtgard.com`.

## Running the Svelte SPA Example

This example uses Svelte and Vite. It runs locally via the Vite dev server.

1.  Navigate to the directory:
    ```bash
    cd examples/svelte-spa
    ```

2.  Install dependencies:
    ```bash
    npm install
    ```

3.  Start the dev server:
    ```bash
    npm run dev
    ```

4.  Access the application at [http://localhost:37182](http://localhost:37182).

### Notes
-   Uses port **37182** and Client ID `test_svelte_spa`.
-   Vite is configured to proxy `/api/oauth` and `/api/resources` to `https://idp.amtgard.com`.
