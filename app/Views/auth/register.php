<div class="auth"><div class="card auth-card">
  <div class="tabs"><a href="<?= url('/login') ?>">Вхід</a><a class="on" href="<?= url('/register') ?>">Реєстрація</a></div>
  <?php if (!$st): ?>
    <form method="post" action="<?= url('/register') ?>" class="stack"><?= csrf_field() ?><input type="hidden" name="stage" value="contact">
      <h1>Створити акаунт</h1>
      <p class="muted">Один акаунт для пошуку роботи й розміщення вакансій. Надішлемо код підтвердження.</p>
      <?= form_alert() ?>
      <?= input_field('contact', 'Телефон або email', ['required' => true, 'autofocus' => true, 'placeholder' => '067 123 45 67 або name@gmail.com', 'autocomplete' => 'username']) ?>
      <button class="btn pri block">Отримати код</button>
    </form>
  <?php else: ?>
    <form method="post" action="<?= url('/register') ?>" class="stack"><?= csrf_field() ?><input type="hidden" name="stage" value="finish">
      <h1>Підтвердження</h1>
      <div class="alert info"><div>Код надіслано на <b><?= e(mask_contact($st['contact'])) ?></b>. Він діє 10 хвилин. <a href="<?= url('/register', ['restart' => 1]) ?>">Змінити</a></div></div>
      <?= form_alert() ?>
      <?= input_field('code', 'Код із повідомлення', ['required' => true, 'inputmode' => 'numeric', 'autocomplete' => 'one-time-code', 'maxlength' => 6, 'placeholder' => '••••••', 'autofocus' => true]) ?>
      <?= input_field('displayName', 'Ваше ім’я', ['required' => true, 'autocomplete' => 'name', 'placeholder' => 'Ім’я та прізвище']) ?>
      <?= input_field('password', 'Придумайте пароль', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'hint' => 'Щонайменше 8 символів, літери та цифри']) ?>
      <div class="field<?= err_class('terms') ?>"><label class="check"><input type="checkbox" name="terms" value="1" <?= !empty(App\Core\App::$old['terms']) ? 'checked' : '' ?>> <span>Погоджуюся з <a href="<?= url('/rules') ?>" target="_blank">правилами сайту</a> та обробкою персональних даних</span></label><?= field_error('terms') ?></div>
      <button class="btn pri block">Створити акаунт</button>
    </form>
    <form method="post" action="<?= url('/register') ?>"><?= csrf_field() ?><input type="hidden" name="stage" value="contact"><input type="hidden" name="contact" value="<?= e($st['contact']) ?>"><button class="btn ghost block small">Надіслати код ще раз</button></form>
  <?php endif ?>
</div></div>
