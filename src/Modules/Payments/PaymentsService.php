<?php
declare(strict_types=1);
namespace App\Modules\Payments;

use App\App;

final class PaymentsService
{
    private PaymentsRepository $repo;
    private App $app;

    public function __construct(App $app) { $this->app = $app; $this->repo = $app->make(PaymentsRepository::class); }

    public function getByContract(int $cid): array { return $this->repo->findByContract($cid); }

    /** @return array{success:bool, errors?:string[]} */
    public function create(int $cid, array $d): array
    {
        if (empty($d['amount']) || (float)$d['amount'] <= 0) return ['success'=>false, 'errors'=>['Сумма > 0.']];
        $valid = ['planned','in_progress','paid','canceled'];
        if (!in_array($d['status'] ?? '', $valid, true)) return ['success'=>false, 'errors'=>['Некорректный статус.']];

        $e = fn(?string $v): ?string => ($v = trim($v ?? '')) === '' ? null : $v;
        $this->repo->insert([
            'contract_id'=>$cid, 'amount'=>(float)$d['amount'], 'status'=>$d['status'],
            'payment_date'=>$e($d['payment_date']??''), 'purpose'=>$e($d['purpose']??''),
            'invoice_number'=>$e($d['invoice_number']??''), 'created_by'=>$this->app->currentUserId(),
        ]);
        $this->app->audit('payment_created', 'payment', null, ['contract_id'=>$cid]);
        return ['success'=>true];
    }

    public function update(int $id, array $d): array
    {
        if (!$this->repo->findById($id)) return ['success'=>false, 'errors'=>['Не найден.']];
        $allowed = ['amount','status','payment_date','purpose','invoice_number'];
        $upd = array_intersect_key($d, array_flip($allowed));
        if (isset($upd['amount'])) $upd['amount'] = (float)$upd['amount'];
        $this->repo->update($id, $upd);
        $this->app->audit('payment_updated', 'payment', $id);
        return ['success'=>true];
    }

    public function delete(int $id): bool
    {
        if (!$this->repo->findById($id)) return false;
        $this->repo->delete($id); $this->app->audit('payment_deleted', 'payment', $id); return true;
    }
}
