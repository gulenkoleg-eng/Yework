<?php /* Модерація вакансій (0.5.22): компактний список із фільтрами, деталі розгортаються, «Редагувати» для адміністрації. */
use App\Controllers\Admin\AdminController as AC;
$backQ = (string) (parse_url(AC::modUrl($f), PHP_URL_QUERY) ?? '') ?: 'status=' . $status;
$modHere = url('/admin/moderation') . '?' . $backQ;
$filtered = $f['q'] !== '' || $f['cat'] || $f['place'] !== '' || $f['emp'] || $f['author'];
$tabs = ['pending' => 'Чекають', 'published' => 'Опубліковані', 'rejected' => 'Відхилені'];
$byCommunity = [];
foreach ($places as $pl) $byCommunity[$pl['community_id']][] = $pl; ?>
<div class="adm-h"><h1>Модерація вакансій</h1><span class="spacer"></span>
  <div class="seg"><?php foreach ($tabs as $k => $l): ?><a class="<?= $status === $k ? 'on' : '' ?>" href="<?= AC::modUrl($f, ['status' => $k, 'page' => 1]) ?>"><?= $l ?> <span class="mq-n"><?= (int) $counts[$k] ?></span></a><?php endforeach ?></div>
</div>

<form class="card mq-filters" method="get" action="<?= url('/admin/moderation') ?>">
  <?php if ($status !== 'pending'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif ?>
  <label class="mq-search"><?= icon('search', 16) ?><input class="input" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Посада, компанія, ЄДРПОУ, автор або № вакансії"></label>
  <select class="input" name="cat" onchange="this.form.submit()" aria-label="Категорія">
    <option value="">Усі категорії</option>
    <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $f['cat'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?>
  </select>
  <select class="input" name="place" onchange="this.form.submit()" aria-label="Громада або населений пункт">
    <option value="">Усі громади й села</option>
    <?php foreach ($byCommunity as $cid => $list): ?><optgroup label="<?= e($list[0]['community']) ?> громада">
      <option value="c:<?= (int) $cid ?>"<?= $f['place'] === "c:$cid" ? ' selected' : '' ?>>Уся громада</option>
      <?php foreach ($list as $pl): ?><option value="s:<?= (int) $pl['id'] ?>"<?= $f['place'] === 's:' . $pl['id'] ? ' selected' : '' ?>><?= e($pl['name']) ?></option><?php endforeach ?>
    </optgroup><?php endforeach ?>
  </select>
  <select class="input" name="emp" onchange="this.form.submit()" aria-label="Роботодавець">
    <option value="">Усі роботодавці</option>
    <?php foreach ($employers as $em): ?><option value="<?= (int) $em['id'] ?>"<?= $f['emp'] === (int) $em['id'] ? ' selected' : '' ?>><?= e(employer_name($em['legal_form'], $em['name'])) ?> (<?= (int) $em['n'] ?>)</option><?php endforeach ?>
  </select>
  <select class="input" name="author" onchange="this.form.submit()" aria-label="Власник (автор)">
    <option value="">Усі власники</option>
    <?php foreach ($authors as $au): ?><option value="<?= (int) $au['id'] ?>"<?= $f['author'] === (int) $au['id'] ? ' selected' : '' ?>><?= e($au['name']) ?> (<?= (int) $au['n'] ?>)</option><?php endforeach ?>
  </select>
  <button class="btn sm pri">Знайти</button>
  <?php if ($filtered): ?><a class="btn sm ghost" href="<?= AC::modUrl(['status' => $status]) ?>"><?= icon('x', 15) ?>Скинути</a><?php endif ?>
</form>

<?php if (!$rows): ?>
<div class="card empty"><h3><?= $filtered ? 'Нічого не знайдено' : ($status === 'pending' ? 'Черга порожня' : 'Нічого немає') ?></h3><p class="muted"><?= $filtered ? 'Змініть або скиньте фільтри.' : ($status === 'pending' ? 'Усі вакансії перевірено. Нові з’являться тут автоматично.' : '') ?></p></div>
<?php else: ?>
<div class="card mq">
  <div class="mq-head"><span>Вакансія</span><span>Категорія · місце</span><span>Зарплата</span><span>Власник</span><span><?= $status === 'pending' ? 'Надіслано' : 'Перевірено' ?></span><span></span></div>
  <?php foreach ($rows as $v): $own = (int) $v['author_id'] === (int) $me['id'];
    $when = $status === 'pending' ? $v['submitted_at'] : ($v['moderated_at'] ?: $v['submitted_at']);
    $edit = url('/admin/vacancies/' . $v['id'] . '/edit', ['back' => $backQ]); ?>
  <details class="mq-row">
    <summary>
      <span class="mq-main"><b class="mq-title"><?= e($v['title']) ?></b>
        <span class="small muted"><?= e(employer_name($v['legal_form'], $v['employer'])) ?><?= $v['is_verified'] ? ' <span class="mq-ok" title="Перевірений роботодавець">✓</span>' : '' ?><?= $v['edrpou'] ? ' · ЄДРПОУ ' . e($v['edrpou']) : '' ?></span></span>
      <span class="small mq-cell"><span><?= e($v['category']) ?></span><span class="muted"><?= e($v['settlement']) ?></span></span>
      <span class="small mq-cell"><b><?= e(salary_text($v['salary_from'] !== null ? (int) $v['salary_from'] : null, $v['salary_to'] !== null ? (int) $v['salary_to'] : null, $v['salary_period'])) ?></b><span class="muted"><?= e(EMPLOYMENT_TYPES[$v['employment']] ?? '') ?></span></span>
      <span class="small mq-cell"><span><?= e($v['author']) ?></span><?php if ($v['author_rejected']): ?><span class="muted"><span class="tag bad" title="Відхилених вакансій цього власника раніше"><?= (int) $v['author_rejected'] ?> відхилено</span></span><?php endif ?></span>
      <span class="small muted mq-when" title="<?= e(local_date($when)) ?>"><?= e(ago($when)) ?></span>
      <span class="mq-act">
        <?php if ($v['status'] === 'pending' && !$own): ?><form method="post" action="<?= url('/admin/moderation/' . $v['id']) ?>"><?= csrf_field() ?><input type="hidden" name="decision" value="approve"><input type="hidden" name="back" value="<?= e($backQ) ?>"><button class="btn sm ok" title="Опублікувати"><?= icon('check', 15) ?><span class="mq-lbl">Опублікувати</span></button></form><?php endif ?>
        <?php if ($canVerify): ?><?= verify_toggle((int) $v['employer_id'], (bool) $v['is_verified'], $modHere, true) ?><?php endif ?>
        <?php if ($canEdit): ?><a class="btn sm" href="<?= $edit ?>" title="Редагувати вакансію"><?= icon('tool', 15) ?><span class="mq-lbl">Редагувати</span></a><?php endif ?>
        <span class="mq-chev" aria-hidden="true"></span>
      </span>
    </summary>
    <div class="mq-body">
      <div class="small muted mq-meta">
        <span>№ <?= (int) $v['id'] ?></span>
        <?php if ($v['contact_phone']): ?><span><?= icon('phone', 14) ?> <?= e(phone_pretty($v['contact_phone'])) ?><?= $v['contact_name'] ? ' · ' . e($v['contact_name']) : '' ?></span><?php endif ?>
        <?php if (!empty($v['address'])): ?><span><?= icon('pin', 14) ?> <?= e($v['address']) ?></span><?php endif ?>
        <?php if (!empty($v['show_from']) && !empty($v['show_to'])): ?><span><?= icon('clock', 14) ?> показ <?= e(local_date($v['show_from'], 'd.m')) ?>–<?= e(local_date($v['show_to'], 'd.m.Y')) ?></span><?php endif ?>
        <?php if ($v['no_experience']): ?><span>можна без досвіду</span><?php endif ?>
        <a href="<?= url('/jobs/' . $v['id']) ?>" target="_blank"><?= icon('eye', 14) ?> на сайті</a>
        <a href="<?= url('/admin/users/' . $v['author_id']) ?>"><?= icon('user', 14) ?> картка власника</a>
        <a href="<?= AC::modUrl($f, ['author' => (int) $v['author_id'], 'page' => 1]) ?>">усі вакансії власника</a>
        <a href="<?= AC::modUrl($f, ['emp' => (int) $v['employer_id'], 'page' => 1]) ?>">усі вакансії компанії</a>
      </div>
      <div class="prose"><?= e($v['description']) ?><?= trim((string) $v['requirements']) !== '' ? "\n\nВимоги:\n" . e($v['requirements']) : '' ?><?= trim((string) $v['offers']) !== '' ? "\n\nПропонуємо:\n" . e($v['offers']) : '' ?></div>
      <?php if ($v['status'] === 'rejected'): ?><div class="alert bad">Причина відхилення: <?= e($v['reject_reason']) ?></div><?php endif ?>
      <?php if ($own): ?><div class="alert info">Це ваша вакансія — її має перевірити інший модератор.</div>
      <?php else: ?>
      <div class="mq-do">
        <?php if ($v['status'] !== 'rejected'): ?>
        <form method="post" action="<?= url('/admin/moderation/' . $v['id']) ?>" class="mq-reject"><?= csrf_field() ?><input type="hidden" name="decision" value="reject"><input type="hidden" name="back" value="<?= e($backQ) ?>">
          <input class="input" name="reason" placeholder="Причина відхилення (побачить роботодавець)" list="reasons" required minlength="5"><button class="btn sm bad">Відхилити</button></form>
        <?php endif ?>
        <?php if ($canVerify): ?><?= verify_toggle((int) $v['employer_id'], (bool) $v['is_verified'], $modHere) ?><?php endif ?>
      </div>
      <?php endif ?>
    </div>
  </details>
  <?php endforeach ?>
</div>
<?php if ($pages > 1): ?><nav class="pager"><?php for ($i = 1; $i <= $pages; $i++): ?><?= $i === $f['page'] ? "<span class=\"on\">$i</span>" : '<a href="' . AC::modUrl($f, ['page' => $i]) . "\">$i</a>" ?><?php endfor ?></nav><?php endif ?>
<p class="small muted" style="text-align:center;margin-top:10px">Показано <?= count($rows) ?> з <?= (int) $total ?>. Натисніть на рядок, щоб прочитати опис і відхилити.</p>
<?php endif ?>
<datalist id="reasons"><option value="Не вказано обов’язки або умови роботи"><option value="Підозра на шахрайство або передоплату"><option value="Дублікат вакансії"><option value="Контакти або посилання в описі"><option value="Дискримінаційні вимоги"></datalist>
