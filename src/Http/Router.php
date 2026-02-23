<?php
declare(strict_types=1);
namespace App\Http;

/**
 * Regex router. Patterns: {id}, {id:\d+}
 */
final class Router
{
    private array $routes = [];

    public function get(string $p, array|callable $h, array $m = []): self  { return $this->add('GET', $p, $h, $m); }
    public function post(string $p, array|callable $h, array $m = []): self { return $this->add('POST', $p, $h, $m); }
    public function patch(string $p, array|callable $h, array $m = []): self { return $this->add('PATCH', $p, $h, $m); }

    private function add(string $method, string $pattern, array|callable $handler, array $meta): self
    {
        $regex = preg_replace_callback('/\{(\w+)(?::([^}]+))?\}/', fn($m) =>
            '(?P<' . $m[1] . '>' . ($m[2] ?? '\w+') . ')', $pattern);

        $this->routes[] = compact('method', 'pattern', 'regex', 'handler', 'meta');
        return $this;
    }

    /** @return null|array{0:callable|array, 1:array, 2:array} */
    public function match(string $method, string $path): ?array
    {
        foreach ($this->routes as $r) {
            if ($r['method'] === $method && preg_match('#^' . $r['regex'] . '$#u', $path, $matches)) {
                $params = array_filter($matches, fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);
                return [$r['handler'], $params, $r['meta']];
            }
        }
        return null;
    }
}
