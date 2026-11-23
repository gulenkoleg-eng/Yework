<?php $site = App\Core\App::setting('site.name', 'YEWORK');
$side ??= [];
// Бокові банери (0.5.19): лише на комп’ютері, колонкою праворуч, яка лишається на місці під час прокрутки (0.5.21.2). На телефоні їх немає. ?>
<div class="home<?= $side ? ' has-side' : '' ?>"><div class="home-main">
<section class="hero">
  <h1>Робота поруч з домом. <b>Є робота!</b></h1>
  <p class="lead">Вакансії, підробіток і послуги в Миронівці, Богуславі та Кагарлику. Без посередників і зайвих кроків.</p>
  <form class="search" action="<?= url('/jobs') ?>" method="get" role="search">
    <input class="input" name="q" placeholder="Посада, компанія або ключове слово" aria-label="Пошук">
    <select class="input" name="settlement" aria-label="Населений пункт"><option value="">Усі громади</option><?php foreach ($settlements as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach ?></select>
    <button class="btn pri"><?= icon('search') ?>Знайти</button>
  </form>
</section>

<section class="stats" aria-label="Статистика сайту">
  <a class="card stat go" href="<?= url('/jobs', ['today' => 1]) ?>"><b><?= (int) $stats['vacanciesToday'] ?></b><span>нових вакансій сьогодні</span><i><?= icon('arrow', 16) ?></i></a>
  <a class="card stat go" href="<?= url('/employers') ?>"><b><?= (int) $stats['employersHiring'] ?></b><span>роботодавців шукають працівників</span><i><?= icon('arrow', 16) ?></i></a>
  <div class="card stat"><b><?= (int) $stats['visitorsToday'] ?></b><span>відвідувачів сьогодні</span></div>
  <div class="card stat"><b><?= (int) $stats['visitorsMonth'] ?></b><span>відвідувачів за місяць</span></div>
</section>

<?php if (!empty($promos)): ?>
<div class="sec-h"><h2>Шукають працівників</h2><a href="<?= url('/cabinet/ads') ?>">Розмістити свій банер →</a></div>
<section class="bnrs"><?php foreach ($promos as $b) echo App\Core\View::partial('partials/promo', ['b' => $b]) ?></section>
<?php endif ?>

<div class="sec-h"><h2>Три напрямки</h2></div>
<section class="dirs">
  <a class="card dir" href="<?= url('/jobs') ?>"><span class="ic"><?= icon('briefcase', 19) ?></span><h3>Вакансії</h3><p>Постійна робота у місцевих підприємствах, магазинах, школах і фермерських господарствах.</p><span class="small"><b><?= (int) $stats['vacanciesActive'] ?></b> активних</span></a>
  <a class="card dir" href="<?= url('/jobs', ['employment' => 'one_time']) ?>"><span class="ic"><?= icon('bolt', 19) ?></span><h3>Підробіток</h3><p>Разова робота на день чи тиждень: допомога на городі, переїзд, сезонні роботи.</p><span class="small muted">Окремий розділ з’явиться найближчим часом</span></a>
  <a class="card dir" href="<?= url('/services') ?>"><span class="ic"><?= icon('tool', 19) ?></span><h3>Послуги</h3><p>Майстри поруч: кондиціонери, відеонагляд, ремонт, сантехніка, електрика. Ціни, фото робіт і відгуки.</p><span class="small"><?= ($mc = App\Core\Services::liveCount()) ? '<b>' . $mc . '</b> ' . e(mb_substr(plural($mc, 'майстер', 'майстри', 'майстрів'), mb_strlen((string) $mc) + 1)) : 'Додайте себе першим →' ?></span></a>
</section>

<div class="sec-h"><h2>Категорії</h2><a href="<?= url('/jobs') ?>">Усі вакансії →</a></div>
<section class="cats">
  <?php foreach ($categories as $c): ?><a class="card cat" href="<?= url('/jobs', ['category' => $c['slug']]) ?>"><span><?= e($c['name']) ?></span><em><?= (int) $c['cnt'] ?></em></a><?php endforeach ?>
</section>

<div class="sec-h"><h2>Нові вакансії</h2><a href="<?= url('/jobs') ?>">Дивитися всі →</a></div>
<?php if ($latest): ?>
  <section class="jlist"><?php foreach ($latest as $j) echo App\Core\View::partial('partials/job_card', ['j' => $j]) ?></section>
<?php else: ?>
  <div class="card empty"><h3>Тут з’являться перші вакансії</h3><p class="muted">Ви роботодавець? Розмістіть вакансію безкоштовно, і її побачать люди з вашої громади.</p><a class="btn pri" href="<?= url('/cabinet/vacancies/new') ?>"><?= icon('plus') ?>Розмістити вакансію</a></div>
<?php endif ?>
</div><?php if ($side): ?>
<aside class="side-bnrs side-col" aria-label="Реклама роботодавців" data-seen="<?= url('/promo/seen') ?>" data-ids="<?= e(implode(',', array_map(static fn ($b) => (int) $b['id'], $side))) ?>">
  <div class="side-bnrs-h"><span>Також шукають</span></div>
  <div class="side-bnrs-list"><?php foreach ($side as $b) echo App\Core\View::partial('partials/promo', ['b' => $b, 'side' => true]) ?></div>
  <a class="side-bnrs-more" href="<?= url('/cabinet/ads') ?>">Розмістити свій банер →</a>
</aside>
<script>(function(a){if(!a||!window.matchMedia||!matchMedia('(min-width:1280px)').matches)return;try{fetch(a.dataset.seen+(a.dataset.seen.indexOf('?')<0?'?':'&')+'ids='+encodeURIComponent(a.dataset.ids),{credentials:'same-origin',keepalive:true})}catch(e){}})(document.currentScript.previousElementSibling)</script>
<?php endif ?></div>
