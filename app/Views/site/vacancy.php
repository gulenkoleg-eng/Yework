<?php $me = App\Core\App::auth()->user(); $st = ['draft' => ['Чернетка', 'muted'], 'pending' => ['На модерації', 'warn'], 'rejected' => ['Відхилено', 'bad'], 'archived' => ['Знято з публікації', 'muted']]; ?>
<nav class="crumbs"><a href="<?= url('/') ?>">Головна</a>›<a href="<?= url('/jobs') ?>">Вакансії</a>›<a href="<?= url('/jobs', ['category' => $v['category_slug']]) ?>"><?= e($v['category']) ?></a></nav>
<?php if ($v['status'] !== 'published'): ?><div class="alert warn" style="margin-bottom:14px">Статус: <b><?= e($st[$v['status']][0] ?? $v['status']) ?></b>. Цю сторінку бачите лише ви<?= $isOwner ? '' : ' (модератор)' ?>.<?= $v['reject_reason'] ? ' Причина: ' . e($v['reject_reason']) : '' ?></div><?php endif ?>
<div class="vac">
  <article class="card vac-main">
    <div class="stack" style="gap:8px">
      <h1 style="font-size:clamp(24px,3.4vw,34px)"><?= e($v['title']) ?></h1>
      <div class="sal"><?= e(salary_text($v['salary_from'] !== null ? (int) $v['salary_from'] : null, $v['salary_to'] !== null ? (int) $v['salary_to'] : null, $v['salary_period'])) ?></div>
    </div>
    <div class="facts">
      <span class="tag"><?= icon('pin', 14) ?><?= e($v['settlement']) ?><?= $v['address'] ? ', ' . e($v['address']) : '' ?></span>
      <span class="tag"><?= icon('clock', 14) ?><?= e(EMPLOYMENT_TYPES[$v['employment']] ?? '') ?></span>
      <span class="tag"><?= e($v['category']) ?></span>
      <?php if ($v['no_experience']): ?><span class="tag ok">Можна без досвіду</span><?php endif ?>
    </div>
    <section class="prose"><h3>Про роботу</h3><?= e($v['description']) ?></section>
    <?php if (trim((string) $v['requirements']) !== ''): ?><section class="prose"><h3>Вимоги</h3><?= e($v['requirements']) ?></section><?php endif ?>
    <?php if (trim((string) $v['offers']) !== ''): ?><section class="prose"><h3>Ми пропонуємо</h3><?= e($v['offers']) ?></section><?php endif ?>
    <p class="small muted">Опубліковано <?= e(ago($v['published_at'])) ?> · <?= icon('eye', 13) ?> <?= (int) $v['views'] ?> переглядів</p>
  </article>
  <aside class="card side-card">
    <?php $en = employer_name($v['legal_form'], $v['employer']); ?>
    <div class="row" style="flex-wrap:nowrap"><?= $v['logo'] ? employer_logo($v['logo'], $en, 56, 'elogo lg') : '<span class="dir" style="padding:0"><span class="ic">' . icon('briefcase', 20) . '</span></span>' ?><div><b><?= e($en) ?></b><?php if ($v['is_verified']): ?><div class="small" style="color:var(--c-success)"><?= icon('shield', 13) ?> Перевірений роботодавець</div><?php endif ?></div></div>
    <?php if ($v['employer_about']): ?><p class="small muted"><?= e(mb_strimwidth($v['employer_about'], 0, 220, '…')) ?></p><?php endif ?>
    <a class="small" href="<?= url('/jobs', ['employer' => $v['employer_id']]) ?>">Усі вакансії роботодавця →</a>
    <?php if ($v['contact_phone']): ?>
      <a class="btn block" href="tel:<?= e($v['contact_phone']) ?>"><?= icon('phone') ?><?= e(phone_pretty($v['contact_phone'])) ?></a>
      <?php if ($v['contact_name']): ?><p class="small muted" style="text-align:center">Контактна особа: <?= e($v['contact_name']) ?></p><?php endif ?>
    <?php endif ?>
    <?php if ($isOwner): ?>
      <a class="btn pri block" href="<?= url('/cabinet/vacancies/' . $v['id']) ?>">Керувати вакансією</a>
    <?php elseif ($applied): ?>
      <div class="alert ok"><?= icon('check') ?> Ви вже відгукнулися</div>
    <?php elseif ($v['status'] === 'published'): ?>
      <?php if ($me): ?>
        <form method="post" action="<?= url('/jobs/' . $v['id'] . '/apply') ?>" class="stack" style="gap:10px"><?= csrf_field() ?>
          <textarea class="input" name="message" rows="3" maxlength="2000" placeholder="Коротко про себе (необов’язково)"></textarea>
          <?php if ($myResume && $myResume['status'] === 'published'): ?>
            <label class="check small"><input type="checkbox" name="with_resume" value="1" checked> <span>Додати моє резюме «<?= e($myResume['title']) ?>». Роботодавець отримає його у PDF.</span></label>
          <?php elseif ($myResume && $myResume['status'] === 'pending'): ?>
            <p class="small muted" style="margin:0">Ваше резюме ще на перевірці, тож цей відгук піде без нього.</p>
          <?php elseif (!$myResume): ?>
            <p class="small muted" style="margin:0"><a href="<?= url('/cabinet/resume') ?>">Створіть резюме</a>, щоб відгукуватися з ним в один клік.</p>
          <?php endif ?>
          <button class="btn pri block"><?= $myResume && $myResume['status'] === 'published' ? 'Відгукнутися з резюме' : 'Відгукнутися' ?></button>
        </form>
      <?php else: ?>
        <a class="btn pri block" href="<?= url('/login', ['next' => '/jobs/' . $v['id']]) ?>">Увійдіть, щоб відгукнутися</a>
      <?php endif ?>
    <?php endif ?>
  </aside>
</div>
<?php if ($similar): ?><div class="sec-h"><h2>Схожі вакансії</h2></div><div class="jlist"><?php foreach ($similar as $j) echo App\Core\View::partial('partials/job_card', ['j' => $j]) ?></div><?php endif ?>
