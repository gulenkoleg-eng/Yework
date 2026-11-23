<?php
/**
 * Компактний пошук для телефона: один рядок пошуку, кнопка «Фільтри» з лічильником
 * і чипи активних фільтрів (кожен можна прибрати хрестиком). Повна форма фільтрів
 * на телефоні відкривається знизу як шторка. На комп’ютері цей блок не показується.
 *
 * @var string $action    адреса розділу (напр. '/jobs')
 * @var string $qVal      поточний пошуковий запит
 * @var string $ph        підказка в полі пошуку
 * @var array  $keep      інші активні параметри, які треба зберегти при пошуку
 * @var array  $chips     [['label' => '…', 'href' => адреса без цього фільтра], …]
 * @var string $reset     адреса «Скинути все»
 */
$cnt = count($chips);
?>
<div class="mfilter">
  <form class="mf-search" method="get" action="<?= url($action) ?>" role="search">
    <?= icon('search', 18) ?>
    <input type="search" name="q" value="<?= e($qVal) ?>" placeholder="<?= e($ph) ?>" aria-label="Пошук" enterkeyhint="search" data-mf-q>
    <?php foreach ($keep as $k => $v): if ($v === null || $v === '' || $k === 'q' || $k === 'page') continue; ?><input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>"><?php endforeach ?>
  </form>
  <a class="btn mf-btn<?= $cnt ? ' on' : '' ?>" href="#flt" data-sheet-open aria-controls="flt"><?= icon('sliders', 18) ?>Фільтри<?php if ($cnt): ?><em><?= $cnt ?></em><?php endif ?></a>
</div>
<?php if ($cnt): ?>
<div class="mf-chips">
  <?php foreach ($chips as $c): ?><a class="chip" href="<?= e($c['href']) ?>" aria-label="Прибрати фільтр <?= e($c['label']) ?>"><?= e($c['label']) ?><?= icon('x', 14) ?></a><?php endforeach ?>
  <?php if ($cnt > 1): ?><a class="chip-clear" href="<?= e($reset) ?>">Скинути все</a><?php endif ?>
</div>
<?php endif ?>
<a class="sheet-bg" href="#" data-sheet-close aria-hidden="true" tabindex="-1"></a>
