<?php

use Slim\Container;
use Zeuxisoo\Whoops\Provider\Slim\WhoopsMiddleware;

$configuration = [
    'settings' => [
        'debug' => DEBUG,
        'whoops.editor' => 'sublime',
        'displayErrorDetails' => DEBUG
    ]
];

$container = new Container($configuration);

// Init slim php view
$container['renderer'] = static function ($c) {
    return new Slim\Views\PhpRenderer();
};

$container['notFoundHandler'] = static function ($c) {
    return static function ($request, $response) use ($c) {
        return $response->withAddedHeader('Location', '/404');
    };
};

$container['notAllowedHandler'] = static function ($c) {
    return static function ($request, $response, $methods) use ($c) {
        return $response->withAddedHeader('Location', '/405');
    };
};

if (DEBUG == false) {
    $container['errorHandler'] = static function ($c) {
        return static function ($request, $response, $exception) use ($c) {
            return $response->withAddedHeader('Location', '/500');
        };
    };
}

$app = new Slim\App($container);
$app->add(new WhoopsMiddleware());

// Home
$app->get('/', function () {
    return 'Hello World.';
});

$app->get('/404', function () {
    return '404.';
});

$app->get('/405', function () {
    return '405.';
});

$app->get('/500', function () {
    return '500.';
});

$app->group('/getClient', function () {
    $this->get('/{token}', App\Controllers\UserController::class . ':getClientfromToken');
});

$app->group('/link', function () {
    $this->get('/{token}', App\Controllers\LinkController::class . ':GetContent');
});

// Run Slim Routes for App
$app->run();
