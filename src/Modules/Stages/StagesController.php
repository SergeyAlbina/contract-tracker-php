<?php
declare(strict_types=1);

namespace App\Modules\Stages;

use App\App;
use App\Http\{Request, Response};

final class StagesController
{
    public function __construct(private readonly App $app) {}

    public function store(Request $r): Response
    {
        $contractId = $r->paramInt('id');
        $res = $this->app->make(StagesService::class)->create($contractId, $r->all());
        $res['success']
            ? $this->app->flash('success', 'Этап добавлен.')
            : $this->app->flash('error', implode(' ', $res['errors']));

        return Response::redirect("/contracts/{$contractId}");
    }

    public function update(Request $r): Response
    {
        $res = $this->app->make(StagesService::class)->update($r->paramInt('id'), $r->all());
        $res['success']
            ? $this->app->flash('success', 'Этап обновлён.')
            : $this->app->flash('error', implode(' ', $res['errors']));

        return Response::redirect('/contracts/' . (int) $r->post('contract_id', 0));
    }

    public function delete(Request $r): Response
    {
        $contractId = (int) $r->post('contract_id', 0);
        $this->app->make(StagesService::class)->delete($r->paramInt('id'))
            ? $this->app->flash('success', 'Этап удалён.')
            : $this->app->flash('error', 'Ошибка удаления этапа.');

        return Response::redirect("/contracts/{$contractId}");
    }
}
