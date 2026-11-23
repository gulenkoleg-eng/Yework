<?php /* Зайнятість рекламних місць (0.5.19): скільки місць, до якої дати зайняті і з якого дня звільняться.
  $occupancy — App\Core\Ads::occupancy(). Назв роботодавців тут немає: видно лише дати. */
$d = static fn (string $x) => date('d.m', strtotime($x));
$today = App\Core\Ads::today(); ?>
<div class="ad-places">
  <?php foreach ($occupancy as $pl => $o): $free = $o['free_from']; $nowFree = $free === $today; ?>
    <div class="ad-place" data-place="<?= e($pl) ?>">
      <div class="ad-place-h"><b><?= e($o['label']) ?></b><span class="small muted"><?= plural((int) $o['limit'], 'місце', 'місця', 'місць') ?></span></div>
      <?php if ($nowFree): ?>
        <span class="tag ok">Вільно зараз<?= $o['limit'] > 1 && $o['now'] ? ' · зайнято ' . (int) $o['now'] . ' з ' . (int) $o['limit'] : '' ?></span>
      <?php elseif ($free): ?>
        <span class="tag warn">Зайнято · вільне з <?= e($d($free)) ?></span>
      <?php else: ?>
        <span class="tag bad">Зайнято на рік уперед</span>
      <?php endif ?>
      <?php if ($o['periods']): ?>
        <ul class="ad-place-list">
          <?php foreach (array_slice($o['periods'], 0, 6) as [$s, $e, $live]): ?>
            <li><span><?= e($d($s)) ?>–<?= e(date('d.m.Y', strtotime($e))) ?></span><small><?= $live ? 'показується, закінчиться ' . e($d($e)) : 'заброньовано' ?></small></li>
          <?php endforeach ?>
          <?php if (count($o['periods']) > 6): ?><li class="muted"><small>і ще <?= count($o['periods']) - 6 ?></small></li><?php endif ?>
        </ul>
      <?php else: ?>
        <p class="small muted">Жодного банера не заброньовано.</p>
      <?php endif ?>
    </div>
  <?php endforeach ?>
</div>
