# Amtgard IDP Client Examples

This repository contains various examples of clients connecting to the Amtgard IDP.

## Structure

-   `examples/plain-php`: A basic PHP implementation using raw cURL requests and manual OAuth flow handling.
-   `examples/phpleague-oauthclient`: (Planned) implementation using `thephpleague/oauth2-client`.
-   `examples/svelte-spa`: (Planned) Single Page Application using Svelte.
-   `examples/react-spa`: (Planned) Single Page Application using React.

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
