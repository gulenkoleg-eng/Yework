<div class="adm-h"><h1>Модерація вакансій</h1><span class="spacer"></span>
  <div class="seg"><?php foreach (['pending' => 'Чекають', 'published' => 'Опубліковані', 'rejected' => 'Відхилені'] as $k => $l): ?><a class="<?= $status === $k ? 'on' : '' ?>" href="<?= url('/admin/moderation', ['status' => $k === 'pending' ? null : $k]) ?>"><?= $l ?></a><?php endforeach ?></div>
</div>
<?php if (!$rows): ?><div class="card empty"><h3><?= $status === 'pending' ? 'Черга порожня' : 'Нічого немає' ?></h3><p class="muted"><?= $status === 'pending' ? 'Усі вакансії перевірено. Нові з’являться тут автоматично.' : '' ?></p></div><?php endif ?>
<?php foreach ($rows as $v): $own = (int) $v['author_id'] === (int) $me['id']; ?>
  <article class="card mod">
    <div class="mod-h">
      <div><h3 style="font-size:19px"><?= e($v['title']) ?></h3><div class="small muted"><?= e($v['employer']) ?><?= $v['is_verified'] ? ' · ✓ перевірений' : '' ?><?= $v['edrpou'] ? ' · ЄДРПОУ ' . e($v['edrpou']) : '' ?> · <?= e($v['settlement']) ?> · <?= e($v['category']) ?></div></div>
      <div class="small muted" style="text-align:right">Автор: <a href="<?= url('/admin/users/' . $v['author_id']) ?>"><?= e($v['author']) ?></a><?php if ($v['author_rejected']): ?><br><span class="tag bad"><?= (int) $v['author_rejected'] ?> відхилених раніше</span><?php endif ?><br><?= e(ago($v['submitted_at'])) ?></div>
    </div>
    <div class="row small"><b><?= e(salary_text($v['salary_from'] !== null ? (int) $v['salary_from'] : null, $v['salary_to'] !== null ? (int) $v['salary_to'] : null, $v['salary_period'])) ?></b> · <?= e(EMPLOYMENT_TYPES[$v['employment']] ?? '') ?><?= $v['contact_phone'] ? ' · ' . e(phone_pretty($v['contact_phone'])) : '' ?></div>
    <div class="prose"><?= e($v['description']) ?><?= trim((string) $v['requirements']) !== '' ? "\n\nВимоги:\n" . e($v['requirements']) : '' ?><?= trim((string) $v['offers']) !== '' ? "\n\nПропонуємо:\n" . e($v['offers']) : '' ?></div>
    <?php if ($v['status'] === 'rejected'): ?><div class="alert bad">Причина: <?= e($v['reject_reason']) ?></div><?php endif ?>
    <?php if ($own): ?><div class="alert info">Це ваша вакансія — її має перевірити інший модератор.</div>
    <?php else: ?>
    <div class="row">
      <?php if ($v['status'] === 'pending'): ?><form method="post" action="<?= url('/admin/moderation/' . $v['id']) ?>"><?= csrf_field() ?><input type="hidden" name="decision" value="approve"><button class="btn ok">✓ Опублікувати</button></form><?php endif ?>
      <?php if ($v['status'] !== 'rejected'): ?>
      <form method="post" action="<?= url('/admin/moderation/' . $v['id']) ?>" class="row" style="flex:1;min-width:280px"><?= csrf_field() ?><input type="hidden" name="decision" value="reject"><input type="hidden" name="back" value="<?= e($status === 'pending' ? '' : $status) ?>">
        <input class="input" name="reason" placeholder="Причина відхилення (побачить роботодавець)" style="flex:1;min-height:44px" list="reasons"><button class="btn bad">Відхилити</button></form>
      <?php endif ?>
      <?php if (!$v['is_verified'] && App\Core\App::auth()->can('identity.user:update')): ?><form method="post" action="<?= url('/admin/moderation/' . $v['id']) ?>"><?= csrf_field() ?><input type="hidden" name="decision" value="verify"><button class="btn sm ghost">Позначити роботодавця перевіреним</button></form><?php endif ?>
    </div>
    <?php endif ?>
  </article>
<?php endforeach ?>
<datalist id="reasons"><option value="Не вказано обов’язки або умови роботи"><option value="Підозра на шахрайство або передоплату"><option value="Дублікат вакансії"><option value="Контакти або посилання в описі"><option value="Дискримінаційні вимоги"></datalist>
