<?php /* Клієнти (0.5.16): роботодавці, шукачі роботи, майстри, приклади. Персонал — окремо в «Команді». */
$isDemo = static fn ($id) => in_array((int) $id, $demo, true); ?>
<div class="adm-h"><h1>Клієнти</h1><span class="muted"><?= (int) $counts[''] ?></span></div>
<p class="muted small" style="margin-top:-8px">Люди й компанії, які користуються сайтом. Адміністратори та модератори — в розділі <a href="<?= url('/admin/team') ?>">«Команда»</a>.</p>
<form class="toolbar" method="get" action="<?= url('/admin/clients') ?>">
  <input type="hidden" name="type" value="<?= e($type) ?>">
  <input class="input" name="q" value="<?= e($q) ?>" placeholder="Ім’я, компанія, логін, телефон або email">
  <button class="btn sm pri">Знайти</button>
</form>
<div class="seg cl-tabs" style="margin-bottom:14px;flex-wrap:wrap"><?php foreach ($types as $k => [$l]): ?><a class="<?= $type === $k ? 'on' : '' ?>" href="<?= url('/admin/clients', ['type' => $k, 'q' => $q]) ?>"><?= e($l) ?> <span class="muted"><?= (int) $counts[$k] ?></span></a><?php endforeach ?></div>
<div class="card tbl-wrap"><table class="tbl">
  <thead><tr><th>Клієнт</th><th>Хто це</th><th>Контакти</th><th>Статус</th><th>Реєстрація</th><th>Останній вхід</th><?php if ($canEdit): ?><th></th><?php endif ?></tr></thead>
  <tbody><?php foreach ($rows as $u): $pic = $u['emp_logo'] ? employer_logo($u['emp_logo'], (string) $u['emp_name'], 40, 'elogo') : ($u['m_photo'] ? '<img class="elogo" src="' . e(App\Core\Media::url('master', $u['m_photo'])) . '" alt="" width="40" height="40" loading="lazy">' : '<span class="elogo ph">' . e(initials((string) ($u['emp_name'] ?: $u['m_name'] ?: $u['display_name']))) . '</span>'); ?><tr>
    <td><div class="row" style="flex-wrap:nowrap;gap:10px"><?= $pic ?><div><a href="<?= url('/admin/users/' . $u['id']) ?>"><?= e($u['display_name']) ?></a><div class="small muted mono"><?= e($u['login']) ?></div></div></div></td>
    <td class="small">
      <?php if ($u['emp_id']): ?><div><span class="tag">Роботодавець</span> <?= e(employer_name($u['emp_form'], $u['emp_name'])) ?><?= $u['emp_verified'] ? ' ✓' : '' ?><?= (int) $u['vacancies'] ? ' · ' . (int) $u['vacancies'] . ' вак.' : '' ?></div><?php endif ?>
      <?php if ($u['m_name'] !== null): ?><div><span class="tag">Майстер</span> <?= e($u['m_name']) ?></div><?php endif ?>
      <?php if ((int) $u['resumes']): ?><div><span class="tag">Шукач роботи</span> резюме</div><?php endif ?>
      <?php if ($isDemo($u['id'])): ?><div><span class="tag warn">Приклад</span></div><?php endif ?>
      <?php if (!$u['emp_id'] && $u['m_name'] === null && !(int) $u['resumes'] && !$isDemo($u['id'])): ?><span class="muted">Профіль не заповнено</span><?php endif ?>
    </td>
    <td class="small"><?= $u['phone_hash'] ? '📱' : '' ?> <?= $u['email_hash'] ? '✉️' : '' ?></td>
    <td><span class="tag <?= $u['status'] === 'active' ? 'ok' : 'bad' ?>"><?= $u['status'] === 'active' ? 'Активний' : 'Заблокований' ?></span></td>
    <td class="small muted"><?= e(local_date($u['created_at'], 'd.m.Y')) ?></td><td class="small muted"><?= e($u['last_login_at'] ? ago($u['last_login_at']) : '—') ?></td>
    <?php if ($canEdit): ?><td><a class="btn sm" href="<?= url('/admin/users/' . $u['id'] . '/edit') ?>">Редагувати</a></td><?php endif ?>
  </tr><?php endforeach ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="muted">Нікого не знайдено.</td></tr><?php endif ?></tbody>
</table></div>
<?php if ($total > App\Controllers\Admin\PeopleController::PER_PAGE): ?><nav class="pager"><?php for ($i = 1; $i <= ceil($total / App\Controllers\Admin\PeopleController::PER_PAGE); $i++): ?><?= $i === $page ? "<span class=\"on\">$i</span>" : '<a href="' . url('/admin/clients', ['q' => $q, 'type' => $type, 'page' => $i]) . "\">$i</a>" ?><?php endfor ?></nav><?php endif ?>
