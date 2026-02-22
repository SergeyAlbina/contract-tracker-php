<?php
declare(strict_types=1);
use App\Modules\Contracts\ContractsController;
/** @var \App\Http\Router $router */
$router->get('/contracts',                  [ContractsController::class, 'index']);
$router->get('/contracts/new',              [ContractsController::class, 'create'], ['roles' => ['admin', 'manager']]);
$router->post('/contracts',                 [ContractsController::class, 'store'], ['roles' => ['admin', 'manager']]);
$router->get('/contracts/{id:\d+}',         [ContractsController::class, 'show']);
$router->get('/contracts/{id:\d+}/edit',    [ContractsController::class, 'edit'], ['roles' => ['admin', 'manager']]);
$router->post('/contracts/{id:\d+}',        [ContractsController::class, 'update'], ['roles' => ['admin', 'manager']]);
$router->post('/contracts/{id:\d+}/delete', [ContractsController::class, 'delete'], ['roles' => ['admin']]);
