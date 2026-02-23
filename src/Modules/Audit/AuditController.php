<?php
declare(strict_types=1);

namespace App\Modules\Audit;

use App\App;
use App\Http\{Request, Response};

final class AuditController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $service = $this->app->make(AuditService::class);
        $page = max(1, (int) $request->query('page', '1'));
        $filters = [
            'q' => $request->query('q', ''),
            'action' => $request->query('action', ''),
            'entity_type' => $request->query('entity_type', ''),
            'user_id' => $request->query('user_id', ''),
            'date_from' => $request->query('date_from', ''),
            'date_to' => $request->query('date_to', ''),
        ];

        $result = $service->list($page, $filters);

        return $this->app->view('audit/list', [
            'title' => 'Журнал аудита',
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'pages' => (int) ceil($result['total'] / 20),
            'filters' => $result['filters'],
            'actions' => $service->actionOptions(),
            'entityTypes' => $service->entityTypeOptions(),
            'users' => $service->userOptions(),
        ]);
    }
}
