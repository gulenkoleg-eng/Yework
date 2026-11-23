<?php $a = App\Core\App::auth(); $isSelf = (int) $u['id'] === (int) $me['id']; $isOwner = (int) $u['owner_flag'] === 1; $has = array_column($roles, 'slug'); ?>
<div class="adm-h"><a href="<?= url($isStaff ? '/admin/team' : '/admin/clients') ?>" class="btn sm ghost" title="<?= $isStaff ? 'До команди' : 'До клієнтів' ?>">←</a><h1><?= e($u['display_name']) ?></h1><span class="tag <?= $u['status'] === 'active' ? 'ok' : 'bad' ?>"><?= $u['status'] === 'active' ? 'Активний' : 'Заблокований' ?></span><?php if ($isOwner): ?><span class="tag">Головний адміністратор</span><?php elseif ($isStaff): ?><span class="tag">Команда</span><?php else: ?><span class="tag muted">Клієнт</span><?php endif ?><?php if ($canEdit): ?><a class="btn sm pri" style="margin-left:auto" href="<?= url('/admin/users/' . $u['id'] . '/edit') ?>">Редагувати картку</a><?php endif ?></div>
<div class="grid2" style="align-items:start">
  <div class="card pad stack">
    <h3>Дані</h3>
    <dl class="kv"><dt>Логін</dt><dd class="mono"><?= e($u['login']) ?></dd><dt>Телефон</dt><dd><?= e($phone ? ($masked ? $phone : phone_pretty($phone)) : '—') ?></dd><dt>Email</dt><dd><?= e($email ?? '—') ?></dd>
      <dt>Реєстрація</dt><dd><?= e(local_date($u['created_at'])) ?></dd><dt>Останній вхід</dt><dd><?= e($u['last_login_at'] ? local_date($u['last_login_at']) : '—') ?></dd>
      <dt>Вакансій / відгуків</dt><dd><?= $stats['vacancies'] ?> / <?= $stats['applications'] ?></dd>
      <dt>Роботодавець</dt><dd><?php if ($employers): foreach ($employers as $x): ?><div class="row" style="gap:8px;flex-wrap:nowrap"><?= $x['logo'] ? employer_logo($x['logo'], $x['name'], 40) : '<span class="elogo ph" title="Логотипу немає">' . e(initials($x['name'])) . '</span>' ?><span><?= e(employer_name($x['legal_form'], $x['name'])) ?><?= $x['is_verified'] ? ' <span class="tag ok" title="Перевірений роботодавець">' . icon('shield', 12) . ' Перевірений</span>' : '' ?></span></div><?php endforeach; else: ?>—<?php endif ?></dd>
      <?php if ($master): ?><dt>Майстер</dt><dd><div class="row" style="gap:8px;flex-wrap:nowrap"><?= $master['photo'] ? '<img class="elogo" src="' . e(App\Core\Media::url('master', $master['photo'])) . '" alt="" width="40" height="40">' : '<span class="elogo ph">' . e(initials($master['name'])) . '</span>' ?><a href="<?= url('/services/' . $master['id']) ?>"><?= e($master['name']) ?></a><?php if (App\Core\App::auth()->can(App\Controllers\Admin\MasterEditController::PERM)): ?><a class="btn sm" href="<?= url('/admin/masters/' . $master['id'] . '/edit') ?>"><?= icon('tool', 14) ?>Анкета: опис, прайс, фото</a><?php endif ?></div></dd><?php endif ?></dl>
    <?php if ($masked): ?><p class="small muted">Контакти приховано: потрібне право «Перегляд телефонів і email».</p><?php endif ?>
    <?php if (!$isSelf && !$isOwner && $a->can('identity.user:update')): ?>
      <form method="post" action="<?= url('/admin/users/' . $u['id'] . '/status') ?>" class="row"><?= csrf_field() ?>
        <input type="hidden" name="status" value="<?= $u['status'] === 'active' ? 'blocked' : 'active' ?>">
        <input class="input" name="reason" placeholder="Причина" required style="flex:1;min-height:42px">
        <button class="btn <?= $u['status'] === 'active' ? 'bad' : 'ok' ?>"><?= $u['status'] === 'active' ? 'Заблокувати' : 'Розблокувати' ?></button>
      </form>
    <?php endif ?>
    <?php if ($employers && $a->can('identity.user:update')): foreach ($employers as $x): $vOn = (bool) $x['is_verified']; ?>
      <form method="post" action="<?= url('/admin/employers/' . $x['id'] . '/verified') ?>" class="verify-box<?= $vOn ? ' on' : '' ?>" id="verify-<?= (int) $x['id'] ?>"><?= csrf_field() ?>
        <input type="hidden" name="on" value="<?= $vOn ? '0' : '1' ?>"><input type="hidden" name="back" value="<?= e(url('/admin/users/' . $u['id'])) ?>">
        <span class="vb-ic"><?= icon('shield', 22) ?></span>
        <div><b><?= $vOn ? 'Перевірений роботодавець' : 'Роботодавця ще не перевірено' ?></b><div class="small muted"><?= $vOn ? 'У вакансіях видно позначку довіри.' : 'Одне натискання, і у вакансіях з’явиться позначка довіри.' ?></div></div>
        <button class="btn <?= $vOn ? '' : 'ok' ?>"><?= $vOn ? 'Зняти позначку' : icon('shield', 16) . 'Позначити перевіреним' ?></button>
      </form>
    <?php endforeach; endif ?>
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
<?php if ($rewards !== null): ?>
<div class="card pad stack" id="reward">
  <div class="row" style="justify-content:space-between;align-items:baseline"><h3>Винагорода</h3><?php if ($rewards): ?><span class="muted small">Усього: <b><?= e(App\Core\Rewards::uah(App\Core\Rewards::total($rewards))) ?></b></span><?php endif ?></div>
  <p class="small muted" style="margin-top:-6px">Суми, які ви виплачуєте за роботу в проєкті. Додавати й видаляти записи можете лише ви. <?= e($u['display_name']) ?> бачить свої записи в кабінеті, у вкладці «Винагорода», інші члени команди їх не бачать.</p>
  <form method="post" action="<?= url('/admin/users/' . $u['id'] . '/rewards') ?>" class="rw-form"><?= csrf_field() ?>
    <label><span class="small muted">Дата</span><input class="input" type="date" name="paid_on" value="<?= e(date('Y-m-d')) ?>" required></label>
    <label><span class="small muted">Сума, грн</span><input class="input" name="amount" inputmode="decimal" placeholder="1500" required pattern="[0-9 ]+([.,][0-9]{1,2})?"></label>
    <label class="rw-cm"><span class="small muted">Коментар (необов’язково)</span><input class="input" name="comment" maxlength="300" placeholder="За що: модерація за жовтень, премія…"></label>
    <button class="btn pri">Додати</button>
  </form>
  <?php if ($rewards): ?><?= App\Core\View::partial('partials/reward_months', ['months' => $rewards, 'deleteBase' => '/admin/users/' . $u['id'] . '/rewards']) ?>
  <?php else: ?><p class="muted small">Записів ще немає.</p><?php endif ?>
</div>
<?php endif ?>
<div class="card"><div class="pad" style="padding-bottom:0"><h3>Історія</h3></div><div class="tbl-wrap"><table class="tbl"><tbody>
<?php foreach ($audit as $r): ?><tr><td class="muted small" style="white-space:nowrap"><?= e(local_date($r['created_at'])) ?></td><td><?= e(App\Core\Audit::label($r['action'])) ?></td><td class="small muted mono"><?= e($r['ip']) ?></td></tr><?php endforeach ?>
<?php if (!$audit): ?><tr><td class="muted">Подій немає.</td></tr><?php endif ?></tbody></table></div></div>
