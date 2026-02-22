<?php
declare(strict_types=1);
use App\Modules\Auth\AuthController;
/** @var \App\Http\Router $router */
$router->get('/login',  [AuthController::class, 'showLogin'],  ['public' => true]);
$router->post('/login', [AuthController::class, 'login'],      ['public' => true]);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/',        [AuthController::class, 'home']);
