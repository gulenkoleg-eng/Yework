<?php /* Відгуки на вакансії (0.5.20): відкривається з картки «відгуків» на «Огляді». Без тексту відгуку й контактів — лише хто, на що і коли. */ ?>
<div class="adm-h"><h1>Відгуки</h1><span class="muted">усього <?= (int) $total ?><?= $today ? ', сьогодні ' . (int) $today : '' ?></span><span class="spacer"></span><a class="btn ghost sm" href="<?= url('/admin') ?>">← Огляд</a></div>
<div class="card tbl-wrap"><table class="tbl"><thead><tr><th>Коли</th><th>Вакансія</th><th>Роботодавець</th><th>Хто відгукнувся</th><th>Статус</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr>
  <td class="small muted" style="white-space:nowrap"><?= e(local_date($r['created_at'], 'd.m.Y H:i')) ?></td>
  <td><?php if ($r['v_status'] === 'published'): ?><a href="<?= url('/jobs/' . $r['vacancy_id']) ?>" target="_blank" rel="noopener"><?= e($r['title']) ?></a><?php else: ?><?= e($r['title']) ?> <span class="tag muted">не на сайті</span><?php endif ?></td>
  <td class="small"><?= e($r['employer']) ?></td>
  <td><a href="<?= url('/admin/users/' . $r['user_id']) ?>"><?= e($r['display_name'] ?: '—') ?></a></td>
  <td><span class="tag <?= $r['status'] === 'new' ? 'ok' : 'muted' ?>"><?= ['new' => 'новий', 'viewed' => 'переглянуто'][$r['status']] ?? e($r['status']) ?></span></td>
</tr><?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="5" class="muted">Відгуків ще не було.</td></tr><?php endif ?>
</tbody></table></div>
<?php if ($total > $per): ?><nav class="pager"><?php for ($i = 1; $i <= ceil($total / $per); $i++): ?><?= $i === $page ? "<span class=\"on\">$i</span>" : '<a href="' . url('/admin/applications', ['page' => $i]) . "\">$i</a>" ?><?php endfor ?></nav><?php endif ?>
