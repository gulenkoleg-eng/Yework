<?php use App\Core\Media; use App\Core\Services as S; $r = S::rating($x); ?>
<a class="card mcard" href="<?= url('/services/' . $x['id']) ?>">
  <span class="m-ava"><?php if ($x['photo']): ?><img src="<?= e(S::photoUrl($x['photo'])) ?>" alt="" loading="lazy"><?php elseif ($x['cover']): ?><img src="<?= e(Media::url('workthumb', $x['cover'])) ?>" alt="" loading="lazy"><?php else: ?><?= e(initials((string) $x['name'])) ?><?php endif ?></span>
  <div class="stack" style="gap:6px;min-width:0">
    <div class="row" style="justify-content:space-between;align-items:flex-start;flex-wrap:nowrap;gap:10px"><h3><?= e($x['name']) ?></h3><?php if ($x['min_price'] !== null): ?><span class="price" style="white-space:nowrap">від <?= money((int) $x['min_price']) ?> грн</span><?php endif ?></div>
    <div class="rate"><?php if ($r !== null): ?><?= S::stars($r, 15) ?> <?= e(number_format($r, 1, ',', '')) ?> <span class="muted">(<?= plural((int) $x['rating_count'], 'відгук', 'відгуки', 'відгуків') ?>)</span><?php else: ?><span class="muted small">Нові відгуки з’являться після замовлень через сайт</span><?php endif ?></div>
    <div class="meta">
      <span><?= icon('tool', 15) ?><?= e($x['category']) ?></span>
      <?php if ($x['settlement']): ?><span><?= icon('pin', 15) ?><?= e($x['settlement']) ?></span><?php endif ?>
      <?php if ($x['experience_years']): ?><span>Досвід <?= plural((int) $x['experience_years'], 'рік', 'роки', 'років') ?></span><?php endif ?>
      <?php if ((int) $x['works']): ?><span><?= icon('eye', 15) ?><?= plural((int) $x['works'], 'об’єкт', 'об’єкти', 'об’єктів') ?> з фото</span><?php endif ?>
      <?php if ((int) $x['orders_done']): ?><span><?= icon('check', 15) ?><?= plural((int) $x['orders_done'], 'замовлення', 'замовлення', 'замовлень') ?> через сайт</span><?php endif ?>
      <?php if (in_array($x['legal_form'], ['ФОП', 'ТОВ', 'ПП'], true)): ?><span class="tag"><?= e($x['legal_form']) ?></span><?php endif ?>
    </div>
  </div>
</a>
