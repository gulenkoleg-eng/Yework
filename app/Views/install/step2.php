<?php $d = $st['db']; $f = $fields; ?>
<form class="ist-card" method="post" action="<?= url('/install', ['step' => 2]) ?>">
  <?= csrf_field() ?>
  <?= App\Core\View::partial('partials/ist_head', ['step' => 2, 'title' => 'Підключення до бази даних', 'sub' => 'Створіть базу MySQL і користувача в панелі хостингу (розділ «Бази даних»), надайте йому всі права, а потім введіть дані тут.', 'error' => $error, 'ok' => $ok]) ?>
  <div class="grid3">
    <div class="field<?= isset($f['host']) ? ' err' : '' ?>"><label for="host">Хост</label><input id="host" name="host" class="input" value="<?= e($d['host']) ?>" required><span class="hint">На хостингу майже завжди localhost</span><?php if (isset($f['host'])): ?><div class="ferr"><?= e($f['host']) ?></div><?php endif ?></div>
    <div class="field<?= isset($f['port']) ? ' err' : '' ?>"><label for="port">Порт</label><input id="port" name="port" class="input" inputmode="numeric" value="<?= e($d['port']) ?>"></div>
  </div>
  <div class="field<?= isset($f['database']) ? ' err' : '' ?>"><label for="database">Назва бази даних</label><input id="database" name="database" class="input" value="<?= e($d['database']) ?>" placeholder="напр. user_yework" required autocomplete="off"><?php if (isset($f['database'])): ?><div class="ferr"><?= e($f['database']) ?></div><?php endif ?></div>
  <div class="grid2">
    <div class="field<?= isset($f['user']) ? ' err' : '' ?>"><label for="user">Користувач (логін)</label><input id="user" name="user" class="input" value="<?= e($d['user']) ?>" required autocomplete="off"><?php if (isset($f['user'])): ?><div class="ferr"><?= e($f['user']) ?></div><?php endif ?></div>
    <div class="field<?= isset($f['password']) ? ' err' : '' ?>"><label for="password">Пароль</label><input id="password" name="password" type="password" class="input" placeholder="<?= $d['password'] !== '' ? '•••••••• (збережено)' : '' ?>" autocomplete="new-password"><?php if (isset($f['password'])): ?><div class="ferr"><?= e($f['password']) ?></div><?php endif ?></div>
  </div>
  <div class="field<?= isset($f['prefix']) ? ' err' : '' ?>"><label for="prefix">Префікс таблиць</label><input id="prefix" name="prefix" class="input" value="<?= e($d['prefix']) ?>" style="max-width:200px"><span class="hint">Дозволяє тримати в одній базі кілька сайтів. Залиште yw_, якщо не впевнені.</span><?php if (isset($f['prefix'])): ?><div class="ferr"><?= e($f['prefix']) ?></div><?php endif ?></div>
  <div class="ist-actions">
    <a class="btn" href="<?= url('/install', ['step' => 1]) ?>">Назад</a>
    <button class="btn" name="action" value="check">Перевірити підключення</button>
    <button class="btn pri" name="action" value="next">Далі</button>
  </div>
</form>
