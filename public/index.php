<?php
declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
$response = App\App::boot(__DIR__ . '/..')->handle(App\Http\Request::fromGlobals());
$response->send();
