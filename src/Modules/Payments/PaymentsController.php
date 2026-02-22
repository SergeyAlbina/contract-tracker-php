<?php
declare(strict_types=1);
namespace App\Modules\Payments;

use App\App;
use App\Http\{Request, Response};

final class PaymentsController
{
    public function __construct(private readonly App $app) {}

    public function store(Request $r): Response
    {
        $cid = $r->paramInt('id');
        $res = $this->app->make(PaymentsService::class)->create($cid, $r->all());
        $res['success'] ? $this->app->flash('success', 'Платёж добавлен.') : $this->app->flash('error', implode(' ', $res['errors']));
        return Response::redirect("/contracts/{$cid}");
    }

    public function update(Request $r): Response
    {
        $res = $this->app->make(PaymentsService::class)->update($r->paramInt('id'), $r->all());
        $res['success'] ? $this->app->flash('success', 'Обновлено.') : $this->app->flash('error', implode(' ', $res['errors']));
        return Response::redirect('/contracts/' . $r->post('contract_id', ''));
    }

    public function delete(Request $r): Response
    {
        $cid = $r->post('contract_id', '');
        $this->app->make(PaymentsService::class)->delete($r->paramInt('id'))
            ? $this->app->flash('success', 'Удалён.') : $this->app->flash('error', 'Ошибка.');
        return Response::redirect("/contracts/{$cid}");
    }
}
