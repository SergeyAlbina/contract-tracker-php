<?php
use App\Shared\Utils\Html;
use App\Shared\Enum\{LawType, ContractStatus, PaymentStatus, StageStatus, InvoiceStatus, ActStatus};
/** @var array $contract */
$c = $contract;

// Подгружаем этапы/счета/акты/платежи/документы (fault-tolerant: если модуль отключён — пусто)
$stages = $invoices = $acts = $payments = $documents = [];
try { $stages    = $app->make(\App\Modules\Stages\StagesService::class)->getByContract((int)$c['id']); } catch (\Throwable) {}
try {
  $billing = $app->make(\App\Modules\BillingDocs\BillingDocsService::class);
  $invoices = $billing->getInvoicesByContract((int)$c['id']);
  $acts = $billing->getActsByContract((int)$c['id']);
} catch (\Throwable) {}
try { $payments  = $app->make(\App\Modules\Payments\PaymentsService::class)->getByContract((int)$c['id']); } catch (\Throwable) {}
try { $documents = $app->make(\App\Modules\Documents\DocumentsService::class)->getByContract((int)$c['id']); } catch (\Throwable) {}

$stagesCount = count($stages);
$invoicesCount = count($invoices);
$actsCount = count($acts);
$paymentsCount = count($payments);
$documentsCount = count($documents);

$law    = LawType::from($c['law_type']);
$status = ContractStatus::from($c['status']);
$canEdit = $session->hasRole('admin', 'manager');

$notesForView = '';
$eisRegistryNumber = '';
$eisConcludedAt = '';
$eisExecutionStart = '';
$eisExecutionEnd = '';
$eisPublishedAt = '';
$rawNotes = trim((string) ($c['notes'] ?? ''));
if ($rawNotes !== '') {
  $cleanLines = [];
  foreach (preg_split('/\R/u', $rawNotes) ?: [] as $line) {
    $trimmed = trim((string) $line);
    if ($trimmed === '') {
      continue;
    }
    if (preg_match('/^Перенесено из дел:/ui', $trimmed)) {
      continue;
    }
    if (preg_match('/^source_case_id=/ui', $trimmed)) {
      continue;
    }
    if (preg_match('/^bundle=/ui', $trimmed)) {
      continue;
    }
    if (preg_match('/^\[ЕИС\]\s*Реестровый №:\s*(.+)$/u', $trimmed, $m)) {
      $eisRegistryNumber = trim((string) ($m[1] ?? ''));
      continue;
    }
    if (preg_match('/^\[ЕИС\]\s*Заключен:\s*(.+)$/u', $trimmed, $m)) {
      $eisConcludedAt = trim((string) ($m[1] ?? ''));
      continue;
    }
    if (preg_match('/^\[ЕИС\]\s*Размещено:\s*(.+)$/u', $trimmed, $m)) {
      $eisPublishedAt = trim((string) ($m[1] ?? ''));
      continue;
    }
    if (preg_match('/^\[ЕИС\]\s*Исполнение:\s*[cс]\s*(.*?)\s*по\s*(.*?)$/ui', $trimmed, $m)) {
      $eisExecutionStart = trim((string) ($m[1] ?? ''));
      $eisExecutionEnd = trim((string) ($m[2] ?? ''));
      continue;
    }
    $cleanLines[] = $trimmed;
  }
  $notesForView = implode(PHP_EOL, $cleanLines);
}

$concludedAt = $eisConcludedAt !== '' ? $eisConcludedAt : (string) ($c['signed_at'] ?? '');
$executionStart = $eisExecutionStart !== '' ? $eisExecutionStart : (string) ($c['signed_at'] ?? '');
$executionEnd = $eisExecutionEnd !== '' ? $eisExecutionEnd : (string) ($c['expires_at'] ?? '');
$executionPeriod = '—';
if ($executionStart !== '' && $executionEnd !== '') {
  $executionPeriod = 'с ' . Html::date($executionStart) . ' по ' . Html::date($executionEnd);
} elseif ($executionStart !== '') {
  $executionPeriod = 'с ' . Html::date($executionStart);
} elseif ($executionEnd !== '') {
  $executionPeriod = 'по ' . Html::date($executionEnd);
}
?>

