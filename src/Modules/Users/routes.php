<?php
declare(strict_types=1);

use App\Modules\Users\UsersController;

/** @var \App\Http\Router $router */
$router->get('/users',                   [UsersController::class, 'index'], ['roles' => ['admin']]);
$router->get('/users/new',               [UsersController::class, 'create'], ['roles' => ['admin']]);
$router->post('/users',                  [UsersController::class, 'store'], ['roles' => ['admin']]);
$router->get('/users/{id:\d+}/edit',     [UsersController::class, 'edit'], ['roles' => ['admin']]);
$router->post('/users/{id:\d+}',         [UsersController::class, 'update'], ['roles' => ['admin']]);
$router->post('/users/{id:\d+}/delete',  [UsersController::class, 'delete'], ['roles' => ['admin']]);

$router->get('/profile/password',        [UsersController::class, 'showPasswordForm']);
$router->post('/profile/password',       [UsersController::class, 'updatePassword']);
