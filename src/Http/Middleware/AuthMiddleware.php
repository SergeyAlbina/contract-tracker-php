<?php
declare(strict_types=1);
namespace App\Http\Middleware;

use App\Http\{Request, Response};
use App\Shared\Security\Session;

final class AuthMiddleware
{
    public function __construct(private readonly Session $session) {}

    public function handle(Request $request): ?Response
    {
        $user = $this->session->get('user');
        if ($user && !empty($user['is_active'])) return null;
        if ($user) $this->session->destroy();
        return Response::redirect('/login');
    }
}
