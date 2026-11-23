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

<?php $S = App\Core\Sale::class; $sOld = App\Core\App::$old; $sEvent = $sOld['event'] ?? $sale['event']; $sRun = $S::running($sale); ?>
<form class="card pad stack sale-admin" method="post" action="<?= url('/admin/billing') ?>"><?= csrf_field() ?><input type="hidden" name="section" value="sale">
  <div class="row" style="justify-content:space-between"><h3>🎉 Святкова акція-заставка</h3><?= $sRun ? '<span class="tag ok">Зараз на сайті</span>' : ($sale['enabled'] && $sale['from'] > date('Y-m-d') ? '<span class="tag">Почнеться ' . e(date('d.m', strtotime($sale['from']))) . '</span>' : '<span class="tag muted">Вимкнено</span>') ?></div>
  <p class="small muted">При вході на сайт на кілька секунд з’являється яскрава анімована заставка з назвою свята і знижкою. Хто встиг натиснути на неї, отримує знижку на рекламу (банери) до кінця акції: вона видна в кабінеті й сама враховується у вартості. Заставка показується одній людині не частіше разу на день, не заважає пошуковим системам, а для тих, хто вимкнув анімацію на телефоні чи комп’ютері, вона без руху.</p>
  <?php if (!$on): ?><div class="alert warn"><div>Зараз монетизацію вимкнено і реклама безкоштовна. Заставку можна показувати й так: знижка запам’ятається за людиною і спрацює, щойно банери стануть платними (якщо акція ще триватиме). Люди побачать про це підказку.</div></div><?php endif ?>
  <?= form_alert() ?>
  <fieldset <?= $canEdit ? '' : 'disabled' ?> class="stack" style="border:0;padding:0;margin:0">
    <label class="check"><input type="checkbox" name="enabled" value="1" <?= ($sOld ? !empty($sOld['enabled']) : $sale['enabled']) ? 'checked' : '' ?>> <b>Показувати заставку в дні акції</b></label>
    <fieldset class="field"><legend>Подія</legend><div class="row" style="flex-wrap:wrap;gap:6px 18px">
      <?php foreach ($S::EVENTS as $k => [$label, $def]): ?><label class="check"><input type="radio" name="event" value="<?= $k ?>" data-def="<?= e($def) ?>" <?= $sEvent === $k ? 'checked' : '' ?>> <?= e($label) ?></label><?php endforeach ?>
    </div></fieldset>
    <div class="grid2">
      <?= input_field('title', 'Великий напис', ['value' => $sale['title'], 'placeholder' => $S::EVENTS[$sEvent][1], 'maxlength' => 40, 'hint' => 'Порожньо — напис події, як у підказці в полі. Наприклад: «КІБЕРПОНЕДІЛОК» чи «ДЕНЬ НАРОДЖЕННЯ САЙТУ»']) ?>
      <?= input_field('subtitle', 'Підпис під знижкою', ['value' => $sale['subtitle'], 'placeholder' => $S::EVENTS[$sEvent][2], 'maxlength' => 90]) ?>
    </div>
    <div class="gridp">
      <?= input_field('percent', 'Знижка, %', ['value' => $sale['percent'], 'inputmode' => 'numeric', 'hint' => 'Від 1 до 90. Додається до знижки за 7 чи 30 днів']) ?>
      <?= input_field('from', 'Початок акції', ['type' => 'date', 'value' => $sale['from'] ?: date('Y-m-d')]) ?>
      <?= input_field('to', 'Кінець акції (включно)', ['type' => 'date', 'value' => $sale['to'] ?: date('Y-m-d', strtotime('+6 days'))]) ?>
      <?= input_field('seconds', 'Скільки показувати', ['type' => 'select', 'value' => $sale['seconds'], 'options' => $S::SECONDS, 'hint' => 'За цей час треба встигнути натиснути']) ?>
    </div>
    <?php if ($sale['id'] !== ''): ?><p class="small">Цю акцію впіймали: <b><?= plural((int) $saleStats['claims'], 'людина', 'людини', 'людей') ?></b>, банерів зі знижкою замовлено: <b><?= (int) $saleStats['used'] ?></b>. Якщо змінити подію, відсоток чи дати, почнеться нова акція і впіймати знижку можна буде знову. Уже впіймані знижки діють до кінця своєї акції, навіть якщо заставку вимкнути.</p><?php endif ?>
    <div class="row"><?php if ($canEdit): ?><button class="btn pri">Зберегти акцію</button><?php endif ?><a class="btn" href="<?= url('/', ['sale_preview' => 1]) ?>" target="_blank">Переглянути заставку</a><span class="small muted">Перегляд показує збережений варіант і знижку не дає.</span></div>
    <?php if (!$canEdit): ?><p class="small muted">Змінювати акцію може фінансовий або старший адміністратор.</p><?php endif ?>
  </fieldset>
