<?php /* Винагорода по місяцях (0.5.21). $months з Rewards::months(); $deleteBase — адреса для видалення (лише в адмінці головного адміністратора), інакше null. */
use App\Core\Rewards; $i = 0; ?>
<div class="rw-months">
<?php foreach ($months as $ym => $m): ?>
  <details class="card rw-m"<?= $i++ === 0 ? ' open' : '' ?>>
    <summary><span class="rw-ml"><?= e($m['label']) ?></span><span class="muted small"><?= plural(count($m['rows']), 'виплата', 'виплати', 'виплат') ?></span><b class="rw-mt"><?= e(Rewards::uah($m['total'])) ?></b></summary>
    <div class="tbl-wrap"><table class="tbl rw-tbl"><tbody>
    <?php foreach ($m['rows'] as $r): ?><tr>
      <td class="rw-d"><?= e(date('d.m.Y', strtotime((string) $r['paid_on']))) ?></td>
      <td class="rw-c small"><?= $r['comment'] !== null && $r['comment'] !== '' ? e($r['comment']) : '<span class="muted">—</span>' ?></td>
      <td class="rw-a"><b><?= e(Rewards::uah($r['amount'])) ?></b></td>
      <?php if ($deleteBase): ?><td class="rw-x"><form method="post" action="<?= url($deleteBase . '/' . (int) $r['id'] . '/delete') ?>" onsubmit="return confirm('Видалити запис <?= e(Rewards::uah($r['amount'])) ?> за <?= e(date('d.m.Y', strtotime((string) $r['paid_on']))) ?>?')"><?= csrf_field() ?><button class="btn sm ghost bad" title="Видалити запис">Видалити</button></form></td><?php endif ?>
    </tr><?php endforeach ?>
    </tbody></table></div>
  </details>
<?php endforeach ?>
</div>
