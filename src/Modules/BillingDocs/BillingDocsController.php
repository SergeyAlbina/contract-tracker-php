<?php
declare(strict_types=1);

namespace App\Modules\BillingDocs;

use App\App;
use App\Http\{Request, Response};

final class BillingDocsController
{
    public function __construct(private readonly App $app) {}

    public function storeInvoice(Request $r): Response
    {
        $contractId = $r->paramInt('id');
        $res = $this->app->make(BillingDocsService::class)->createInvoice($contractId, $r->all());
        $res['success']
            ? $this->app->flash('success', 'Счёт добавлен.')
            : $this->app->flash('error', implode(' ', $res['errors']));

        return Response::redirect("/contracts/{$contractId}");
    }

    public function updateInvoice(Request $r): Response
    {
        $res = $this->app->make(BillingDocsService::class)->updateInvoice($r->paramInt('id'), $r->all());
        $res['success']
            ? $this->app->flash('success', 'Счёт обновлён.')
            : $this->app->flash('error', implode(' ', $res['errors']));

        return Response::redirect('/contracts/' . (int) $r->post('contract_id', 0));
    }

    public function deleteInvoice(Request $r): Response
    {
        $contractId = (int) $r->post('contract_id', 0);
        $this->app->make(BillingDocsService::class)->deleteInvoice($r->paramInt('id'))
            ? $this->app->flash('success', 'Счёт удалён.')
            : $this->app->flash('error', 'Ошибка удаления счёта.');

        return Response::redirect("/contracts/{$contractId}");
    }

    public function storeAct(Request $r): Response
    {
        $contractId = $r->paramInt('id');
        $res = $this->app->make(BillingDocsService::class)->createAct($contractId, $r->all());
        $res['success']
            ? $this->app->flash('success', 'Акт добавлен.')
            : $this->app->flash('error', implode(' ', $res['errors']));

        return Response::redirect("/contracts/{$contractId}");
    }

    public function updateAct(Request $r): Response
    {
        $res = $this->app->make(BillingDocsService::class)->updateAct($r->paramInt('id'), $r->all());
        $res['success']
            ? $this->app->flash('success', 'Акт обновлён.')
            : $this->app->flash('error', implode(' ', $res['errors']));

        return Response::redirect('/contracts/' . (int) $r->post('contract_id', 0));
    }

    public function deleteAct(Request $r): Response
    {
        $contractId = (int) $r->post('contract_id', 0);
        $this->app->make(BillingDocsService::class)->deleteAct($r->paramInt('id'))
            ? $this->app->flash('success', 'Акт удалён.')
            : $this->app->flash('error', 'Ошибка удаления акта.');

        return Response::redirect("/contracts/{$contractId}");
    }
}
