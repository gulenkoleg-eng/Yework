<?php $a = App\Core\App::auth(); $isSelf = (int) $u['id'] === (int) $me['id']; $isOwner = (int) $u['owner_flag'] === 1; $has = array_column($roles, 'slug'); ?>
<div class="adm-h"><a href="<?= url('/admin/users') ?>" class="btn sm ghost">←</a><h1><?= e($u['display_name']) ?></h1><span class="tag <?= $u['status'] === 'active' ? 'ok' : 'bad' ?>"><?= $u['status'] === 'active' ? 'Активний' : 'Заблокований' ?></span><?php if ($isOwner): ?><span class="tag">Головний адміністратор</span><?php endif ?></div>
<div class="grid2" style="align-items:start">
  <div class="card pad stack">
    <h3>Дані</h3>
    <dl class="kv"><dt>Логін</dt><dd class="mono"><?= e($u['login']) ?></dd><dt>Телефон</dt><dd><?= e($phone ? ($masked ? $phone : phone_pretty($phone)) : '—') ?></dd><dt>Email</dt><dd><?= e($email ?? '—') ?></dd>
      <dt>Реєстрація</dt><dd><?= e(local_date($u['created_at'])) ?></dd><dt>Останній вхід</dt><dd><?= e($u['last_login_at'] ? local_date($u['last_login_at']) : '—') ?></dd>
      <dt>Вакансій / відгуків</dt><dd><?= $stats['vacancies'] ?> / <?= $stats['applications'] ?></dd>
      <dt>Роботодавець</dt><dd><?= $employers ? e(implode(', ', array_map(fn ($x) => $x['name'] . ($x['is_verified'] ? ' ✓' : ''), $employers))) : '—' ?></dd></dl>
    <?php if ($masked): ?><p class="small muted">Контакти приховано: потрібне право «Перегляд телефонів і email».</p><?php endif ?>
    <?php if (!$isSelf && !$isOwner && $a->can('identity.user:update')): ?>
      <form method="post" action="<?= url('/admin/users/' . $u['id'] . '/status') ?>" class="row"><?= csrf_field() ?>
        <input type="hidden" name="status" value="<?= $u['status'] === 'active' ? 'blocked' : 'active' ?>">
        <input class="input" name="reason" placeholder="Причина" required style="flex:1;min-height:42px">
        <button class="btn <?= $u['status'] === 'active' ? 'bad' : 'ok' ?>"><?= $u['status'] === 'active' ? 'Заблокувати' : 'Розблокувати' ?></button>
      </form>
    <?php endif ?>
  </div>
  <div class="card pad stack">
    <h3>Ролі</h3>
    <?php if ($isOwner): ?><p class="muted">Головний адміністратор має всі права. Цю роль не можна змінити.</p>
    <?php elseif ($isSelf): ?><p class="muted">Свої ролі змінювати не можна.</p>
    <?php else: ?>
      <?php foreach ($allRoles as $r): $on = in_array($r['slug'], $has, true); ?>
        <form method="post" action="<?= url('/admin/users/' . $u['id'] . '/roles') ?>" class="row" style="justify-content:space-between;flex-wrap:nowrap"><?= csrf_field() ?>
          <div><b><?= e($r['name']) ?></b><div class="small muted"><?= e($r['description']) ?></div></div>
          <?php if ($a->can('access.role:manage_settings')): ?><input type="hidden" name="role" value="<?= e($r['slug']) ?>"><input type="hidden" name="op" value="<?= $on ? 'revoke' : 'grant' ?>"><button class="btn sm <?= $on ? 'bad' : '' ?>"><?= $on ? 'Зняти' : 'Видати' ?></button>
          <?php else: ?><span class="tag <?= $on ? 'ok' : 'muted' ?>"><?= $on ? 'Є' : '—' ?></span><?php endif ?>
        </form>
      <?php endforeach ?>
    <?php endif ?>
  </div>
</div>
<div class="card"><div class="pad" style="padding-bottom:0"><h3>Історія</h3></div><div class="tbl-wrap"><table class="tbl"><tbody>
<?php foreach ($audit as $r): ?><tr><td class="muted small" style="white-space:nowrap"><?= e(local_date($r['created_at'])) ?></td><td><?= e(App\Core\Audit::label($r['action'])) ?></td><td class="small muted mono"><?= e($r['ip']) ?></td></tr><?php endforeach ?>
<?php if (!$audit): ?><tr><td class="muted">Подій немає.</td></tr><?php endif ?></tbody></table></div></div>
