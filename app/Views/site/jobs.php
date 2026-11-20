<?php $q = fn (array $over) => url('/jobs', array_merge(array_filter(['q' => $f['q'], 'settlement' => $f['settlement'] ?: null, 'category' => $f['category'], 'employment' => $f['employment'], 'salary' => $f['salary'] ?: null, 'noexp' => $f['noexp'] ? 1 : null, 'sort' => $f['sort'] === 'salary' ? 'salary' : null]), $over)); ?>
<div class="row" style="margin-bottom:16px"><h1 style="font-size:30px">Вакансії</h1><span class="muted"><?= plural($total, 'вакансія', 'вакансії', 'вакансій') ?></span>
  <span class="spacer"></span>
  <div class="seg" style="background:var(--c-secondary)"><a class="<?= $f['sort'] === 'new' ? 'on' : '' ?>" href="<?= $q(['sort' => null, 'page' => null]) ?>">Нові</a><a class="<?= $f['sort'] === 'salary' ? 'on' : '' ?>" href="<?= $q(['sort' => 'salary', 'page' => null]) ?>">Більша зарплата</a></div>
</div>
<div class="jobs-layout">
  <form class="card filters" method="get" action="<?= url('/jobs') ?>">
    <div class="field"><label for="q">Пошук</label><input id="q" class="input" name="q" value="<?= e($f['q']) ?>" placeholder="Посада або компанія"></div>
    <div class="field"><label for="st">Населений пункт</label><select id="st" class="input" name="settlement"><option value="">Усі громади</option><?php foreach ($settlements as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $f['settlement'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach ?></select></div>
    <div class="field"><label for="cat">Категорія</label><select id="cat" class="input" name="category"><option value="">Усі категорії</option><?php foreach ($categories as $c): ?><option value="<?= e($c['slug']) ?>" <?= $f['category'] === $c['slug'] ? 'selected' : '' ?>><?= e($c['name']) ?> (<?= (int) $c['cnt'] ?>)</option><?php endforeach ?></select></div>
    <div class="field"><label for="emp">Зайнятість</label><select id="emp" class="input" name="employment"><option value="">Будь-яка</option><?php foreach (EMPLOYMENT_TYPES as $k => $v): ?><option value="<?= $k ?>" <?= $f['employment'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach ?></select></div>
    <div class="field"><label for="sal">Зарплата від, грн</label><input id="sal" class="input" name="salary" inputmode="numeric" value="<?= $f['salary'] ?: '' ?>" placeholder="напр. 12000"></div>
    <label class="check"><input type="checkbox" name="noexp" value="1" <?= $f['noexp'] ? 'checked' : '' ?>> Без досвіду</label>
    <?php if ($f['sort'] === 'salary'): ?><input type="hidden" name="sort" value="salary"><?php endif ?>
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