<div class="page-head">
  <h1>
    <?= Html::badge($c['law_type'], $law->label()) ?>
    <?= Html::badge($c['status'], $status->label()) ?>
    №<?= Html::e($c['number']) ?>
  </h1>
  <div class="flex gap-sm">
    <?php if ($canEdit): ?>
      <a href="/contracts/<?= (int)$c['id'] ?>/edit" class="btn btn--ghost btn--sm">✏️ Редактировать</a>
    <?php endif; ?>
    <a href="/contracts" class="btn btn--ghost btn--sm">← К списку</a>
  </div>
</div>

<!-- ФИНАНСОВЫЙ БЛОК -->
<div class="finance-row">
  <div class="fin-card">
    <div class="fin-card__label">Сумма контракта</div>
    <div class="fin-card__val"><?= Html::money($c['total_amount']) ?></div>
  </div>
  <?php if ($c['nmck_amount']): ?>
  <div class="fin-card">
    <div class="fin-card__label">НМЦК</div>
    <div class="fin-card__val"><?= Html::money($c['nmck_amount']) ?></div>
  </div>
  <?php endif; ?>
  <div class="fin-card">
    <div class="fin-card__label">Оплачено</div>
    <div class="fin-card__val fin-card__val--g"><?= Html::money($c['paid_sum']) ?></div>
  </div>
  <div class="fin-card">
    <div class="fin-card__label"><?= $c['overspend'] ? '⚠ Перерасход' : 'Остаток' ?></div>
    <div class="fin-card__val <?= $c['overspend'] ? 'fin-card__val--r' : 'fin-card__val--a' ?>">
      <?= Html::money(abs($c['remaining'])) ?>
    </div>
  </div>
</div>

<!-- ДЕТАЛИ -->
<div class="card">
  <div class="detail-grid">
    <div class="di"><div class="di__label">Предмет</div><div class="di__value"><?= Html::e($c['subject']) ?></div></div>
    <div class="di"><div class="di__label">Контрагент</div><div class="di__value"><?= Html::e($c['contractor_name']) ?></div></div>
    <div class="di"><div class="di__label">ИНН</div><div class="di__value"><?= Html::e($c['contractor_inn'] ?: '—') ?></div></div>
    <div class="di"><div class="di__label">Реестровый №</div><div class="di__value"><?= Html::e($eisRegistryNumber !== '' ? $eisRegistryNumber : '—') ?></div></div>
    <div class="di"><div class="di__label">Заключен</div><div class="di__value"><?= Html::date($concludedAt) ?></div></div>
    <div class="di"><div class="di__label">Исполнение</div><div class="di__value"><?= Html::e($executionPeriod) ?></div></div>
    <div class="di"><div class="di__label">Размещено</div><div class="di__value"><?= Html::date($eisPublishedAt) ?></div></div>
    <div class="di"><div class="di__label">Создал</div><div class="di__value"><?= Html::e($c['creator_name'] ?? '—') ?></div></div>
    <?php if ($notesForView !== ''): ?>
    <div class="di" style="grid-column:1/-1"><div class="di__label">Примечания</div><div class="di__value"><?= nl2br(Html::e($notesForView)) ?></div></div>
    <?php endif; ?>
  </div>
</div>

<!-- ЭТАПЫ -->
<details class="section-fold" <?= $stagesCount > 0 ? 'open' : '' ?>>
  <summary>Этапы <span class="section-fold__count">(<?= $stagesCount ?>)</span></summary>
  <div class="section-fold__body">
