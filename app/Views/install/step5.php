<?php $d = $st['admin']; $f = $fields; $fe = fn ($k) => isset($f[$k]) ? '<div class="ferr">' . e($f[$k]) . '</div>' : ''; ?>
<form class="ist-card" method="post" action="<?= url('/install', ['step' => 5]) ?>" id="adm">
  <?= csrf_field() ?>
  <?= App\Core\View::partial('partials/ist_head', ['step' => 5, 'title' => 'Головний адміністратор', 'sub' => 'Власник платформи з усіма правами. Такий акаунт може бути лише один.', 'error' => $error]) ?>
  <div class="grid2">
    <div class="field<?= isset($f['login']) ? ' err' : '' ?>"><label for="login">Логін</label><input id="login" name="login" class="input" value="<?= e($d['login']) ?>" autocomplete="username" required><span class="hint">Латиниця, цифри, . _ - (3–32)</span><?= $fe('login') ?></div>
    <div class="field<?= isset($f['displayName']) ? ' err' : '' ?>"><label for="displayName">Ім’я</label><input id="displayName" name="displayName" class="input" value="<?= e($d['displayName']) ?>" required><?= $fe('displayName') ?></div>
  </div>
  <div class="grid2">
    <div class="field<?= isset($f['email']) ? ' err' : '' ?>"><label for="email">Email</label><input id="email" name="email" type="email" class="input" value="<?= e($d['email']) ?>"><span class="hint">Для відновлення доступу</span><?= $fe('email') ?></div>
    <div class="field<?= isset($f['phone']) ? ' err' : '' ?>"><label for="phone">Телефон</label><input id="phone" name="phone" type="tel" class="input" value="<?= e($d['phone']) ?>" placeholder="067 123 45 67"><?= $fe('phone') ?></div>
  </div>
  <div class="grid2">
    <div class="field<?= isset($f['password']) ? ' err' : '' ?>"><label for="pw">Пароль</label><input id="pw" name="password" type="password" class="input" autocomplete="new-password" required><?= $fe('password') ?></div>
    <div class="field<?= isset($f['password2']) ? ' err' : '' ?>"><label for="pw2">Повторіть пароль</label><input id="pw2" name="password2" type="password" class="input" autocomplete="new-password" required><?= $fe('password2') ?></div>
  </div>
  <ul class="ist-pw" id="pwr"><li data-r="len">Щонайменше 12 символів</li><li data-r="case">Великі й малі літери</li><li data-r="dig">Хоча б одна цифра</li><li data-r="eq">Паролі збігаються</li></ul>
  <label class="check"><input type="checkbox" id="show"> Показати пароль</label>
  <div class="alert warn">Після першого входу система попросить увімкнути двофакторну автентифікацію для адміністраторів.</div>
  <div class="ist-actions"><a class="btn" href="<?= url('/install', ['step' => 4]) ?>">Назад</a><button class="btn pri">Далі</button></div>
</form>
<script>
(function(){var p=document.getElementById('pw'),q=document.getElementById('pw2'),l=document.querySelectorAll('#pwr li');
function u(){var v=p.value,r={len:v.length>=12,case:/\p{Ll}/u.test(v)&&/\p{Lu}/u.test(v),dig:/\d/.test(v),eq:v!==''&&v===q.value};l.forEach(function(li){li.className=r[li.dataset.r]?'ok':'';li.textContent=(r[li.dataset.r]?'✓ ':'')+li.textContent.replace(/^✓ /,'')})}
p.oninput=q.oninput=u;document.getElementById('show').onchange=function(){p.type=q.type=this.checked?'text':'password'};})();
</script>
