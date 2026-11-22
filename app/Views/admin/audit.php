<div class="adm-h"><h1>Журнал дій</h1><span class="spacer"></span>
  <div class="seg"><?php foreach (['' => 'Усі', 'auth' => 'Входи', 'jobs' => 'Вакансії', 'user' => 'Користувачі', 'access' => 'Ролі', 'settings' => 'Налаштування'] as $k => $l): ?><a class="<?= $action === $k ? 'on' : '' ?>" href="<?= url('/admin/audit', ['action' => $k]) ?>"><?= $l ?></a><?php endforeach ?></div></div>
<p class="muted small">Записи журналу не можна змінити чи видалити.</p>
<div class="card tbl-wrap"><table class="tbl"><thead><tr><th>Час</th><th>Дія</th><th>Хто</th><th>Об’єкт</th><th>IP</th></tr></thead><tbody>
<?php foreach ($rows as $r): $d = $r['data'] ? json_decode($r['data'], true) : []; ?><tr>
  <td class="small muted" style="white-space:nowrap"><?= e(local_date($r['created_at'])) ?></td>
  <td><?= e(App\Core\Audit::label($r['action'])) ?><?php if (!empty($d['reason'])): ?><div class="small muted"><?= e($d['reason']) ?></div><?php endif ?><?php if (($d['via'] ?? '') === 'telegram'): ?><div class="small muted">через Telegram</div><?php endif ?><?php if (!empty($d['tg'])): ?><div class="small muted">Telegram: <?= e($d['tg']) ?></div><?php endif ?><?php if (!empty($d['role'])): ?><div class="small muted">роль: <?= e($d['role']) ?></div><?php endif ?></td>
  <td><?= $r['actor_id'] ? '<a href="' . url('/admin/users/' . $r['actor_id']) . '">' . e($r['display_name']) . '</a>' : '—' ?></td>
  <td class="small"><?php if ($r['entity_type'] === 'vacancy'): ?><a href="<?= url('/jobs/' . $r['entity_id']) ?>">вакансія #<?= e($r['entity_id']) ?></a><?php elseif ($r['entity_type'] === 'user'): ?><a href="<?= url('/admin/users/' . $r['entity_id']) ?>">користувач #<?= e($r['entity_id']) ?></a><?php else: ?><?= e(trim(($r['entity_type'] ?? '') . ' ' . ($r['entity_id'] ?? ''))) ?><?php endif ?></td>
  <td class="small muted mono"><?= e($r['ip']) ?></td></tr><?php endforeach ?>
</tbody></table></div>
<nav class="pager"><?php if ($page > 1): ?><a href="<?= url('/admin/audit', ['action' => $action, 'page' => $page - 1]) ?>">←</a><?php endif ?><span class="on"><?= $page ?></span><?php if (count($rows) === 50): ?><a href="<?= url('/admin/audit', ['action' => $action, 'page' => $page + 1]) ?>">→</a><?php endif ?></nav>