<?php if ($stages): ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>#</th><th>Этап</th><th>План</th><th>Факт</th><th>Статус</th><th>Комментарий</th><?php if($canEdit):?><th></th><?php endif;?></tr></thead>
    <tbody>
      <?php foreach ($stages as $s): ?>
      <?php $stageStatus = StageStatus::tryFrom((string)$s['status']); ?>
      <tr>
        <td class="td-num"><?= (int)$s['sort_order'] ?></td>
        <td><?= Html::e($s['title']) ?></td>
        <td class="text-muted"><?= Html::date($s['planned_date']) ?></td>
        <td class="text-muted"><?= Html::date($s['actual_date']) ?></td>
        <td><?= Html::badge((string)$s['status'], $stageStatus?->label() ?? (string)$s['status']) ?></td>
        <td><?= Html::e($s['description'] ? Html::truncate((string)$s['description'], 90) : '—') ?></td>
        <?php if ($canEdit): ?>
        <td style="text-align:right">
          <a href="#stage-edit-<?= (int)$s['id'] ?>" class="btn btn--ghost btn--sm">✏️</a>
          <?php if ($session->hasRole('admin')): ?>
          <form method="post" action="/stages/<?= (int)$s['id'] ?>/delete" style="display:inline">
            <?= $csrf->field() ?>
            <input type="hidden" name="contract_id" value="<?= (int)$c['id'] ?>">
            <button type="submit" class="btn--icon" data-confirm="Удалить этап?">🗑</button>
          </form>
          <?php endif; ?>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<div class="empty">
  <div class="empty__icon">🧭</div>
  <p>Этапы пока не добавлены</p>
</div>
<?php endif; ?>

<?php if ($canEdit): ?>
<div class="card mt-2">
  <div class="card__head"><div class="card__title">Добавить этап</div></div>
  <form method="post" action="/contracts/<?= (int)$c['id'] ?>/stages">
    <?= $csrf->field() ?>
    <div class="form-grid">
      <div class="fg">
        <label>Порядок</label>
        <input type="number" name="sort_order" min="0" step="1" value="0">
      </div>
      <div class="fg">
        <label>Название этапа *</label>
        <input type="text" name="title" required>
      </div>
      <div class="fg">
        <label>Статус</label>
        <select name="status">
          <?php foreach (StageStatus::cases() as $ss): ?>
            <option value="<?= $ss->value ?>"><?= $ss->label() ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fg">
        <label>Плановая дата</label>
        <input type="date" name="planned_date">
      </div>
      <div class="fg">
        <label>Фактическая дата</label>
        <input type="date" name="actual_date">
      </div>
      <div class="fg form-grid--full">
        <label>Комментарий</label>
        <textarea name="description"></textarea>
      </div>
    </div>
    <div class="form-actions"><button type="submit" class="btn btn--primary btn--sm">+ Добавить этап</button></div>
  </form>
</div>

<?php if ($stages): ?>
<div class="card mt-2">
  <div class="card__head"><div class="card__title">Редактировать этапы</div></div>
  <?php foreach ($stages as $s): ?>
    <?php $currentStageStatus = StageStatus::tryFrom((string)$s['status']) ?? StageStatus::PLANNED; ?>
    <form method="post" action="/stages/<?= (int)$s['id'] ?>/update" id="stage-edit-<?= (int)$s['id'] ?>" class="mt-2">
      <?= $csrf->field() ?>
      <input type="hidden" name="contract_id" value="<?= (int)$c['id'] ?>">
      <div class="form-grid">
        <div class="fg">
          <label>Порядок</label>
          <input type="number" name="sort_order" min="0" step="1" value="<?= (int)$s['sort_order'] ?>">
        </div>
        <div class="fg">
          <label>Название этапа *</label>
          <input type="text" name="title" value="<?= Html::e($s['title']) ?>" required>
        </div>
        <div class="fg">
          <label>Статус</label>
          <select name="status">
            <?php foreach (StageStatus::cases() as $ss): ?>
              <option value="<?= $ss->value ?>" <?= $currentStageStatus === $ss ? 'selected' : '' ?>><?= $ss->label() ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg">
          <label>Плановая дата</label>
          <input type="date" name="planned_date" value="<?= Html::e((string)($s['planned_date'] ?? '')) ?>">
        </div>
        <div class="fg">
          <label>Фактическая дата</label>
          <input type="date" name="actual_date" value="<?= Html::e((string)($s['actual_date'] ?? '')) ?>">
        </div>
        <div class="fg form-grid--full">
          <label>Комментарий</label>
          <textarea name="description"><?= Html::e((string)($s['description'] ?? '')) ?></textarea>
        </div>
      </div>
      <div class="form-actions"><button type="submit" class="btn btn--ghost btn--sm">Сохранить этап</button></div>
    </form>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>
  </div>
