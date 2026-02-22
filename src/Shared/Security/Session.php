<?php
declare(strict_types=1);
namespace App\Shared\Security;

use App\Shared\Utils\Env;

final class Session
{
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;

        session_set_cookie_params([
            'lifetime' => Env::int('SESSION_LIFETIME', 3600),
            'path'     => '/',
            'secure'   => Env::bool('SESSION_SECURE'),
            'httponly'  => true,
            'samesite'=> 'Lax',
        ]);
        session_start();

        // Regenerate ID каждые 5 мин
        if (time() - ($_SESSION['_regen'] ?? 0) > 300) {
            session_regenerate_id(true);
            $_SESSION['_regen'] = time();
        }
    }

    public function get(string $k, mixed $d = null): mixed    { return $_SESSION[$k] ?? $d; }
    public function set(string $k, mixed $v): void            { $_SESSION[$k] = $v; }
    public function delete(string $k): void                    { unset($_SESSION[$k]); }

    public function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    // ── Flash ──
    public function flash(string $type, string $msg): void { $_SESSION['_flash'][] = ['type' => $type, 'message' => $msg]; }
    public function getFlashes(): array { $f = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return $f; }

    // ── Auth ──
    public function login(array $user): void { session_regenerate_id(true); $this->set('user', $user); $this->set('_regen', time()); }
    public function logout(): void { $this->destroy(); }
    public function isAuthenticated(): bool { return $this->get('user') !== null; }
    public function userRole(): ?string { return $this->get('user')['role'] ?? null; }
    public function hasRole(string ...$roles): bool { $r = $this->userRole(); return $r && in_array($r, $roles, true); }
}
