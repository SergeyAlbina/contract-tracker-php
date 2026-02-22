<?php
declare(strict_types=1);
use App\Modules\Contracts\ContractsController;
/** @var \App\Http\Router $router */
$router->get('/contracts',                  [ContractsController::class, 'index']);
$router->get('/contracts/new',              [ContractsController::class, 'create']);
$router->post('/contracts',                 [ContractsController::class, 'store']);
$router->get('/contracts/{id:\d+}',         [ContractsController::class, 'show']);
$router->get('/contracts/{id:\d+}/edit',    [ContractsController::class, 'edit']);
$router->post('/contracts/{id:\d+}',        [ContractsController::class, 'update']);
$router->post('/contracts/{id:\d+}/delete', [ContractsController::class, 'delete'], ['roles' => ['admin']]);
