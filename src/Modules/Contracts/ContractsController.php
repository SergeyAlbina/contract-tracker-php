<?php
declare(strict_types=1);
namespace App\Modules\Contracts;

use App\App;
use App\Http\{Request, Response};
use App\Modules\Contracts\Dto\ContractCreateDto;
use App\Shared\Enum\{ContractStatus, LawType};

final class ContractsController
{
    public function __construct(private readonly App $app) {}

    public function index(Request $r): Response
    {
        $svc = $this->app->make(ContractsService::class);
        $page = max(1, (int) $r->query('page', '1'));
        $f = ['search' => $r->query('search',''), 'law_type' => $r->query('law_type',''), 'status' => $r->query('status','')];
        $res = $svc->list($page, $f);
        return $this->app->view('contracts/list', [
            'title' => 'Контракты',
            'items' => $res['items'],
            'total' => $res['total'],
            'page' => $page,
            'pages' => (int) ceil($res['total'] / 20),
            'filters' => $f,
            'statusCounts' => $res['status_counts'] ?? [],
            'totalWithoutStatus' => (int) ($res['total_without_status'] ?? $res['total']),
        ]);
    }

    public function exportCsv(Request $r): Response
    {
        $filters = [
            'search' => $r->query('search', ''),
            'law_type' => $r->query('law_type', ''),
            'status' => $r->query('status', ''),
        ];
        $rows = $this->app->make(ContractsService::class)->export($filters);

        $fp = fopen('php://temp', 'r+');
        if ($fp === false) {
            return Response::html('Не удалось сформировать CSV.', 500);
        }

        fwrite($fp, "\xEF\xBB\xBF");
        fputcsv($fp, [
            'ID', 'Номер', 'Предмет', 'Контрагент', 'ИНН', 'Закон', 'Статус',
            'Сумма', 'НМЦК', 'Дата подписания', 'Дата окончания', 'Создал', 'Создан'
        ], ';');

        foreach ($rows as $c) {
            fputcsv($fp, [
                (int) $c['id'],
                (string) $c['number'],
                (string) $c['subject'],
                (string) $c['contractor_name'],
                (string) ($c['contractor_inn'] ?? ''),
                LawType::from((string) $c['law_type'])->label(),
                ContractStatus::from((string) $c['status'])->label(),
                (string) $c['total_amount'],
                (string) ($c['nmck_amount'] ?? ''),
                (string) ($c['signed_at'] ?? ''),
                (string) ($c['expires_at'] ?? ''),
                (string) ($c['creator_name'] ?? ''),
                (string) ($c['created_at'] ?? ''),
            ], ';');
        }

        rewind($fp);
        $csv = stream_get_contents($fp) ?: '';
        fclose($fp);

        return Response::download(
            $csv,
            'contracts_' . date('Ymd_His') . '.csv',
            'text/csv; charset=utf-8'
        );
    }

    public function create(Request $r): Response
    {
        return $this->app->view('contracts/form', ['title'=>'Новый контракт', 'contract'=>null, 'errors'=>[]]);
    }

    public function store(Request $r): Response
    {
        $svc = $this->app->make(ContractsService::class);
        $dto = ContractCreateDto::fromRequest($r->all(), $this->app->currentUserId());
        $res = $svc->create($dto);
        if (!$res['success']) return $this->app->view('contracts/form', ['title'=>'Новый контракт', 'contract'=>$dto->toArray(), 'errors'=>$res['errors']]);
        $this->app->flash('success', 'Контракт создан.');
        return Response::redirect('/contracts/' . $res['id']);
    }

    public function show(Request $r): Response
    {
        $c = $this->app->make(ContractsService::class)->getWithFinances($r->paramInt('id'));
        if (!$c) { $this->app->flash('error', 'Не найден.'); return Response::redirect('/contracts'); }
        return $this->app->view('contracts/view', ['title'=>"#{$c['number']}", 'contract'=>$c]);
    }

    public function edit(Request $r): Response
    {
        $c = $this->app->make(ContractsRepository::class)->findById($r->paramInt('id'));
        if (!$c) { $this->app->flash('error', 'Не найден.'); return Response::redirect('/contracts'); }
        return $this->app->view('contracts/form', ['title'=>"Редактировать #{$c['number']}", 'contract'=>$c, 'errors'=>[]]);
    }

    public function update(Request $r): Response
    {
        $id = $r->paramInt('id');
        $res = $this->app->make(ContractsService::class)->update($id, $r->all());
        if (!$res['success']) {
            $c = $this->app->make(ContractsRepository::class)->findById($id);
            return $this->app->view('contracts/form', ['title'=>'Редактировать', 'contract'=>array_merge($c ?? [], $r->all()), 'errors'=>$res['errors']]);
        }
        $this->app->flash('success', 'Сохранено.');
        return Response::redirect("/contracts/{$id}");
    }

    public function delete(Request $r): Response
    {
        $this->app->make(ContractsService::class)->delete($r->paramInt('id'))
            ? $this->app->flash('success', 'Удалён.') : $this->app->flash('error', 'Ошибка.');
        return Response::redirect('/contracts');
    }
}
