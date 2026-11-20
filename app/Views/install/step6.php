<?php $d = $st['db']; $s = $st['site']; $a = $st['admin']; ?>
<form class="ist-card" method="post" action="<?= url('/install', ['step' => 6]) ?>" onsubmit="this.querySelector('button.pri').disabled=true;this.querySelector('button.pri').textContent='Встановлюю…'">
  <?= csrf_field() ?>
  <?= App\Core\View::partial('partials/ist_head', ['step' => 6, 'title' => 'Усе готово до встановлення', 'sub' => 'Перевірте дані. Встановлення займе кілька секунд.', 'error' => $error]) ?>
  <dl class="ist-sum">
    <dt>База даних</dt><dd><?= e($d['user']) ?>@<?= e($d['host']) ?>:<?= e($d['port']) ?> / <?= e($d['database']) ?></dd>
    <dt>Префікс таблиць</dt><dd class="mono"><?= e($d['prefix']) ?></dd>
    <dt>Сайт</dt><dd><?= e($s['name']) ?> — <?= e($s['slogan']) ?></dd>
    <dt>Адреса</dt><dd><?= e($s['url']) ?></dd>
    <dt>Файл конфігурації</dt><dd class="mono">config/<?= e($s['configFile']) ?>.php</dd>
    <dt>Тема</dt><dd><?= e($s['theme']) ?></dd>
    <dt>Головний адміністратор</dt><dd><?= e($a['displayName']) ?> (<?= e($a['login']) ?>)</dd>
  </dl>
  <div class="ist-actions"><a class="btn" href="<?= url('/install', ['step' => 5]) ?>">Назад</a><button class="btn pri">Встановити</button></div>
</form>
