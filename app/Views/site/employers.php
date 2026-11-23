<a class="small back-link" href="<?= url('/') ?>">← На головну</a>
<div class="row" style="margin-bottom:16px"><h1 style="font-size:30px">Роботодавці</h1><span class="muted"><?= plural(count($items), 'шукає працівників', 'шукають працівників', 'шукають працівників') ?></span></div>
<?php if ($items): ?>
<div class="emps">
  <?php foreach ($items as $x): $en = employer_name($x['legal_form'], $x['name']); ?>
  <a class="card emp" href="<?= url('/jobs', ['employer' => (int) $x['id']]) ?>">
    <?= $x['logo'] ? employer_logo($x['logo'], $en, 52, 'elogo lg') : '<span class="elogo lg ph">' . e(initials($x['name'])) . '</span>' ?>
    <span class="t">
      <b><?= e($en) ?><?= $x['is_verified'] ? ' <span class="tag ok" title="Перевірений роботодавець">' . icon('shield', 13) . '</span>' : '' ?></b>
      <?php if ($x['settlement']): ?><span><?= icon('pin', 14) ?> <?= e($x['settlement']) ?></span><?php endif ?>
      <em><?= plural((int) $x['cnt'], 'вакансія', 'вакансії', 'вакансій') ?> →</em>
    </span>
  </a>
  <?php endforeach ?>
</div>
<?php else: ?>
<div class="card empty"><h3>Поки що порожньо</h3><p class="muted">Зараз немає відкритих вакансій. Загляньте трохи згодом.</p><a class="btn" href="<?= url('/jobs') ?>">Усі вакансії</a></div>
<?php endif ?>