</details>

<!-- СЧЕТА -->
<details class="section-fold" <?= $invoicesCount > 0 ? 'open' : '' ?>>
  <summary>Счета <span class="section-fold__count">(<?= $invoicesCount ?>)</span></summary>
  <div class="section-fold__body">

<?php if ($invoices): ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Номер</th><th>Дата</th><th>Оплатить до</th><th>Статус</th><th style="text-align:right">Сумма</th><th>Комментарий</th><?php if($canEdit):?><th></th><?php endif;?></tr></thead>
    <tbody>
      <?php foreach ($invoices as $inv): ?>
      <?php $invoiceStatus = InvoiceStatus::tryFrom((string)$inv['status']); ?>
      <tr>
        <td class="td-num"><?= Html::e($inv['invoice_number']) ?></td>
        <td class="text-muted"><?= Html::date($inv['invoice_date']) ?></td>
        <td class="text-muted"><?= Html::date($inv['due_date']) ?></td>
        <td><?= Html::badge((string)$inv['status'], $invoiceStatus?->label() ?? (string)$inv['status']) ?></td>
        <td class="td-num" style="text-align:right"><?= Html::money($inv['amount']) ?></td>
        <td><?= Html::e($inv['comment'] ? Html::truncate((string)$inv['comment'], 90) : '—') ?></td>
        <?php if ($canEdit): ?>
        <td style="text-align:right">
          <a href="#invoice-edit-<?= (int)$inv['id'] ?>" class="btn btn--ghost btn--sm">✏️</a>
          <?php if ($session->hasRole('admin')): ?>
          <form method="post" action="/invoices/<?= (int)$inv['id'] ?>/delete" style="display:inline">
            <?= $csrf->field() ?>
            <input type="hidden" name="contract_id" value="<?= (int)$c['id'] ?>">
            <button type="submit" class="btn--icon" data-confirm="Удалить счёт?">🗑</button>
          </form>
          <?php endif; ?>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<div class="empty">
  <div class="empty__icon">🧾</div>
  <p>Счета пока не добавлены</p>
</div>
<?php endif; ?>

<?php if ($canEdit): ?>
<div class="card mt-2">
  <div class="card__head"><div class="card__title">Добавить счёт</div></div>
  <form method="post" action="/contracts/<?= (int)$c['id'] ?>/invoices">
    <?= $csrf->field() ?>
    <div class="form-grid">
      <div class="fg">
        <label>Номер счёта *</label>
        <input type="text" name="invoice_number" required>
      </div>
      <div class="fg">
        <label>Дата счёта</label>
        <input type="date" name="invoice_date">
      </div>
      <div class="fg">
        <label>Оплатить до</label>
        <input type="date" name="due_date">
      </div>
      <div class="fg">
        <label>Сумма *</label>
        <input type="number" name="amount" step="0.01" min="0.01" required>
      </div>
      <div class="fg">
        <label>Статус</label>
        <select name="status">
          <?php foreach (InvoiceStatus::cases() as $is): ?>
            <option value="<?= $is->value ?>"><?= $is->label() ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fg form-grid--full">
        <label>Комментарий</label>
        <textarea name="comment"></textarea>
      </div>
    </div>
    <div class="form-actions"><button type="submit" class="btn btn--primary btn--sm">+ Добавить счёт</button></div>
  </form>
</div>

