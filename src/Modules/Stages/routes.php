<?php
declare(strict_types=1);

use App\Modules\Stages\StagesController;

/** @var \App\Http\Router $router */
$router->post('/contracts/{id:\d+}/stages', [StagesController::class, 'store'], ['roles' => ['admin', 'manager']]);
$router->post('/stages/{id:\d+}/update',    [StagesController::class, 'update'], ['roles' => ['admin', 'manager']]);
$router->post('/stages/{id:\d+}/delete',    [StagesController::class, 'delete'], ['roles' => ['admin']]);
