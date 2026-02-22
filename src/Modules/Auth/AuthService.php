<?php
declare(strict_types=1);
namespace App\Modules\Auth;

use App\App;
use App\Shared\Security\Passwords;

final class AuthService
{
    private AuthRepository $repo;
    private App $app;

    public function __construct(App $app) { $this->app = $app; $this->repo = $app->make(AuthRepository::class); }

    /** @return array{success:bool, error?:string} */
    public function attempt(string $login, string $password, string $ip): array
    {
        if ($this->isRateLimited($ip))
            return ['success' => false, 'error' => 'Слишком много попыток. Подождите 5 минут.'];

        $login = trim($login);
        if ($login === '' || $password === '')
            return ['success' => false, 'error' => 'Введите логин и пароль.'];

        $user = $this->repo->findByLogin($login);

        if (!$user || !Passwords::verify($password, $user['password_hash'])) {
            $this->recordAttempt($ip);
            $this->app->audit('login_failed', 'auth', null, ['login' => $login]);
            return ['success' => false, 'error' => 'Неверный логин или пароль.'];
        }

        if (!$user['is_active'])
            return ['success' => false, 'error' => 'Учётная запись деактивирована.'];

        if (Passwords::needsRehash($user['password_hash']))
            $this->repo->updatePasswordHash($user['id'], Passwords::hash($password));

        $this->app->session()->login([
            'id' => $user['id'], 'login' => $user['login'],
            'full_name' => $user['full_name'], 'role' => $user['role'], 'is_active' => $user['is_active'],
        ]);

        $this->clearAttempts($ip);
        $this->app->audit('login_success', 'auth', (int)$user['id']);
        return ['success' => true];
    }

    // Rate limit: 5 попыток / 5 мин / IP (session-based, shared-hosting friendly)
    private function isRateLimited(string $ip): bool
    {
        $a = $this->app->session()->get('_la_' . md5($ip), []);
        return count(array_filter($a, fn($t) => $t > time() - 300)) >= 5;
    }

    private function recordAttempt(string $ip): void
    {
        $k = '_la_' . md5($ip);
        $a = array_filter($this->app->session()->get($k, []), fn($t) => $t > time() - 300);
        $a[] = time();
        $this->app->session()->set($k, array_values($a));
    }

    private function clearAttempts(string $ip): void
    {
        $this->app->session()->delete('_la_' . md5($ip));
    }
}