</form>
<script>
(function(){var f=document.querySelector('.sale-admin');if(!f)return;var t=f.querySelector('[name=title]');f.addEventListener('change',function(e){if(e.target.name==='event'&&t)t.placeholder=e.target.dataset.def})})();
</script>

<section class="card pad stack">
  <h3>Зайнятість рекламних місць</h3>
  <?= App\Core\View::partial('partials/ad_places', ['occupancy' => $occupancy]) ?>
</section>

<form class="card pad stack" method="post" action="<?= url('/admin/billing') ?>"><?= csrf_field() ?><input type="hidden" name="section" value="prices">
  <h3>Ціни</h3>
  <p class="small muted">Стартові ціни розраховані на невеликі громади: тиждень банера на головній коштує як оголошення в районній газеті (≈190 грн), місяць — ≈675 грн, дешевше за одну платну вакансію на великих сайтах. Публікація вакансій лишається безкоштовною завжди.</p>
  <fieldset <?= $canEdit ? '' : 'disabled' ?> class="stack" style="border:0;padding:0;margin:0">
    <div class="gridp">
      <?php foreach (['banner_home_day', 'banner_jobs_day', 'banner_both_day', 'banner_side_day'] as $key): ?><?= input_field($key, $L[$key], ['value' => $prices[$key], 'inputmode' => 'numeric', 'hint' => $key === 'banner_side_day' ? 'Менший банер у колонці праворуч, тому дешевший' : null]) ?><?php endforeach ?>
    </div>
    <div class="gridp">
      <?= input_field('discount_week', $L['discount_week'], ['value' => $prices['discount_week'], 'inputmode' => 'numeric']) ?>
      <?= input_field('discount_month', $L['discount_month'], ['value' => $prices['discount_month'], 'inputmode' => 'numeric']) ?>
    </div>
    <h3 style="margin-top:6px">Скільки банерів одночасно</h3>
    <p class="small muted">Коли всі місця на якісь дні зайняті, роботодавець не зможе замовити банер на ці дні. У формі замовлення він бачить, до якої дати місце зайняте і з якого дня звільниться.</p>
    <div class="gridp">
      <?= input_field('slots', 'Банерів по центру', ['value' => $slots, 'inputmode' => 'numeric', 'hint' => 'Від 1 до 10. На головній у блоці «Шукають працівників» і над списком вакансій. Рекомендуємо 1']) ?>
      <?= input_field('slots_side', 'Банерів збоку на головній', ['value' => $sideSlots, 'inputmode' => 'numeric', 'hint' => 'Від 1 до ' . Ads::MAX_SIDE_SLOTS . '. Колонка праворуч лише на комп’ютері, на телефоні бокових банерів немає. Рекомендуємо 4']) ?>
    </div>
    <p class="small"><b>Приклад:</b> банер на головній на 7 днів — <?= money(Ads::bannerPrice('home', 7)) ?> грн, на 30 днів — <?= money(Ads::bannerPrice('home', 30)) ?> грн; збоку на 7 днів — <?= money(Ads::bannerPrice('side', 7)) ?> грн, на 30 днів — <?= money(Ads::bannerPrice('side', 30)) ?> грн; в обох місцях по центру на 30 днів — <?= money(Ads::bannerPrice('both', 30)) ?> грн.</p>
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
