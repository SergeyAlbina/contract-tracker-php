<?php
use App\Shared\Enum\CaseBlockType;
use App\Shared\Enum\CaseResultStatus;
use App\Shared\Utils\Html;

/** @var array $items @var int $total @var int $page @var int $pages @var int $perPage @var array $filters @var array $users */
/** @var array<CaseBlockType> $blockTypes @var array<CaseResultStatus> $statuses */

$queryBase = [
    'block_type' => (string) ($filters['block_type'] ?? ''),
    'year' => (string) ($filters['year'] ?? ''),
    'result_status' => (string) ($filters['result_status'] ?? ''),
    'assignee' => (string) ($filters['assignee'] ?? ''),
    'q' => (string) ($filters['q'] ?? ''),
    'show_duplicates' => (int) ($filters['show_duplicates'] ?? 0),
    'overdue' => (int) ($filters['overdue'] ?? 0),
    'in_progress' => (int) ($filters['in_progress'] ?? 0),
    'per_page' => (int) ($filters['per_page'] ?? $perPage),
];

$pageQuery = static function (int $targetPage) use ($queryBase): string {
    return http_build_query(array_merge($queryBase, ['page' => $targetPage]));
};
?>

<div class="page-head">
  <h1><span class="emoji">📚</span> Дела <span class="text-muted" style="font-size:.7em;font-weight:400">(<?= (int) $total ?>)</span></h1>
  <div class="flex gap-sm">
    <a href="/cases" class="btn btn--ghost">API JSON</a>
  </div>
</div>

<form class="filters" method="get" action="/cases/registry">
  <input
    type="text"
    name="q"
    placeholder="Поиск: код, предмет, номер контракта…"
    value="<?= Html::e($queryBase['q']) ?>"
    style="flex:1;min-width:220px"
  >

  <select name="block_type">
    <option value="">Все блоки</option>
    <?php foreach ($blockTypes as $block): ?>
      <option value="<?= Html::e($block->value) ?>" <?= $queryBase['block_type'] === $block->value ? 'selected' : '' ?>><?= Html::e($block->label()) ?></option>
    <?php endforeach; ?>
  </select>

  <select name="result_status">
    <option value="">Все статусы</option>
    <?php foreach ($statuses as $status): ?>
      <option value="<?= Html::e($status->value) ?>" <?= $queryBase['result_status'] === $status->value ? 'selected' : '' ?>><?= Html::e($status->label()) ?></option>
    <?php endforeach; ?>
  </select>

  <select name="assignee">
    <option value="">Все исполнители</option>
    <?php foreach ($users as $u): ?>
      <?php $uid = (string) (int) $u['id']; ?>
      <option value="<?= Html::e($uid) ?>" <?= $queryBase['assignee'] === $uid ? 'selected' : '' ?>><?= Html::e((string) ($u['full_name'] ?: $u['login'])) ?></option>
    <?php endforeach; ?>
  </select>

  <input type="number" min="2020" max="2100" name="year" placeholder="Год" value="<?= Html::e($queryBase['year']) ?>" style="width:100px">

  <select name="per_page">
    <?php foreach ([20, 50, 100, 200] as $pp): ?>
      <option value="<?= $pp ?>" <?= (int) $queryBase['per_page'] === $pp ? 'selected' : '' ?>><?= $pp ?>/стр.</option>
    <?php endforeach; ?>
  </select>

  <label style="display:inline-flex;align-items:center;gap:.35rem">
    <input type="checkbox" name="overdue" value="1" <?= ((int) $queryBase['overdue'] === 1) ? 'checked' : '' ?>>
    <span class="text-muted">Просрочка</span>
  </label>

  <label style="display:inline-flex;align-items:center;gap:.35rem">
    <input type="checkbox" name="in_progress" value="1" <?= ((int) $queryBase['in_progress'] === 1) ? 'checked' : '' ?>>
    <span class="text-muted">В работе</span>
  </label>

  <label style="display:inline-flex;align-items:center;gap:.35rem">
    <input type="checkbox" name="show_duplicates" value="1" <?= ((int) $queryBase['show_duplicates'] === 1) ? 'checked' : '' ?>>
    <span class="text-muted">Показывать дубли</span>
  </label>

  <button type="submit" class="btn btn--ghost btn--sm">🔍 Найти</button>
  <a href="/cases/registry" class="btn btn--ghost btn--sm">Сброс</a>
</form>

<?php if (empty($items)): ?>
  <div class="empty">
    <div class="empty__icon"><span class="emoji">📂</span></div>
    <p>Записей не найдено</p>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Код</th>
          <th>Предмет</th>
          <th>Блок</th>
          <th>Год</th>
          <th>Статус</th>
          <th>Исполнители</th>
          <th>Контракт</th>
          <th>Срок</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($items as $row): ?>
        <?php
          $block = CaseBlockType::tryFrom((string) ($row['block_type'] ?? ''));
          $status = CaseResultStatus::tryFrom((string) ($row['result_status'] ?? ''));
          $subject = (string) ($row['subject_raw'] ?? '');
          $code = (string) ($row['case_code'] ?? '');
          if ($code === '') $code = '—';
          $due = (string) ($row['due_date'] ?? '');
          $isOverdue = (bool) ($row['is_overdue'] ?? false);
          $contractNumber = (string) ($row['contract_number'] ?? '');
          $assignees = (string) ($row['assignees'] ?? '');
        ?>
        <tr>
          <td class="td-link">
            <a href="/cases/registry/<?= Html::e((string) $row['id']) ?>"><?= Html::e($code) ?></a>
          </td>
          <td><?= Html::e(Html::truncate($subject, 85)) ?></td>
          <td>
            <?php if ($block): ?>
              <?= Html::badge($block->value, $block->label()) ?>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td class="td-num"><?= Html::e((string) ($row['year'] ?? '—')) ?></td>
          <td>
            <?php if ($status): ?>
              <?= Html::badge($status->value, $status->label()) ?>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td><?= Html::e($assignees !== '' ? Html::truncate($assignees, 45) : '—') ?></td>
          <td><?= Html::e($contractNumber !== '' ? Html::truncate($contractNumber, 40) : '—') ?></td>
          <td class="<?= $isOverdue ? 'text-rose' : 'text-muted' ?>">
            <?= Html::date($due) ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
  <nav class="pgn">
    <?php if ($page > 1): ?><a href="?<?= $pageQuery($page - 1) ?>">←</a><?php endif; ?>
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <?php if ($i === $page): ?><span class="cur"><?= $i ?></span>
      <?php elseif (abs($i - $page) < 3 || $i === 1 || $i === $pages): ?><a href="?<?= $pageQuery($i) ?>"><?= $i ?></a>
      <?php elseif (abs($i - $page) === 3): ?><span class="text-muted">…</span>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $pages): ?><a href="?<?= $pageQuery($page + 1) ?>">→</a><?php endif; ?>
  </nav>
  <?php endif; ?>
<?php endif; ?>
