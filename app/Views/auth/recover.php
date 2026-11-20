<div class="auth"><div class="card auth-card">
  <?php if (!$st): ?>
    <form method="post" action="<?= url('/recover') ?>" class="stack"><?= csrf_field() ?><input type="hidden" name="stage" value="contact">
      <h1>Відновлення доступу</h1>
      <p class="muted">Вкажіть телефон або email, з якими ви реєструвалися. Надішлемо код для зміни пароля.</p>
      <?= form_alert() ?>
      <?= input_field('contact', 'Телефон або email', ['required' => true, 'autofocus' => true, 'placeholder' => '067 123 45 67']) ?>
      <button class="btn pri block">Надіслати код</button>
      <a class="small" style="text-align:center" href="<?= url('/login') ?>">Згадали пароль? Увійти</a>
    </form>
  <?php else: ?>
    <form method="post" action="<?= url('/recover') ?>" class="stack"><?= csrf_field() ?><input type="hidden" name="stage" value="finish">
      <h1>Новий пароль</h1>
      <div class="alert info"><div>Якщо <b><?= e(mask_contact($st['contact'])) ?></b> зареєстровано, ми надіслали туди код. <a href="<?= url('/recover', ['restart' => 1]) ?>">Інший контакт</a></div></div>
      <?= form_alert() ?>
      <?= input_field('code', 'Код із повідомлення', ['required' => true, 'inputmode' => 'numeric', 'autocomplete' => 'one-time-code', 'maxlength' => 6, 'autofocus' => true]) ?>
      <?= input_field('password', 'Новий пароль', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'hint' => 'Щонайменше 8 символів (для адміністраторів — 12), літери та цифри']) ?>
      <button class="btn pri block">Змінити пароль і увійти</button>
    </form>
    <form method="post" action="<?= url('/recover') ?>"><?= csrf_field() ?><input type="hidden" name="stage" value="contact"><input type="hidden" name="contact" value="<?= e($st['contact']) ?>"><button class="btn ghost block small">Надіслати код ще раз</button></form>
  <?php endif ?>
</div></div>
