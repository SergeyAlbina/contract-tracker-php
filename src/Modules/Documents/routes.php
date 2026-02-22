<?php
declare(strict_types=1);
use App\Modules\Documents\DocumentsController;
/** @var \App\Http\Router $router */
$router->post('/contracts/{id:\d+}/documents', [DocumentsController::class, 'upload'], ['roles' => ['admin', 'manager']]);
$router->get('/documents/{id:\d+}/download',   [DocumentsController::class, 'download']);
$router->post('/documents/{id:\d+}/delete',    [DocumentsController::class, 'delete'], ['roles' => ['admin']]);
