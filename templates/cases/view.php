<?php
use App\Shared\Enum\CaseAssigneeRole;
use App\Shared\Enum\CaseBlockType;
use App\Shared\Enum\CaseEventType;
use App\Shared\Enum\CaseResultStatus;
use App\Shared\Utils\Html;

/** @var array $item */

$block = CaseBlockType::tryFrom((string) ($item['block_type'] ?? ''));
$status = CaseResultStatus::tryFrom((string) ($item['result_status'] ?? ''));
$attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : [];
$assignees = is_array($item['assignees'] ?? null) ? $item['assignees'] : [];
$events = is_array($item['events'] ?? null) ? $item['events'] : [];
$files = is_array($item['files'] ?? null) ? $item['files'] : [];

$hasValue = static function (mixed $value): bool {
    if ($value === null) {
        return false;
    }
    if (is_string($value)) {
        return trim($value) !== '';
    }
    return true;
};

$procurementFormRaw = trim((string) ($item['procurement_form'] ?? ''));
$stageRaw = trim((string) ($item['stage_raw'] ?? ''));
$bundleKey = trim((string) ($item['bundle_key'] ?? ''));
$subjectText = trim((string) ($item['subject_raw'] ?? ''));
$notesText = trim((string) ($item['notes'] ?? ''));
$archivePathText = trim((string) ($item['archive_path'] ?? ''));
$resultRawText = trim((string) ($item['result_raw'] ?? ''));

$procurementFormLabels = [
    'INCOMING_DOC' => 'Входящий документ',
];
$stageLabels = [
    'INCOMING_REGISTRY' => 'Журнал входящих',
];

$procurementFormLabel = $procurementFormRaw !== ''
    ? ($procurementFormLabels[strtoupper($procurementFormRaw)] ?? $procurementFormRaw)
    : '';
$stageLabel = $stageRaw !== ''
    ? ($stageLabels[strtoupper($stageRaw)] ?? $stageRaw)
    : '';

$caseTitle = (string) ($item['case_code'] ?: ('#' . $item['id']));
$isAdmin = isset($session) && method_exists($session, 'hasRole') && $session->hasRole('admin');
?>

<div class="page-head">
  <h1>
    <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
      <path d="M3 7a2 2 0 0 1 2-2h5l2 2h9"></path>
      <path d="M3 7h18v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
    </svg>
    Дело: <?= Html::e($caseTitle) ?>
  </h1>
  <div class="flex gap-sm">
    <a href="/cases/registry" class="btn btn--ghost">← К реестру</a>
  </div>
</div>

