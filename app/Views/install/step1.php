<?php $bad = array_filter($requirements, fn ($r) => !$r['ok']); ?>
<form class="ist-card" method="post" action="<?= url('/install', ['step' => 1]) ?>">
  <?= csrf_field() ?>
  <?= App\Core\View::partial('partials/ist_head', ['step' => 1, 'title' => 'Ласкаво просимо до YEWORK', 'sub' => 'Майстер встановлення за кілька хвилин підготує сайт: створить базу даних, ролі та першого адміністратора.', 'error' => $error]) ?>
  <h3>Перевірка сервера</h3>
  <ul class="ist-reqs">
    <?php foreach ($requirements as $r): ?>
      <li class="<?= !$r['ok'] ? 'bad' : (!empty($r['warn']) ? 'warn' : '') ?>"><b><?= !$r['ok'] ? '✕' : (!empty($r['warn']) ? '!' : '✓') ?></b><span><?= e($r['label']) ?><small><?= e($r['detail']) ?></small></span></li>
    <?php endforeach ?>
  </ul>
  <h3>Ключ встановлення</h3>
  <?php if ($keyFileReady): ?>
    <div class="alert info"><div>Щоб ніхто сторонній не встановив сайт замість вас, введіть одноразовий ключ. Він лежить у файлі <b class="mono">config/setup-key.php</b>. Відкрийте його у файловому менеджері хостингу: ключ має вигляд <span class="mono">ABCD-1234-EF56</span>.</div></div>
  <?php else: ?>
    <div class="alert bad">Не вдалося створити ключ: папка config недоступна для запису. Встановіть на неї права 755 і оновіть сторінку.</div>
  <?php endif ?>
  <div class="field">
    <label for="key">Ключ встановлення</label>
    <input id="key" name="key" class="input keybox" placeholder="XXXX-XXXX-XXXX" autocomplete="off" spellcheck="false" required <?= $bad ? 'disabled' : 'autofocus' ?>>
  </div>
  <div class="ist-actions">
    <?php if ($bad): ?><a class="btn" href="<?= url('/install') ?>">Перевірити ще раз</a><?php endif ?>
    <button class="btn pri" <?= $bad ? 'disabled' : '' ?>>Далі: база даних</button>
  </div>
</form>
