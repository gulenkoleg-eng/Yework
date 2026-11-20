<div class="ist-card">
  <header><span class="ist-n ok">Встановлено</span><h2><?= e($st['site']['name']) ?> готовий до роботи</h2><p class="muted">Інсталятор вимкнено назавжди. Ключ встановлення видалено.</p></header>
  <ul class="ist-reqs"><?php foreach ($result['steps'] as $s): ?><li><b>✓</b><span><?= e($s) ?></span></li><?php endforeach ?></ul>
  <?php if ($result['warnings']): ?><div class="alert warn">Захист журналу дій від змін на рівні бази недоступний на цьому хостингу (немає права TRIGGER). Журнал захищено на рівні програми.</div><?php endif ?>
  <div class="alert info"><div>Збережіть копію файлу <b class="mono"><?= e($result['configPath']) ?></b> поза сервером (на комп’ютер або флешку). Він містить ключ шифрування телефонів і email: без нього резервна копія бази марна.</div></div>
  <div class="ist-actions"><a class="btn" href="<?= url('/') ?>">Відкрити сайт</a><a class="btn pri" href="<?= url('/login') ?>">Увійти в адмін-панель</a></div>
</div>
