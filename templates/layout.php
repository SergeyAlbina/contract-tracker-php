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
?>
<div class="shell">

  <header class="topbar" role="banner">
    <a href="/contracts" class="topbar__brand">
      <div class="topbar__brand-icon">📋</div>
      Реестр контрактов
    </a>

    <nav class="topbar__nav" role="navigation">
      <a href="/contracts" class="<?= str_starts_with($uriPath, '/contracts') ? 'active' : '' ?>">
        📄 <span class="nav-text">Контракты</span>
      </a>
      <a href="/cases/registry" class="<?= str_starts_with($uriPath, '/cases/registry') ? 'active' : '' ?>">
        📚 <span class="nav-text">Дела</span>
      </a>
      <a href="/procurements" class="<?= str_starts_with($uriPath, '/procurements') ? 'active' : '' ?>">
        🛒 <span class="nav-text">Закупки</span>
      </a>
      <?php if (($user['role'] ?? '') === 'admin'): ?>
      <a href="/audit" class="<?= str_starts_with($uriPath, '/audit') ? 'active' : '' ?>">
        🧾 <span class="nav-text">Аудит</span>
      </a>
      <a href="/users" class="<?= str_starts_with($uriPath, '/users') ? 'active' : '' ?>">
        👥 <span class="nav-text">Пользователи</span>
      </a>
      <?php endif; ?>
    </nav>

    <?php if ($user): ?>
    <div class="topbar__user">
      <span class="user-name"><?= Html::e($user['full_name']) ?></span>
      <?= Html::badge((string) $user['role'], $roleLabels[(string) $user['role']] ?? 'Роль') ?>
      <button type="button" class="btn btn--ghost btn--sm theme-toggle" data-theme-toggle aria-label="Переключить тему">Тема</button>
      <a href="/profile/password" class="btn btn--ghost btn--sm">🔐 Сменить пароль</a>
      <form method="post" action="/logout" style="display:inline">
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
