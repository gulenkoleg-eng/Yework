<div class="cab">
  <?= App\Core\View::partial('partials/cab_nav', compact('tab', 'counts', 'me')) ?>
  <div class="stack">
    <h1 style="font-size:28px">Вітаємо, <?= e($me['display_name']) ?></h1>
    <div class="dirs" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
      <a class="card dir" href="<?= url('/cabinet/resume') ?>"><span class="ic"><?= icon('user', 22) ?></span><h3>Шукаю роботу</h3><p><?= ($resume ?? null) ? 'Моє резюме: ' . e(App\Core\Resumes::STATUS[$resume['status']][0] ?? '') . '. Відгукуйтеся на вакансії з резюме.' : 'Створіть резюме з фото, і роботодавці знайдуть вас самі.' ?></p></a>
      <a class="card dir" href="<?= url('/jobs') ?>"><span class="ic"><?= icon('search', 22) ?></span><h3>Вакансії</h3><p>Перегляньте вакансії і відгукніться в один клік.</p></a>
      <a class="card dir" href="<?= url('/cabinet/vacancies/new') ?>"><span class="ic"><?= icon('plus', 22) ?></span><h3>Шукаю працівника</h3><p><?= $employer ? 'Розмістіть нову вакансію.' : 'Заповніть профіль роботодавця й розмістіть вакансію.' ?></p></a>
      <a class="card dir" href="<?= url('/cabinet/master') ?>"><span class="ic"><?= icon('tool', 22) ?></span><h3>Надаю послуги</h3><p><?= ($master ?? null) ? 'Мій профіль майстра: ' . e(App\Core\Services::STATUS[$master['status']][0] ?? '') . '. Прайс, фото робіт, замовлення.' : 'Майстер чи ремонтник? Додайте прайс і фото робіт — клієнти замовлять через сайт.' ?></p></a>
    </div>
    <form class="card pad stack" method="post" action="<?= url('/cabinet/profile') ?>"><?= csrf_field() ?>
      <h2 style="font-size:20px">Профіль</h2>
      <?= input_field('displayName', 'Ім’я', ['value' => $me['display_name'], 'required' => true]) ?>
      <dl class="kv"><dt>Телефон</dt><dd><?= $phone ? e(phone_pretty($phone)) . ' <span class="tag ok">підтверджено</span>' : '<span class="muted">не вказано</span>' ?></dd><dt>Email</dt><dd><?= $email ? e($email) . ($me['email_verified_at'] ? ' <span class="tag ok">підтверджено</span>' : '') : '<span class="muted">не вказано</span>' ?></dd><dt>Логін</dt><dd class="mono"><?= e($me['login']) ?></dd></dl>
      <div><button class="btn pri">Зберегти</button></div>
    </form>
    <form class="card pad stack" method="post" action="<?= url('/cabinet/password') ?>"><?= csrf_field() ?>
      <h2 style="font-size:20px">Зміна пароля</h2>
      <?= form_alert() ?>
      <div class="grid2"><?= input_field('current', 'Поточний пароль', ['type' => 'password', 'autocomplete' => 'current-password', 'required' => true]) ?><?= input_field('password', 'Новий пароль', ['type' => 'password', 'autocomplete' => 'new-password', 'required' => true, 'hint' => $me['is_staff'] ? 'Для адміністраторів — від 12 символів' : 'Від 8 символів, літери та цифри']) ?></div>
      <div><button class="btn">Змінити пароль</button></div>
    </form>
  </div>
</div>
