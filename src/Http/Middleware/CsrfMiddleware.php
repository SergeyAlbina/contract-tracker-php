<?php
declare(strict_types=1);
namespace App\Http\Middleware;

use App\Http\{Request, Response};
use App\Shared\Security\Csrf;

final class CsrfMiddleware
{
    public function __construct(private readonly Csrf $csrf) {}

    public function handle(Request $request): ?Response
    {
        if (!$request->isPost()) return null;
        if ($this->csrf->validate((string) $request->post('_csrf_token', ''))) return null;

        return Response::html('<div style="text-align:center;padding:4rem;font-family:sans-serif">
            <h1 style="color:#f43f5e">403 — CSRF</h1>
            <p>Токен устарел. <a href="javascript:history.back()">Назад</a></p></div>', 403);
    }
}
