<?php
/**
 * Налаштування: розділи (Основне, Модерація, Вигляд, Роботодавці, Сповіщення, Система),
 * у кожному згортальні блоки зі своєю кнопкою «Зберегти». Згори навігація розділами й пошук.
 * Який блок відкритий, браузер пам’ятає сам (localStorage), тож сторінка відкривається так, як ви її залишили.
 */
$saved = preg_replace('/[^a-z]/', '', (string) ($_GET['saved'] ?? ''));
$driverNames = ['mail' => 'Пошта хостингу', 'smtp' => 'SMTP', 'log' => 'Лише запис'];
$onOff = static fn (bool $on, string $yes, string $no) => '<span class="tag ' . ($on ? 'ok' : 'muted') . '">' . ($on ? $yes : $no) . '</span>';
$groups = [
  'main' => ['Основне', 'Назва, слоган і адреса сайту.'],
  'moderation' => ['Модерація', 'Що перевіряє модератор перед публікацією.'],
  'look' => ['Вигляд', 'Як сайт виглядає у браузері.'],
  'employers' => ['Роботодавці', 'Підказки й бонуси для роботодавців.'],
  'notify' => ['Сповіщення', 'Як сайт надсилає коди й листи.'],
  'system' => ['Система', 'Версія та база даних.'],
];
/** Відкриває блок: заголовок, опис, позначки стану праворуч. */
$open = static function (string $id, string $group, string $title, string $desc, string $badges = '', string $keys = '') use ($saved): string {
  $isSaved = $saved === $id;
  return '<details class="card set-blk" id="set-' . $id . '" data-group="' . $group . '" data-keys="' . e($keys) . '"' . ($isSaved ? ' open' : '') . '>'
    . '<summary><span class="set-blk-t"><b>' . e($title) . '</b><span class="small muted">' . e($desc) . '</span></span>'
    . '<span class="set-blk-b">' . ($isSaved ? '<span class="tag ok">✓ Збережено</span>' : '') . $badges . '</span></summary><div class="set-blk-in stack">';
};
$close = '</div></details>';
?>
<div class="adm-h"><h1>Налаштування</h1><span class="spacer"></span>
  <div class="set-search"><input class="input" type="search" id="setQ" placeholder="Знайти налаштування…" aria-label="Знайти налаштування" autocomplete="off"></div>
  <button type="button" class="btn sm" id="setAll">Розгорнути все</button>
</div>
<?= form_alert() ?>
<nav class="set-nav" aria-label="Розділи налаштувань">
  <?php foreach ($groups as $g => [$name]): ?><a href="#grp-<?= $g ?>" data-g="<?= $g ?>"><?= e($name) ?></a><?php endforeach ?>
</nav>
<p class="set-none muted" id="setNone" hidden>Нічого не знайдено. Спробуйте інше слово, наприклад «пошта», «іконка» чи «логотип».</p>

<?php $grp = static fn (string $g) => '<section class="set-grp" id="grp-' . $g . '" data-group="' . $g . '"><div class="set-grp-h"><h2>' . e($groups[$g][0]) . '</h2><span class="small muted">' . e($groups[$g][1]) . '</span></div>'; ?>

<?= $grp('main') ?>
  <?= $open('site', 'main', 'Сайт', 'Назва, слоган і адреса, які бачать відвідувачі.', '<span class="tag muted">' . e($site['name']) . '</span>', 'назва слоган адреса домен url сайт') ?>
    <form class="stack" method="post" action="<?= url('/admin/settings') ?>"><?= csrf_field() ?><input type="hidden" name="section" value="site">
      <div class="grid2"><?= input_field('name', 'Назва', ['value' => $site['name']]) ?><?= input_field('slogan', 'Слоган', ['value' => $site['slogan']]) ?></div>
      <?= input_field('url', 'Адреса сайту', ['value' => $site['url']]) ?>
      <div><button class="btn pri">Зберегти</button></div>
    </form>
  <?= $close ?>
</section>

