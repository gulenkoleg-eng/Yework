<?php $q = fn (array $over) => url('/jobs', array_merge(array_filter(['q' => $f['q'], 'settlement' => $f['settlement'] ?: null, 'category' => $f['category'], 'employment' => $f['employment'], 'salary' => $f['salary'] ?: null, 'noexp' => $f['noexp'] ? 1 : null, 'sort' => $f['sort'] === 'salary' ? 'salary' : null, 'employer' => $f['employer'] ?: null]), $over)); ?>
<?php if (!empty($employer)): $en = employer_name($employer['legal_form'], $employer['name']); ?>
<div class="card emp-head">
  <?= $employer['logo'] ? employer_logo($employer['logo'], $en, 64, 'elogo lg') : '<span class="elogo lg ph">' . e(initials($employer['name'])) . '</span>' ?>
  <div class="stack" style="gap:6px;min-width:0">
    <h1 style="font-size:26px"><?= e($en) ?><?= $employer['is_verified'] ? ' <span class="tag ok">' . icon('shield', 13) . ' Перевірений</span>' : '' ?></h1>
    <?php if ($employerPromo): ?><p><b><?= e($employerPromo['title']) ?>.</b> <?= e($employerPromo['body']) ?></p>
    <?php elseif ($employer['about']): ?><p class="muted"><?= e(mb_strimwidth($employer['about'], 0, 300, '…')) ?></p><?php endif ?>
    <a class="small" href="<?= url('/jobs') ?>">← Усі вакансії</a>
  </div>
</div>
<?php endif ?>
<?php if (!empty($promos)) echo App\Core\View::partial('partials/promo', ['b' => $promos[0], 'wide' => true]) ?>
<div class="row" style="margin-bottom:16px"><h1 style="font-size:30px"><?= !empty($employer) ? 'Вакансії роботодавця' : 'Вакансії' ?></h1><span class="muted"><?= plural($total, 'вакансія', 'вакансії', 'вакансій') ?></span>
  <span class="spacer"></span>
  <div class="seg" style="background:var(--c-secondary)"><a class="<?= $f['sort'] === 'new' ? 'on' : '' ?>" href="<?= $q(['sort' => null, 'page' => null]) ?>">Нові</a><a class="<?= $f['sort'] === 'salary' ? 'on' : '' ?>" href="<?= $q(['sort' => 'salary', 'page' => null]) ?>">Більша зарплата</a></div>
</div>
<?php
$chips = [];
$drop = fn (string $k) => $q([$k => null, 'page' => null]);
if ($f['settlement']) foreach ($settlements as $s) if ((int) $s['id'] === $f['settlement']) $chips[] = ['label' => $s['name'], 'href' => $drop('settlement')];
if ($f['category']) foreach ($categories as $c) if ($c['slug'] === $f['category']) $chips[] = ['label' => $c['name'], 'href' => $drop('category')];
if ($f['employment'] && isset(EMPLOYMENT_TYPES[$f['employment']])) $chips[] = ['label' => EMPLOYMENT_TYPES[$f['employment']], 'href' => $drop('employment')];
if ($f['salary']) $chips[] = ['label' => 'від ' . number_format((int) $f['salary'], 0, '', ' ') . ' грн', 'href' => $drop('salary')];
if ($f['noexp']) $chips[] = ['label' => 'Без досвіду', 'href' => $drop('noexp')];
echo App\Core\View::partial('partials/mfilter', ['action' => '/jobs', 'qVal' => $f['q'], 'ph' => 'Посада або компанія', 'chips' => $chips,
    'keep' => ['settlement' => $f['settlement'] ?: null, 'category' => $f['category'], 'employment' => $f['employment'], 'salary' => $f['salary'] ?: null, 'noexp' => $f['noexp'] ? 1 : null, 'sort' => $f['sort'] === 'salary' ? 'salary' : null, 'employer' => $f['employer'] ?: null],
    'reset' => url('/jobs', ['sort' => $f['sort'] === 'salary' ? 'salary' : null, 'employer' => $f['employer'] ?: null])]);
?>
<div class="jobs-layout">
  <form class="card filters" id="flt" method="get" action="<?= url('/jobs') ?>">
    <?= App\Core\View::partial('partials/sheet_head', []) ?>
    <div class="field f-q"><label for="q">Пошук</label><input id="q" class="input" name="q" value="<?= e($f['q']) ?>" placeholder="Посада або компанія"></div>
    <div class="field"><label for="st">Населений пункт</label><select id="st" class="input" name="settlement"><option value="">Усі громади</option><?php foreach ($settlements as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $f['settlement'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach ?></select></div>
    <div class="field"><label for="cat">Категорія</label><select id="cat" class="input" name="category"><option value="">Усі категорії</option><?php foreach ($categories as $c): ?><option value="<?= e($c['slug']) ?>" <?= $f['category'] === $c['slug'] ? 'selected' : '' ?>><?= e($c['name']) ?> (<?= (int) $c['cnt'] ?>)</option><?php endforeach ?></select></div>
    <div class="field"><label for="emp">Зайнятість</label><select id="emp" class="input" name="employment"><option value="">Будь-яка</option><?php foreach (EMPLOYMENT_TYPES as $k => $v): ?><option value="<?= $k ?>" <?= $f['employment'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach ?></select></div>
    <div class="field"><label for="sal">Зарплата від, грн</label><input id="sal" class="input" name="salary" inputmode="numeric" value="<?= $f['salary'] ?: '' ?>" placeholder="напр. 12000"></div>
    <label class="check"><input type="checkbox" name="noexp" value="1" <?= $f['noexp'] ? 'checked' : '' ?>> Без досвіду</label>
    <?php if ($f['sort'] === 'salary'): ?><input type="hidden" name="sort" value="salary"><?php endif ?>
    <?php if ($f['employer']): ?><input type="hidden" name="employer" value="<?= (int) $f['employer'] ?>"><?php endif ?>
    <button class="btn pri block">Показати</button>
    <a class="btn ghost block" href="<?= url('/jobs') ?>">Скинути фільтри</a>
  </form>
  <div>
    <?php if ($items): ?>
      <div class="jlist"><?php foreach ($items as $j) echo App\Core\View::partial('partials/job_card', ['j' => $j]) ?></div>
      <?php $pages = (int) ceil($total / App\Controllers\SiteController::PAGE); if ($pages > 1): ?>
        <nav class="pager" aria-label="Сторінки"><?php for ($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++): ?><?= $i === $page ? "<span class=\"on\">$i</span>" : '<a href="' . $q(['page' => $i]) . "\">$i</a>" ?><?php endfor ?></nav>
      <?php endif ?>
    <?php else: ?>
      <div class="card empty"><h3>Нічого не знайдено</h3><p class="muted">Спробуйте прибрати частину фільтрів або змінити запит.</p><a class="btn" href="<?= url('/jobs') ?>">Усі вакансії</a></div>
    <?php endif ?>
  </div>
</div>
