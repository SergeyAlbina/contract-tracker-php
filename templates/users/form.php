<?php
use App\Shared\Utils\Html;

/** @var array $userForm @var array $errors @var bool $isEdit */
$u = $userForm ?? [];
$v = fn(string $k, string $d = ''): string => Html::e((string) ($u[$k] ?? $d));
$checked = (($u['is_active'] ?? '1') === '1' || (int)($u['is_active'] ?? 1) === 1);
?>

<div class="page-head">
  <h1><?= $isEdit ? '✏️ Редактирование пользователя' : '🧑‍💼 Новый пользователь' ?></h1>
  <a href="/users" class="btn btn--ghost btn--sm">← К списку</a>
</div>

<?php if (!empty($errors)): ?>
  <ul class="err-list"><?php foreach ($errors as $e): ?><li><?= Html::e($e) ?></li><?php endforeach; ?></ul>
<?php endif; ?>

<div class="card">
  <form method="post" action="<?= $isEdit ? '/users/' . (int)($u['id'] ?? 0) : '/users' ?>" autocomplete="off">
    <?= $csrf->field() ?>

    <div class="form-grid">
      <div class="fg">
        <label for="login">Логин *</label>
        <input type="text" id="login" name="login" value="<?= $v('login') ?>" required minlength="3" maxlength="64" pattern="[A-Za-z0-9_.-]+">
      </div>

      <div class="fg">
        <label for="full_name">ФИО *</label>
        <input type="text" id="full_name" name="full_name" value="<?= $v('full_name') ?>" required maxlength="255">
      </div>

      <div class="fg">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= $v('email') ?>" maxlength="255">
      </div>

      <div class="fg">
        <label for="role">Роль *</label>
        <select id="role" name="role" required>
          <option value="admin" <?= ($u['role'] ?? 'viewer') === 'admin' ? 'selected' : '' ?>>admin</option>
          <option value="manager" <?= ($u['role'] ?? 'viewer') === 'manager' ? 'selected' : '' ?>>manager</option>
          <option value="viewer" <?= ($u['role'] ?? 'viewer') === 'viewer' ? 'selected' : '' ?>>viewer</option>
        </select>
      </div>

      <div class="fg">
        <label for="password"><?= $isEdit ? 'Новый пароль' : 'Пароль *' ?></label>
        <input type="password" id="password" name="password" minlength="8" <?= $isEdit ? '' : 'required' ?> autocomplete="new-password">
        <small class="text-muted"><?= $isEdit ? 'Оставьте пустым, если не нужно менять пароль.' : 'Минимум 8 символов.' ?></small>
      </div>

      <div class="fg">
        <label for="is_active">Активность</label>
        <input type="hidden" name="is_active" value="0">
        <label style="display:flex;align-items:center;gap:.5rem;text-transform:none;letter-spacing:normal;font-size:.88rem;color:var(--text-1);font-weight:500">
          <input type="checkbox" id="is_active" name="is_active" value="1" <?= $checked ? 'checked' : '' ?> style="width:16px;height:16px">
          Активный пользователь
        </label>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn--primary"><?= $isEdit ? '💾 Сохранить' : '✓ Создать' ?></button>
      <a href="/users" class="btn btn--ghost">Отмена</a>
    </div>
  </form>
</div>
