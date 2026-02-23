<?php
declare(strict_types=1);

use App\Modules\Cases\CasesController;

/** @var \App\Http\Router $router */
$router->get('/cases', [CasesController::class, 'index']);
$router->get('/cases/{id:[0-9a-fA-F\-]{36}}', [CasesController::class, 'show']);

$router->post('/cases', [CasesController::class, 'store'], ['roles' => ['admin', 'manager']]);
$router->patch('/cases/{id:[0-9a-fA-F\-]{36}}', [CasesController::class, 'update'], ['roles' => ['admin', 'manager']]);
$router->post('/cases/{id:[0-9a-fA-F\-]{36}}', [CasesController::class, 'update'], ['roles' => ['admin', 'manager']]);

$router->post('/cases/{id:[0-9a-fA-F\-]{36}}/attributes', [CasesController::class, 'saveAttributes'], ['roles' => ['admin', 'manager']]);
$router->post('/cases/{id:[0-9a-fA-F\-]{36}}/events', [CasesController::class, 'addEvent'], ['roles' => ['admin', 'manager']]);
$router->post('/cases/{id:[0-9a-fA-F\-]{36}}/files', [CasesController::class, 'uploadFile'], ['roles' => ['admin', 'manager']]);
$router->post('/cases/{id:[0-9a-fA-F\-]{36}}/assignees', [CasesController::class, 'assign'], ['roles' => ['admin', 'manager']]);