<?= $grp('moderation') ?>
  <?php $preOn = (int) !empty($pre['jobs']) + (int) ($pre['services'] ?? true); ?>
  <?= $open('moderation', 'moderation', 'Премодерація', 'Вакансії й майстри з’являються на сайті лише після перевірки.', '<span class="tag ' . ($preOn ? 'ok' : 'muted') . '">' . ($preOn === 2 ? 'Усе перевіряється' : ($preOn ? 'Частково' : 'Вимкнено')) . '</span>', 'премодерація перевірка модератор вакансії майстри послуги') ?>
    <form class="stack" method="post" action="<?= url('/admin/settings') ?>"><?= csrf_field() ?><input type="hidden" name="section" value="moderation">
      <label class="check"><input type="checkbox" name="pre_jobs" value="1" <?= !empty($pre['jobs']) ? 'checked' : '' ?>> Премодерація вакансій (публікація лише після перевірки модератором)</label>
      <label class="check"><input type="checkbox" name="pre_services" value="1" <?= ($pre['services'] ?? true) ? 'checked' : '' ?>> Премодерація майстрів (профіль майстра з’являється в «Послугах» лише після перевірки; подальші зміни видно одразу, а модератор бачить їх у вкладці «Змінені»)</label>
      <div><button class="btn pri">Зберегти</button></div>
    </form>
  <?= $close ?>
</section>

<?= $grp('look') ?>
  <?= $open('favicon', 'look', 'Іконка сайту', 'Картинка у вкладці браузера, у закладках і на екрані телефона.', '<img src="' . e($favicon['preview']) . '" alt="" width="20" height="20" class="set-fav"><span class="tag muted">' . ($favicon['custom'] ? 'Своя' : 'Стандартна') . '</span>', 'іконка favicon фавікон вкладка картинка png') ?>
    <form class="stack" method="post" action="<?= url('/admin/settings') ?>" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="section" value="favicon">
      <div class="row" style="align-items:center;gap:16px;flex-wrap:wrap">
        <img src="<?= e($favicon['preview']) ?>" alt="Іконка сайту" width="64" height="64" style="border-radius:14px;flex:none">
        <img src="<?= e($favicon['preview']) ?>" alt="" width="16" height="16" style="flex:none">
        <span class="small muted"><?= $favicon['custom'] ? 'Стоїть ваша іконка.' : 'Стоїть стандартна іконка «YE» у кольорах сайту.' ?></span>
      </div>
      <div class="field<?= err_class('icon') ?>"><label for="f_icon">Завантажити свою</label><input id="f_icon" type="file" name="icon" accept="image/png,image/jpeg,image/webp"><span class="hint">Квадратна картинка PNG, краще 512×512, до 3 МБ. Простий знак або літери, без дрібного тексту: у вкладці її видно розміром з ніготь.</span><?= field_error('icon') ?></div>
      <div class="row" style="gap:8px;flex-wrap:wrap"><button class="btn pri">Зберегти іконку</button><?php if ($favicon['custom']): ?><button class="btn" name="reset" value="1">Повернути стандартну</button><?php endif ?></div>
    </form>
  <?= $close ?>
  <a class="card set-link" href="<?= url('/admin/themes') ?>" data-group="look" data-keys="тема теми оформлення кольори новорічна темна світла"><span class="set-blk-t"><b>Теми оформлення</b><span class="small muted">Кольори, шрифти й святкова тема налаштовуються в окремому розділі.</span></span></a>
</section>

<?= $grp('employers') ?>
  <?= $open('logo', 'employers', 'Логотипи роботодавців', 'Підказка в кабінеті тим, хто ще не додав логотип.', $onOff((bool) $logo['hint'], 'Підказка ввімкнена', 'Підказка вимкнена') . ($logo['perk'] ? '<span class="tag ok">Є бонус</span>' : ''), 'логотип лого роботодавці компанія підказка бонус') ?>
    <form class="stack" method="post" action="<?= url('/admin/settings') ?>"><?= csrf_field() ?><input type="hidden" name="section" value="logo">
      <p class="small muted">Вакансії з логотипом виділяються в списку. Підказка показується в кабінеті роботодавцям, які ще не додали логотип.</p>
      <label class="check"><input type="checkbox" name="hint" value="1" <?= $logo['hint'] ? 'checked' : '' ?>> Показувати підказку «З логотипом вас помітять швидше»</label>
      <?= input_field('hintText', 'Текст підказки', ['type' => 'textarea', 'rows' => 2, 'value' => $logo['hintText']]) ?>
      <label class="check"><input type="checkbox" name="perk" value="1" <?= $logo['perk'] ? 'checked' : '' ?>> Бонус за логотип (додатково до підказки)</label>
      <?= input_field('perkText', 'Який бонус отримає роботодавець', ['value' => $logo['perkText'], 'placeholder' => 'Наприклад: перший банер на 7 днів — у подарунок', 'hint' => 'Поки бонус не визначено, залиште вимкненим. Текст побачить роботодавець у підказці.']) ?>
      <div><button class="btn pri">Зберегти</button></div>
    </form>
  <?= $close ?>
