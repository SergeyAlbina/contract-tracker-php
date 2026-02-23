<?php
use App\Shared\Enum\CaseBlockType;
use App\Shared\Enum\CaseResultStatus;
use App\Shared\Utils\Html;

/** @var array $item */

$block = CaseBlockType::tryFrom((string) ($item['block_type'] ?? ''));
$status = CaseResultStatus::tryFrom((string) ($item['result_status'] ?? ''));
$attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : [];
$assignees = is_array($item['assignees'] ?? null) ? $item['assignees'] : [];
$events = is_array($item['events'] ?? null) ? $item['events'] : [];
$files = is_array($item['files'] ?? null) ? $item['files'] : [];
?>

<div class="page-head">
  <h1>📘 Дело: <?= Html::e((string) ($item['case_code'] ?: ('#' . $item['id']))) ?></h1>
  <div class="flex gap-sm">
    <a href="/cases/registry" class="btn btn--ghost">← К реестру</a>
  </div>
</div>

<div class="card">
  <div class="card__head">
    <div class="card__title">Общие данные</div>
  </div>

  <div class="detail-grid">
    <div class="di"><div class="di__label">ID</div><div class="di__value td-num"><?= Html::e((string) $item['id']) ?></div></div>
    <div class="di">
      <div class="di__label">Блок</div>
      <div class="di__value">
        <?= $block ? Html::badge($block->value, $block->label()) : '<span class="text-muted">—</span>' ?>
      </div>
    </div>
    <div class="di"><div class="di__label">Год</div><div class="di__value"><?= Html::e((string) ($item['year'] ?? '—')) ?></div></div>
    <div class="di"><div class="di__label">Рег. №</div><div class="di__value"><?= Html::e((string) ($item['reg_no'] ?? '—')) ?></div></div>
    <div class="di">
      <div class="di__label">Статус</div>
      <div class="di__value">
        <?= $status ? Html::badge($status->value, $status->label()) : '<span class="text-muted">—</span>' ?>
      </div>
    </div>
    <div class="di"><div class="di__label">Просрочено</div><div class="di__value <?= !empty($item['is_overdue']) ? 'text-rose' : 'text-muted' ?>"><?= !empty($item['is_overdue']) ? 'Да' : 'Нет' ?></div></div>
    <div class="di"><div class="di__label">Дата задачи</div><div class="di__value"><?= Html::date((string) ($item['task_date'] ?? null)) ?></div></div>
    <div class="di"><div class="di__label">Срок</div><div class="di__value"><?= Html::date((string) ($item['due_date'] ?? null)) ?></div></div>
    <div class="di"><div class="di__label">Код дела</div><div class="di__value"><?= Html::e((string) ($item['case_code'] ?? '—')) ?></div></div>
    <div class="di"><div class="di__label">Форма закупки</div><div class="di__value"><?= Html::e((string) ($item['procurement_form'] ?? '—')) ?></div></div>
    <div class="di"><div class="di__label">Статья</div><div class="di__value"><?= Html::e((string) ($item['budget_article'] ?? '—')) ?></div></div>
    <div class="di"><div class="di__label">Этап</div><div class="di__value"><?= Html::e((string) ($item['stage_raw'] ?? '—')) ?></div></div>
    <div class="di"><div class="di__label">Плановая сумма</div><div class="di__value"><?= Html::money($item['amount_planned'] ?? null) ?></div></div>
    <div class="di"><div class="di__label">РНМЦК</div><div class="di__value"><?= Html::money($item['rnmc_amount'] ?? null) ?></div></div>
    <div class="di"><div class="di__label">Результат сумма</div><div class="di__value"><?= Html::money($item['result_amount'] ?? null) ?></div></div>
    <div class="di"><div class="di__label">Результат %</div><div class="di__value"><?= Html::e((string) (($item['result_percent'] ?? null) !== null ? ((int) $item['result_percent']) . '%' : '—')) ?></div></div>
    <div class="di"><div class="di__label">Контракт</div><div class="di__value"><?= Html::e((string) ($item['contract_number'] ?? '—')) ?></div></div>
    <div class="di"><div class="di__label">Дата контракта</div><div class="di__value"><?= Html::date((string) ($item['contract_date'] ?? null)) ?></div></div>
    <div class="di"><div class="di__label">Сумма контракта</div><div class="di__value"><?= Html::money($item['contract_amount'] ?? null) ?></div></div>
    <div class="di"><div class="di__label">Bundle key</div><div class="di__value td-num"><?= Html::e((string) ($item['bundle_key'] ?? '—')) ?></div></div>
  </div>

  <div class="section-title">Предмет</div>
  <div class="card" style="margin-bottom:1rem"><?= nl2br(Html::e((string) ($item['subject_raw'] ?? '—'))) ?></div>

  <div class="section-title">Примечания</div>
  <div class="card" style="margin-bottom:1rem"><?= nl2br(Html::e((string) ($item['notes'] ?? '—'))) ?></div>

  <div class="section-title">Архивный путь</div>
  <div class="card" style="margin-bottom:1rem"><span class="td-num"><?= Html::e((string) ($item['archive_path'] ?? '—')) ?></span></div>

  <div class="section-title">Исполнители</div>
  <?php if (!$assignees): ?>
    <div class="text-muted">Не назначены</div>
  <?php else: ?>
    <div class="table-wrap" style="margin-bottom:1rem">
      <table>
        <thead><tr><th>ФИО</th><th>Роль</th><th>Primary</th></tr></thead>
        <tbody>
        <?php foreach ($assignees as $a): ?>
          <tr>
            <td><?= Html::e((string) ($a['full_name'] ?? '—')) ?></td>
            <td><?= Html::e((string) ($a['role'] ?? '—')) ?></td>
            <td><?= !empty($a['is_primary']) ? 'Да' : 'Нет' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <div class="section-title">Атрибуты</div>
  <?php if (!$attributes): ?>
    <div class="text-muted">Нет атрибутов</div>
  <?php else: ?>
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

  <div class="section-title">События</div>
  <?php if (!$events): ?>
    <div class="text-muted">Нет событий</div>
  <?php else: ?>
    <div class="table-wrap" style="margin-bottom:1rem">
      <table>
        <thead><tr><th>Дата</th><th>Тип</th><th>Сумма</th><th>Текст</th><th>Создано</th></tr></thead>
        <tbody>
        <?php foreach ($events as $e): ?>
          <tr>
            <td><?= Html::date((string) ($e['event_date'] ?? null)) ?></td>
            <td><?= Html::e((string) ($e['event_type'] ?? '—')) ?></td>
            <td><?= Html::money($e['amount'] ?? null) ?></td>
            <td><?= Html::e((string) ($e['text'] ?? '—')) ?></td>
            <td class="text-muted"><?= Html::date((string) ($e['created_at'] ?? null), 'd.m.Y H:i') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <div class="section-title">Файлы</div>
  <?php if (!$files): ?>
    <div class="text-muted">Нет файлов</div>
  <?php else: ?>
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
</div>
