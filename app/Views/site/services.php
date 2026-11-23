<?php
$q = fn (array $over) => url('/services', array_merge(array_filter(['q' => $f['q'], 'category' => $f['category'], 'settlement' => $f['settlement'] ?: null, 'sort' => $f['sort'] !== 'rating' ? $f['sort'] : null]), $over));
$allCnt = array_sum(array_map(static fn ($c) => (int) $c['cnt'], $cats));
?>
<div class="row" style="margin-bottom:12px"><h1 style="font-size:30px"><?= $cat ? e($cat['name']) : 'Послуги майстрів' ?></h1><span class="muted"><?= plural($total, 'майстер', 'майстри', 'майстрів') ?></span>
  <span class="spacer"></span>
  <a class="btn sm" href="<?= url('/cabinet/master') ?>"><?= icon('plus', 16) ?><?= $mine ? 'Мій профіль майстра' : 'Я майстер — додати себе' ?></a>
</div>
<nav class="svc-cats" aria-label="Категорії послуг">
  <a class="<?= $cat ? '' : 'on' ?>" href="<?= $q(['category' => null, 'page' => null]) ?>">Усі</a>
  <?php foreach ($cats as $c): ?><a class="<?= $cat && $cat['id'] === $c['id'] ? 'on' : '' ?>" href="<?= $q(['category' => $c['slug'], 'page' => null]) ?>"><?= e($c['name']) ?><?php if ((int) $c['cnt']): ?><em><?= (int) $c['cnt'] ?></em><?php endif ?></a><?php endforeach ?>
</nav>
<?php
$chips = [];
$drop = fn (string $k) => $q([$k => null, 'page' => null]);
if ($f['settlement']) foreach ($settlements as $s) if ((int) $s['id'] === $f['settlement']) $chips[] = ['label' => $s['name'], 'href' => $drop('settlement')];
if ($f['sort'] === 'new') $chips[] = ['label' => 'Нові майстри', 'href' => $drop('sort')];
if ($f['sort'] === 'price') $chips[] = ['label' => 'Дешевші', 'href' => $drop('sort')];
echo App\Core\View::partial('partials/mfilter', ['action' => '/services', 'qVal' => $f['q'], 'ph' => 'Кондиціонер, камери, плитка…', 'chips' => $chips,
    'keep' => ['category' => $f['category'] ?: null, 'settlement' => $f['settlement'] ?: null, 'sort' => $f['sort'] !== 'rating' ? $f['sort'] : null],
    'reset' => url('/services', ['category' => $f['category'] ?: null])]);
?>
<div class="jobs-layout">
  <form class="card filters" id="flt" method="get" action="<?= url('/services') ?>">
    <?= App\Core\View::partial('partials/sheet_head', []) ?>
    <?php if ($cat): ?><input type="hidden" name="category" value="<?= e($cat['slug']) ?>"><?php endif ?>
    <div class="field f-q"><label for="q">Пошук</label><input id="q" class="input" name="q" value="<?= e($f['q']) ?>" placeholder="Кондиціонер, камери, плитка…"></div>
    <div class="field"><label for="st">Населений пункт</label><select id="st" class="input" name="settlement"><option value="">Усі громади</option><?php foreach ($settlements as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $f['settlement'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach ?></select></div>
    <div class="field"><label for="so">Спочатку</label><select id="so" class="input" name="sort"><option value="rating">З кращими відгуками</option><option value="new" <?= $f['sort'] === 'new' ? 'selected' : '' ?>>Нові майстри</option><option value="price" <?= $f['sort'] === 'price' ? 'selected' : '' ?>>Дешевші</option></select></div>
    <button class="btn pri block">Показати</button>
    <?php if ($f['q'] !== '' || $f['settlement'] || $f['sort'] !== 'rating'): ?><a class="btn ghost block" href="<?= url('/services', ['category' => $f['category'] ?: null]) ?>">Скинути фільтри</a><?php endif ?>
    <p class="small muted" style="margin:0">Зірки ставлять лише клієнти, які замовили майстра через сайт і отримали роботу. Так відгуки чесні.</p>
  </form>
  <div>
    <?php if ($items): ?>
      <div class="mlist"><?php foreach ($items as $x) echo App\Core\View::partial('partials/master_card', ['x' => $x]) ?></div>
      <?php $pages = (int) ceil($total / App\Controllers\ServicesController::PAGE); if ($pages > 1): ?>
        <nav class="pager" aria-label="Сторінки"><?php for ($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++): ?><?= $i === $page ? "<span class=\"on\">$i</span>" : '<a href="' . $q(['page' => $i]) . "\">$i</a>" ?><?php endfor ?></nav>
      <?php endif ?>
    <?php elseif (!$allCnt): ?>
      <div class="card empty"><h3>Розділ щойно відкрився</h3><p class="muted">Тут з’являться майстри з Миронівської, Богуславської та Кагарлицької громад: ціни, фото робіт і відгуки. Ви майстер? Додайте себе першим — це безкоштовно.</p><a class="btn pri" href="<?= url('/cabinet/master') ?>"><?= icon('plus') ?>Додати свої послуги</a></div>
    <?php else: ?>
      <div class="card empty"><h3>Нічого не знайдено</h3><p class="muted">Спробуйте іншу категорію або приберіть частину фільтрів.</p><a class="btn" href="<?= url('/services') ?>">Усі майстри</a></div>
    <?php endif ?>
  </div>
</div>
