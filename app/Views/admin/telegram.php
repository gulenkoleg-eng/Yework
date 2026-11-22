<?php
/** @var array $cfg @var bool $ready @var ?array $account @var ?string $linkUrl @var bool $canManage @var bool $canModerate @var array $staff @var ?array $info @var string $currentUrl */
$bot = $cfg['botUsername'] !== '' ? '@' . $cfg['botUsername'] : '';
$hookOk = $ready && $cfg['webhookAt'];
$domainChanged = $hookOk && $cfg['siteUrl'] !== '' && rtrim($cfg['siteUrl'], '/') !== rtrim($currentUrl, '/');
?>
<div class="adm-h"><h1>Telegram</h1><?php if ($bot): ?><span class="tag <?= $hookOk ? 'ok' : 'warn' ?>"><?= e($bot) ?> · <?= $hookOk ? 'підключено' : 'не підключено' ?></span><?php endif ?></div>
<?= form_alert() ?>

<div class="card pad stack">
  <h3>Мій Telegram</h3>
  <?php if ($account): ?>
    <p>Прив’язано: <b><?= e($account['tg_username'] ? '@' . $account['tg_username'] : ($account['tg_name'] ?: 'Telegram')) ?></b> · з <?= e(local_date($account['linked_at'], 'd.m.Y')) ?>.</p>
    <p class="small muted"><?= $canModerate ? 'Ви можете схвалювати й відхиляти вакансії та резюме кнопками під повідомленнями бота. Команда /pending у боті покаже чергу.' : 'У вашої ролі немає права модерації, тому кнопки модерації для вас не діятимуть.' ?></p>
    <form method="post" action="<?= url('/admin/telegram/me') ?>"><?= csrf_field() ?><input type="hidden" name="do" value="unlink"><button class="btn sm ghost">Відв’язати</button></form>
  <?php elseif (!$ready): ?>
    <p class="muted">Бота ще не налаштовано<?= $canManage ? ' — зробіть це нижче.' : '. Попросіть головного адміністратора.' ?></p>
  <?php elseif ($linkUrl): ?>
    <p>Залишився один крок: відкрийте бота і натисніть у ньому <b>«Start»</b> (або «Запустити»). Посилання діє 15 хвилин.</p>
    <div class="row"><a class="btn pri" href="<?= e($linkUrl) ?>" target="_blank" rel="noopener">Відкрити <?= e($bot) ?> у Telegram</a>
      <a class="btn sm ghost" href="<?= url('/admin/telegram') ?>">Я натиснув «Start», оновити</a></div>
  <?php else: ?>
    <p>Прив’яжіть свій Telegram, щоб модерувати оголошення прямо з групи або з чату з ботом. Дії обмежені правами вашої ролі й записуються в журнал.</p>
    <form method="post" action="<?= url('/admin/telegram/me') ?>"><?= csrf_field() ?><input type="hidden" name="do" value="link"><button class="btn pri">Прив’язати Telegram</button></form>
  <?php endif ?>
</div>

<?php if ($canManage): ?>

<?php if (!$ready): ?>
<div class="card pad stack">
  <h3>Як створити бота (2 хвилини)</h3>
  <ol class="stack" style="padding-left:20px;margin:0">
    <li>У Telegram відкрийте <a href="https://t.me/BotFather" target="_blank" rel="noopener"><b>@BotFather</b></a> (офіційний бот Telegram із синьою галочкою) і натисніть «Start».</li>
    <li>Надішліть йому команду <code>/newbot</code>.</li>
    <li>Напишіть назву бота, наприклад <i>YEWORK Модерація</i>.</li>
    <li>Напишіть ім’я користувача бота латиницею, яке закінчується на <code>bot</code>, наприклад <i>yework_moderation_bot</i>.</li>
    <li>BotFather надішле довгий <b>токен</b>, схожий на <code>1234567890:AAH…</code>. Скопіюйте його і вставте в поле нижче.</li>
  </ol>
  <p class="small muted">Токен — це пароль від бота. Нікому його не пересилайте, вставляйте лише сюди. На сайті він зберігається зашифрованим.</p>
</div>
<?php endif ?>

