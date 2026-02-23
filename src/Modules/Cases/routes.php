<?php
declare(strict_types=1);

use App\Modules\Cases\CasesController;

/** @var \App\Http\Router $router */
$router->get('/cases/registry', [CasesController::class, 'registry']);
$router->get('/cases/registry/{id:[0-9a-fA-F-]+}', [CasesController::class, 'registryShow']);

$router->get('/cases', [CasesController::class, 'index']);
$router->get('/cases/{id:[0-9a-fA-F-]+}', [CasesController::class, 'show']);

$router->post('/cases', [CasesController::class, 'store'], ['roles' => ['admin', 'manager']]);
$router->patch('/cases/{id:[0-9a-fA-F-]+}', [CasesController::class, 'update'], ['roles' => ['admin', 'manager']]);
$router->post('/cases/{id:[0-9a-fA-F-]+}', [CasesController::class, 'update'], ['roles' => ['admin', 'manager']]);

$router->post('/cases/{id:[0-9a-fA-F-]+}/attributes', [CasesController::class, 'saveAttributes'], ['roles' => ['admin', 'manager']]);
$router->post('/cases/{id:[0-9a-fA-F-]+}/events', [CasesController::class, 'addEvent'], ['roles' => ['admin', 'manager']]);
$router->post('/cases/{id:[0-9a-fA-F-]+}/files', [CasesController::class, 'uploadFile'], ['roles' => ['admin', 'manager']]);
$router->post('/cases/{id:[0-9a-fA-F-]+}/assignees', [CasesController::class, 'assign'], ['roles' => ['admin', 'manager']]);
