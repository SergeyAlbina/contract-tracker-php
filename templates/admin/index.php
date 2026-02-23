<?php
use App\Shared\Utils\Html;
use App\Shared\Enum\CaseBlockType;

/** @var int $usersTotal @var int $usersActive @var int $contractsTotal @var int $procurementsTotal @var int $casesTotal */
/** @var int $duplicatesRows @var int $duplicateBundles @var array<string,int> $caseCounts @var array<CaseBlockType> $blockTypes */
?>

<div class="page-head">
  <h1>
    <svg class="ico" viewBox="0 0 24 24" aria-hidden="true">
      <circle cx="12" cy="12" r="3"></circle>
      <path d="M19.4 15a1 1 0 0 0 .2 1.1l.1.1a1.8 1.8 0 1 1-2.5 2.5l-.1-.1a1 1 0 0 0-1.1-.2 1 1 0 0 0-.6.9V20a1.8 1.8 0 1 1-3.6 0v-.1a1 1 0 0 0-.6-.9 1 1 0 0 0-1.1.2l-.1.1a1.8 1.8 0 1 1-2.5-2.5l.1-.1a1 1 0 0 0 .2-1.1 1 1 0 0 0-.9-.6H4a1.8 1.8 0 1 1 0-3.6h.1a1 1 0 0 0 .9-.6 1 1 0 0 0-.2-1.1l-.1-.1a1.8 1.8 0 1 1 2.5-2.5l.1.1a1 1 0 0 0 1.1.2h.1a1 1 0 0 0 .6-.9V4a1.8 1.8 0 1 1 3.6 0v.1a1 1 0 0 0 .6.9h.1a1 1 0 0 0 1.1-.2l.1-.1a1.8 1.8 0 1 1 2.5 2.5l-.1.1a1 1 0 0 0-.2 1.1v.1a1 1 0 0 0 .9.6H20a1.8 1.8 0 1 1 0 3.6h-.1a1 1 0 0 0-.9.6z"></path>
    </svg>
    Администрирование
  </h1>
</div>

<div class="admin-grid">
  <article class="card admin-card">
    <div class="admin-card__title">Пользователи</div>
    <div class="admin-card__meta">Всего: <?= (int) $usersTotal ?> · Активных: <?= (int) $usersActive ?></div>
    <div class="admin-card__actions">
      <a href="/users" class="btn btn--primary btn--sm">Открыть пользователей</a>
      <a href="/profile/password" class="btn btn--ghost btn--sm">Сменить мой пароль</a>
    </div>
  </article>

  <article class="card admin-card">
    <div class="admin-card__title">Аудит</div>
    <div class="admin-card__meta">Журнал изменений по всем сущностям</div>
    <div class="admin-card__actions">
      <a href="/audit" class="btn btn--primary btn--sm">Открыть аудит</a>
    </div>
  </article>
</div>

<div class="card mt-2">
  <div class="card__head">
    <div class="card__title">Состояние данных</div>
  </div>
  <div class="admin-stats">
    <div class="admin-stat"><span>Закупки</span><strong><?= (int) $procurementsTotal ?></strong></div>
    <div class="admin-stat"><span>Контракты</span><strong><?= (int) $contractsTotal ?></strong></div>
    <div class="admin-stat"><span>Дела</span><strong><?= (int) $casesTotal ?></strong></div>
    <div class="admin-stat"><span>Дубли строк</span><strong><?= (int) $duplicatesRows ?></strong></div>
    <div class="admin-stat"><span>Дубли bundle_key</span><strong><?= (int) $duplicateBundles ?></strong></div>
  </div>
  <div class="table-wrap mt-2">
    <table>
      <thead>
        <tr>
          <th>Блок дел</th>
          <th style="text-align:right">Количество</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($blockTypes as $block): ?>
          <?php $count = (int) ($caseCounts[$block->value] ?? 0); ?>
          <tr>
            <td><?= Html::e($block->label()) ?></td>
            <td class="td-num" style="text-align:right"><?= $count ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
