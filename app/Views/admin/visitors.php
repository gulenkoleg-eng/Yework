<?php /* Відвідувачі по днях (0.5.20): відкривається з картки «відвідувачів» на «Огляді». Лише кількість унікальних відвідувачів, без персональних даних. */
$max = max(1, ...array_values($days)); ?>
<div class="adm-h"><h1>Відвідувачі</h1><span class="spacer"></span><a class="btn ghost sm" href="<?= url('/admin') ?>">← Огляд</a></div>
<div class="kpis">
  <div class="card kpi"><b><?= (int) $pub['visitorsToday'] ?></b><span>сьогодні</span></div>
  <div class="card kpi"><b><?= (int) $pub['visitorsMonth'] ?></b><span>з початку місяця</span></div>
  <div class="card kpi"><b><?= (int) $total30 ?></b><span>за останні 30 днів</span></div>
</div>
<div class="card tbl-wrap" style="margin-top:16px"><table class="tbl"><thead><tr><th>День</th><th style="width:70%">Унікальних відвідувачів</th></tr></thead><tbody>
<?php foreach ($days as $d => $n): ?><tr><td class="small" style="white-space:nowrap"><?= e(date('d.m.Y', strtotime($d))) ?></td>
  <td><div class="vbar"><i style="width:<?= round($n / $max * 100, 1) ?>%"></i><b><?= (int) $n ?></b></div></td></tr><?php endforeach ?>
</tbody></table></div>
<p class="small muted">Кожен відвідувач рахується один раз на день, незалежно від кількості переглянутих сторінок.</p>