<form class="card pad stack" method="post" action="<?= url('/admin/telegram/manage') ?>" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="do" value="token">
  <h3>Бот</h3>
  <?php if ($ready): ?><p>Підключений бот: <b><?= e($bot) ?></b><?= $cfg['botName'] ? ' (' . e($cfg['botName']) . ')' : '' ?>. Щоб замінити бота, вставте новий токен.</p><?php endif ?>
  <?= input_field('token', 'Токен від @BotFather', ['type' => 'password', 'autocomplete' => 'new-password', 'placeholder' => '1234567890:AAH…', 'hint' => $ready ? 'Збережено. Залиште порожнім, якщо не змінюєте бота' : 'Після збереження бот одразу підключиться до цього сайту']) ?>
  <div><button class="btn pri"><?= $ready ? 'Замінити токен' : 'Зберегти і підключити' ?></button></div>
</form>

<?php if ($ready): ?>
<div class="card pad stack">
  <h3>Підключення до сайту</h3>
  <?php if ($hookOk): ?>
    <p>Бот надсилає запити на <b><?= e($cfg['siteUrl']) ?></b>.<?= $info && !empty($info['last_error_message']) ? '' : ' Усе гаразд.' ?></p>
    <?php if ($info && !empty($info['last_error_message'])): ?><div class="alert warn">Остання помилка зв’язку (<?= e(date('d.m H:i', (int) ($info['last_error_date'] ?? time()))) ?>): <?= e($info['last_error_message']) ?>. Спробуйте натиснути «Підключити до цього домену».</div><?php endif ?>
    <?php if ($info && ($info['url'] ?? '') === ''): ?><div class="alert warn">Telegram каже, що бот не підключений до жодного сайту. Натисніть «Підключити до цього домену».</div><?php endif ?>
  <?php else: ?>
    <p class="muted">Бот ще не підключений до сайту, кнопки в Telegram не працюватимуть.</p>
  <?php endif ?>
  <?php if ($domainChanged): ?><div class="alert warn">Ви зараз на іншій адресі: <b><?= e($currentUrl) ?></b>. Якщо сайт переїхав, натисніть «Підключити до цього домену».</div><?php endif ?>
  <p class="small muted">Коли сайт переїде на новий домен (наприклад, .ua), відкрийте цю сторінку вже на новому домені й натисніть кнопку ще раз. Більше нічого міняти не потрібно.</p>
  <div class="row">
    <form method="post" action="<?= url('/admin/telegram/manage') ?>"><?= csrf_field() ?><input type="hidden" name="do" value="webhook"><button class="btn <?= $hookOk && !$domainChanged ? '' : 'pri' ?>">Підключити до цього домену</button></form>
    <?php if ($hookOk): ?><form method="post" action="<?= url('/admin/telegram/manage') ?>"><?= csrf_field() ?><input type="hidden" name="do" value="webhook_off"><button class="btn ghost sm">Відключити</button></form><?php endif ?>
  </div>
</div>

<form class="card pad stack" method="post" action="<?= url('/admin/telegram/manage') ?>"><?= csrf_field() ?><input type="hidden" name="do" value="settings">
  <h3>Група для сповіщень</h3>
  <p class="small muted">Додайте <?= e($bot) ?> у вашу групу (у групі: назва → «Додати учасника» → знайдіть бота). Після цього група з’явиться нижче, достатньо її обрати. Або напишіть у групі <code>/id</code>, бот відповість номером групи.</p>
  <?php if ($cfg['seenChats']): ?>
    <div class="stack"><?php foreach ($cfg['seenChats'] as $cid => $ctitle): ?>
      <label class="check"><input type="radio" name="chat_id" value="<?= e((string) $cid) ?>" <?= (string) $cid === $cfg['chatId'] ? 'checked' : '' ?>> <?= e($ctitle ?: 'Група') ?> <span class="small muted mono"><?= e((string) $cid) ?></span></label>
    <?php endforeach ?>
    <label class="check"><input type="radio" name="chat_id" value="" <?= $cfg['chatId'] === '' ? 'checked' : '' ?>> Не надсилати в групу</label></div>
    <?php if ($cfg['chatId'] !== '' && !isset($cfg['seenChats'][$cfg['chatId']])): ?><p class="small">Зараз обрано: <span class="mono"><?= e($cfg['chatId']) ?></span></p><?php endif ?>
  <?php else: ?>
    <?= input_field('chat_id', 'ID групи', ['value' => $cfg['chatId'], 'placeholder' => '-1001234567890', 'inputmode' => 'numeric', 'hint' => 'Бот ще не бачив жодної групи. Додайте його в групу або впишіть ID вручну']) ?>
  <?php endif ?>
  <?php if ($cfg['chatId'] !== ''): ?>
  <div class="stack" style="gap:6px">
    <p><b>Куди пише бот у групі</b></p>
    <p class="small">Модерація: <b><?= e(App\Core\Telegram::topicLabel($cfg['topicModeration'] ? (int) $cfg['topicModeration'] : null)) ?></b> · Нові версії: <b><?= e(App\Core\Telegram::topicLabel($cfg['topicUpdates'] ? (int) $cfg['topicUpdates'] : null)) ?></b></p>
    <p class="small muted">Якщо в групі ввімкнено теми: відкрийте тему «Модерація» і напишіть у ній <code>/moderation</code>, потім у темі «Оновлення» напишіть <code>/updates</code>. Бот запам’ятає, куди що надсилати. Це може зробити адміністратор сайту, який прив’язав свій Telegram.</p>
  </div>
  <?php endif ?>
  <label class="check"><input type="checkbox" name="moderation" value="1" <?= $cfg['moderation'] ? 'checked' : '' ?>> Нові вакансії й резюме на модерації — у групу з кнопками «Схвалити» і «Відхилити»</label>
  <label class="check"><input type="checkbox" name="updates" value="1" <?= $cfg['updates'] ? 'checked' : '' ?>> Повідомляти про нову версію сайту і що в ній змінилось</label>
  <p class="small muted">Імена, фото й телефони шукачів у Telegram не надсилаються, лише посада, громада й досвід. Усе інше модератор бачить на сайті.</p>
  <div class="row"><button class="btn pri">Зберегти</button></div>