</section>

<?= $grp('notify') ?>
  <?= $open('email', 'notify', 'Пошта', 'Коди підтвердження та сповіщення на email.', '<span class="tag ' . (($email['driver'] ?? 'mail') === 'log' ? 'warn' : 'muted') . '">' . e($driverNames[$email['driver'] ?? 'mail'] ?? 'Пошта хостингу') . '</span>', 'пошта email лист smtp сервер порт пароль відправник тестовий') ?>
    <form class="stack" method="post" action="<?= url('/admin/settings') ?>"><?= csrf_field() ?><input type="hidden" name="section" value="email">
      <?= input_field('driver', 'Спосіб надсилання', ['type' => 'select', 'value' => $email['driver'] ?? 'mail', 'options' => ['mail' => 'Пошта хостингу (функція mail)', 'smtp' => 'SMTP-сервер (Gmail, Ukr.net, поштова скринька хостингу)', 'log' => 'Не надсилати, лише записувати (для перевірки)']]) ?>
      <div class="stack" data-smtp>
        <div class="grid2"><?= input_field('host', 'SMTP-сервер', ['value' => $email['host'] ?? '', 'placeholder' => 'mail.ваш-домен.ua']) ?><?= input_field('port', 'Порт', ['value' => $email['port'] ?? 587, 'inputmode' => 'numeric']) ?></div>
        <div class="grid2"><?= input_field('secure', 'Шифрування', ['type' => 'select', 'value' => $email['secure'] ?? 'tls', 'options' => ['tls' => 'STARTTLS (587)', 'ssl' => 'SSL (465)', 'none' => 'Без шифрування']]) ?><?= input_field('user', 'Логін SMTP', ['value' => $email['user'] ?? '', 'autocomplete' => 'off']) ?></div>
        <?= input_field('pass', 'Пароль SMTP', ['type' => 'password', 'autocomplete' => 'new-password', 'hint' => !empty($email['passEnc']) ? 'Збережено. Залиште порожнім, щоб не змінювати' : '']) ?>
      </div>
      <?= input_field('from', 'Адреса відправника', ['value' => $email['from'] ?? '', 'placeholder' => 'no-reply@ваш-домен.ua']) ?>
      <?= input_field('test_to', 'Надіслати тестовий лист на (необов’язково)', ['type' => 'email']) ?>
      <div><button class="btn pri">Зберегти</button></div>
    </form>
  <?= $close ?>
  <?= $open('sms', 'notify', 'SMS', 'Коди підтвердження на телефон.', '<span class="tag muted">Не підключено</span>', 'sms смс телефон turbosms alphasms kyivstar') ?>
    <p class="muted">Провайдера ще не обрано. Коли оберете (TurboSMS, AlphaSMS, Kyivstar тощо), підключимо його без змін у решті сайту. Поки що SMS-коди видно в розділі <a href="<?= url('/admin/outbox') ?>">«Повідомлення»</a>.</p>
  <?= $close ?>
  <a class="card set-link" href="<?= url('/admin/telegram') ?>" data-group="notify" data-keys="telegram телеграм бот група канал"><span class="set-blk-t"><b>Telegram</b><span class="small muted">Бот і публікації в групу налаштовуються в окремому розділі.</span></span></a>
</section>

<?= $grp('system') ?>
  <?= $open('db', 'system', 'База даних', 'Оновлення структури бази після завантаження файлів по FTP.', '<span class="tag muted">Версія ' . e($version) . '</span><span class="tag ' . ($pendingMigrations ? 'warn' : 'ok') . '">' . ($pendingMigrations ? 'Потрібне оновлення' : 'Актуальна') . '</span>', 'оновлення база даних версія міграції ftp') ?>
    <p>Версія: <b><?= e($version) ?></b>. <?= $pendingMigrations ? 'Є нові зміни структури бази: ' . e(implode(', ', $pendingMigrations)) : 'База даних актуальна.' ?></p>
    <p class="small muted">Нові версії встановлюються з розділу <a href="<?= url('/admin/updates') ?>">«Оновлення»</a>. Якщо ви завантажили файли по FTP, база оновиться автоматично, а ця кнопка — на всякий випадок.</p>
    <form method="post" action="<?= url('/admin/upgrade') ?>"><?= csrf_field() ?><button class="btn <?= $pendingMigrations ? 'pri' : '' ?>">Оновити базу</button></form>
  <?= $close ?>
</section>

