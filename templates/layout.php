<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light dark">
  <meta name="theme-color" content="#07080d">
  <title><?= \App\Shared\Utils\Html::e($title ?? 'Реестр контрактов') ?></title>
  <script>
    (function () {
      var theme = 'dark';
      try {
        var saved = localStorage.getItem('theme');
        if (saved === 'light' || saved === 'dark') {
          theme = saved;
        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
          theme = 'light';
        }
      } catch (e) {}
      document.documentElement.setAttribute('data-theme', theme);
      document.documentElement.style.colorScheme = theme;
    })();
  </script>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<?php
  use App\Shared\Utils\Html;
  $user = $app->currentUser();
  $flashes = $session->getFlashes();
  $failed = $app->failedModules();
  $uriPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
  $roleLabels = ['admin' => 'Администратор', 'manager' => 'Менеджер', 'viewer' => 'Наблюдатель'];
  $isAdmin = (($user['role'] ?? '') === 'admin');
  $adminMenuActive = str_starts_with($uriPath, '/admin')
      || str_starts_with($uriPath, '/audit')
      || str_starts_with($uriPath, '/users')
      || str_starts_with($uriPath, '/profile/password');
  $userRole = (string) ($user['role'] ?? '');
  $roleLabel = $roleLabels[$userRole] ?? 'Роль';
  $userName = trim((string) ($user['full_name'] ?? ''));
  $normalize = static function (string $value): string {
    return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
  };
  $showUserName = $userName !== '' && $normalize($userName) !== $normalize($roleLabel);
?>
<div class="shell">

  <header class="topbar" role="banner">
    <a href="/procurements" class="topbar__brand">
      <div class="topbar__brand-icon">
        <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
          <rect x="9" y="2" width="6" height="4" rx="1"></rect>
          <path d="M9 4H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-3"></path>
        </svg>
      </div>
      Реестр контрактов
    </a>

    <nav class="topbar__nav" role="navigation">
      <a href="/procurements" class="<?= str_starts_with($uriPath, '/procurements') ? 'active' : '' ?>">
        <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="9" cy="20" r="1"></circle>
          <circle cx="17" cy="20" r="1"></circle>
          <path d="M3 4h2l2.4 10.4a1 1 0 0 0 1 .8h8.6a1 1 0 0 0 1-.8L20 7H7"></path>
        </svg>
        <span class="nav-text">Закупки</span>
      </a>
      <a href="/contracts" class="<?= str_starts_with($uriPath, '/contracts') ? 'active' : '' ?>">
        <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <path d="M14 2v6h6"></path>
          <path d="M16 13H8"></path>
          <path d="M16 17H8"></path>
          <path d="M10 9H8"></path>
        </svg>
        <span class="nav-text">Контракты</span>
      </a>
      <a href="/cases/registry" class="<?= str_starts_with($uriPath, '/cases/registry') ? 'active' : '' ?>">
        <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M3 7a2 2 0 0 1 2-2h5l2 2h9"></path>
          <path d="M3 7h18v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
        </svg>
        <span class="nav-text">Дела</span>
      </a>
      <?php if ($isAdmin): ?>
      <a href="/admin" class="<?= $adminMenuActive ? 'active' : '' ?>">
        <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="12" cy="12" r="3"></circle>
          <path d="M19.4 15a1 1 0 0 0 .2 1.1l.1.1a1.8 1.8 0 1 1-2.5 2.5l-.1-.1a1 1 0 0 0-1.1-.2 1 1 0 0 0-.6.9V20a1.8 1.8 0 1 1-3.6 0v-.1a1 1 0 0 0-.6-.9 1 1 0 0 0-1.1.2l-.1.1a1.8 1.8 0 1 1-2.5-2.5l.1-.1a1 1 0 0 0 .2-1.1 1 1 0 0 0-.9-.6H4a1.8 1.8 0 1 1 0-3.6h.1a1 1 0 0 0 .9-.6 1 1 0 0 0-.2-1.1l-.1-.1a1.8 1.8 0 1 1 2.5-2.5l.1.1a1 1 0 0 0 1.1.2h.1a1 1 0 0 0 .6-.9V4a1.8 1.8 0 1 1 3.6 0v.1a1 1 0 0 0 .6.9h.1a1 1 0 0 0 1.1-.2l.1-.1a1.8 1.8 0 1 1 2.5 2.5l-.1.1a1 1 0 0 0-.2 1.1v.1a1 1 0 0 0 .9.6H20a1.8 1.8 0 1 1 0 3.6h-.1a1 1 0 0 0-.9.6z"></path>
        </svg>
        <span class="nav-text">Администрирование</span>
      </a>
      <?php endif; ?>
    </nav>

    <?php if ($user): ?>
    <div class="topbar__user">
      <?php if ($showUserName): ?>
      <span class="user-name"><?= Html::e($userName) ?></span>
      <?php endif; ?>
      <?= Html::badge($userRole, $roleLabel) ?>
      <button type="button" class="btn btn--ghost btn--sm theme-toggle" data-theme-toggle aria-label="Переключить тему">Тема</button>
      <form method="post" action="/logout">
        <?= $csrf->field() ?>
        <button type="submit" class="btn btn--ghost btn--sm">Выйти</button>
      </form>
    </div>
    <?php endif; ?>
  </header>

  <main class="main stagger" role="main">

    <?php if ($failed && ($user['role'] ?? '') === 'admin'): ?>
      <div class="flash flash--error">⚠ Модули не загружены: <?= Html::e(implode(', ', array_keys($failed))) ?></div>
    <?php endif; ?>

    <?php foreach ($flashes as $f): ?>
      <div class="flash flash--<?= Html::e($f['type']) ?>">
        <?= $f['type'] === 'success' ? '✓' : '⚠' ?>
        <?= Html::e($f['message']) ?>
      </div>
    <?php endforeach; ?>

    <?= $_content ?? '' ?>

  </main>

</div>
<script src="/assets/app.js" defer></script>
</body>
</html>
