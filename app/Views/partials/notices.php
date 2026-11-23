<?php /** Сповіщення людині (0.5.3): «Адміністрація сайту виправила вашу вакансію…». $notices, $back — куди повернутися після «Зрозуміло». */ ?>
<?php foreach ($notices ?? [] as $n): ?>
  <div class="alert info notice" role="status">
    <div class="stack" style="gap:6px;flex:1">
      <b><?= icon('shield', 15) ?> <?= e($n['title']) ?></b>
      <?php if ($n['body']): ?><div class="small" style="white-space:pre-line"><?= e($n['body']) ?></div><?php endif ?>
      <div class="small muted"><?= e(ago($n['created_at'])) ?></div>
      <form method="post" action="<?= url('/cabinet/notices/' . $n['id']) ?>" class="row" style="gap:8px"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back ?? '/cabinet') ?>">
        <?php if ($n['link'] && ($back ?? '') !== $n['link']): ?><a class="btn sm" href="<?= url($n['link']) ?>">Переглянути</a><?php endif ?>
        <button class="btn sm ghost">Зрозуміло</button>
      </form>
    </div>
  </div>
<?php endforeach ?>
