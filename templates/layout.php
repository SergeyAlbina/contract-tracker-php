<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="dark">
  <meta name="theme-color" content="#07080d">
  <title><?= \App\Shared\Utils\Html::e($title ?? 'Contract Tracker') ?></title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<?php
  use App\Shared\Utils\Html;
  $user = $app->currentUser();
  $flashes = $session->getFlashes();
  $failed = $app->failedModules();
?>
<div class="shell">

  <header class="topbar" role="banner">
    <a href="/contracts" class="topbar__brand">
      <div class="topbar__brand-icon">📋</div>
      Contract Tracker
    </a>

    <nav class="topbar__nav" role="navigation">
      <a href="/contracts" class="<?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/contracts') ? 'active' : '' ?>">
        📄 <span class="nav-text">Контракты</span>
      </a>
    </nav>

    <?php if ($user): ?>
    <div class="topbar__user">
      <span class="user-name"><?= Html::e($user['full_name']) ?></span>
      <?= Html::badge($user['role'], $user['role']) ?>
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
