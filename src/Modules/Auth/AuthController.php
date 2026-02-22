<?php
declare(strict_types=1);
namespace App\Modules\Auth;

use App\App;
use App\Http\{Request, Response};

final class AuthController
{
    public function __construct(private readonly App $app) {}

    public function home(Request $r): Response { return Response::redirect('/contracts'); }

    public function showLogin(Request $r): Response
    {
        if ($this->app->session()->isAuthenticated()) return Response::redirect('/contracts');
        return Response::html($this->app->renderView('auth/login', ['title' => 'Вход']));
    }

    public function login(Request $r): Response
    {
        $result = $this->app->make(AuthService::class)->attempt(
            (string) $r->post('login', ''), (string) $r->post('password', ''), $r->ip()
        );
        if (!$result['success']) { $this->app->flash('error', $result['error']); return Response::redirect('/login'); }
        $this->app->flash('success', 'Добро пожаловать!');
        return Response::redirect('/contracts');
    }

    public function logout(Request $r): Response
    {
        $this->app->session()->logout();
        return Response::redirect('/login');
    }
}