<script>
(function () {
  var KEY = 'yw.settings.open', blocks = [].slice.call(document.querySelectorAll('.set-blk')), items = [].slice.call(document.querySelectorAll('.set-blk, .set-link'));
  var store = function (v) { try { if (v === undefined) return JSON.parse(localStorage.getItem(KEY) || '[]'); localStorage.setItem(KEY, JSON.stringify(v)); } catch (e) { return []; } };
  var searching = false;
  // Відновлюємо, які блоки були відкриті минулого разу. Блок, який щойно зберегли або в якому помилка, відкриваємо завжди.
  var remembered = store();
  blocks.forEach(function (d) {
    if (remembered.indexOf(d.id) !== -1 || d.querySelector('.ferr, .err')) d.open = true;
    d.addEventListener('toggle', function () { if (!searching) { store(blocks.filter(function (b) { return b.open; }).map(function (b) { return b.id; })); syncAll(); } });
  });
  // Позначка «Збережено» показується один раз: прибираємо ?saved з адреси, щоб після оновлення сторінки її не було.
  if (/[?&]saved=/.test(location.search) && history.replaceState) history.replaceState(null, '', location.pathname + location.hash);
  var target = location.hash && document.getElementById(location.hash.slice(1));
  if (target && target.classList.contains('set-blk')) { target.open = true; setTimeout(function () { target.scrollIntoView({ block: 'start' }); }, 0); }

  // «Розгорнути все» / «Згорнути все».
  var allBtn = document.getElementById('setAll');
  function syncAll() { allBtn.textContent = blocks.every(function (b) { return b.open; }) ? 'Згорнути все' : 'Розгорнути все'; }
  allBtn.addEventListener('click', function () { var open = !blocks.every(function (b) { return b.open; }); blocks.forEach(function (b) { b.open = open; }); });
  syncAll();

  // Навігація розділами: перехід відкриває блоки розділу, активний розділ підсвічується під час прокручування.
  var nav = [].slice.call(document.querySelectorAll('.set-nav a')), groups = [].slice.call(document.querySelectorAll('.set-grp'));
  nav.forEach(function (a) { a.addEventListener('click', function () { var g = document.getElementById('grp-' + a.dataset.g); g.querySelectorAll('.set-blk').forEach(function (b) { b.open = true; }); }); });
  function spy() {
    var cur = groups[0], y = window.innerHeight * 0.35;
    groups.forEach(function (g) { if (!g.hidden && g.getBoundingClientRect().top <= y) cur = g; });
    if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4) { var vis = groups.filter(function (g) { return !g.hidden; }); cur = vis[vis.length - 1] || cur; }
    nav.forEach(function (a) { a.classList.toggle('on', cur && a.dataset.g === cur.dataset.group); });
  }
  window.addEventListener('scroll', spy, { passive: true }); spy();

  // Пошук: показує лише блоки, де є слово (у назві, описі, підписах полів чи ключових словах), і розгортає їх.
  var q = document.getElementById('setQ'), none = document.getElementById('setNone'), before = null;
  q.addEventListener('input', function () {
    var s = q.value.trim().toLowerCase();
    if (s && !searching) { searching = true; before = blocks.map(function (b) { return b.open; }); }
    if (!s) {
      searching = false; items.forEach(function (i) { i.hidden = false; }); groups.forEach(function (g) { g.hidden = false; }); nav.forEach(function (a) { a.hidden = false; });
      if (before) blocks.forEach(function (b, i) { b.open = before[i]; }); before = null; none.hidden = true; syncAll(); spy(); return;
    }
    items.forEach(function (i) { var hit = (i.textContent + ' ' + (i.dataset.keys || '')).toLowerCase().indexOf(s) !== -1; i.hidden = !hit; if (hit && i.tagName === 'DETAILS') i.open = true; });
    var any = false;
    groups.forEach(function (g) { var vis = !!g.querySelector('.set-blk:not([hidden]), .set-link:not([hidden])'); g.hidden = !vis; any = any || vis; });
    nav.forEach(function (a) { a.hidden = document.getElementById('grp-' + a.dataset.g).hidden; });
    none.hidden = any; spy();
  });
  document.addEventListener('keydown', function (e) { if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') { e.preventDefault(); q.focus(); } });

  // Пошта: поля SMTP показуємо лише коли обрано SMTP.
  var drv = document.querySelector('#set-email [name=driver]'), smtp = document.querySelector('#set-email [data-smtp]');
  if (drv && smtp) { var sync = function () { smtp.hidden = drv.value !== 'smtp'; }; drv.addEventListener('change', sync); sync(); }
})();
</script>