<?php if ($invoices): ?>
<div class="card mt-2">
  <div class="card__head"><div class="card__title">Редактировать счета</div></div>
  <?php foreach ($invoices as $inv): ?>
    <?php $currentInvoiceStatus = InvoiceStatus::tryFrom((string)$inv['status']) ?? InvoiceStatus::ISSUED; ?>
    <form method="post" action="/invoices/<?= (int)$inv['id'] ?>/update" id="invoice-edit-<?= (int)$inv['id'] ?>" class="mt-2">
      <?= $csrf->field() ?>
      <input type="hidden" name="contract_id" value="<?= (int)$c['id'] ?>">
      <div class="form-grid">
        <div class="fg">
          <label>Номер счёта *</label>
          <input type="text" name="invoice_number" value="<?= Html::e((string)$inv['invoice_number']) ?>" required>
        </div>
        <div class="fg">
          <label>Дата счёта</label>
          <input type="date" name="invoice_date" value="<?= Html::e((string)($inv['invoice_date'] ?? '')) ?>">
        </div>
        <div class="fg">
          <label>Оплатить до</label>
          <input type="date" name="due_date" value="<?= Html::e((string)($inv['due_date'] ?? '')) ?>">
        </div>
        <div class="fg">
          <label>Сумма *</label>
          <input type="number" name="amount" step="0.01" min="0.01" value="<?= Html::e((string)$inv['amount']) ?>" required>
        </div>
        <div class="fg">
          <label>Статус</label>
          <select name="status">
            <?php foreach (InvoiceStatus::cases() as $is): ?>
              <option value="<?= $is->value ?>" <?= $currentInvoiceStatus === $is ? 'selected' : '' ?>><?= $is->label() ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg form-grid--full">
          <label>Комментарий</label>
          <textarea name="comment"><?= Html::e((string)($inv['comment'] ?? '')) ?></textarea>
        </div>
      </div>
      <div class="form-actions"><button type="submit" class="btn btn--ghost btn--sm">Сохранить счёт</button></div>
    </form>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>
  </div>
</details>

<!-- АКТЫ -->
<details class="section-fold" <?= $actsCount > 0 ? 'open' : '' ?>>
  <summary>Акты <span class="section-fold__count">(<?= $actsCount ?>)</span></summary>
  <div class="section-fold__body">

<?php if ($acts): ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Номер</th><th>Дата</th><th>Статус</th><th style="text-align:right">Сумма</th><th>Комментарий</th><?php if($canEdit):?><th></th><?php endif;?></tr></thead>
    <tbody>
      <?php foreach ($acts as $act): ?>
      <?php $actStatus = ActStatus::tryFrom((string)$act['status']); ?>
      <tr>
        <td class="td-num"><?= Html::e($act['act_number']) ?></td>
        <td class="text-muted"><?= Html::date($act['act_date']) ?></td>
        <td><?= Html::badge((string)$act['status'], $actStatus?->label() ?? (string)$act['status']) ?></td>
        <td class="td-num" style="text-align:right"><?= Html::money($act['amount']) ?></td>
        <td><?= Html::e($act['comment'] ? Html::truncate((string)$act['comment'], 90) : '—') ?></td>
        <?php if ($canEdit): ?>
        <td style="text-align:right">
          <a href="#act-edit-<?= (int)$act['id'] ?>" class="btn btn--ghost btn--sm">✏️</a>
          <?php if ($session->hasRole('admin')): ?>
          <form method="post" action="/acts/<?= (int)$act['id'] ?>/delete" style="display:inline">
            <?= $csrf->field() ?>
            <input type="hidden" name="contract_id" value="<?= (int)$c['id'] ?>">
            <button type="submit" class="btn--icon" data-confirm="Удалить акт?">🗑</button>
          </form>
          <?php endif; ?>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<div class="empty">
  <div class="empty__icon">📑</div>
  <p>Акты пока не добавлены</p>
</div>
<?php endif; ?>

