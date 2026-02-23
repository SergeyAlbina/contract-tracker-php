<?php
declare(strict_types=1);

namespace App\Modules\Cases;

use App\App;
use App\Http\Request;
use App\Http\Response;
use App\Modules\Users\UsersRepository;
use App\Shared\Enum\CaseBlockType;
use App\Shared\Enum\CaseResultStatus;

final class CasesController
{
    public function __construct(private readonly App $app) {}

    public function registry(Request $request): Response
    {
        $service = $this->app->make(CasesService::class);
        $filters = [
            'block_type' => (string) $request->query('block_type', ''),
            'year' => (string) $request->query('year', ''),
            'result_status' => (string) $request->query('status', $request->query('result_status', '')),
            'assignee' => (string) $request->query('assignee', ''),
            'q' => (string) $request->query('q', ''),
            'show_duplicates' => (int) $request->query('show_duplicates', '0'),
            'overdue' => (int) $request->query('overdue', '0'),
            'in_progress' => (int) $request->query('in_progress', '0'),
            'page' => (int) $request->query('page', '1'),
            'per_page' => (int) $request->query('per_page', '50'),
        ];

        $result = $service->list($filters);
        $users = $this->app->make(UsersRepository::class)->all();

        return $this->app->view('cases/list', [
            'title' => 'Дела',
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'perPage' => $result['per_page'],
            'filters' => $filters,
            'users' => $users,
            'blockTypes' => CaseBlockType::cases(),
            'statuses' => CaseResultStatus::cases(),
            'blockCounts' => $result['block_counts'] ?? [],
            'totalWithoutBlock' => (int) ($result['total_without_block'] ?? $result['total']),
        ]);
    }

    public function registryShow(Request $request): Response
    {
        $caseId = (string) $request->param('id', '');
        $item = $this->app->make(CasesService::class)->get($caseId);
        if (!$item) {
            $this->app->flash('error', 'Дело не найдено.');
            return Response::redirect('/cases/registry');
        }

        $title = (string) ($item['case_code'] ?? '');
        if ($title === '') {
            $subject = trim((string) ($item['subject_raw'] ?? ''));
            if ($subject !== '') {
                $title = function_exists('mb_substr') ? mb_substr($subject, 0, 60) : substr($subject, 0, 60);
            } else {
                $title = (string) $item['id'];
            }
        }

        return $this->app->view('cases/view', [
            'title' => 'Дело: ' . $title,
            'item' => $item,
        ]);
    }

    public function index(Request $request): Response
    {
        $service = $this->app->make(CasesService::class);
        $filters = [
            'block_type' => (string) $request->query('block_type', ''),
            'year' => (string) $request->query('year', ''),
            'result_status' => (string) $request->query('status', $request->query('result_status', '')),
            'assignee' => (string) $request->query('assignee', ''),
            'q' => (string) $request->query('q', ''),
            'show_duplicates' => (int) $request->query('show_duplicates', '0'),
            'overdue' => (int) $request->query('overdue', '0'),
            'in_progress' => (int) $request->query('in_progress', '0'),
            'page' => (int) $request->query('page', '1'),
            'per_page' => (int) $request->query('per_page', '50'),
        ];

        return Response::json($service->list($filters));
    }

    public function show(Request $request): Response
    {
        $caseId = (string) $request->param('id', '');
        $case = $this->app->make(CasesService::class)->get($caseId);
        if (!$case) {
            return Response::json(['success' => false, 'errors' => ['Дело не найдено.']], 404);
        }

        return Response::json(['success' => true, 'item' => $case]);
    }

    public function store(Request $request): Response
    {
        $result = $this->app->make(CasesService::class)->create($request->all());
        if (!$result['success']) {
            return Response::json($result, 422);
        }

        return Response::json($result, 201);
    }

    public function update(Request $request): Response
    {
        $caseId = (string) $request->param('id', '');
        $result = $this->app->make(CasesService::class)->update($caseId, $request->all());

        if (!$result['success']) {
            $status = in_array('Дело не найдено.', $result['errors'] ?? [], true) ? 404 : 422;
            return Response::json($result, $status);
        }

        return Response::json($result);
    }

    public function saveAttributes(Request $request): Response
    {
        $caseId = (string) $request->param('id', '');
        $payload = $request->all();

        $attributes = $payload['attributes'] ?? null;
        if (is_string($attributes)) {
            $decoded = json_decode($attributes, true);
            if (is_array($decoded)) {
                $attributes = $decoded;
            }
        }

        if (!is_array($attributes)) {
            $singleKey = (string) ($payload['attr_key'] ?? '');
            if ($singleKey !== '') {
                $attributes = [
                    $singleKey => [
                        'attr_value' => $payload['attr_value'] ?? $payload['value'] ?? null,
                        'attr_value_num' => $payload['attr_value_num'] ?? $payload['num'] ?? null,
                        'attr_value_date' => $payload['attr_value_date'] ?? $payload['date'] ?? null,
                    ],
                ];
            } else {
                return Response::json(['success' => false, 'errors' => ['Передайте attributes или attr_key.']], 422);
            }
        }

        $result = $this->app->make(CasesService::class)->saveAttributes($caseId, $attributes);
        if (!$result['success']) {
            $status = in_array('Дело не найдено.', $result['errors'] ?? [], true) ? 404 : 422;
            return Response::json($result, $status);
        }

        return Response::json($result);
    }

    public function addEvent(Request $request): Response
    {
        $caseId = (string) $request->param('id', '');
        $result = $this->app->make(CasesService::class)->addEvent($caseId, $request->all());

        if (!$result['success']) {
            $status = in_array('Дело не найдено.', $result['errors'] ?? [], true) ? 404 : 422;
            return Response::json($result, $status);
        }

        return Response::json($result, 201);
    }

    public function uploadFile(Request $request): Response
    {
        $caseId = (string) $request->param('id', '');
        $file = $request->file('file') ?? $request->file('document');

        if (!$file) {
            return Response::json(['success' => false, 'errors' => ['Файл не передан (поле file).']], 422);
        }

        $result = $this->app->make(CasesService::class)->uploadFile($caseId, $file);
        if (!$result['success']) {
            $status = in_array('Дело не найдено.', $result['errors'] ?? [], true) ? 404 : 422;
            return Response::json($result, $status);
        }

        return Response::json($result, 201);
    }

    public function assign(Request $request): Response
    {
        $caseId = (string) $request->param('id', '');
        $payload = $request->all();

        $assignees = $payload['assignees'] ?? null;
        if (is_string($assignees)) {
            $decoded = json_decode($assignees, true);
            if (is_array($decoded)) {
                $assignees = $decoded;
            }
        }

        if (!is_array($assignees)) {
            if (!isset($payload['user_id'])) {
                return Response::json(['success' => false, 'errors' => ['Передайте assignees[] или user_id.']], 422);
            }

            $assignees = [[
                'user_id' => $payload['user_id'],
                'role' => $payload['role'] ?? 'EXECUTOR',
                'is_primary' => $payload['is_primary'] ?? 0,
            ]];
        }

        $result = $this->app->make(CasesService::class)->assignAssignees($caseId, $assignees);
        if (!$result['success']) {
            $status = in_array('Дело не найдено.', $result['errors'] ?? [], true) ? 404 : 422;
            return Response::json($result, $status);
        }

        return Response::json($result);
    }
}
