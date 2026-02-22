<?php
use App\Shared\Utils\Html;
$flashes = $session->getFlashes();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="dark">
  <meta name="theme-color" content="#07080d">
  <title>Вход — Contract Tracker</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <div style="text-align:center;margin-bottom:.5rem">
      <div class="topbar__brand-icon" style="width:48px;height:48px;font-size:1.4rem;margin:0 auto .75rem;border-radius:14px">📋</div>
    </div>
    <h1>Contract Tracker</h1>
    <p class="subtitle">Система управления контрактами 223-ФЗ / 44-ФЗ</p>

    <?php foreach ($flashes as $f): ?>
      <div class="flash flash--<?= Html::e($f['type']) ?>"><?= Html::e($f['message']) ?></div>
    <?php endforeach; ?>

    <form method="post" action="/login" autocomplete="on">
      <?= $csrf->field() ?>
      <div class="fg">
        <label for="login">Логин</label>
        <input type="text" id="login" name="login" required autofocus autocomplete="username" placeholder="admin">
      </div>
      <div class="fg">
        <label for="password">Пароль</label>
        <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
      </div>
      <button type="submit" class="btn btn--primary">Войти</button>
    </form>
  </div>
</div>
</body>
</html>
