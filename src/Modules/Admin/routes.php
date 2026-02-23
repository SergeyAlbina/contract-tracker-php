<?php
declare(strict_types=1);

use App\Modules\Admin\AdminController;

/** @var \App\Http\Router $router */
$router->get('/admin', [AdminController::class, 'index'], ['roles' => ['admin']]);
