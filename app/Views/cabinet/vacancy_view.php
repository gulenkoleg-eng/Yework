<?php use App\Core\Vacancies as VC; $VS = VC::STATUS; ?>
<div class="cab">
  <?= App\Core\View::partial('partials/cab_nav', compact('tab', 'counts', 'me')) ?>
  <div class="stack">
    <div class="row"><h1 style="font-size:26px"><?= e($v['title']) ?></h1><span class="tag <?= $VS[$v['status']][1] ?>"><?= $VS[$v['status']][0] ?></span></div>
    <?= App\Core\View::partial('partials/notices', ['notices' => $notices ?? [], 'back' => '/cabinet/vacancies/' . $v['id']]) ?>
    <?php if ($v['status'] === 'rejected'): ?><div class="alert bad">Модератор відхилив вакансію: <?= e($v['reject_reason']) ?>. Виправте її та надішліть ще раз.</div><?php endif ?>
    <?php if ($v['status'] === 'pending'): ?><div class="alert info">Вакансія на перевірці. Ми повідомимо, щойно її опублікують.</div><?php endif ?>
    <div class="row">
      <a class="btn" href="<?= url('/jobs/' . $v['id']) ?>"><?= icon('eye') ?>Переглянути</a>
      <a class="btn" href="<?= url('/cabinet/vacancies/' . $v['id'] . '/edit') ?>">Редагувати</a>
    </div>
    <?php if ($v['status'] === 'scheduled'): ?><div class="alert info">Вакансія чекає на свою дату: на сайті вона з’явиться <?= e(date('d.m.Y', strtotime($v['show_from']))) ?>.</div><?php endif ?>
    <?php if (in_array($v['status'], ['published', 'scheduled'], true)): ?>
      <form class="card pad stack" method="post" action="<?= url('/cabinet/vacancies/' . $v['id'] . '/archive') ?>"><?= csrf_field() ?>
        <div><b>Перенести в архів</b><div class="small muted">Вакансія зникне з сайту, але залишиться у вас в архіві разом із відгуками. Поновити можна будь-коли.</div></div>
        <div class="row"><button class="btn pri" name="reason" value="hired">Знайшов працівника</button><button class="btn" name="reason" value="manual">В архів</button></div>
      </form>
    <?php endif ?>
    <?php if ($v['status'] === 'archived'): ?>
      <div class="alert warn">Вакансія в архіві<?= $v['archive_reason'] ? ': ' . e(mb_strtolower(VC::REASONS[$v['archive_reason']] ?? '')) : '' ?>. На сайті її не видно.</div>
      <form class="card pad stack" method="post" action="<?= url('/cabinet/vacancies/' . $v['id'] . '/restore') ?>"><?= csrf_field() ?>
        <div><b>Поновити вакансію</b><div class="small muted">Виберіть новий період показу (до <?= VC::MAX_DAYS ?> днів). Якщо текст не змінювати, вакансія з’явиться одразу, без повторної перевірки.</div></div>
        <?= form_alert() ?>
        <div class="grid2">
          <?= input_field('show_from', 'Показувати з', ['type' => 'date', 'value' => VC::today()]) ?>
          <?= input_field('show_to', 'по (включно)', ['type' => 'date', 'value' => VC::plusDays(VC::today(), VC::DEFAULT_DAYS - 1)]) ?>
        </div>
        <div class="row"><button class="btn pri">Поновити</button></div>
      </form>
    <?php endif ?>
    <div class="card pad"><dl class="kv"><dt>Населений пункт</dt><dd><?= e($v['settlement']) ?></dd><dt>Зарплата</dt><dd><?= e(salary_text($v['salary_from'] !== null ? (int) $v['salary_from'] : null, $v['salary_to'] !== null ? (int) $v['salary_to'] : null, $v['salary_period'])) ?></dd><dt>Переглядів</dt><dd><?= (int) $v['views'] ?></dd><?php if ($v['show_from'] && $v['show_to']): ?><dt>Термін показу</dt><dd>з <?= e(date('d.m.Y', strtotime($v['show_from']))) ?> по <?= e(date('d.m.Y', strtotime($v['show_to']))) ?></dd><?php endif ?></dl></div>
    <h2 style="font-size:20px">Відгуки (<?= count($apps) ?>)</h2>
    <?php if (!$apps): ?><div class="card empty"><p class="muted">Відгуків поки немає. Вони з’являться тут разом із контактами кандидатів.</p></div><?php else: ?>
      <div class="card list"><?php foreach ($apps as $a): ?>
        <div class="stack" style="gap:6px"><div class="row"><b><?= e($a['display_name']) ?></b><span class="small muted"><?= e(ago($a['created_at'])) ?></span></div>
          <div class="row small"><?php if ($a['phone']): ?><a href="tel:<?= e($a['phone']) ?>"><?= icon('phone', 14) ?> <?= e(phone_pretty($a['phone'])) ?></a><?php endif ?><?php if ($a['email']): ?><a href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a><?php endif ?></div>
          <?php if ($a['resume_id']): ?><div class="row small"><a class="btn sm" href="<?= url('/resumes/' . $a['resume_id']) ?>">Резюме</a><a class="btn sm ghost" href="<?= url('/resumes/' . $a['resume_id'] . '/pdf', ['dl' => 1]) ?>">Завантажити PDF</a></div><?php endif ?>
          <?php if (trim((string) $a['message']) !== ''): ?><p class="prose small"><?= e($a['message']) ?></p><?php endif ?></div>
      <?php endforeach ?></div>
    <?php endif ?>
  </div>
</div>
