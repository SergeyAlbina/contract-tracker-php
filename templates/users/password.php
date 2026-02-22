<?php
use App\Shared\Utils\Html;

/** @var array $errors */
?>

<div class="page-head">
  <h1>🔐 Смена пароля</h1>
  <a href="/contracts" class="btn btn--ghost btn--sm">← Назад</a>
</div>

<?php if (!empty($errors)): ?>
  <ul class="err-list"><?php foreach ($errors as $e): ?><li><?= Html::e($e) ?></li><?php endforeach; ?></ul>
<?php endif; ?>

<div class="card">
  <form method="post" action="/profile/password" autocomplete="off">
    <?= $csrf->field() ?>
    <div class="form-grid">
      <div class="fg">
        <label for="current_password">Текущий пароль *</label>
        <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
      </div>
      <div class="fg">
        <label for="new_password">Новый пароль *</label>
        <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
      </div>
      <div class="fg">
        <label for="new_password_confirm">Подтверждение *</label>
        <input type="password" id="new_password_confirm" name="new_password_confirm" required minlength="8" autocomplete="new-password">
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn--primary">💾 Изменить пароль</button>
      <a href="/contracts" class="btn btn--ghost">Отмена</a>
    </div>
  </form>
</div>
