<?php
use App\Shared\Utils\Html;

/** @var array $items */
$currentUserId = (int) (($app->currentUser()['id'] ?? 0));
$roleLabels = [
    'admin' => 'Администратор',
    'manager' => 'Менеджер',
    'viewer' => 'Наблюдатель',
];
?>

<div class="page-head">
  <h1>
    <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
      <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
      <circle cx="9" cy="7" r="4"></circle>
      <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
      <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
    </svg>
    Пользователи <span class="page-head__count">(<?= count($items) ?>)</span>
  </h1>
  <div class="flex gap-sm">
    <a href="/profile/password" class="btn btn--ghost btn--sm">Сменить мой пароль</a>
    <a href="/users/new" class="btn btn--primary">+ Новый пользователь</a>
  </div>
</div>

<?php if (empty($items)): ?>
  <div class="empty">
    <div class="empty__icon">👤</div>
    <p>Пользователи не найдены</p>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Логин</th>
          <th>ФИО</th>
          <th>Эл. почта</th>
          <th>Роль</th>
          <th>Статус</th>
          <th>Создан</th>
          <th style="text-align:right">Действия</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($items as $u): ?>
        <tr>
          <td class="td-link"><?= Html::e($u['login']) ?></td>
          <td><?= Html::e($u['full_name']) ?></td>
          <td><?= Html::e($u['email'] ?: '—') ?></td>
          <td><?= Html::badge((string) $u['role'], $roleLabels[$u['role']] ?? (string) $u['role']) ?></td>
          <td>
            <?php if ((int) $u['is_active'] === 1): ?>
              <span class="badge badge--emerald">Активен</span>
            <?php else: ?>
              <span class="badge badge--rose">Отключён</span>
            <?php endif; ?>
          </td>
          <td class="text-muted"><?= Html::date($u['created_at']) ?></td>
          <td style="text-align:right">
            <a href="/users/<?= (int) $u['id'] ?>/edit" class="btn btn--ghost btn--sm">✏️</a>
            <?php if ((int) $u['id'] !== $currentUserId): ?>
            <form method="post" action="/users/<?= (int) $u['id'] ?>/delete" style="display:inline">
              <?= $csrf->field() ?>
              <button type="submit" class="btn--icon" data-confirm="Удалить пользователя?">🗑</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
