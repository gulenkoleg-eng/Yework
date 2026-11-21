<div class="adm-h"><h1>Налаштування</h1></div>
<?= form_alert() ?>
<form class="card pad stack" method="post" action="<?= url('/admin/settings') ?>"><?= csrf_field() ?><input type="hidden" name="section" value="site">
  <h3>Сайт</h3>
  <div class="grid2"><?= input_field('name', 'Назва', ['value' => $site['name']]) ?><?= input_field('slogan', 'Слоган', ['value' => $site['slogan']]) ?></div>
  <?= input_field('url', 'Адреса сайту', ['value' => $site['url']]) ?>
  <label class="check"><input type="checkbox" name="pre_jobs" value="1" <?= !empty($pre['jobs']) ? 'checked' : '' ?>> Премодерація вакансій (публікація лише після перевірки модератором)</label>
  <div><button class="btn pri">Зберегти</button></div>
</form>
<form class="card pad stack" method="post" action="<?= url('/admin/settings') ?>"><?= csrf_field() ?><input type="hidden" name="section" value="logo">
  <h3>Логотипи роботодавців</h3>
  <p class="small muted">Вакансії з логотипом виділяються в списку. Підказка показується в кабінеті роботодавцям, які ще не додали логотип.</p>
  <label class="check"><input type="checkbox" name="hint" value="1" <?= $logo['hint'] ? 'checked' : '' ?>> Показувати підказку «З логотипом вас помітять швидше»</label>
  <?= input_field('hintText', 'Текст підказки', ['type' => 'textarea', 'rows' => 2, 'value' => $logo['hintText']]) ?>
  <label class="check"><input type="checkbox" name="perk" value="1" <?= $logo['perk'] ? 'checked' : '' ?>> Бонус за логотип (додатково до підказки)</label>
  <?= input_field('perkText', 'Який бонус отримає роботодавець', ['value' => $logo['perkText'], 'placeholder' => 'Наприклад: перший банер на 7 днів — у подарунок', 'hint' => 'Поки бонус не визначено, залиште вимкненим. Текст побачить роботодавець у підказці.']) ?>
  <div><button class="btn pri">Зберегти</button></div>
</form>
<form class="card pad stack" method="post" action="<?= url('/admin/settings') ?>"><?= csrf_field() ?><input type="hidden" name="section" value="email">
  <h3>Пошта (коди підтвердження, сповіщення)</h3>
  <?= input_field('driver', 'Спосіб надсилання', ['type' => 'select', 'value' => $email['driver'] ?? 'mail', 'options' => ['mail' => 'Пошта хостингу (функція mail)', 'smtp' => 'SMTP-сервер (Gmail, Ukr.net, поштова скринька хостингу)', 'log' => 'Не надсилати, лише записувати (для перевірки)']]) ?>
  <div class="grid2"><?= input_field('host', 'SMTP-сервер', ['value' => $email['host'] ?? '', 'placeholder' => 'mail.ваш-домен.ua']) ?><?= input_field('port', 'Порт', ['value' => $email['port'] ?? 587, 'inputmode' => 'numeric']) ?></div>
  <div class="grid2"><?= input_field('secure', 'Шифрування', ['type' => 'select', 'value' => $email['secure'] ?? 'tls', 'options' => ['tls' => 'STARTTLS (587)', 'ssl' => 'SSL (465)', 'none' => 'Без шифрування']]) ?><?= input_field('user', 'Логін SMTP', ['value' => $email['user'] ?? '', 'autocomplete' => 'off']) ?></div>
  <div class="grid2"><?= input_field('pass', 'Пароль SMTP', ['type' => 'password', 'autocomplete' => 'new-password', 'hint' => !empty($email['passEnc']) ? 'Збережено. Залиште порожнім, щоб не змінювати' : '']) ?><?= input_field('from', 'Адреса відправника', ['value' => $email['from'] ?? '', 'placeholder' => 'no-reply@ваш-домен.ua']) ?></div>
  <?= input_field('test_to', 'Надіслати тестовий лист на (необов’язково)', ['type' => 'email']) ?>
  <div><button class="btn pri">Зберегти</button></div>
</form>
<div class="card pad stack"><h3>SMS</h3><p class="muted">Провайдера ще не обрано. Коли оберете (TurboSMS, AlphaSMS, Kyivstar тощо), підключимо його без змін у решті сайту. Поки що SMS-коди видно в розділі «Повідомлення».</p></div>
<div class="card pad stack"><h3>Оновлення</h3>
  <p>Версія: <b><?= e($version) ?></b>. <?= $pendingMigrations ? 'Є нові зміни структури бази: ' . e(implode(', ', $pendingMigrations)) : 'База даних актуальна.' ?></p>
  <p class="small muted">Нові версії тепер встановлюються з розділу <a href="<?= url('/admin/updates') ?>">«Оновлення»</a>. Якщо ви завантажили файли по FTP, база оновиться автоматично, а ця кнопка — на всякий випадок.</p>
  <form method="post" action="<?= url('/admin/upgrade') ?>"><?= csrf_field() ?><button class="btn <?= $pendingMigrations ? 'pri' : '' ?>">Оновити базу</button></form>
</div>
