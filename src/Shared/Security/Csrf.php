<?php
declare(strict_types=1);
namespace App\Shared\Security;

final class Csrf
{
    public function __construct(private readonly Session $session) {}

    public function token(): string
    {
        $t = $this->session->get('_csrf');
        if (!$t) { $t = bin2hex(random_bytes(32)); $this->session->set('_csrf', $t); }
        return $t;
    }

    public function validate(string $submitted): bool
    {
        $stored = $this->session->get('_csrf');
        return $stored && $submitted && hash_equals($stored, $submitted);
    }

    /** HTML hidden input */
    public function field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($this->token(), ENT_QUOTES) . '">';
    }
}
