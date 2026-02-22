<?php
declare(strict_types=1);

use App\Modules\BillingDocs\BillingDocsController;

/** @var \App\Http\Router $router */
$router->post('/contracts/{id:\d+}/invoices', [BillingDocsController::class, 'storeInvoice'], ['roles' => ['admin', 'manager']]);
$router->post('/invoices/{id:\d+}/update',    [BillingDocsController::class, 'updateInvoice'], ['roles' => ['admin', 'manager']]);
$router->post('/invoices/{id:\d+}/delete',    [BillingDocsController::class, 'deleteInvoice'], ['roles' => ['admin']]);

$router->post('/contracts/{id:\d+}/acts', [BillingDocsController::class, 'storeAct'], ['roles' => ['admin', 'manager']]);
$router->post('/acts/{id:\d+}/update',    [BillingDocsController::class, 'updateAct'], ['roles' => ['admin', 'manager']]);
$router->post('/acts/{id:\d+}/delete',    [BillingDocsController::class, 'deleteAct'], ['roles' => ['admin']]);
