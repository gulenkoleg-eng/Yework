<div class="adm-h"><h1>Користувачі</h1><span class="muted"><?= (int) $total ?></span></div>
<form class="toolbar" method="get" action="<?= url('/admin/users') ?>">
  <input class="input" name="q" value="<?= e($q) ?>" placeholder="Ім’я, логін, телефон або email">
  <div class="seg"><?php foreach (['' => 'Усі', 'staff' => 'Персонал', 'blocked' => 'Заблоковані'] as $k => $l): ?><a class="<?= $filter === $k ? 'on' : '' ?>" href="<?= url('/admin/users', ['filter' => $k, 'q' => $q]) ?>"><?= $l ?></a><?php endforeach ?></div>
  <button class="btn sm pri">Знайти</button>
</form>
<div class="card tbl-wrap"><table class="tbl">
  <thead><tr><th>Користувач</th><th>Контакти</th><th>Ролі</th><th>Статус</th><th>Реєстрація</th><th>Останній вхід</th></tr></thead>
  <tbody><?php foreach ($rows as $u): ?><tr>
    <td><a href="<?= url('/admin/users/' . $u['id']) ?>"><?= e($u['display_name']) ?></a><div class="small muted mono"><?= e($u['login']) ?></div></td>
    <td class="small"><?= $u['phone_hash'] ? '📱' : '' ?> <?= $u['email_hash'] ? '✉️' : '' ?></td>
    <td class="small"><?= (int) $u['owner_flag'] === 1 ? '<span class="tag">Головний адміністратор</span>' : e($u['roles'] ?? '—') ?></td>
    <td><span class="tag <?= $u['status'] === 'active' ? 'ok' : 'bad' ?>"><?= $u['status'] === 'active' ? 'Активний' : 'Заблокований' ?></span></td>
    <td class="small muted"><?= e(local_date($u['created_at'], 'd.m.Y')) ?></td><td class="small muted"><?= e($u['last_login_at'] ? ago($u['last_login_at']) : '—') ?></td>
  </tr><?php endforeach ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="muted">Нікого не знайдено.</td></tr><?php endif ?></tbody>
</table></div>
<?php if ($total > 30): ?><nav class="pager"><?php for ($i = 1; $i <= ceil($total / 30); $i++): ?><?= $i === $page ? "<span class=\"on\">$i</span>" : '<a href="' . url('/admin/users', ['q' => $q, 'filter' => $filter, 'page' => $i]) . "\">$i</a>" ?><?php endfor ?></nav><?php endif ?>
