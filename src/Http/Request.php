<?php
declare(strict_types=1);
namespace App\Http;

final class Request
{
    private array $routeParams = [];

    private function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array  $query,
        private readonly array  $post,
        private readonly array  $server,
        private readonly array  $files,
    ) {}

    public static function fromGlobals(): self
    {
        $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        if ($path !== '/' && str_ends_with($path, '/')) $path = rtrim($path, '/');

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path, $_GET, $_POST, $_SERVER, $_FILES,
        );
    }

    public function method(): string  { return $this->method; }
    public function path(): string    { return $this->path; }
    public function isPost(): bool    { return $this->method === 'POST'; }

    public function query(string $k, mixed $d = null): mixed  { return $this->query[$k] ?? $d; }
    public function post(string $k, mixed $d = null): mixed   { return $this->post[$k] ?? $d; }
    public function input(string $k, mixed $d = null): mixed  { return $this->post[$k] ?? $this->query[$k] ?? $d; }
    public function all(): array                               { return array_merge($this->query, $this->post); }
    public function file(string $k): ?array                    { return $this->files[$k] ?? null; }
    public function ip(): string                               { return $this->server['HTTP_X_FORWARDED_FOR'] ?? $this->server['REMOTE_ADDR'] ?? '0.0.0.0'; }
    public function userAgent(): string                        { return $this->server['HTTP_USER_AGENT'] ?? ''; }

    public function setRouteParams(array $p): void   { $this->routeParams = $p; }
    public function param(string $k, mixed $d = null): mixed { return $this->routeParams[$k] ?? $d; }
    public function paramInt(string $k): int         { return (int) ($this->routeParams[$k] ?? 0); }
}
