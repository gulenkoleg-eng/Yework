<?php $VS = ["draft" => ["Чернетка", "muted"], "pending" => ["На модерації", "warn"], "published" => ["Опубліковано", "ok"], "rejected" => ["Відхилено", "bad"], "archived" => ["Знято", "muted"]]; ?>
<div class="cab">
  <?= App\Core\View::partial('partials/cab_nav', compact('tab', 'counts', 'me')) ?>
  <div class="stack">
    <div class="row"><h1 style="font-size:26px"><?= e($v['title']) ?></h1><span class="tag <?= $VS[$v['status']][1] ?>"><?= $VS[$v['status']][0] ?></span></div>
    <?php if ($v['status'] === 'rejected'): ?><div class="alert bad">Модератор відхилив вакансію: <?= e($v['reject_reason']) ?>. Виправте її та надішліть ще раз.</div><?php endif ?>
    <?php if ($v['status'] === 'pending'): ?><div class="alert info">Вакансія на перевірці. Ми повідомимо, щойно її опублікують.</div><?php endif ?>
    <div class="row">
      <a class="btn" href="<?= url('/jobs/' . $v['id']) ?>"><?= icon('eye') ?>Переглянути</a>
      <a class="btn" href="<?= url('/cabinet/vacancies/' . $v['id'] . '/edit') ?>">Редагувати</a>
      <?php if ($v['status'] === 'published'): ?><form method="post" action="<?= url('/cabinet/vacancies/' . $v['id'] . '/archive') ?>"><?= csrf_field() ?><button class="btn bad">Зняти з публікації</button></form><?php endif ?>
    </div>
    <div class="card pad"><dl class="kv"><dt>Населений пункт</dt><dd><?= e($v['settlement']) ?></dd><dt>Зарплата</dt><dd><?= e(salary_text($v['salary_from'] !== null ? (int) $v['salary_from'] : null, $v['salary_to'] !== null ? (int) $v['salary_to'] : null, $v['salary_period'])) ?></dd><dt>Переглядів</dt><dd><?= (int) $v['views'] ?></dd><?php if ($v['expires_at']): ?><dt>Активна до</dt><dd><?= e(local_date($v['expires_at'], 'd.m.Y')) ?></dd><?php endif ?></dl></div>
    <h2 style="font-size:20px">Відгуки (<?= count($apps) ?>)</h2>
    <?php if (!$apps): ?><div class="card empty"><p class="muted">Відгуків поки немає. Вони з’являться тут разом із контактами кандидатів.</p></div><?php else: ?>
      <div class="card list"><?php foreach ($apps as $a): ?>
        <div class="stack" style="gap:6px"><div class="row"><b><?= e($a['display_name']) ?></b><span class="small muted"><?= e(ago($a['created_at'])) ?></span></div>
          <div class="row small"><?php if ($a['phone']): ?><a href="tel:<?= e($a['phone']) ?>"><?= icon('phone', 14) ?> <?= e(phone_pretty($a['phone'])) ?></a><?php endif ?><?php if ($a['email']): ?><a href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a><?php endif ?></div>
          <?php if (trim((string) $a['message']) !== ''): ?><p class="prose small"><?= e($a['message']) ?></p><?php endif ?></div>
      <?php endforeach ?></div>
    <?php endif ?>
  </div>
</div>
