<?php
declare(strict_types=1);

use App\Modules\Audit\AuditController;

/** @var \App\Http\Router $router */
$router->get('/audit', [AuditController::class, 'index'], ['roles' => ['admin']]);
