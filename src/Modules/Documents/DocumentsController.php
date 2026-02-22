<?php
declare(strict_types=1);
namespace App\Modules\Documents;

use App\App;
use App\Http\{Request, Response};

final class DocumentsController
{
    public function __construct(private readonly App $app) {}

    public function upload(Request $r): Response
    {
        $cid = $r->paramInt('id');
        $file = $r->file('document');
        if (!$file) { $this->app->flash('error', 'Файл не выбран.'); return Response::redirect("/contracts/{$cid}"); }
        try {
            $this->app->make(DocumentsService::class)->upload($cid, $file, (string)$r->post('doc_type', 'other'));
            $this->app->flash('success', 'Документ загружен.');
        } catch (\RuntimeException $e) { $this->app->flash('error', $e->getMessage()); }
        return Response::redirect("/contracts/{$cid}");
    }

    public function download(Request $r): Response
    {
        $svc = $this->app->make(DocumentsService::class);
        $d = $svc->getById($r->paramInt('id'));
        if (!$d) return Response::html('404', 404);
        $content = file_get_contents($svc->absolutePath($d['relative_path']));
        if ($content === false) return Response::html('404', 404);
        return Response::download($content, $d['original_name'], $d['mime_type']);
    }

    public function delete(Request $r): Response
    {
        $svc = $this->app->make(DocumentsService::class);
        $d = $svc->getById($r->paramInt('id'));
        $cid = $d['contract_id'] ?? 0;
        $svc->delete($r->paramInt('id')) ? $this->app->flash('success', 'Удалён.') : $this->app->flash('error', 'Ошибка.');
        return Response::redirect("/contracts/{$cid}");
    }
}
