<?php $VS = ["draft" => ["Чернетка", "muted"], "pending" => ["На модерації", "warn"], "published" => ["Опубліковано", "ok"], "rejected" => ["Відхилено", "bad"], "archived" => ["Знято", "muted"]]; ?>
<div class="cab">
  <?= App\Core\View::partial('partials/cab_nav', compact('tab', 'counts', 'me')) ?>
  <div class="stack">
    <div class="row"><h1 style="font-size:28px">Мої вакансії</h1><span class="spacer"></span><a class="btn pri" href="<?= url('/cabinet/vacancies/new') ?>"><?= icon('plus') ?>Нова вакансія</a></div>
    <?php if (!$rows): ?>
      <div class="card empty"><h3>Вакансій ще немає</h3><p class="muted">Розмістіть першу вакансію. Це безкоштовно і займає кілька хвилин.</p><a class="btn pri" href="<?= url('/cabinet/vacancies/new') ?>">Розмістити вакансію</a></div>
    <?php else: ?>
      <div class="card list">
        <?php foreach ($rows as $r): ?>
          <div class="row" style="justify-content:space-between">
            <div style="min-width:0"><a href="<?= url('/cabinet/vacancies/' . $r['id']) ?>"><b><?= e($r['title']) ?></b></a><div class="small muted"><?= e($r['settlement']) ?> · оновлено <?= e(ago($r['updated_at'])) ?> · <?= (int) $r['views'] ?> переглядів</div><?php if ($r['status'] === 'rejected' && $r['reject_reason']): ?><div class="small" style="color:var(--c-danger)">Причина: <?= e($r['reject_reason']) ?></div><?php endif ?></div>
            <div class="row"><span class="tag <?= $VS[$r['status']][1] ?>"><?= $VS[$r['status']][0] ?></span><?php if ($r['apps']): ?><span class="tag"><?= plural((int) $r['apps'], 'відгук', 'відгуки', 'відгуків') ?></span><?php endif ?></div>
          </div>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </div>
</div>
