<?php $d = $st['site']; $f = $fields; ?>
<form class="ist-card" method="post" action="<?= url('/install', ['step' => 3]) ?>">
  <?= csrf_field() ?>
  <?= App\Core\View::partial('partials/ist_head', ['step' => 3, 'title' => 'Сайт', 'sub' => 'Назву й слоган можна буде змінити в адмін-панелі.', 'error' => $error]) ?>
  <div class="grid2">
    <div class="field<?= isset($f['name']) ? ' err' : '' ?>"><label for="name">Назва сайту</label><input id="name" name="name" class="input" value="<?= e($d['name']) ?>" required><?php if (isset($f['name'])): ?><div class="ferr"><?= e($f['name']) ?></div><?php endif ?></div>
    <div class="field"><label for="slogan">Слоган</label><input id="slogan" name="slogan" class="input" value="<?= e($d['slogan']) ?>"></div>
  </div>
  <div class="field<?= isset($f['url']) ? ' err' : '' ?>"><label for="url">Адреса сайту</label><input id="url" name="url" class="input" value="<?= e($d['url']) ?>" required><span class="hint">Використовується в листах і SMS</span><?php if (isset($f['url'])): ?><div class="ferr"><?= e($f['url']) ?></div><?php endif ?></div>
  <div class="field<?= isset($f['configFile']) ? ' err' : '' ?>">
    <label for="configFile">Назва файлу конфігурації</label>
    <div class="row" style="flex-wrap:nowrap"><span class="mono muted">config/</span><input id="configFile" name="configFile" class="input" value="<?= e($d['configFile']) ?>" required><span class="mono muted">.php</span></div>
    <span class="hint">У цей файл запишуться доступи до бази та ключі шифрування. Ім’я може бути будь-яким, напр. yework-config.</span>
    <?php if (isset($f['configFile'])): ?><div class="ferr"><?= e($f['configFile']) ?></div><?php endif ?>
  </div>
  <div class="ist-actions"><a class="btn" href="<?= url('/install', ['step' => 2]) ?>">Назад</a><button class="btn pri">Далі</button></div>
</form>
