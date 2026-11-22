<?php use App\Core\Resumes; ?>
<link rel="stylesheet" href="<?= asset('css/resume.css') ?>">
<div class="adm-h"><h1>Модерація резюме</h1><span class="spacer"></span>
  <div class="seg"><?php foreach (['pending' => 'Чекають', 'published' => 'Опубліковані', 'rejected' => 'Відхилені'] as $k => $l): ?><a class="<?= $status === $k ? 'on' : '' ?>" href="<?= url('/admin/resumes', ['status' => $k === 'pending' ? null : $k]) ?>"><?= $l ?></a><?php endforeach ?></div>
</div>
<p class="small muted" style="margin:-6px 0 14px">Перевірте, що це справжнє резюме: без реклами, посилань, образ і чужих фото. Фото модератор бачить завжди, навіть якщо людина приховала його від роботодавців.</p>
<?php if (!$rows): ?><div class="card empty"><h3><?= $status === 'pending' ? 'Черга порожня' : 'Нічого немає' ?></h3><p class="muted"><?= $status === 'pending' ? 'Усі резюме перевірено. Нові з’являться тут автоматично.' : '' ?></p></div><?php endif ?>
<?php foreach ($rows as $r): $own = (int) $r['user_id'] === (int) $me['id']; $p = $r['profile']; ?>
  <article class="card mod">
    <div class="mod-h">
      <div class="row" style="flex-wrap:nowrap;align-items:flex-start">
        <span class="rz-ph sm"><?php if ($p['photo_file']): ?><img src="<?= e(Resumes::photoUrl($p)) ?>" alt=""><?php else: ?><?= icon('user', 26) ?><?php endif ?></span>
        <div><h3 style="font-size:19px"><?= e($r['title']) ?></h3>
          <div class="small muted"><?= e($r['name']) ?><?= $p['settlement'] ? ' · ' . e($p['settlement']) : '' ?><?= ($a = Resumes::age($p['birth_year'] !== null ? (int) $p['birth_year'] : null)) ? ' · ' . e($a) : '' ?> · <?= e(EMPLOYMENT_TYPES[$r['employment']] ?? '') ?><?= $r['salary_from'] ? ' · від ' . money((int) $r['salary_from']) . ' грн' : '' ?></div>
          <div class="small muted">Фото: <?= $p['photo_file'] ? e(mb_strtolower(Resumes::VISIBILITY[Resumes::vis($p, 'photo')])) : 'немає' ?> · телефон: <?= e(mb_strtolower(Resumes::VISIBILITY[Resumes::vis($p, 'phone')])) ?><?= $r['is_hidden'] ? ' · приховано власником' : '' ?></div>
        </div>
      </div>
      <div class="small muted" style="text-align:right">Автор: <a href="<?= url('/admin/users/' . $r['user_id']) ?>"><?= e($r['display_name']) ?></a><?php if ($r['author_rejected']): ?><br><span class="tag bad"><?= (int) $r['author_rejected'] ?> відхилень раніше</span><?php endif ?><br><?= e(ago($r['submitted_at'])) ?> · версія <?= (int) $r['version'] ?></div>
    </div>
    <details><summary class="small">Повний текст резюме</summary>
      <div class="prose small"><?php foreach ($r['experience'] as $x): ?><b><?= e($x['position'] ?? '') ?></b> — <?= e($x['place'] ?? '') ?> (<?= e(($x['from'] ?? '') . '–' . ($x['to'] ?? '')) ?>)
<?= e($x['duties'] ?? '') ?>

<?php endforeach ?><?php foreach ($r['education'] as $x): ?>Освіта: <?= e(($x['place'] ?? '') . ', ' . ($x['specialty'] ?? '') . ' ' . ($x['year'] ?? '')) ?>
<?php endforeach ?><?= trim((string) $r['skills']) !== '' ? "\nНавички:\n" . e($r['skills']) : '' ?><?= $r['driving'] ? "\nПосвідчення водія: " . e($r['driving']) : '' ?><?= trim((string) $r['about']) !== '' ? "\n\nПро себе:\n" . e($r['about']) : '' ?></div>
    </details>
    <div class="row small"><a href="<?= url('/resumes/' . $r['id']) ?>" target="_blank" rel="noopener">Сторінка резюме</a><a href="<?= url('/resumes/' . $r['id'] . '/pdf') ?>" target="_blank" rel="noopener">PDF</a></div>
    <?php if ($r['status'] === 'rejected'): ?><div class="alert bad">Причина: <?= e($r['reject_reason']) ?></div><?php endif ?>
    <?php if ($own): ?><div class="alert info">Це ваше резюме — його має перевірити інший модератор.</div>
    <?php else: ?>
    <div class="row">
      <?php if ($r['status'] === 'pending'): ?><form method="post" action="<?= url('/admin/resumes/' . $r['id']) ?>"><?= csrf_field() ?><input type="hidden" name="decision" value="approve"><button class="btn ok">✓ Опублікувати</button></form><?php endif ?>
      <?php if ($r['status'] !== 'rejected'): ?>
      <form method="post" action="<?= url('/admin/resumes/' . $r['id']) ?>" class="row" style="flex:1;min-width:280px"><?= csrf_field() ?><input type="hidden" name="decision" value="reject"><input type="hidden" name="back" value="<?= e($status === 'pending' ? '' : $status) ?>">
        <input class="input" name="reason" placeholder="Причина відхилення (побачить автор)" style="flex:1;min-height:44px" list="rz-reasons"><button class="btn bad">Відхилити</button></form>
      <?php endif ?>
    </div>
    <?php endif ?>
  </article>
<?php endforeach ?>
<datalist id="rz-reasons"><option value="Фото не підходить (не ваше обличчя, чужі люди, неприйнятний зміст)"><option value="Реклама або посилання в тексті резюме"><option value="Заповніть бажану посаду та досвід зрозуміло"><option value="Образливий або неприйнятний зміст"><option value="Схоже на вакансію, а не на резюме"></datalist>
