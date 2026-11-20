<div class="auth"><form class="card auth-card" method="post" action="<?= url('/login') ?>">
  <?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
  <div class="tabs"><a class="on" href="<?= url('/login') ?>">Вхід</a><a href="<?= url('/register') ?>">Реєстрація</a></div>
  <h1>Вхід</h1>
  <?= form_alert() ?>
  <?= input_field('login', 'Телефон, email або логін', ['autocomplete' => 'username', 'required' => true, 'autofocus' => true, 'placeholder' => '067 123 45 67']) ?>
  <?= input_field('password', 'Пароль', ['type' => 'password', 'autocomplete' => 'current-password', 'required' => true]) ?>
  <button class="btn pri block">Увійти</button>
  <a href="<?= url('/recover', ['restart' => 1]) ?>" class="small" style="text-align:center">Забули пароль?</a>
</form></div>
