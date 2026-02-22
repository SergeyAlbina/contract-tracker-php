<?php
declare(strict_types=1);
namespace App\Modules\Contracts;

use App\App;
use App\Infrastructure\Telegram\TelegramClient;
use App\Modules\Contracts\Dto\ContractCreateDto;
use App\Shared\Policy\LawPolicy;

final class ContractsService
{
    private ContractsRepository $repo;
    private App $app;

    public function __construct(App $app) { $this->app = $app; $this->repo = $app->make(ContractsRepository::class); }

    public function list(int $page, array $filters): array { return $this->repo->paginate($page, 20, $filters); }

    public function export(array $filters): array { return $this->repo->exportRows($filters); }

    /** @return array{success:bool, id?:int, errors?:string[]} */
    public function create(ContractCreateDto $dto): array
    {
        $errors = LawPolicy::validateContract($dto->toArray());
        if ($errors) return ['success' => false, 'errors' => $errors];

        $id = $this->repo->insert($dto->toArray());
        $this->app->audit('contract_created', 'contract', $id);
        $this->notify('Создан', array_merge($dto->toArray(), ['id' => $id]));
        return ['success' => true, 'id' => $id];
    }

    /** @return array{success:bool, errors?:string[]} */
    public function update(int $id, array $data): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) return ['success' => false, 'errors' => ['Контракт не найден.']];

        $errors = LawPolicy::validateContract(array_merge($existing, $data));
        if ($errors) return ['success' => false, 'errors' => $errors];

        $allowed = ['number','subject','law_type','contractor_name','contractor_inn','total_amount','nmck_amount','status','signed_at','expires_at','notes'];
        $this->repo->update($id, array_intersect_key($data, array_flip($allowed)));
        $this->app->audit('contract_updated', 'contract', $id);
        return ['success' => true];
    }

    public function delete(int $id): bool
    {
        if (!$this->repo->findById($id)) return false;
        $this->repo->delete($id);
        $this->app->audit('contract_deleted', 'contract', $id);
        return true;
    }

    public function getWithFinances(int $id): ?array
    {
        $c = $this->repo->findById($id);
        if (!$c) return null;
        $paid = $this->repo->paidSum($id);
        $total = (float) $c['total_amount'];
        $c['paid_sum'] = $paid;
        $c['remaining'] = $total - $paid;
        $c['overspend'] = $paid > $total;
        return $c;
    }

    private function notify(string $action, array $c): void
    {
        try { $tg = new TelegramClient(); if ($tg->isConfigured()) $tg->notifyContract($action, $c); }
        catch (\Throwable) {} // Telegram не ломает основной флоу
    }
}
