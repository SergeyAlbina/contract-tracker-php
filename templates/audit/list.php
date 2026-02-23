<?php
use App\Shared\Utils\Html;

/** @var array $items @var int $total @var int $page @var int $pages @var array $filters */
/** @var string[] $actions @var string[] $entityTypes @var array<int,array{id:int,login:string,full_name:string}> $users */
$selectedUserId = is_int($filters['user_id'] ?? null) ? (string) $filters['user_id'] : '';
?>

<div class="page-head">
  <h1>
    <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
      <path d="M4 3h16v18l-3-2-3 2-2-2-2 2-3-2-3 2z"></path>
      <path d="M8 7h8"></path>
      <path d="M8 11h8"></path>
      <path d="M8 15h5"></path>
    </svg>
    Журнал аудита <span class="text-muted" style="font-size:.7em;font-weight:400">(<?= $total ?>)</span>
  </h1>
</div>

<form class="filters" method="get" action="/audit">
  <input
    type="text"
    name="q"
    placeholder="Поиск: действие, сущность, пользователь, IP…"
    value="<?= Html::e($filters['q'] ?? '') ?>"
    style="flex:1;min-width:220px"
  >

  <select name="action">
    <option value="">Все действия</option>
    <?php foreach ($actions as $action): ?>
      <option value="<?= Html::e($action) ?>" <?= ($filters['action'] ?? '') === $action ? 'selected' : '' ?>><?= Html::e($action) ?></option>
    <?php endforeach; ?>
  </select>

  <select name="entity_type">
    <option value="">Все сущности</option>
    <?php foreach ($entityTypes as $entityType): ?>
      <option value="<?= Html::e($entityType) ?>" <?= ($filters['entity_type'] ?? '') === $entityType ? 'selected' : '' ?>><?= Html::e($entityType) ?></option>
    <?php endforeach; ?>
  </select>

  <select name="user_id">
    <option value="">Все пользователи</option>
    <?php foreach ($users as $user): ?>
      <option value="<?= (int) $user['id'] ?>" <?= $selectedUserId === (string) $user['id'] ? 'selected' : '' ?>>
        <?= Html::e(($user['full_name'] ?: $user['login']) . ' (@' . $user['login'] . ')') ?>
      </option>
    <?php endforeach; ?>
  </select>

  <input type="date" name="date_from" value="<?= Html::e($filters['date_from'] ?? '') ?>" title="С даты">
  <input type="date" name="date_to" value="<?= Html::e($filters['date_to'] ?? '') ?>" title="По дату">

  <button type="submit" class="btn btn--ghost btn--sm">🔍 Найти</button>
  <a href="/audit" class="btn btn--ghost btn--sm">Сброс</a>
</form>

<?php if (empty($items)): ?>
  <div class="empty">
    <div class="empty__icon"><span class="emoji">🧾</span></div>
    <p>Событий по заданным фильтрам не найдено</p>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="min-width:140px">Дата</th>
          <th>Пользователь</th>
          <th>Действие</th>
          <th>Сущность</th>
          <th>IP</th>
          <th style="min-width:280px">Детали</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($items as $row): ?>
        <?php
          $actor = 'Система';
          $actorName = trim((string) ($row['actor_name'] ?? ''));
          $actorLogin = trim((string) ($row['actor_login'] ?? ''));
          if ($actorName !== '' || $actorLogin !== '') {
              $actor = $actorName;
              if ($actorLogin !== '') {
                  $actor .= ($actor !== '' ? ' ' : '') . '@' . $actorLogin;
              }
          } elseif (($row['user_id'] ?? null) !== null) {
              $actor = 'ID ' . (int) $row['user_id'];
          }

          $entity = '—';
          $parts = [];
          if (($row['entity_type'] ?? '') !== '') {
              $parts[] = (string) $row['entity_type'];
          }
          if (($row['entity_id'] ?? null) !== null) {
              $parts[] = '#' . (int) $row['entity_id'];
          }
          if ($parts) {
              $entity = implode(' ', $parts);
          }

          $detailsRaw = (string) ($row['details'] ?? '');
          $detailsPretty = '';
          $detailsPreview = '';
          if ($detailsRaw !== '') {
              $decoded = json_decode($detailsRaw, true);
              if (json_last_error() === JSON_ERROR_NONE) {
                  $detailsPretty = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
              } else {
                  $detailsPretty = $detailsRaw;
              }

              $previewSrc = preg_replace('/\s+/', ' ', $detailsPretty) ?? '';
              $detailsPreview = Html::truncate(trim($previewSrc), 80);
          }
        ?>
        <tr>
          <td class="text-muted td-num"><?= Html::date((string) ($row['created_at'] ?? ''), 'd.m.Y H:i') ?></td>
          <td><?= Html::e($actor) ?></td>
          <td><code class="audit-action"><?= Html::e((string) ($row['action'] ?? '')) ?></code></td>
          <td><?= Html::e($entity) ?></td>
          <td class="td-num"><?= Html::e((string) ($row['ip_address'] ?? '—')) ?></td>
          <td>
            <?php if ($detailsPretty !== ''): ?>
              <details class="audit-details">
                <summary><?= Html::e($detailsPreview !== '' ? $detailsPreview : 'Показать JSON') ?></summary>
                <pre><?= Html::e($detailsPretty) ?></pre>
              </details>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
  <nav class="pgn">
    <?php if ($page > 1): ?><a href="?<?= http_build_query(array_merge($filters, ['page' => $page - 1])) ?>">←</a><?php endif; ?>
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <?php if ($i === $page): ?><span class="cur"><?= $i ?></span>
      <?php elseif (abs($i - $page) < 3 || $i === 1 || $i === $pages): ?><a href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>"><?= $i ?></a>
      <?php elseif (abs($i - $page) === 3): ?><span class="text-muted">…</span>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $pages): ?><a href="?<?= http_build_query(array_merge($filters, ['page' => $page + 1])) ?>">→</a><?php endif; ?>
  </nav>
  <?php endif; ?>
<?php endif; ?>