<?php if ($canEdit): ?>
<div class="card mt-2">
  <div class="card__head"><div class="card__title">Добавить акт</div></div>
  <form method="post" action="/contracts/<?= (int)$c['id'] ?>/acts">
    <?= $csrf->field() ?>
    <div class="form-grid">
      <div class="fg">
        <label>Номер акта *</label>
        <input type="text" name="act_number" required>
      </div>
      <div class="fg">
        <label>Дата акта</label>
        <input type="date" name="act_date">
      </div>
      <div class="fg">
        <label>Сумма *</label>
        <input type="number" name="amount" step="0.01" min="0.01" required>
      </div>
      <div class="fg">
        <label>Статус</label>
        <select name="status">
          <?php foreach (ActStatus::cases() as $as): ?>
            <option value="<?= $as->value ?>"><?= $as->label() ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fg form-grid--full">
        <label>Комментарий</label>
        <textarea name="comment"></textarea>
      </div>
    </div>
    <div class="form-actions"><button type="submit" class="btn btn--primary btn--sm">+ Добавить акт</button></div>
  </form>
</div>

<?php if ($acts): ?>
<div class="card mt-2">
  <div class="card__head"><div class="card__title">Редактировать акты</div></div>
  <?php foreach ($acts as $act): ?>
    <?php $currentActStatus = ActStatus::tryFrom((string)$act['status']) ?? ActStatus::PENDING; ?>
    <form method="post" action="/acts/<?= (int)$act['id'] ?>/update" id="act-edit-<?= (int)$act['id'] ?>" class="mt-2">
      <?= $csrf->field() ?>
      <input type="hidden" name="contract_id" value="<?= (int)$c['id'] ?>">
      <div class="form-grid">
        <div class="fg">
          <label>Номер акта *</label>
          <input type="text" name="act_number" value="<?= Html::e((string)$act['act_number']) ?>" required>
        </div>
        <div class="fg">
          <label>Дата акта</label>
          <input type="date" name="act_date" value="<?= Html::e((string)($act['act_date'] ?? '')) ?>">
        </div>
        <div class="fg">
          <label>Сумма *</label>
          <input type="number" name="amount" step="0.01" min="0.01" value="<?= Html::e((string)$act['amount']) ?>" required>
        </div>
        <div class="fg">
          <label>Статус</label>
          <select name="status">
            <?php foreach (ActStatus::cases() as $as): ?>
              <option value="<?= $as->value ?>" <?= $currentActStatus === $as ? 'selected' : '' ?>><?= $as->label() ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg form-grid--full">
          <label>Комментарий</label>
          <textarea name="comment"><?= Html::e((string)($act['comment'] ?? '')) ?></textarea>
        </div>
      </div>
      <div class="form-actions"><button type="submit" class="btn btn--ghost btn--sm">Сохранить акт</button></div>
    </form>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>
  </div>
</details>

<!-- ПЛАТЕЖИ -->
<details class="section-fold" <?= $paymentsCount > 0 ? 'open' : '' ?>>
  <summary>Платежи <span class="section-fold__count">(<?= $paymentsCount ?>)</span></summary>
  <div class="section-fold__body">

<?php if ($payments): ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Дата</th><th>Назначение</th><th>Счёт</th><th>Статус</th><th style="text-align:right">Сумма</th><?php if($canEdit):?><th></th><?php endif;?></tr></thead>
    <tbody>
      <?php foreach ($payments as $p): ?>
      <tr>
        <td class="text-muted"><?= Html::date($p['payment_date']) ?></td>
        <td><?= Html::e($p['purpose'] ?: '—') ?></td>
        <td class="td-num"><?= Html::e($p['invoice_number'] ?: '—') ?></td>
        <td><?= Html::badge($p['status'], PaymentStatus::from($p['status'])->label()) ?></td>
        <td class="td-num" style="text-align:right"><?= Html::money($p['amount']) ?></td>
        <?php if ($canEdit): ?>
        <td style="text-align:right">
          <form method="post" action="/payments/<?= (int)$p['id'] ?>/delete" style="display:inline">
            <?= $csrf->field() ?>
            <input type="hidden" name="contract_id" value="<?= (int)$c['id'] ?>">
            <button type="submit" class="btn--icon" data-confirm="Удалить платёж?">🗑</button>
          </form>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php if ($canEdit): ?>
