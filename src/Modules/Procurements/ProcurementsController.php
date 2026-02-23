<?php
declare(strict_types=1);

namespace App\Modules\Procurements;

use App\App;
use App\Http\{Request, Response};

final class ProcurementsController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $filters = [
            'search' => (string) $request->query('search', ''),
            'law_type' => (string) $request->query('law_type', ''),
            'status' => (string) $request->query('status', ''),
        ];
        $page = max(1, (int) $request->query('page', '1'));

        $result = $this->app->make(ProcurementsService::class)->list($page, $filters);

        return $this->app->view('procurements/list', [
            'title' => 'Закупки',
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'pages' => (int) ceil($result['total'] / 20),
            'filters' => $filters,
            'statusCounts' => $result['status_counts'] ?? [],
            'totalWithoutStatus' => (int) ($result['total_without_status'] ?? $result['total']),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->app->view('procurements/form', [
            'title' => 'Новая закупка',
            'procurement' => null,
            'errors' => [],
        ]);
    }

    public function store(Request $request): Response
    {
        $result = $this->app->make(ProcurementsService::class)->create($request->all());
        if (!$result['success']) {
            return $this->app->view('procurements/form', [
                'title' => 'Новая закупка',
                'procurement' => $request->all(),
                'errors' => $result['errors'],
            ]);
        }

        $this->app->flash('success', 'Закупка создана.');
        return Response::redirect('/procurements/' . $result['id']);
    }

    public function show(Request $request): Response
    {
        $id = $request->paramInt('id');
        $procurement = $this->app->make(ProcurementsService::class)->getWithProposals($id);
        if (!$procurement) {
            $this->app->flash('error', 'Закупка не найдена.');
            return Response::redirect('/procurements');
        }

        return $this->app->view('procurements/view', [
            'title' => 'Закупка #' . $procurement['number'],
            'procurement' => $procurement,
        ]);
    }

    public function edit(Request $request): Response
    {
        $id = $request->paramInt('id');
        $procurement = $this->app->make(ProcurementsService::class)->getById($id);
        if (!$procurement) {
            $this->app->flash('error', 'Закупка не найдена.');
            return Response::redirect('/procurements');
        }

        return $this->app->view('procurements/form', [
            'title' => 'Редактирование закупки',
            'procurement' => $procurement,
            'errors' => [],
        ]);
    }

    public function update(Request $request): Response
    {
        $id = $request->paramInt('id');
        $result = $this->app->make(ProcurementsService::class)->update($id, $request->all());
        if (!$result['success']) {
            return $this->app->view('procurements/form', [
                'title' => 'Редактирование закупки',
                'procurement' => array_merge($request->all(), ['id' => $id]),
                'errors' => $result['errors'],
            ]);
        }

        $this->app->flash('success', 'Изменения сохранены.');
        return Response::redirect('/procurements/' . $id);
    }

    public function delete(Request $request): Response
    {
        $id = $request->paramInt('id');
        $ok = $this->app->make(ProcurementsService::class)->delete($id);
        $ok ? $this->app->flash('success', 'Закупка удалена.') : $this->app->flash('error', 'Не удалось удалить закупку.');
        return Response::redirect('/procurements');
    }

    public function addProposal(Request $request): Response
    {
        $id = $request->paramInt('id');
        $result = $this->app->make(ProcurementsService::class)->addProposal($id, $request->all());
        if ($result['success']) {
            $this->app->flash('success', 'КП добавлено.');
        } else {
            $this->app->flash('error', implode(' ', $result['errors'] ?? ['Ошибка сохранения КП.']));
        }
        return Response::redirect('/procurements/' . $id);
    }

    public function deleteProposal(Request $request): Response
    {
        $procurementId = (int) $request->post('procurement_id', '0');
        $proposalId = $request->paramInt('id');

        $result = $this->app->make(ProcurementsService::class)->deleteProposal($proposalId);
        if ($result['success']) {
            $this->app->flash('success', 'КП удалено.');
        } else {
            $this->app->flash('error', implode(' ', $result['errors'] ?? ['Ошибка удаления КП.']));
        }

        return Response::redirect('/procurements/' . $procurementId);
    }

    public function setWinner(Request $request): Response
    {
        $procurementId = $request->paramInt('id');
        $proposalId = (int) $request->post('proposal_id', '0');

        $result = $this->app->make(ProcurementsService::class)->setWinner($procurementId, $proposalId);
        if ($result['success']) {
            $this->app->flash('success', 'Победитель выбран.');
        } else {
            $this->app->flash('error', implode(' ', $result['errors'] ?? ['Ошибка выбора победителя.']));
        }

        return Response::redirect('/procurements/' . $procurementId);
    }
}
