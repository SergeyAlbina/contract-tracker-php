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
  <h1>👥 Пользователи <span class="text-muted" style="font-size:.7em;font-weight:400">(<?= count($items) ?>)</span></h1>
  <a href="/users/new" class="btn btn--primary">+ Новый пользователь</a>
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
