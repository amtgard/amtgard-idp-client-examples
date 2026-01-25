<?php

use DI\Container;
use Slim\Factory\AppFactory;
use App\Database;
use App\AuthController;
use Slim\Views\PhpRenderer;

require __DIR__ . '/../vendor/autoload.php';

session_start();

// Container Setup
$container = new Container();

$container->set(Database::class, function () {
    $db = new Database();
    $db->init();
    return $db;
});

$container->set(AuthController::class, function (Container $c) {
    return new AuthController($c->get(Database::class));
});

// App Creation
AppFactory::setContainer($container);
$app = AppFactory::create();

// Middleware
$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// Routes

// Routes

$app->get('/', function ($request, $response, $args) {
    // Check if handling callback at root
    $queryParams = $request->getQueryParams();
    if (isset($queryParams['code'])) {
        $controller = $this->get(AuthController::class);
        return $controller->callback($request, $response);
    }

    $db = $this->get(Database::class);

    $isLoggedIn = isset($_SESSION['user_id']);
    $isRegistered = $db->hasAnyUser();
    $user = null;

    if ($isLoggedIn) {
        $user = $db->getUser($_SESSION['user_id']);
        if (!$user) {
            unset($_SESSION['user_id']);
            $isLoggedIn = false;
        }
    }

    $renderer = new PhpRenderer(__DIR__ . '/../src/views');
    return $renderer->render($response, 'home.php', [
        'isLoggedIn' => $isLoggedIn,
        'isRegistered' => $isRegistered,
        'user' => $user
    ]);
});

$app->get('/login', [AuthController::class, 'login']);
// $app->get('/callback', [AuthController::class, 'callback']); // Now handled by /
$app->post('/logout', [AuthController::class, 'logout']);
$app->post('/validate', [AuthController::class, 'validate']);

// Handle clear_db via a simple closure or separate controller
$app->post('/clear_db', function ($request, $response) {
    $db = $this->get(Database::class);
    $db->clearUsers();
    session_destroy();
    return $response->withHeader('Location', '/')->withStatus(302);
});

$app->run();
