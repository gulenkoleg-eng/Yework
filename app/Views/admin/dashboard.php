<div class="adm-h"><h1>Огляд</h1><span class="muted"><?= e(date('d.m.Y')) ?></span></div>
<?php if ($mfa && $me['is_staff']): ?><div class="alert warn">Двофакторна автентифікація для адміністраторів буде додана в наступному оновленні. Поки що використовуйте складний пароль і не входьте з чужих пристроїв.</div><?php endif ?>
<?php
  // Картки ведуть на відповідну сторінку (0.5.20). Якщо в ролі немає права на ту сторінку — картка лишається простим блоком.
  $a = App\Core\App::auth();
  $cards = [
    [$k['pending'], 'вакансій чекають модерації', '/admin/moderation', 'jobs.listing:moderate', $k['pending'] > 0],
    [$k['published'], 'опубліковано вакансій', ['/admin/moderation', ['status' => 'published']], 'jobs.listing:moderate'],
    [$k['users'], 'користувачів (+' . $k['usersToday'] . ' сьогодні)', '/admin/clients', 'identity.user:view'],
    [$k['employers'], 'роботодавців', ['/admin/clients', ['type' => 'employers']], 'identity.user:view'],
    [$k['applications'], 'відгуків', '/admin/applications', 'identity.user:view'],
    [(int) $pub['visitorsToday'] . ' / ' . (int) $pub['visitorsMonth'], 'відвідувачів сьогодні / за місяць', '/admin/visitors', 'stats.dashboard:view'],
  ];
?>
<div class="kpis">
<?php foreach ($cards as $c): [$num, $label, $href, $perm] = $c; $cls = 'card kpi' . (!empty($c[4]) ? ' attn' : ''); ?>
  <?php if ($a->can($perm)): $href = is_array($href) ? url($href[0], $href[1]) : url($href); ?><a class="<?= $cls ?> link" href="<?= e($href) ?>"><b><?= e((string) $num) ?></b><span><?= e($label) ?></span><i class="kpi-go" aria-hidden="true">→</i></a>
  <?php else: ?><div class="<?= $cls ?>"><b><?= e((string) $num) ?></b><span><?= e($label) ?></span></div><?php endif ?>
<?php endforeach ?>
</div>
<?php if ($k['failedMessages']): ?><div class="alert bad">За добу не вдалося надіслати <?= $k['failedMessages'] ?> повідомлень. <a href="<?= url('/admin/outbox') ?>">Переглянути</a></div><?php endif ?>
<?php if ($recent): ?>
<div class="card"><div class="pad" style="padding-bottom:0"><h3>Останні дії</h3></div><div class="tbl-wrap"><table class="tbl"><tbody>
<?php foreach ($recent as $r): ?><tr><td class="muted" style="white-space:nowrap"><?= e(local_date($r['created_at'], 'd.m H:i')) ?></td><td><?= e(App\Core\Audit::label($r['action'])) ?></td><td><?= e($r['display_name'] ?? '—') ?></td></tr><?php endforeach ?>
</tbody></table></div></div>
<?php endif ?>
