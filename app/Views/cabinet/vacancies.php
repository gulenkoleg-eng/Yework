<?php use App\Core\Vacancies as VC; $VS = VC::STATUS; ?>
<div class="cab">
  <?= App\Core\View::partial('partials/cab_nav', compact('tab', 'counts', 'me')) ?>
  <div class="stack">
    <div class="row"><h1 style="font-size:28px">Мої вакансії</h1><span class="spacer"></span><a class="btn pri" href="<?= url('/cabinet/vacancies/new') ?>"><?= icon('plus') ?>Нова вакансія</a></div>
    <?php if ($nActive || $nArchived): ?><div class="tabs"><a class="<?= $archive ? '' : 'on' ?>" href="<?= url('/cabinet/vacancies') ?>">Активні (<?= $nActive ?>)</a><a class="<?= $archive ? 'on' : '' ?>" href="<?= url('/cabinet/vacancies', ['tab' => 'archive']) ?>">Архів (<?= $nArchived ?>)</a></div><?php endif ?>
    <?php if (!$rows && $archive): ?>
      <div class="card empty"><h3>Архів порожній</h3><p class="muted">Сюди потрапляють вакансії, у яких закінчився термін показу або які ви перенесли в архів самі. Звідси їх можна поновити.</p></div>
    <?php elseif (!$rows): ?>
      <div class="card empty"><h3><?= $nArchived ? 'Активних вакансій немає' : 'Вакансій ще немає' ?></h3><p class="muted"><?= $nArchived ? 'Розмістіть нову вакансію або поновіть одну з архіву.' : 'Розмістіть першу вакансію. Це безкоштовно і займає кілька хвилин.' ?></p><a class="btn pri" href="<?= url('/cabinet/vacancies/new') ?>">Розмістити вакансію</a></div>
    <?php else: ?>
      <div class="card list">
        <?php foreach ($rows as $r): ?>
          <div class="row" style="justify-content:space-between">
            <div style="min-width:0"><a href="<?= url('/cabinet/vacancies/' . $r['id']) ?>"><b><?= e($r['title']) ?></b></a><div class="small muted"><?= e($r['settlement']) ?> · оновлено <?= e(ago($r['updated_at'])) ?> · <?= (int) $r['views'] ?> переглядів</div><?php if (in_array($r['status'], ['published', 'scheduled'], true) && $r['show_to']): ?><div class="small muted"><?= $r['status'] === 'scheduled' ? 'Показ з ' . e(date('d.m', strtotime($r['show_from']))) . ' по ' : 'Показується до ' ?><?= e(date('d.m.Y', strtotime($r['show_to']))) ?></div><?php endif ?><?php if ($r['status'] === 'archived'): ?><div class="small muted"><?= e(VC::REASONS[$r['archive_reason'] ?? 'manual'] ?? 'Знято вручну') ?><?= $r['archived_at'] ? ' · ' . e(local_date($r['archived_at'], 'd.m.Y')) : '' ?></div><?php endif ?><?php if ($r['status'] === 'rejected' && $r['reject_reason']): ?><div class="small" style="color:var(--c-danger)">Причина: <?= e($r['reject_reason']) ?></div><?php endif ?></div>
            <div class="row"><span class="tag <?= $VS[$r['status']][1] ?>"><?= $VS[$r['status']][0] ?></span><?php if ($r['apps']): ?><span class="tag"><?= plural((int) $r['apps'], 'відгук', 'відгуки', 'відгуків') ?></span><?php endif ?></div>
          </div>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </div>
</div>
