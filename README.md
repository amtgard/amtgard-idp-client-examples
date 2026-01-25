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