<div class="card mt-2">
  <div class="card__head"><div class="card__title">Добавить платёж</div></div>
  <form method="post" action="/contracts/<?= (int)$c['id'] ?>/payments">
    <?= $csrf->field() ?>
    <div class="form-grid">
      <div class="fg">
        <label>Сумма *</label>
        <input type="number" name="amount" step="0.01" min="0.01" required>
      </div>
      <div class="fg">
        <label>Статус</label>
        <select name="status">
          <?php foreach (PaymentStatus::cases() as $ps): ?>
            <option value="<?= $ps->value ?>"><?= $ps->label() ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fg">
        <label>Дата</label>
        <input type="date" name="payment_date">
      </div>
      <div class="fg">
        <label>Назначение</label>
        <input type="text" name="purpose">
      </div>
      <div class="fg">
        <label>Номер счёта</label>
        <input type="text" name="invoice_number">
      </div>
    </div>
    <div class="form-actions"><button type="submit" class="btn btn--primary btn--sm">+ Добавить</button></div>
  </form>
</div>
<?php endif; ?>
  </div>
</details>

<!-- ДОКУМЕНТЫ -->
<details class="section-fold" <?= $documentsCount > 0 ? 'open' : '' ?>>
  <summary>Документы <span class="section-fold__count">(<?= $documentsCount ?>)</span></summary>
  <div class="section-fold__body">

<?php if ($documents): ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Файл</th><th>Тип</th><th>Размер</th><th>Загрузил</th><th>Дата</th><?php if($canEdit):?><th></th><?php endif;?></tr></thead>
    <tbody>
      <?php foreach ($documents as $d): ?>
      <tr>
        <td><a href="/documents/<?= (int)$d['id'] ?>/download"><?= Html::e($d['original_name']) ?></a></td>
        <td><?= Html::badge('slate', $d['doc_type']) ?></td>
        <td class="text-muted"><?= Html::fileSize((int)$d['size_bytes']) ?></td>
        <td class="text-muted"><?= Html::e($d['uploader_name'] ?? '—') ?></td>
        <td class="text-muted"><?= Html::date($d['created_at']) ?></td>
        <?php if ($canEdit): ?>
        <td style="text-align:right">
          <form method="post" action="/documents/<?= (int)$d['id'] ?>/delete" style="display:inline">
            <?= $csrf->field() ?>
            <button type="submit" class="btn--icon" data-confirm="Удалить документ?">🗑</button>
          </form>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php if ($canEdit): ?>
<div class="card mt-2">
  <form method="post" action="/contracts/<?= (int)$c['id'] ?>/documents" enctype="multipart/form-data">
    <?= $csrf->field() ?>
    <div class="form-grid" style="align-items:end">
      <div class="fg">
        <label>Тип документа</label>
        <select name="doc_type">
          <option value="contract">Контракт</option>
          <option value="supplement">Допсоглашение</option>
          <option value="act">Акт</option>
          <option value="invoice">Счёт</option>
          <option value="other" selected>Другое</option>
        </select>
      </div>
      <div class="upload-zone" style="flex:1">
        <input type="file" name="document" required>
        <div class="upload-zone__label">📁 Выберите файл или перетащите сюда</div>
      </div>
      <button type="submit" class="btn btn--primary btn--sm">📤 Загрузить</button>
    </div>
  </form>
</div>
<?php endif; ?>
  </div>
</details>

<?php if ($canEdit && $session->hasRole('admin')): ?>
<div style="margin-top:2rem;padding-top:1rem;border-top:1px solid var(--border)">
  <form method="post" action="/contracts/<?= (int)$c['id'] ?>/delete">
    <?= $csrf->field() ?>
    <button type="submit" class="btn btn--danger btn--sm" data-confirm="Удалить контракт и все связанные документы/платежи?">🗑 Удалить контракт</button>
  </form>
</div>
<?php endif; ?>
