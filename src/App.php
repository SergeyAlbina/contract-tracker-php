<?php
declare(strict_types=1);

namespace App;

use App\Http\{Request, Response, Router};
use App\Http\Middleware\{AuthMiddleware, CsrfMiddleware};
use App\Infrastructure\Db\PdoFactory;
use App\Shared\Security\{Session, Csrf};
use App\Shared\Utils\Env;

/**
 * Application kernel.
 *
 * — DI-lite: lazy singletons через make()
 * — Модули подключаются автоматически из src/Modules/<Module>/routes.php
 * — ОТКАЗОУСТОЙЧИВОСТЬ: сбой одного модуля НЕ роняет систему
 */
final class App
{
    private static ?self $instance = null;

    private \PDO $pdo;
    private Router $router;
    private Session $session;
    private Csrf $csrf;
    private string $basePath;

    /** @var array<string, object> lazy singleton cache */
    private array $services = [];

    /** @var array<string, string> module load errors (module => error) */
    private array $moduleErrors = [];

    private function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /* ================================================================
     *  BOOTSTRAP
     * ================================================================ */

    public static function boot(string $basePath): self
    {
        if (self::$instance) return self::$instance;

        $app = new self($basePath);

        // 1) Environment
        Env::load($app->basePath . '/.env');

        // 2) Errors
        if (Env::bool('APP_DEBUG')) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(0);
            ini_set('display_errors', '0');
        }

        // 3) Database
        $app->pdo = PdoFactory::create();

        // 4) Session
        $app->session = new Session();
        $app->session->start();

        // 5) CSRF
        $app->csrf = new Csrf($app->session);

        // 6) Router + fault-tolerant module loading
        $app->router = new Router();
        $app->loadModules();

        self::$instance = $app;
        return $app;
    }

    /* ================================================================
     *  FAULT-TOLERANT MODULE LOADER
     *
     *  Каждый модуль загружается в try/catch.
     *  Если модуль падает — он пропускается, остальные работают.
     *  Ошибки логируются в $moduleErrors и в error_log.
     * ================================================================ */

    private function loadModules(): void
    {
        $modulesDir = $this->basePath . '/src/Modules';
        if (!is_dir($modulesDir)) return;

        $dirs = glob($modulesDir . '/*/routes.php');
        $router = $this->router;
        $app = $this;

        foreach ($dirs as $routeFile) {
            $moduleName = basename(dirname($routeFile));
            try {
                require $routeFile;
            } catch (\Throwable $e) {
                // Модуль сломан — логируем, но НЕ останавливаем систему
                $this->moduleErrors[$moduleName] = $e->getMessage();
                error_log("[MODULE FAIL] {$moduleName}: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");
            }
        }
    }

    /**
     * Какие модули не загрузились? (для admin-дашборда)
     * @return array<string, string>
     */
    public function failedModules(): array
    {
        return $this->moduleErrors;
    }

    /* ================================================================
     *  REQUEST HANDLING
     * ================================================================ */

    public function handle(Request $request): Response
    {
        try {
            // CSRF на все POST
            $csrfCheck = (new CsrfMiddleware($this->csrf))->handle($request);
            if ($csrfCheck) return $csrfCheck;

            // Match route
            $match = $this->router->match($request->method(), $request->path());
            if (!$match) {
                return $this->view('errors/404', ['title' => '404'], 404);
            }

            [$handler, $params, $meta] = $match;

            // Auth check (если маршрут не public)
            if (!($meta['public'] ?? false)) {
                $authCheck = (new AuthMiddleware($this->session))->handle($request);
                if ($authCheck) return $authCheck;
            }

            // RBAC check (если указаны roles)
            if (!empty($meta['roles'])) {
                if (!$this->session->hasRole(...$meta['roles'])) {
                    $this->flash('error', 'Недостаточно прав.');
                    return Response::redirect('/contracts');
                }
            }

            $request->setRouteParams($params);
            return $this->dispatch($handler, $request);

        } catch (\Throwable $e) {
            error_log("[APP ERROR] {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");

            if (Env::bool('APP_DEBUG')) {
                return Response::html(
                    '<div style="font-family:monospace;background:#1a1a2e;color:#e94560;padding:2rem;margin:2rem;border-radius:12px">'
                    . '<h2>⚠ Debug Error</h2>'
                    . '<pre style="color:#eee;white-space:pre-wrap">' . htmlspecialchars($e->getMessage()) . "\n\n" . $e->getTraceAsString() . '</pre></div>',
                    500
                );
            }

            return $this->view('errors/500', ['title' => 'Ошибка'], 500);
        }
    }

    private function dispatch(array|callable $handler, Request $request): Response
    {
        if (is_callable($handler)) {
            return $handler($request, $this);
        }
        [$class, $method] = $handler;
        $controller = new $class($this);
        return $controller->$method($request);
    }

    /* ================================================================
     *  DI-LITE: LAZY SINGLETONS
     * ================================================================ */

    public function pdo(): \PDO             { return $this->pdo; }
    public function session(): Session      { return $this->session; }
    public function csrf(): Csrf            { return $this->csrf; }
    public function router(): Router        { return $this->router; }
    public function basePath(): string      { return $this->basePath; }

    public function storagePath(): string
    {
        $path = Env::get('STORAGE_PATH', $this->basePath . '/storage');
        // Поддержка относительных путей
        if (!str_starts_with($path, '/')) {
            $path = $this->basePath . '/' . $path;
        }
        return $path;
    }

    /**
     * Lazy singleton factory.
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public function make(string $class): object
    {
        return $this->services[$class] ??= new $class($this);
    }

    /* ================================================================
     *  VIEW ENGINE
     * ================================================================ */

    /**
     * Render template file, return raw HTML string.
     */
    public function renderView(string $view, array $data = []): string
    {
        $file = $this->basePath . '/templates/' . $view . '.php';
        if (!file_exists($file)) return "<!-- view '{$view}' not found -->";

        extract($data, EXTR_SKIP);
        $app     = $this;
        $csrf    = $this->csrf;
        $session = $this->session;

        ob_start();
        require $file;
        return ob_get_clean() ?: '';
    }

    /**
     * Render view inside layout → Response.
     */
    public function view(string $view, array $data = [], int $status = 200, string $layout = 'layout'): Response
    {
        $data['_content'] = $this->renderView($view, $data);
        return Response::html($this->renderView($layout, $data), $status);
    }

    /* ================================================================
     *  HELPERS
     * ================================================================ */

    public function flash(string $type, string $message): void
    {
        $this->session->flash($type, $message);
    }

    public function currentUser(): ?array
    {
        return $this->session->get('user');
    }

    public function currentUserId(): ?int
    {
        return ($u = $this->currentUser()) ? (int) $u['id'] : null;
    }

    /**
     * Записать в audit_log (общая функция — НЕ дублируем в каждом сервисе).
     */
    public function audit(string $action, ?string $entityType = null, ?int $entityId = null, ?array $details = null): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address, user_agent, created_at)
                 VALUES (:uid, :action, :etype, :eid, :details, :ip, :ua, NOW())'
            );
            $stmt->execute([
                'uid'     => $this->currentUserId(),
                'action'  => $action,
                'etype'   => $entityType,
                'eid'     => $entityId,
                'details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
                'ua'      => function_exists('mb_substr')
                    ? mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
                    : substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            ]);
        } catch (\Throwable $e) {
            error_log("[AUDIT FAIL] {$e->getMessage()}");
            // Аудит не должен ломать основной флоу
        }
    }
}