<div class="card">
  <div class="card__head">
    <div class="card__title">Общие данные</div>
  </div>

  <div class="detail-grid">
    <div class="di">
      <div class="di__label">Блок</div>
      <div class="di__value">
        <?= $block ? Html::badge($block->value, $block->label()) : '<span class="text-muted">—</span>' ?>
      </div>
    </div>

    <div class="di">
      <div class="di__label">Статус</div>
      <div class="di__value">
        <?= $status ? Html::badge($status->value, $status->label()) : '<span class="text-muted">—</span>' ?>
      </div>
    </div>

    <?php if ($hasValue($item['year'] ?? null)): ?>
      <div class="di"><div class="di__label">Год</div><div class="di__value"><?= Html::e((string) $item['year']) ?></div></div>
    <?php endif; ?>

    <?php if ($hasValue($item['reg_no'] ?? null)): ?>
      <div class="di"><div class="di__label">Рег. №</div><div class="di__value"><?= Html::e((string) $item['reg_no']) ?></div></div>
    <?php endif; ?>

    <?php if ($hasValue($item['case_code'] ?? null)): ?>
      <div class="di"><div class="di__label">Код дела</div><div class="di__value"><?= Html::e((string) $item['case_code']) ?></div></div>
    <?php endif; ?>

    <?php if ($hasValue($item['task_date'] ?? null)): ?>
      <div class="di"><div class="di__label">Дата задачи</div><div class="di__value"><?= Html::date((string) $item['task_date']) ?></div></div>
    <?php endif; ?>

    <?php if ($hasValue($item['due_date'] ?? null)): ?>
      <div class="di"><div class="di__label">Срок</div><div class="di__value"><?= Html::date((string) $item['due_date']) ?></div></div>
      <div class="di"><div class="di__label">Просрочено</div><div class="di__value <?= !empty($item['is_overdue']) ? 'text-rose' : 'text-muted' ?>"><?= !empty($item['is_overdue']) ? 'Да' : 'Нет' ?></div></div>
    <?php endif; ?>

    <?php if ($procurementFormLabel !== ''): ?>
      <div class="di"><div class="di__label">Тип источника</div><div class="di__value"><?= Html::e($procurementFormLabel) ?></div></div>
    <?php endif; ?>

    <?php if ($stageLabel !== ''): ?>
      <div class="di"><div class="di__label">Этап</div><div class="di__value"><?= Html::e($stageLabel) ?></div></div>
    <?php endif; ?>

    <?php if ($resultRawText !== ''): ?>
      <div class="di"><div class="di__label">Результат</div><div class="di__value"><?= Html::e($resultRawText) ?></div></div>
    <?php endif; ?>

    <?php if ($hasValue($item['amount_planned'] ?? null)): ?>
      <div class="di"><div class="di__label">Плановая сумма</div><div class="di__value"><?= Html::money($item['amount_planned'] ?? null) ?></div></div>
    <?php endif; ?>

    <?php if ($hasValue($item['rnmc_amount'] ?? null)): ?>
      <div class="di"><div class="di__label">РНМЦК</div><div class="di__value"><?= Html::money($item['rnmc_amount'] ?? null) ?></div></div>
    <?php endif; ?>

    <?php if ($hasValue($item['contract_number'] ?? null)): ?>
      <div class="di"><div class="di__label">Контракт</div><div class="di__value"><?= Html::e((string) $item['contract_number']) ?></div></div>
    <?php endif; ?>

    <?php if ($hasValue($item['contract_date'] ?? null)): ?>
      <div class="di"><div class="di__label">Дата контракта</div><div class="di__value"><?= Html::date((string) $item['contract_date']) ?></div></div>
    <?php endif; ?>

    <?php if ($hasValue($item['contract_amount'] ?? null)): ?>
      <div class="di"><div class="di__label">Сумма контракта</div><div class="di__value"><?= Html::money($item['contract_amount'] ?? null) ?></div></div>
    <?php endif; ?>

    <?php if ($hasValue($item['result_amount'] ?? null)): ?>
      <div class="di"><div class="di__label">Сумма результата</div><div class="di__value"><?= Html::money($item['result_amount'] ?? null) ?></div></div>
    <?php endif; ?>

    <?php if (($item['result_percent'] ?? null) !== null && $item['result_percent'] !== ''): ?>
      <div class="di"><div class="di__label">Результат %</div><div class="di__value"><?= Html::e((string) ((int) $item['result_percent']) . '%') ?></div></div>
    <?php endif; ?>
  </div>

  <div class="section-title">Предмет</div>
  <div class="card" style="margin-bottom:1rem"><?= nl2br(Html::e($subjectText !== '' ? $subjectText : '—')) ?></div>

  <?php if ($notesText !== ''): ?>
    <div class="section-title">Примечания</div>
    <div class="card" style="margin-bottom:1rem"><?= nl2br(Html::e($notesText)) ?></div>
  <?php endif; ?>

  <?php if ($archivePathText !== ''): ?>
    <div class="section-title">Архивный путь</div>
    <div class="card" style="margin-bottom:1rem"><span class="td-num"><?= Html::e($archivePathText) ?></span></div>
  <?php endif; ?>

  <div class="section-title">Исполнители</div>
  <?php if (!$assignees): ?>
    <div class="text-muted">Не назначены</div>
  <?php else: ?>
    <div class="table-wrap" style="margin-bottom:1rem">
      <table>
        <thead><tr><th>ФИО</th><th>Роль</th><th>Primary</th></tr></thead>
        <tbody>
        <?php foreach ($assignees as $a): ?>
          <?php $role = CaseAssigneeRole::tryFrom((string) ($a['role'] ?? '')); ?>
          <tr>
            <td><?= Html::e((string) ($a['full_name'] ?? '—')) ?></td>
            <td><?= Html::e($role ? $role->label() : (string) ($a['role'] ?? '—')) ?></td>
            <td><?= !empty($a['is_primary']) ? 'Да' : 'Нет' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($attributes): ?>
    <div class="section-title">Атрибуты</div>
    <div class="table-wrap" style="margin-bottom:1rem">
      <table>
        <thead><tr><th>Ключ</th><th>Значение</th><th>Число</th><th>Дата</th></tr></thead>
        <tbody>
        <?php foreach ($attributes as $k => $v): ?>
          <tr>
            <td class="td-num"><?= Html::e((string) $k) ?></td>
            <td><?= Html::e((string) ($v['value'] ?? '—')) ?></td>
            <td class="td-num"><?= Html::e((string) ($v['num'] ?? '—')) ?></td>
            <td><?= Html::date((string) ($v['date'] ?? null)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($events): ?>
    <div class="section-title">События</div>
    <div class="table-wrap" style="margin-bottom:1rem">
      <table>
        <thead><tr><th>Дата</th><th>Тип</th><th>Сумма</th><th>Текст</th><th>Создано</th></tr></thead>
        <tbody>
        <?php foreach ($events as $e): ?>
          <?php $eventType = CaseEventType::tryFrom((string) ($e['event_type'] ?? '')); ?>
          <tr>
            <td><?= Html::date((string) ($e['event_date'] ?? null)) ?></td>
            <td><?= Html::e($eventType ? $eventType->label() : (string) ($e['event_type'] ?? '—')) ?></td>
            <td><?= Html::money($e['amount'] ?? null) ?></td>
            <td><?= Html::e((string) ($e['text'] ?? '—')) ?></td>
            <td class="text-muted"><?= Html::date((string) ($e['created_at'] ?? null), 'd.m.Y H:i') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($files): ?>
    <div class="section-title">Файлы</div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Файл</th><th>MIME</th><th>Размер</th><th>Загружен</th></tr></thead>
        <tbody>
        <?php foreach ($files as $f): ?>
          <tr>
            <td><?= Html::e((string) ($f['file_name'] ?? '—')) ?></td>
            <td class="td-num"><?= Html::e((string) ($f['mime_type'] ?? '—')) ?></td>
            <td><?= isset($f['size_bytes']) ? Html::fileSize((int) $f['size_bytes']) : '—' ?></td>
            <td class="text-muted"><?= Html::date((string) ($f['uploaded_at'] ?? null), 'd.m.Y H:i') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($isAdmin): ?>
    <details class="case-tech">
      <summary>Технические данные</summary>
      <div class="case-tech__grid">
        <div class="di"><div class="di__label">ID</div><div class="di__value td-num"><?= Html::e((string) $item['id']) ?></div></div>
        <?php if ($bundleKey !== ''): ?>
          <div class="di"><div class="di__label">Bundle key</div><div class="di__value td-num"><?= Html::e($bundleKey) ?></div></div>
        <?php endif; ?>
        <?php if ($procurementFormRaw !== '' && $procurementFormRaw !== $procurementFormLabel): ?>
          <div class="di"><div class="di__label">Форма закупки (raw)</div><div class="di__value td-num"><?= Html::e($procurementFormRaw) ?></div></div>
        <?php endif; ?>
        <?php if ($stageRaw !== '' && $stageRaw !== $stageLabel): ?>
          <div class="di"><div class="di__label">Этап (raw)</div><div class="di__value td-num"><?= Html::e($stageRaw) ?></div></div>
        <?php endif; ?>
      </div>
    </details>

    <div style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--border)">
      <form method="post" action="/cases/<?= Html::e((string) $item['id']) ?>/delete">
        <?= $csrf->field() ?>
        <button type="submit" class="btn btn--danger btn--sm" data-confirm="Удалить карточку дела со всеми событиями и файлами?">🗑 Удалить карточку дела</button>
      </form>
    </div>
  <?php endif; ?>
</div>
