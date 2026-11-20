<div class="adm-h"><h1>Огляд</h1><span class="muted"><?= e(date('d.m.Y')) ?></span></div>
<?php if ($mfa && $me['is_staff']): ?><div class="alert warn">Двофакторна автентифікація для адміністраторів буде додана в наступному оновленні. Поки що використовуйте складний пароль і не входьте з чужих пристроїв.</div><?php endif ?>
<div class="kpis">
  <a class="card kpi<?= $k['pending'] ? ' attn' : '' ?>" href="<?= url('/admin/moderation') ?>"><b><?= $k['pending'] ?></b><span>вакансій чекають модерації</span></a>
  <div class="card kpi"><b><?= $k['published'] ?></b><span>опубліковано вакансій</span></div>
  <div class="card kpi"><b><?= $k['users'] ?></b><span>користувачів (+<?= $k['usersToday'] ?> сьогодні)</span></div>
  <div class="card kpi"><b><?= $k['employers'] ?></b><span>роботодавців</span></div>
  <div class="card kpi"><b><?= $k['applications'] ?></b><span>відгуків</span></div>
  <div class="card kpi"><b><?= (int) $pub['visitorsToday'] ?> / <?= (int) $pub['visitorsMonth'] ?></b><span>відвідувачів сьогодні / за місяць</span></div>
</div>
<?php if ($k['failedMessages']): ?><div class="alert bad">За добу не вдалося надіслати <?= $k['failedMessages'] ?> повідомлень. <a href="<?= url('/admin/outbox') ?>">Переглянути</a></div><?php endif ?>
<?php if ($recent): ?>
<div class="card"><div class="pad" style="padding-bottom:0"><h3>Останні дії</h3></div><div class="tbl-wrap"><table class="tbl"><tbody>
<?php foreach ($recent as $r): ?><tr><td class="muted" style="white-space:nowrap"><?= e(local_date($r['created_at'], 'd.m H:i')) ?></td><td><?= e(App\Core\Audit::label($r['action'])) ?></td><td><?= e($r['display_name'] ?? '—') ?></td></tr><?php endforeach ?>
</tbody></table></div></div>
<?php endif ?>
