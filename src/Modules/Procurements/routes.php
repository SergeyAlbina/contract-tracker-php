<?php
declare(strict_types=1);

use App\Modules\Procurements\ProcurementsController;

/** @var \App\Http\Router $router */
$router->get('/procurements',                               [ProcurementsController::class, 'index']);
$router->get('/procurements/new',                           [ProcurementsController::class, 'create'], ['roles' => ['admin', 'manager']]);
$router->post('/procurements',                              [ProcurementsController::class, 'store'], ['roles' => ['admin', 'manager']]);
$router->get('/procurements/{id:\d+}',                      [ProcurementsController::class, 'show']);
$router->get('/procurements/{id:\d+}/edit',                 [ProcurementsController::class, 'edit'], ['roles' => ['admin', 'manager']]);
$router->post('/procurements/{id:\d+}',                     [ProcurementsController::class, 'update'], ['roles' => ['admin', 'manager']]);
$router->post('/procurements/{id:\d+}/delete',              [ProcurementsController::class, 'delete'], ['roles' => ['admin']]);
$router->post('/procurements/{id:\d+}/proposals',           [ProcurementsController::class, 'addProposal'], ['roles' => ['admin', 'manager']]);
$router->post('/procurements/{id:\d+}/winner',              [ProcurementsController::class, 'setWinner'], ['roles' => ['admin', 'manager']]);
$router->post('/procurement-proposals/{id:\d+}/delete',     [ProcurementsController::class, 'deleteProposal'], ['roles' => ['admin', 'manager']]);
