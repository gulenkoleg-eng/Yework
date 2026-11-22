<?php use App\Core\Ads; $L = Ads::PRICE_LABELS; ?>
<div class="adm-h"><h1>Монетизація</h1><span class="tag <?= $on ? 'ok' : 'muted' ?>"><?= $on ? 'Увімкнено' : 'Вимкнено — усе безкоштовно' ?></span></div>
<div class="kpis">
  <a class="card kpi" href="<?= url('/admin/banners', ['status' => 'approved']) ?>"><b><?= (int) $k['live'] ?></b><span>банерів показується зараз</span></a>
  <a class="card kpi<?= $k['pending'] ? ' attn' : '' ?>" href="<?= url('/admin/banners') ?>"><b><?= (int) $k['pending'] ?></b><span>банерів чекають модерації</span></a>
  <div class="card kpi"><b><?= (int) $k['unpaid'] ?></b><span>схвалених банерів чекають оплати</span></div>
  <div class="card kpi"><b><?= (int) $k['withLogo'] ?> з <?= (int) $k['employers'] ?></b><span>роботодавців мають логотип</span></div>
</div>

<form class="card pad stack" method="post" action="<?= url('/admin/billing') ?>"><?= csrf_field() ?><input type="hidden" name="section" value="toggle">
  <h3>Платні послуги</h3>
  <?php if ($on): ?>
    <p>Монетизацію <b>увімкнено</b>. Нові банери замовляються з ціною за прайсом нижче й показуються після того, як фінансовий адміністратор позначить оплату. Уже замовлені безкоштовні банери діють до кінця свого періоду.</p>
  <?php else: ?>
    <p>Зараз <b>усе безкоштовно</b>: банери замовляються без оплати, а в кабінеті роботодавця кнопки «Купити» і «Виставити рахунок» сірі з позначкою «Безкоштовно на період розвитку». Ціни нижче роботодавці бачать закресленими.</p>
  <?php endif ?>
  <?php if ($canToggle): ?>
    <input type="hidden" name="enabled" value="<?= $on ? '0' : '1' ?>">
    <div><button class="btn <?= $on ? '' : 'pri' ?>" onclick="return confirm('<?= $on ? 'Вимкнути монетизацію? Нові банери знову стануть безкоштовними.' : 'Увімкнути монетизацію? Нові банери стануть платними.' ?>')"><?= $on ? 'Вимкнути монетизацію' : 'Увімкнути монетизацію' ?></button></div>
  <?php else: ?>
    <p class="small muted">Вмикати й вимикати монетизацію може лише той, кому головний адміністратор окремо видав критичне право «Увімкнення та вимкнення монетизації».</p>
  <?php endif ?>
</form>

<form class="card pad stack" method="post" action="<?= url('/admin/billing') ?>"><?= csrf_field() ?><input type="hidden" name="section" value="prices">
  <h3>Ціни</h3>
  <p class="small muted">Стартові ціни розраховані на невеликі громади: тиждень банера на головній коштує як оголошення в районній газеті (≈190 грн), місяць — ≈675 грн, дешевше за одну платну вакансію на великих сайтах. Публікація вакансій лишається безкоштовною завжди.</p>
  <fieldset <?= $canEdit ? '' : 'disabled' ?> class="stack" style="border:0;padding:0;margin:0">
    <div class="gridp">
      <?php foreach (['banner_home_day', 'banner_jobs_day', 'banner_both_day'] as $key): ?><?= input_field($key, $L[$key], ['value' => $prices[$key], 'inputmode' => 'numeric']) ?><?php endforeach ?>
    </div>
    <div class="gridp">
      <?= input_field('discount_week', $L['discount_week'], ['value' => $prices['discount_week'], 'inputmode' => 'numeric']) ?>
      <?= input_field('discount_month', $L['discount_month'], ['value' => $prices['discount_month'], 'inputmode' => 'numeric']) ?>
      <?= input_field('slots', 'Банерів одночасно в одному місці', ['value' => $slots, 'inputmode' => 'numeric', 'hint' => 'Від 1 до 10. Коли місця зайняті, на ці дні замовити не можна']) ?>
    </div>
    <p class="small"><b>Приклад:</b> банер на головній на 7 днів — <?= money(Ads::bannerPrice('home', 7)) ?> грн, на 30 днів — <?= money(Ads::bannerPrice('home', 30)) ?> грн; в обох місцях на 30 днів — <?= money(Ads::bannerPrice('both', 30)) ?> грн.</p>
    <h3 style="margin-top:6px">Послуги для вакансій <span class="tag muted">скоро</span></h3>
    <div class="gridp">
      <?php foreach (['vacancy_bump', 'vacancy_highlight', 'vacancy_urgent'] as $key): ?><?= input_field($key, $L[$key], ['value' => $prices[$key], 'inputmode' => 'numeric']) ?><?php endforeach ?>
    </div>
    <h3 style="margin-top:6px">Послуги майстрів</h3>
    <p class="small muted">Скільки об’єктів із фото майстер додає в портфоліо безкоштовно. Більше об’єктів і підняття в каталозі — майбутні платні опції: поки монетизацію вимкнено, майстри бачать їх сірими з позначкою «Скоро».</p>
    <div class="gridp">
      <?= input_field('svc_works', 'Безкоштовно об’єктів у портфоліо', ['value' => $svcLimits['works'], 'inputmode' => 'numeric', 'hint' => 'Від 0 до 50. Зараз ' . $svcLimits['works']]) ?>
      <?= input_field('svc_photos', 'Фото в одному об’єкті', ['value' => $svcLimits['photos'], 'inputmode' => 'numeric', 'hint' => 'Від 1 до 20']) ?>
    </div>
    <div class="gridp">
      <?php foreach (['master_more_works', 'master_top'] as $key): ?><?= input_field($key, $L[$key], ['value' => $prices[$key], 'inputmode' => 'numeric']) ?><?php endforeach ?>
    </div>
    <h3 style="margin-top:6px">Реквізити для рахунків</h3>
    <p class="small muted">Потрібні, коли ввімкнете монетизацію: з них роботодавець отримує рахунок на оплату банера.</p>
    <div class="grid2"><?= input_field('req_name', 'Отримувач (ФОП або підприємство)', ['value' => $req['name'] ?? '', 'placeholder' => 'ФОП Іваненко І. І.']) ?><?= input_field('req_code', 'ЄДРПОУ / РНОКПП', ['value' => $req['code'] ?? '', 'inputmode' => 'numeric']) ?></div>
    <div class="grid2"><?= input_field('req_iban', 'IBAN', ['value' => $req['iban'] ?? '', 'placeholder' => 'UA00 0000 0000 0000 0000 0000 000']) ?><?= input_field('req_bank', 'Банк', ['value' => $req['bank'] ?? '']) ?></div>
    <?= input_field('req_note', 'Примітка в рахунку', ['value' => $req['note'] ?? '', 'placeholder' => 'Платник єдиного податку 3 групи, без ПДВ']) ?>
    <?php if ($canEdit): ?><div><button class="btn pri">Зберегти ціни</button></div><?php else: ?><p class="small muted">Змінювати ціни може фінансовий або старший адміністратор.</p><?php endif ?>
  </fieldset>
</form>