</form>

<?php if ($cfg['chatId'] !== ''): ?>
<div class="card pad stack">
  <h3>Перевірка</h3>
  <p class="small muted">«Очистити всі» видаляє з групи повідомлення бота: тести, новини про версії, модерацію. Telegram дозволяє боту видаляти лише повідомлення, яким менше 48 годин. Старіші, а також повідомлення, надіслані до версії 0.4.8 (крім модерації), доведеться видалити вручну.</p>
  <div class="row">
    <form method="post" action="<?= url('/admin/telegram/manage') ?>"><?= csrf_field() ?><input type="hidden" name="do" value="test"><button class="btn">Надіслати тест у групу</button></form>
    <?php if ($cfg['topicModeration'] || $cfg['topicUpdates']): ?><form method="post" action="<?= url('/admin/telegram/manage') ?>"><?= csrf_field() ?><input type="hidden" name="do" value="topic_reset"><button class="btn ghost sm">Писати в загальний чат</button></form><?php endif ?>
    <form method="post" action="<?= url('/admin/telegram/manage') ?>"><?= csrf_field() ?><input type="hidden" name="do" value="version"><button class="btn">Надіслати опис версії <?= e(YW_VERSION) ?></button></form>
    <form method="post" action="<?= url('/admin/telegram/manage') ?>" onsubmit="return confirm('Видалити з групи всі повідомлення бота? Це стосується і повідомлень модерації з кнопками. Відновити їх не можна.')"><?= csrf_field() ?><input type="hidden" name="do" value="clear"><button class="btn ghost">Очистити всі</button></form>
  </div>
</div>
<?php endif ?>

<div class="card pad stack">
  <h3>Хто прив’язав Telegram</h3>
  <?php if (!$staff): ?><p class="muted">Поки ніхто. Кожен працівник прив’язує свій Telegram на цій сторінці, у блоці «Мій Telegram».</p><?php else: ?>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Працівник</th><th>Telegram</th><th>Коли</th><th></th></tr></thead><tbody>
  <?php foreach ($staff as $s): ?><tr>
    <td><a href="<?= url('/admin/users/' . $s['user_id']) ?>"><?= e($s['display_name']) ?></a></td>
    <td><?= e($s['tg_username'] ? '@' . $s['tg_username'] : ($s['tg_name'] ?: '—')) ?></td>
    <td class="small muted"><?= e(local_date($s['linked_at'], 'd.m.Y')) ?></td>
    <td><form method="post" action="<?= url('/admin/telegram/manage') ?>"><?= csrf_field() ?><input type="hidden" name="do" value="unlink"><input type="hidden" name="user_id" value="<?= (int) $s['user_id'] ?>"><button class="btn sm ghost">Відв’язати</button></form></td>
  </tr><?php endforeach ?>
  </tbody></table></div>
  <?php endif ?>
</div>

<form class="card pad stack" method="post" action="<?= url('/admin/telegram/manage') ?>"><?= csrf_field() ?><input type="hidden" name="do" value="remove">
  <h3>Видалити бота з сайту</h3>
  <p class="small muted">Сайт забуде токен і перестане надсилати повідомлення. Самого бота в Telegram це не видаляє.</p>
  <div><button class="btn sm ghost">Видалити токен</button></div>
</form>
<?php endif ?>
<?php endif ?>
