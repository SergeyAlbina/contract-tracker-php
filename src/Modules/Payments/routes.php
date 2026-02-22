<?php
declare(strict_types=1);
use App\Modules\Payments\PaymentsController;
/** @var \App\Http\Router $router */
$router->post('/contracts/{id:\d+}/payments', [PaymentsController::class, 'store']);
$router->post('/payments/{id:\d+}/update',    [PaymentsController::class, 'update']);
$router->post('/payments/{id:\d+}/delete',    [PaymentsController::class, 'delete']);
