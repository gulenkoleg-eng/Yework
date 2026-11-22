<?php
use App\Core\Resumes;
$full = $acc['level'] === 'full';
$prof = $r['profile'];
$age = Resumes::age($prof['birth_year'] !== null ? (int) $prof['birth_year'] : null);
$isOwner = $acc['reason'] === 'owner';
?>
<nav class="crumbs"><a href="<?= url('/') ?>">Головна</a>›<a href="<?= url('/resumes') ?>">Резюме</a>›<?= e($r['title']) ?></nav>
<?php if (!Resumes::isPublic($r)): ?><div class="alert warn" style="margin-bottom:14px">Статус: <b><?= e(Resumes::STATUS[$r['status']][0] ?? $r['status']) ?><?= $r['is_hidden'] ? ', приховано з пошуку' : '' ?></b>. <?= $isOwner ? 'Так резюме бачитимуть роботодавці після публікації.' : 'Резюме зараз не показується в пошуку.' ?></div><?php endif ?>
<?php if ($isOwner): $pvis = Resumes::vis($prof, 'photo'); $tvis = Resumes::vis($prof, 'phone'); ?><div class="alert info" style="margin-bottom:14px">Це ваше резюме. Фото: <b><?= e(mb_strtolower(Resumes::VISIBILITY[$pvis])) ?></b>, телефон: <b><?= e(mb_strtolower(Resumes::VISIBILITY[$tvis])) ?></b>.<?= Resumes::openToAll($prof) ? ' Повне резюме бачать усі відвідувачі.' : ' Гості бачать лише посаду й місто.' ?> <a href="<?= url('/cabinet/resume') ?>#person">Змінити</a></div><?php endif ?>
<div class="rz">
  <article class="card rz-main">
    <div class="rz-top">
      <div class="stack" style="gap:6px">
        <?php if ($full): ?><h1 class="rz-name"><?= e($r['name']) ?></h1><?php endif ?>
        <div class="rz-title"><?= e($r['title']) ?></div>
        <div class="facts">
          <?php if ($prof['settlement']): ?><span class="tag"><?= icon('pin', 14) ?><?= e($prof['settlement']) ?></span><?php endif ?>
          <span class="tag"><?= icon('clock', 14) ?><?= e(EMPLOYMENT_TYPES[$r['employment']] ?? '') ?></span>
          <?php if ($full && $age): ?><span class="tag"><?= e($age) ?></span><?php endif ?>
          <?php if ($r['category']): ?><span class="tag"><?= e($r['category']) ?></span><?php endif ?>
          <?php if ($r['no_experience'] && !$r['experience']): ?><span class="tag ok">Шукаю першу роботу</span><?php endif ?>
        </div>
        <div class="sal" style="font-size:20px;font-weight:800"><?= $r['salary_from'] ? 'від ' . money((int) $r['salary_from']) . ' грн/міс' : 'Зарплата за домовленістю' ?></div>
      </div>
      <?php if ($full): ?><div class="rz-ph"><?php if ($acc['photo']): ?><img src="<?= e(Resumes::photoUrl($prof)) ?>" alt="Фото: <?= e($r['name']) ?>"><?php else: ?><?= icon('user', 48) ?><?php endif ?></div><?php endif ?>
    </div>

    <?php if (!$full): ?>
      <div class="rz-lock">
        <b>Повне резюме бачать роботодавці</b>
        <p class="small muted" style="margin:0">Ім’я, фото, досвід, освіта та PDF доступні після входу з профілем роботодавця. Так ми захищаємо людей, які шукають роботу.</p>
        <div><?php if ($me): ?><a class="btn pri" href="<?= url('/cabinet/employer') ?>">Заповнити профіль роботодавця</a><?php else: ?><a class="btn pri" href="<?= url('/login', ['next' => '/resumes/' . $r['id']]) ?>">Увійти</a> <a class="btn" href="<?= url('/register') ?>">Реєстрація</a><?php endif ?></div>
      </div>
    <?php else: ?>
      <?php if ($r['experience']): ?><section class="stack" style="gap:0"><h3 style="font-size:18px;margin-bottom:6px">Досвід роботи</h3>
        <?php foreach ($r['experience'] as $x): $period = trim(($x['from'] ?? '') . (($x['from'] ?? '') !== '' ? ' — ' . (($x['to'] ?? '') !== '' ? $x['to'] : 'тепер') : (string) ($x['to'] ?? ''))); ?>
          <div class="rz-item"><div class="rz-item-h"><b><?= e($x['position'] ?? '') ?></b><span class="small muted"><?= e($period) ?></span></div>
            <?php if (($x['place'] ?? '') !== ''): ?><div class="small muted"><?= e($x['place']) ?></div><?php endif ?>
            <?php if (($x['duties'] ?? '') !== ''): ?><div class="prose small"><?= e($x['duties']) ?></div><?php endif ?></div>
        <?php endforeach ?></section><?php endif ?>
      <?php if ($r['education']): ?><section class="stack" style="gap:0"><h3 style="font-size:18px;margin-bottom:6px">Освіта</h3>
        <?php foreach ($r['education'] as $x): ?>
          <div class="rz-item"><div class="rz-item-h"><b><?= e($x['place'] ?? '') ?></b><span class="small muted"><?= e($x['year'] ?? '') ?></span></div><?php if (($x['specialty'] ?? '') !== ''): ?><div class="small muted"><?= e($x['specialty']) ?></div><?php endif ?></div>
        <?php endforeach ?></section><?php endif ?>
      <?php if (trim((string) $r['skills']) !== '' || $r['driving']): ?><section class="prose"><h3>Навички</h3><?= e($r['skills']) ?><?= $r['driving'] ? (trim((string) $r['skills']) !== '' ? "\n" : '') . 'Посвідчення водія: ' . e(str_replace(',', ', ', $r['driving'])) : '' ?></section><?php endif ?>
      <?php if (trim((string) $r['about']) !== ''): ?><section class="prose"><h3>Про себе</h3><?= e($r['about']) ?></section><?php endif ?>
    <?php endif ?>
    <p class="small muted">Оновлено <?= e(ago($r['updated_at'])) ?><?php if ($isOwner || $acc['reason'] === 'moderator'): ?> · <?= icon('eye', 13) ?> <?= (int) $r['views'] ?> переглядів роботодавцями<?php endif ?></p>
  </article>

  <aside class="card side-card">
    <?php if ($full): ?>
      <?php if ($contacts && $acc['reason'] === 'public'): ?>
        <b>Контакти</b>
        <button type="button" class="btn pri block" id="showPhone" data-url="<?= url('/resumes/' . $r['id'] . '/phone') ?>"><?= icon('phone') ?>Показати номер</button>
        <script>document.getElementById('showPhone').onclick=function(){var b=this,m=document.cookie.match(/(?:^|; )yw_csrf=([^;]*)/);b.disabled=true;
          fetch(b.dataset.url,{method:'POST',headers:{'Accept':'application/json','X-CSRF':m?decodeURIComponent(m[1]):''},credentials:'same-origin'}).then(function(r){return r.json()}).then(function(d){
            if(!d.phone){b.disabled=false;b.textContent=d.message||'Помилка';return}var a=document.createElement('a');a.className='btn pri block';a.href='tel:'+d.phone;a.textContent=d.pretty;b.replaceWith(a)}).catch(function(){b.disabled=false})};</script>
      <?php elseif ($contacts): ?>
        <b>Контакти</b>
        <?php if (!empty($contacts['phone'])): ?><a class="btn block" href="tel:<?= e($contacts['phone']) ?>"><?= icon('phone') ?><?= e(phone_pretty($contacts['phone'])) ?></a><?php endif ?>
        <?php if (!empty($contacts['email'])): ?><a class="btn block" href="mailto:<?= e($contacts['email']) ?>"><?= e($contacts['email']) ?></a><?php endif ?>
      <?php elseif (!$isOwner && $acc['reason'] === 'public'): ?>
        <p class="small muted" style="margin:0">Щоб зв’язатися з кандидатом, увійдіть як роботодавець і надішліть запрошення.</p>
      <?php elseif (!$isOwner): ?>
        <p class="small muted" style="margin:0">Кандидат приховав контакти. Надішліть запрошення: людина отримає його на пошту або SMS і зв’яжеться з вами.</p>
      <?php endif ?>
      <a class="btn block" href="<?= url($isOwner ? '/cabinet/resume/pdf' : '/resumes/' . $r['id'] . '/pdf') ?>" target="_blank" rel="noopener">Відкрити PDF</a>
      <a class="btn ghost block" href="<?= url($isOwner ? '/cabinet/resume/pdf' : '/resumes/' . $r['id'] . '/pdf', ['dl' => 1]) ?>">Завантажити PDF</a>
      <?php if ($employer && !$isOwner): ?>
        <form id="invite" method="post" action="<?= url('/resumes/' . $r['id'] . '/invite') ?>" class="stack" style="gap:10px;margin-top:6px"><?= csrf_field() ?>
          <b>Запросити на роботу</b>
          <?php if ($lastInvite && strtotime($lastInvite . ' UTC') > time() - 7 * 86400): ?><div class="alert ok small">Ви вже надіслали запрошення <?= e(ago($lastInvite)) ?>. Повторно можна через тиждень.</div>
          <?php else: ?>
            <?php if ($vacancies): ?><select class="input" name="vacancy_id" aria-label="Вакансія"><option value="">Без прив’язки до вакансії</option><?php foreach ($vacancies as $v): ?><option value="<?= (int) $v['id'] ?>"><?= e($v['title']) ?></option><?php endforeach ?></select><?php endif ?>
            <textarea class="input" name="message" rows="4" maxlength="1500" required placeholder="Доброго дня! Шукаємо… Умови… Чекаємо на дзвінок." aria-label="Повідомлення"></textarea>
            <input class="input" name="contact" maxlength="120" required value="<?= e($myPhone ? phone_pretty($myPhone) : ($vacancies[0]['contact_phone'] ?? '')) ?>" placeholder="Ваш телефон для зв’язку" aria-label="Контакт для зв’язку">
            <button class="btn pri block">Надіслати запрошення</button>
            <span class="small muted">Від імені «<?= e($employer['name']) ?>». Кандидат побачить повідомлення й ваш контакт.</span>
          <?php endif ?>
        </form>
      <?php endif ?>
    <?php else: ?>
      <b>Шукаєте працівника?</b>
      <p class="small muted" style="margin:0">Створіть профіль роботодавця, щоб бачити повні резюме й запрошувати кандидатів.</p>
      <a class="btn pri block" href="<?= url($me ? '/cabinet/employer' : '/register') ?>"><?= $me ? 'Профіль роботодавця' : 'Зареєструватися' ?></a>
    <?php endif ?>
  </aside>
</div>
