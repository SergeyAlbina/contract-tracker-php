<?php
use App\Shared\Enum\{LawType, ProcurementStatus};
use App\Shared\Utils\Html;

/** @var array $items @var int $total @var int $page @var int $pages @var array $filters */
$canEdit = $session->hasRole('admin', 'manager');
?>

<div class="page-head">
  <h1>🛒 Закупки <span class="text-muted" style="font-size:.7em;font-weight:400">(<?= $total ?>)</span></h1>
  <?php if ($canEdit): ?>
    <a href="/procurements/new" class="btn btn--primary">+ Новая закупка</a>
  <?php endif; ?>
</div>

<form class="filters" method="get" action="/procurements">
  <input type="text" name="search" placeholder="Поиск по номеру и предмету..." value="<?= Html::e($filters['search'] ?? '') ?>" style="flex:1;min-width:200px">
  <select name="law_type">
    <option value="">Все законы</option>
    <?php foreach (LawType::cases() as $law): ?>
      <option value="<?= $law->value ?>" <?= ($filters['law_type'] ?? '') === $law->value ? 'selected' : '' ?>><?= $law->label() ?></option>
    <?php endforeach; ?>
  </select>
  <select name="status">
    <option value="">Все статусы</option>
    <?php foreach (ProcurementStatus::cases() as $status): ?>
      <option value="<?= $status->value ?>" <?= ($filters['status'] ?? '') === $status->value ? 'selected' : '' ?>><?= $status->label() ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn--ghost btn--sm">🔍 Найти</button>
</form>

<?php if (!$items): ?>
  <div class="empty">
    <div class="empty__icon">🧾</div>
    <p>Закупки не найдены</p>
    <?php if ($canEdit): ?>
      <a href="/procurements/new" class="btn btn--primary mt-2" style="display:inline-flex">+ Создать первую закупку</a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Номер</th>
          <th>Предмет</th>
          <th>Закон</th>
          <th>Статус</th>
          <th style="text-align:right">НМЦК</th>
          <th>КП</th>
          <th>Дедлайн</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($items as $item): ?>
        <?php
          $status = ProcurementStatus::tryFrom((string) $item['status']);
          $statusLabel = $status ? $status->label() : (string) $item['status'];
        ?>
        <tr>
          <td class="td-link"><a href="/procurements/<?= (int) $item['id'] ?>"><?= Html::e($item['number']) ?></a></td>
          <td><?= Html::e(Html::truncate((string) $item['subject'], 80)) ?></td>
          <td><?= Html::badge((string) $item['law_type'], LawType::from((string) $item['law_type'])->label()) ?></td>
          <td><?= Html::badge((string) $item['status'], $statusLabel) ?></td>
          <td class="td-num" style="text-align:right"><?= Html::money((float) $item['nmck_amount']) ?></td>
          <td class="td-num">
            <?= (int) $item['proposals_count'] ?>
            <?php if ($item['min_quote_amount'] !== null): ?>
              <span class="text-muted">(от <?= Html::money((float) $item['min_quote_amount']) ?>)</span>
            <?php endif; ?>
          </td>
          <td class="text-muted"><?= Html::date($item['deadline_at'] ?? null) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
    <nav class="pgn">
      <?php if ($page > 1): ?><a href="?<?= http_build_query(array_merge($filters, ['page' => $page - 1])) ?>">←</a><?php endif; ?>
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === $page): ?>
          <span class="cur"><?= $i ?></span>
        <?php elseif (abs($i - $page) < 3 || $i === 1 || $i === $pages): ?>
          <a href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>"><?= $i ?></a>
        <?php elseif (abs($i - $page) === 3): ?>
          <span class="text-muted">…</span>
        <?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $pages): ?><a href="?<?= http_build_query(array_merge($filters, ['page' => $page + 1])) ?>">→</a><?php endif; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>
