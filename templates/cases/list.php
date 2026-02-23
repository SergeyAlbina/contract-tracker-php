<?php
use App\Shared\Enum\CaseBlockType;
use App\Shared\Enum\CaseResultStatus;
use App\Shared\Utils\Html;

/** @var array $items @var int $total @var int $page @var int $pages @var int $perPage @var array $filters @var array $users */
/** @var array<CaseBlockType> $blockTypes @var array<CaseResultStatus> $statuses */
/** @var array<string,int> $blockCounts @var int $totalWithoutBlock */

$queryBase = [
    'block_type' => (string) ($filters['block_type'] ?? ''),
    'year' => (string) ($filters['year'] ?? ''),
    'result_status' => (string) ($filters['result_status'] ?? ''),
    'assignee' => (string) ($filters['assignee'] ?? ''),
    'q' => (string) ($filters['q'] ?? ''),
    'overdue' => (int) ($filters['overdue'] ?? 0),
    'in_progress' => (int) ($filters['in_progress'] ?? 0),
    'per_page' => (int) ($filters['per_page'] ?? $perPage),
];

$pageQuery = static function (int $targetPage) use ($queryBase): string {
    return http_build_query(array_merge($queryBase, ['page' => $targetPage]));
};

$tabsQueryBase = array_merge($queryBase, ['page' => 1]);
$tabHref = static function (string $blockType) use ($tabsQueryBase): string {
    $query = array_merge($tabsQueryBase, ['block_type' => $blockType]);
    return '/cases/registry?' . http_build_query($query);
};
$visibleBlockTypes = array_values(array_filter(
    $blockTypes,
    static fn(CaseBlockType $block): bool => $block !== CaseBlockType::CONCLUDED
));
$allBlocksCount = isset($totalWithoutBlock) ? (int) $totalWithoutBlock : (int) array_sum($blockCounts ?? []);
?>

<div class="page-head">
  <h1>
    <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
      <path d="M3 7a2 2 0 0 1 2-2h5l2 2h9"></path>
      <path d="M3 7h18v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
    </svg>
    Дела <span class="page-head__count">(<?= (int) $total ?>)</span>
  </h1>
</div>

<nav class="cases-tabs" aria-label="Блоки дел">
  <a href="<?= Html::e($tabHref('')) ?>" class="cases-tab <?= $queryBase['block_type'] === '' ? 'active' : '' ?>">
    Все
    <span class="cases-tab__count"><?= $allBlocksCount ?></span>
  </a>
  <?php foreach ($visibleBlockTypes as $block): ?>
    <?php
      $value = $block->value;
      $count = (int) ($blockCounts[$value] ?? 0);
    ?>
    <a href="<?= Html::e($tabHref($value)) ?>" class="cases-tab <?= $queryBase['block_type'] === $value ? 'active' : '' ?>">
      <?= Html::e($block->label()) ?>
      <span class="cases-tab__count"><?= $count ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<form class="filters filters--cases" method="get" action="/cases/registry">
  <div class="cases-filters__row cases-filters__row--main">
    <input
      type="text"
      name="q"
      placeholder="Поиск: код, предмет, номер контракта…"
      value="<?= Html::e($queryBase['q']) ?>"
      style="flex:1"
    >

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
  </div>

  <div class="cases-filters__row">
    <input type="hidden" name="block_type" value="<?= Html::e($queryBase['block_type']) ?>">

    <label class="cases-check">
      <input type="checkbox" name="overdue" value="1" <?= ((int) $queryBase['overdue'] === 1) ? 'checked' : '' ?>>
      <span class="text-muted">Просрочка</span>
    </label>

    <label class="cases-check">
      <input type="checkbox" name="in_progress" value="1" <?= ((int) $queryBase['in_progress'] === 1) ? 'checked' : '' ?>>
      <span class="text-muted">В работе</span>
    </label>

    <button type="submit" class="btn btn--ghost btn--sm">Найти</button>
    <a href="/cases/registry" class="btn btn--ghost btn--sm">Сброс</a>
  </div>
</form>

<?php if (empty($items)): ?>
  <div class="empty">
    <div class="empty__icon">
      <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M3 7a2 2 0 0 1 2-2h5l2 2h9"></path>
        <path d="M3 7h18v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
      </svg>
    </div>
    <p>Записей не найдено</p>
  </div>
<?php else: ?>
  <div class="table-tools">
    <details class="column-picker">
      <summary>Колонки таблицы</summary>
      <div class="column-picker__menu">
        <label><input type="checkbox" data-table-id="cases-registry" data-table-column="code" checked> Код</label>
        <label><input type="checkbox" data-table-id="cases-registry" data-table-column="subject" checked> Предмет</label>
        <label><input type="checkbox" data-table-id="cases-registry" data-table-column="block" checked> Блок</label>
        <label><input type="checkbox" data-table-id="cases-registry" data-table-column="year" checked> Год</label>
        <label><input type="checkbox" data-table-id="cases-registry" data-table-column="status" checked> Статус</label>
        <label><input type="checkbox" data-table-id="cases-registry" data-table-column="assignees" checked> Исполнители</label>
        <label><input type="checkbox" data-table-id="cases-registry" data-table-column="contract" checked> Контракт</label>
        <label><input type="checkbox" data-table-id="cases-registry" data-table-column="due" checked> Срок</label>
      </div>
    </details>
  </div>

  <div class="table-wrap">
    <table class="cases-table" data-table-id="cases-registry">
      <colgroup>
        <col data-col-key="code" style="width:94px">
        <col data-col-key="subject" style="width:390px">
        <col data-col-key="block" style="width:96px">
        <col data-col-key="year" style="width:64px">
        <col data-col-key="status" style="width:108px">
        <col data-col-key="assignees" style="width:170px">
        <col data-col-key="contract" style="width:210px">
        <col data-col-key="due" style="width:92px">
      </colgroup>
      <thead>
        <tr>
          <th class="col-code" data-col-key="code">Код</th>
          <th class="col-subject" data-col-key="subject">Предмет</th>
          <th class="col-block" data-col-key="block">Блок</th>
          <th class="col-year" data-col-key="year">Год</th>
          <th class="col-status" data-col-key="status">Статус</th>
          <th class="col-assignees" data-col-key="assignees">Исполнители</th>
          <th class="col-contract" data-col-key="contract">Контракт</th>
          <th class="col-due" data-col-key="due">Срок</th>
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
          <td class="td-link col-code" data-col-key="code">
            <a href="/cases/registry/<?= Html::e((string) $row['id']) ?>"><?= Html::e($code) ?></a>
          </td>
          <td class="col-subject" data-col-key="subject"><?= Html::e(Html::truncate($subject, 85)) ?></td>
          <td class="col-block" data-col-key="block">
            <?php if ($block): ?>
              <?= Html::badge($block->value, $block->label()) ?>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td class="td-num col-year" data-col-key="year"><?= Html::e((string) ($row['year'] ?? '—')) ?></td>
          <td class="col-status" data-col-key="status">
            <?php if ($status): ?>
              <?= Html::badge($status->value, $status->label()) ?>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td class="col-assignees" data-col-key="assignees"><?= Html::e($assignees !== '' ? Html::truncate($assignees, 45) : '—') ?></td>
          <td class="col-contract" data-col-key="contract"><?= Html::e($contractNumber !== '' ? Html::truncate($contractNumber, 40) : '—') ?></td>
          <td class="col-due <?= $isOverdue ? 'text-rose' : 'text-muted' ?>" data-col-key="due">
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
