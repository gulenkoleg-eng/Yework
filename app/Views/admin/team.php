<?php /* Команда (0.5.16): головний адміністратор і всі, кому видано роль. */ ?>
<div class="adm-h"><h1>Команда</h1><span class="muted"><?= count($rows) ?></span></div>
<p class="muted small" style="margin-top:-8px">Адміністратори, модератори та інші працівники сайту. Щоб додати людину в команду, знайдіть її в <a href="<?= url('/admin/clients') ?>">«Клієнтах»</a>, відкрийте картку й видайте роль. Що може кожна роль — у <a href="<?= url('/admin/roles') ?>">«Ролях та правах»</a>.</p>
<form class="toolbar" method="get" action="<?= url('/admin/team') ?>">
  <input class="input" name="q" value="<?= e($q) ?>" placeholder="Ім’я, логін, телефон або email">
  <button class="btn sm pri">Знайти</button>
</form>
<div class="card tbl-wrap"><table class="tbl">
  <thead><tr><th>Працівник</th><th>Ролі</th><th>Контакти</th><th>Статус</th><th>Останній вхід</th></tr></thead>
  <tbody><?php foreach ($rows as $u): ?><tr>
    <td><a href="<?= url('/admin/users/' . $u['id']) ?>"><?= e($u['display_name']) ?></a><div class="small muted mono"><?= e($u['login']) ?></div></td>
    <td class="small"><?= (int) $u['owner_flag'] === 1 ? '<span class="tag">Головний адміністратор</span>' : e($u['roles'] ?? '—') ?></td>
    <td class="small"><?= $u['phone_hash'] ? '📱' : '' ?> <?= $u['email_hash'] ? '✉️' : '' ?></td>
    <td><span class="tag <?= $u['status'] === 'active' ? 'ok' : 'bad' ?>"><?= $u['status'] === 'active' ? 'Активний' : 'Заблокований' ?></span></td>
    <td class="small muted"><?= e($u['last_login_at'] ? ago($u['last_login_at']) : '—') ?></td>
  </tr><?php endforeach ?>
  <?php if (!$rows): ?><tr><td colspan="5" class="muted">Нікого не знайдено.</td></tr><?php endif ?></tbody>
</table></div>
