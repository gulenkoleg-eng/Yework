<?php
use App\Core\App; use App\Controllers\Admin\AdminController;
$me = App::auth()->user(); $a = App::auth();
$site = (string) App::setting('site.name', 'YEWORK');
$pending = $a->can('jobs.listing:moderate') ? AdminController::pendingCount() : 0;
$items = [
  ['Робота', null],
  ['dashboard', 'Огляд', '/admin', null],
  ['moderation', 'Модерація', '/admin/moderation', 'jobs.listing:moderate', $pending],
  ['Люди та доступ', null],
  ['users', 'Користувачі', '/admin/users', 'identity.user:view'],
  ['roles', 'Ролі та права', '/admin/roles', 'access.role:view'],
  ['audit', 'Журнал дій', '/admin/audit', 'audit.log:view'],
  ['Система', null],
  ['themes', 'Теми оформлення', '/admin/themes', 'theming.theme:view'],
  ['outbox', 'Повідомлення', '/admin/outbox', 'settings.system:manage_settings'],
  ['settings', 'Налаштування', '/admin/settings', 'settings.system:manage_settings'],
];
?><!doctype html>
<html lang="uk">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title><?= e(($title ?? 'Адмін-панель') . " — $site") ?></title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>"></head>
<body>
<div class="adm-top"><button class="btn ghost sm" style="color:#fff" onclick="document.getElementById('side').classList.toggle('open')" aria-label="Меню"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button><span class="logo"><i>YE</i><span><?= e($site) ?></span></span></div>
<div class="adm">
  <aside class="adm-side" id="side">
    <a class="logo" href="<?= url('/admin') ?>"><i>YE</i><span><?= e($site) ?><small>Адмін-панель</small></span></a>
    <nav class="adm-nav">
      <?php foreach ($items as $it): if ($it[1] === null) { echo '<div class="t">' . e($it[0]) . '</div>'; continue; } if ($it[3] && !$a->can($it[3])) continue; ?>
        <a href="<?= url($it[2]) ?>" class="<?= $nav === $it[0] ? 'on' : '' ?>"><?= e($it[1]) ?><?php if (!empty($it[4])): ?><em><?= (int) $it[4] ?></em><?php endif ?></a>
      <?php endforeach ?>
    </nav>
    <div class="adm-side-f">
      <span><?= e($me['display_name']) ?> · <?= e($me['is_owner'] ? 'Головний адміністратор' : implode(', ', array_column($me['roles'], 'name'))) ?></span>
      <a href="<?= url('/') ?>">← На сайт</a>
      <form method="post" action="<?= url('/logout') ?>"><?= csrf_field() ?><button class="btn sm" style="background:transparent;color:#C5CBD6;border-color:#2A303C">Вийти</button></form>
    </div>
  </aside>
  <main class="adm-main"><?= flash_alert() ?><?= $content ?></main>
</div>
</body>
</html>
