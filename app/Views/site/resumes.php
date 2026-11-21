<?php
use App\Core\Resumes;
$q = fn (array $over) => url('/resumes', array_merge(array_filter(['q' => $f['q'], 'settlement' => $f['settlement'] ?: null, 'category' => $f['category'] ?: null, 'employment' => $f['employment'], 'salary' => $f['salary'] ?: null, 'driving' => $f['driving']]), $over));
?>
<div class="row" style="margin-bottom:16px"><h1 style="font-size:30px">Резюме</h1><span class="muted"><?= plural($total, 'кандидат', 'кандидати', 'кандидатів') ?></span>
  <span class="spacer"></span>
  <a class="btn sm" href="<?= url('/cabinet/resume') ?>"><?= icon('plus', 16) ?>Розмістити своє резюме</a>
</div>
<?php if (!$isEmployer): ?>
  <div class="alert info" style="margin-bottom:16px">
    <?php if ($me): ?>Імена, фото, досвід і PDF резюме бачать роботодавці. <a href="<?= url('/cabinet/employer') ?>">Заповніть профіль роботодавця</a>: це займе хвилину.
    <?php else: ?>Імена, фото, досвід і PDF резюме бачать зареєстровані роботодавці. <a href="<?= url('/login', ['next' => '/resumes']) ?>">Увійдіть</a> або <a href="<?= url('/register') ?>">зареєструйтеся</a>.<?php endif ?>
  </div>
<?php endif ?>
<div class="jobs-layout">
  <form class="card filters" method="get" action="<?= url('/resumes') ?>">
    <div class="field"><label for="q">Пошук</label><input id="q" class="input" name="q" value="<?= e($f['q']) ?>" placeholder="Посада або навичка"></div>
    <div class="field"><label for="st">Населений пункт</label><select id="st" class="input" name="settlement"><option value="">Усі громади</option><?php foreach ($settlements as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $f['settlement'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach ?></select></div>
    <div class="field"><label for="cat">Сфера</label><select id="cat" class="input" name="category"><option value="">Усі сфери</option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $f['category'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?></select></div>
    <div class="field"><label for="emp">Графік</label><select id="emp" class="input" name="employment"><option value="">Будь-який</option><?php foreach (EMPLOYMENT_TYPES as $k => $v): ?><option value="<?= $k ?>" <?= $f['employment'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach ?></select></div>
    <div class="field"><label for="sal">Зарплата до, грн</label><input id="sal" class="input" name="salary" inputmode="numeric" value="<?= $f['salary'] ?: '' ?>" placeholder="напр. 20000"><span class="hint">Кандидати, чиї очікування не вищі</span></div>
    <div class="field"><label for="drv">Посвідчення водія</label><select id="drv" class="input" name="driving"><option value="">Не важливо</option><?php foreach (Resumes::DRIVING as $c): ?><option value="<?= $c ?>" <?= $f['driving'] === $c ? 'selected' : '' ?>>Категорія <?= $c ?></option><?php endforeach ?></select></div>
    <button class="btn pri block">Показати</button>
    <a class="btn ghost block" href="<?= url('/resumes') ?>">Скинути фільтри</a>
  </form>
  <div>
    <?php if ($items): ?>
      <div class="rlist"><?php foreach ($items as $x) echo App\Core\View::partial('partials/resume_card', ['x' => $x, 'full' => $isEmployer]) ?></div>
      <?php $pages = (int) ceil($total / App\Controllers\ResumeController::PAGE); if ($pages > 1): ?>
        <nav class="pager" aria-label="Сторінки"><?php for ($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++): ?><?= $i === $page ? "<span class=\"on\">$i</span>" : '<a href="' . $q(['page' => $i]) . "\">$i</a>" ?><?php endfor ?></nav>
      <?php endif ?>
    <?php else: ?>
      <div class="card empty"><h3>Нічого не знайдено</h3><p class="muted">Спробуйте прибрати частину фільтрів або змінити запит.</p><a class="btn" href="<?= url('/resumes') ?>">Усі резюме</a></div>
    <?php endif ?>
  </div>
</div>
