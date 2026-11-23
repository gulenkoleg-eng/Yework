<?php
use App\Core\Updater;
$latest = $info['latest'] ?? null;
$hasNew = $latest && version_compare($latest, $version, '>');
$newer = array_values(array_filter($info['versions'] ?? [], static fn ($v) => version_compare($v['version'], $version, '>')));
$target = null;
foreach ($newer as $v) if ($v['zip'] !== '') { $target = $v; break; }
// Історія версій: з GitHub, а якщо немає зв’язку — з локального CHANGELOG.md.
$all = [];
foreach ($info['versions'] ?? [] as $v) $all[$v['version']] = $v;
foreach ($local as $ver => $s) {
  if (!isset($all[$ver])) $all[$ver] = ['version' => $ver, 'title' => $ver, 'date' => $s['date'], 'notes' => $s['notes'], 'zip' => '', 'prs' => $s['prs'] ?? [], 'commits' => $s['commits'] ?? []];
  elseif (empty($all[$ver]['prs']) && empty($all[$ver]['commits'])) { $all[$ver]['prs'] = $s['prs'] ?? []; $all[$ver]['commits'] = $s['commits'] ?? []; }
}
$repoUrl = Updater::repoUrl();
$gh = static function (array $v): string {
  $l = Updater::versionLinks($v);
  if (!$l) return '';
  return '<div class="upd-links">' . implode('', array_map(static fn ($x) => '<a href="' . e($x[1]) . '" target="_blank" rel="noopener">' . e($x[0]) . ' ↗</a>', $l)) . '</div>';
};
uksort($all, static fn ($a, $b) => version_compare($b, $a));
$kinds = ['update' => 'Перед оновленням', 'restore' => 'Перед відновленням', 'manual' => 'Вручну'];
$fmtDate = static fn (?string $d) => $d ? (strlen($d) === 10 ? date('d.m.Y', strtotime($d)) : date('d.m.Y H:i', strtotime($d))) : '';
?>
<div class="adm-h"><h1>Оновлення</h1><span class="tag">Версія <?= e($version) ?></span><span class="spacer"></span>
  <form method="post" action="<?= url('/admin/updates/check') ?>"><?= csrf_field() ?><button class="btn sm">Перевірити оновлення</button></form>
</div>

<?php if ($pending): ?>
  <div class="alert warn">Оновлення до <?= e($pending['to']) ?> встановлено не до кінця. <a href="<?= url('/admin/updates/finish') ?>"><b>Завершити оновлення</b></a></div>
<?php endif ?>
<?php foreach ($problems as $p): ?><div class="alert bad"><?= e($p) ?></div><?php endforeach ?>

<?php if ($info['error']): ?>
  <div class="card pad stack">
    <h3>Не вдалося перевірити оновлення</h3>
    <p><?= e($info['error']) ?></p>
    <p class="small muted">Сайт працює як і раніше. Перевірка лише читає репозиторій на GitHub.</p>
  </div>
<?php elseif ($hasNew && $target): ?>
  <div class="card pad stack upd-new">
    <div class="row" style="gap:10px;flex-wrap:wrap;align-items:center"><span class="tag ok">Нова версія</span><h2 style="font-size:22px">YEWORK <?= e($target['version']) ?></h2>
      <?php if ($target['date']): ?><span class="muted small"><?= e($fmtDate($target['date'])) ?></span><?php endif ?></div>
    <?php if (count($newer) > 1): ?><p class="small muted">Між вашою версією та новою є <?= count($newer) ?> оновлення. Встановлюється одразу остання, зміни всіх версій нижче.</p><?php endif ?>
    <?php foreach ($newer as $i => $v): ?>
      <details class="upd-notes" <?= $i === 0 ? 'open' : '' ?>><summary>Що змінилося у <?= e($v['version']) ?></summary>
        <div class="upd-md small"><?= $v['notes'] !== '' ? Updater::markdown($v['notes']) : '<p class="muted">Опису змін немає.</p>' ?></div><?= $gh($all[$v['version']] ?? $v) ?></details>
    <?php endforeach ?>
    <div class="alert info small">Перед оновленням сайт автоматично зробить резервну копію файлів і бази. Якщо після оновлення щось не так, внизу сторінки можна повернути все, як було, одним натисканням.</div>
    <form method="post" action="<?= url('/admin/updates/install') ?>" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Оновлюємо… не закривайте сторінку'"><?= csrf_field() ?>
      <input type="hidden" name="version" value="<?= e($target['version']) ?>">
      <button class="btn pri" <?= $problems ? 'disabled' : '' ?>>Оновити до <?= e($target['version']) ?></button>
    </form>
  </div>
<?php else: ?>
  <div class="card pad stack"><h3>✓ У вас найновіша версія</h3>
    <p class="muted small">Остання перевірка: <?= e(date('d.m.Y H:i', (int) $info['checkedAt'])) ?>. Сайт перевіряє GitHub не частіше ніж раз на годину, або коли ви натискаєте «Перевірити оновлення».</p></div>
<?php endif ?>

<div class="card pad stack" id="versions"><div class="row" style="justify-content:space-between;gap:10px"><h3>Історія версій</h3>
  <div class="upd-links"><a href="<?= e($repoUrl) ?>" target="_blank" rel="noopener">Репозиторій ↗</a><a href="<?= e($repoUrl . '/blob/' . rawurlencode($cfg['branch']) . '/CHANGELOG.md') ?>" target="_blank" rel="noopener">Усі зміни (CHANGELOG) ↗</a><a href="<?= e($repoUrl . '/pulls?q=is%3Apr+is%3Amerged') ?>" target="_blank" rel="noopener">Злиті PR ↗</a><a href="<?= e($repoUrl . '/commits/' . rawurlencode($cfg['branch'])) ?>" target="_blank" rel="noopener">Коміти ↗</a></div></div>
  <p class="small muted">Натисніть на версію, щоб побачити, що в ній змінилося. Посилання «PR» відкривають цю версію на GitHub: опис, обговорення і всі змінені файли.</p>
  <?php if (!$all): ?><p class="muted">Поки немає даних.</p><?php endif ?>
  <?php
  // Сторінка «Історії версій»: по 10 версій. На першій видно 3 останні, решта розгортається кнопкою.
  $perPage = 10; $showFirst = 3;
  $pages = max(1, (int) ceil(count($all) / $perPage));
  $vpage = min($vpage ?? 1, $pages);
  $slice = array_slice(array_values($all), ($vpage - 1) * $perPage, $perPage);
  $shown = $vpage === 1 ? array_slice($slice, 0, $showFirst) : $slice;
  $hidden = $vpage === 1 ? array_slice($slice, $showFirst) : [];
  $row = static function (array $v) use ($version, $fmtDate, $gh): void { $cmp = version_compare($v['version'], $version); ?>
    <details class="upd-notes"><summary><b><?= e($v['version']) ?></b>
      <?php if ($cmp === 0): ?><span class="tag ok">встановлено</span><?php elseif ($cmp > 0): ?><span class="tag warn">нова</span><?php endif ?>
      <?php if ($v['date']): ?><span class="muted small"><?= e($fmtDate($v['date'])) ?></span><?php endif ?>
      <?php foreach ($v['prs'] ?? [] as $n): ?><span class="tag">PR #<?= (int) $n ?></span><?php endforeach ?>
      <?php if (empty($v['prs'])) foreach ($v['commits'] ?? [] as $c): ?><span class="tag">коміт <?= e(substr((string) $c, 0, 7)) ?></span><?php endforeach ?></summary>
      <div class="upd-md small"><?= $v['notes'] !== '' ? Updater::markdown($v['notes']) : '<p class="muted">Опису змін немає.</p>' ?></div>
      <?= $gh($v) ?>
    </details>
  <?php };
  $pager = static function () use ($pages, $vpage): void {
    if ($pages < 2) return; ?>
    <nav class="pager upd-pager" aria-label="Сторінки історії версій"><?php if ($vpage > 1): ?><a href="<?= url('/admin/updates', ['vpage' => $vpage - 1]) ?>#versions" aria-label="Новіші версії">←</a><?php endif ?>
      <?php for ($i = 1; $i <= $pages; $i++): ?><?= $i === $vpage ? "<span class=\"on\">$i</span>" : '<a href="' . url('/admin/updates', $i > 1 ? ['vpage' => $i] : []) . "#versions\">$i</a>" ?><?php endfor ?>
      <?php if ($vpage < $pages): ?><a href="<?= url('/admin/updates', ['vpage' => $vpage + 1]) ?>#versions" aria-label="Старіші версії">→</a><?php endif ?></nav>
  <?php };
  ?>
  <?php if ($vpage > 1): ?><p class="small muted">Сторінка <?= $vpage ?> з <?= $pages ?>: старіші версії. <a href="<?= url('/admin/updates') ?>#versions">← До останніх версій</a></p><?php endif ?>
  <?php foreach ($shown as $v) $row($v) ?>
  <?php if ($hidden): $moreLabel = 'Показати ще ' . plural(count($hidden), 'версію', 'версії', 'версій') . ($pages > 1 ? ' (усього ' . count($all) . ')' : ''); ?>
    <div id="upd-more" class="stack upd-more" hidden><?php foreach ($hidden as $v) $row($v) ?></div>
    <div><button type="button" class="btn sm upd-more-btn" aria-controls="upd-more" aria-expanded="false" data-more="<?= e($moreLabel) ?>" data-less="Згорнути"
      onclick="var o=this.getAttribute('aria-expanded')!=='true';document.querySelectorAll('.upd-more').forEach(function(m){m.hidden=!o});this.setAttribute('aria-expanded',o);this.textContent=o?this.dataset.less:this.dataset.more"><?= e($moreLabel) ?></button></div>
    <?php if ($pages > 1): ?><div class="upd-more" hidden><?php $pager() ?></div><?php endif ?>
  <?php else: $pager(); endif ?>
</div>

<?php
$keep = Updater::keepCount();
$bkTotal = array_sum(array_map(static fn ($b) => (int) ($b['size'] ?? 0), $backups));
$bkShowFirst = 3;
$bkDate = static fn (array $b) => date('d.m.Y H:i', strtotime($b['createdAt']));
$bkRow = static function (array $b, int $i) use ($kinds, $users, $bkDate, $canConfigure): void {
  $mig = $b['migrations'] ?? []; $dbDefault = !($b['kind'] === 'update' && isset($b['migrations']) && !$mig);
  $blocker = Updater::deleteBlocker($b['id']); ?>
    <div class="upd-bk">
      <div class="upd-bk-h">
        <div><b>Версія <?= e($b['version']) ?></b> · <?= e($bkDate($b)) ?><?php if ($i === 0): ?> <span class="tag ok">найновіша</span><?php endif ?>
          <div class="small muted"><?= e($b['note'] ?? ($kinds[$b['kind']] ?? '')) ?><?php if (!empty($b['by']) && isset($users[(int) $b['by']])): ?> · <?= e($users[(int) $b['by']]) ?><?php endif ?> · <?= e(Updater::humanSize((int) ($b['size'] ?? 0))) ?></div>
          <?php if (!empty($b['to'])): ?><div class="small">Після неї встановлено <?= e($b['to']) ?><?= $mig ? ', змінено структуру бази' : ', база не змінювалась' ?>.</div><?php endif ?>
        </div>
      </div>
      <?php $pid = 'bk-' . preg_replace('/[^0-9A-Za-z-]/', '-', $b['id']); ?>
      <div class="upd-bk-acts">
        <button type="button" class="btn sm<?= $i === 0 && !empty($b['to']) ? ' pri' : '' ?>" aria-expanded="false" aria-controls="<?= $pid ?>-r" onclick="ywBk(this)">Повернути цю копію</button>
        <button type="button" class="btn sm ghost" aria-expanded="false" aria-controls="<?= $pid ?>-i" onclick="ywBk(this)">Що всередині</button>
        <button type="button" class="btn sm ghost upd-del" aria-expanded="false" aria-controls="<?= $pid ?>-d" onclick="ywBk(this)">Видалити</button>
      </div>
      <div class="upd-act" id="<?= $pid ?>-r" hidden>
        <form class="stack" method="post" action="<?= url('/admin/updates/restore') ?>" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Відновлюємо…'"><?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= e($b['id']) ?>">
          <p class="small">Файли сайту стануть такими, як у версії <b><?= e($b['version']) ?></b>. Перед цим автоматично збережеться копія поточного стану, тож відновлення теж можна скасувати.</p>
          <label class="check"><input type="checkbox" name="with_db" value="1" <?= $dbDefault ? 'checked' : '' ?>> <span>Також повернути базу даних на <?= e($bkDate($b)) ?>.<br><span class="small muted">Вакансії, користувачі й відгуки, додані після цього часу, зникнуть. Журнал дій збережеться повністю.<?= !$dbDefault ? ' Для цієї копії базу повертати не обов’язково: оновлення її не змінювало.' : '' ?></span></span></label>
          <label class="check"><input type="checkbox" name="confirm" value="1" required> <span>Так, я розумію, повернути</span></label>
          <div><button class="btn bad">Повернути версію <?= e($b['version']) ?></button></div>
        </form>
      </div>
      <div class="upd-act" id="<?= $pid ?>-i" hidden>
        <div class="upd-bk-info small">
          <dl>
            <dt>Створено</dt><dd><?= e($bkDate($b)) ?>, <?= e(mb_strtolower($kinds[$b['kind']] ?? $b['kind'])) ?></dd>
            <dt>Версія сайту</dt><dd><?= e($b['version']) ?><?= !empty($b['to']) ? ' (потім встановлено ' . e($b['to']) . ')' : '' ?></dd>
            <dt>Файли сайту</dt><dd><?= plural((int) ($b['files'] ?? 0), 'файл', 'файли', 'файлів') ?></dd>
            <dt>База даних</dt><dd><?= plural(is_array($b['tables'] ?? null) ? count($b['tables']) : (int) ($b['tables'] ?? 0), 'таблиця', 'таблиці', 'таблиць') ?></dd>
            <dt>Розмір</dt><dd><?= e(Updater::humanSize((int) ($b['size'] ?? 0))) ?></dd>
            <dt>Папка</dt><dd><code>storage/backups/<?= e($b['id']) ?></code></dd>
          </dl>
          <?php if ($canConfigure): ?>
            <div class="row" style="gap:8px;flex-wrap:wrap"><a class="btn sm" href="<?= url('/admin/updates/download', ['id' => $b['id'], 'part' => 'files']) ?>">Завантажити файли (.zip)</a><a class="btn sm" href="<?= url('/admin/updates/download', ['id' => $b['id'], 'part' => 'db']) ?>">Завантажити базу (.sql)</a></div>
            <p class="muted">У файлі бази є дані всіх користувачів. Зберігайте його в надійному місці й нікому не пересилайте.</p>
          <?php endif ?>
        </div>
      </div>
      <div class="upd-act" id="<?= $pid ?>-d" hidden>
        <?php if ($blocker): ?><p class="small muted upd-bk-info"><?= e($blocker) ?></p><?php else: ?>
        <form class="stack" method="post" action="<?= url('/admin/updates/delete') ?>"><?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= e($b['id']) ?>">
          <?php if ($i === 0): ?>
            <p class="small"><b>Це найновіша копія.</b> Якщо після останнього оновлення щось працює не так, саме вона дозволяє повернути сайт як було. Видаляйте її, лише якщо впевнені, що все гаразд.</p>
            <label class="check"><input type="checkbox" name="confirm_latest" value="1" required> <span>Так, видалити найновішу копію</span></label>
          <?php else: ?>
            <p class="small">Копію версії <b><?= e($b['version']) ?></b> від <?= e($bkDate($b)) ?> буде видалено з хостингу назавжди. Звільниться <?= e(Updater::humanSize((int) ($b['size'] ?? 0))) ?>.</p>
          <?php endif ?>
          <div><button class="btn bad sm">Видалити копію</button></div>
        </form>
        <?php endif ?>
      </div>
    </div>
<?php };
?>
<script>
// Кнопки під копією: відкривають одну панель (повернути / що всередині / видалити), повторне натискання закриває.
function ywBk(btn) {
  var open = btn.getAttribute('aria-expanded') !== 'true';
  btn.parentNode.querySelectorAll('button[aria-controls]').forEach(function (b) {
    b.setAttribute('aria-expanded', 'false'); document.getElementById(b.getAttribute('aria-controls')).hidden = true;
  });
  btn.setAttribute('aria-expanded', open); document.getElementById(btn.getAttribute('aria-controls')).hidden = !open;
}
</script>
<div class="card pad stack" id="backups"><div class="row" style="justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap"><div><h3>Резервні копії і відкат</h3>
  <?php if ($backups): ?><div class="small muted"><?= plural(count($backups), 'копія', 'копії', 'копій') ?>, разом <?= e(Updater::humanSize($bkTotal)) ?></div><?php endif ?></div>
  <form method="post" action="<?= url('/admin/updates/backup') ?>" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Створюємо…'"><?= csrf_field() ?><button class="btn sm">Створити копію зараз</button></form></div>
  <p class="small muted">Копія містить файли сайту і всю базу даних. Зберігаються останні <?= plural($keep, 'копія', 'копії', 'копій') ?> у папці <code>storage/backups</code> на хостингу, старіші видаляються автоматично. Файл конфігурації <code>config/…</code> не копіюється і не змінюється.</p>
  <?php if (!$backups): ?><p class="muted">Копій ще немає. Вона з’явиться автоматично перед першим оновленням.</p><?php endif ?>
  <?php foreach (array_slice($backups, 0, $bkShowFirst) as $i => $b) $bkRow($b, $i) ?>
  <?php if (count($backups) > $bkShowFirst): $bkMore = 'Показати ще ' . plural(count($backups) - $bkShowFirst, 'копію', 'копії', 'копій'); ?>
    <div id="bk-more" class="stack bk-more" hidden><?php foreach (array_slice($backups, $bkShowFirst) as $j => $b) $bkRow($b, $j + $bkShowFirst) ?></div>
    <div><button type="button" class="btn sm" aria-controls="bk-more" aria-expanded="false" data-more="<?= e($bkMore) ?>" data-less="Згорнути"
      onclick="var o=this.getAttribute('aria-expanded')!=='true';document.getElementById('bk-more').hidden=!o;this.setAttribute('aria-expanded',o);this.textContent=o?this.dataset.less:this.dataset.more"><?= e($bkMore) ?></button></div>
  <?php endif ?>
  <?php if ($backups): ?>
  <div class="upd-clean">
    <h4>Очищення</h4>
    <?php if (count($backups) > 1): ?>
    <form class="row" method="post" action="<?= url('/admin/updates/clean') ?>" onsubmit="return confirm(this.querySelector('button').textContent+'? Це не можна скасувати.')"><?= csrf_field() ?>
      <label class="small" for="bk-clean">Видалити старі копії, залишити</label>
      <select id="bk-clean" name="keep" class="input" style="width:auto" onchange="var b=this.form.querySelector('button');b.textContent=this.options[this.selectedIndex].dataset.label">
        <?php $firstLabel = null; for ($k = 1; $k < count($backups) && $k <= 5; $k++): $gone = array_slice($backups, $k); $freed = array_sum(array_map(static fn ($b) => (int) ($b['size'] ?? 0), $gone));
          $label = 'Видалити ' . plural(count($gone), 'стару копію', 'старі копії', 'старих копій') . ' (' . Updater::humanSize($freed) . ')'; $firstLabel ??= $label; ?>
          <option value="<?= $k ?>" data-label="<?= e($label) ?>"><?= $k === 1 ? 'лише найновішу' : $k . ' найновіші' ?></option>
        <?php endfor ?>
      </select>
      <button class="btn sm bad"><?= e($firstLabel) ?></button>
    </form>
    <?php endif ?>
    <form class="row" method="post" action="<?= url('/admin/updates/keep') ?>"><?= csrf_field() ?>
      <label class="small" for="bk-keep">Зберігати автоматично останні</label>
      <select id="bk-keep" name="keep" class="input" style="width:auto"><?php foreach (Updater::KEEP_CHOICES as $k): ?><option value="<?= $k ?>" <?= $k === $keep ? 'selected' : '' ?>><?= plural($k, 'копію', 'копії', 'копій') ?></option><?php endforeach ?></select>
      <button class="btn sm">Зберегти</button>
    </form>
    <p class="small muted">Нова копія з’являється перед кожним оновленням і відновленням. Коли їх стає більше, ніж вибрано, найстаріші видаляються самі.</p>
  </div>
  <?php endif ?>
</div>

<?php if ($history): ?>
<div class="card"><div class="pad" style="padding-bottom:0"><h3>Журнал оновлень</h3></div><div class="tbl-wrap"><table class="tbl"><tbody>
<?php foreach ($history as $r): $d = $r['data'] ? json_decode($r['data'], true) : []; ?><tr>
  <td class="muted small" style="white-space:nowrap"><?= e(local_date($r['created_at'])) ?></td>
  <td><?= e(App\Core\Audit::label($r['action'])) ?><?php if (!empty($d['from']) || !empty($d['to'])): ?> <span class="small muted"><?= e(($d['from'] ?? '?') . ' → ' . ($d['to'] ?? '?')) ?></span><?php endif ?>
    <?php if (!empty($d['error'])): ?><div class="small muted">Причина: <?= e($d['error']) ?></div><?php endif ?></td>
  <td><?= e($r['display_name'] ?? '—') ?></td></tr><?php endforeach ?>
</tbody></table></div></div>
<?php endif ?>

<?php if ($canConfigure): ?>
<details class="card pad upd-cfg" <?= $info['error'] && !$cfg['tokenEnc'] ? 'open' : '' ?>><summary><h3 style="display:inline">Підключення до GitHub</h3></summary>
  <form class="stack" method="post" action="<?= url('/admin/updates/settings') ?>" style="margin-top:14px"><?= csrf_field() ?>
    <div class="grid2"><?= input_field('repo', 'Репозиторій', ['value' => $cfg['repo'], 'hint' => 'власник/назва']) ?><?= input_field('branch', 'Гілка', ['value' => $cfg['branch'], 'hint' => 'Використовується, якщо в репозиторії немає релізів']) ?></div>
    <?= input_field('token', 'Токен GitHub (лише для читання)', ['type' => 'password', 'autocomplete' => 'off', 'placeholder' => 'github_pat_…',
      'hint' => $cfg['tokenEnc'] ? 'Збережено: ' . $cfg['tokenHint'] . '. Залиште порожнім, щоб не змінювати.' : 'Потрібен лише для закритого (приватного) репозиторію. Для відкритого можна не вказувати. Зберігається зашифрованим.']) ?>
    <?php if ($cfg['tokenEnc']): ?><label class="check"><input type="checkbox" name="clear_token" value="1"> Видалити збережений токен</label><?php endif ?>
    <div><button class="btn pri">Зберегти і перевірити</button></div>
  </form>
  <div class="upd-md small" style="margin-top:16px">
    <h4>Як отримати токен, якщо репозиторій закритий (5 хвилин)</h4>
    <ol>
      <li>Увійдіть на <a href="https://github.com/settings/personal-access-tokens/new" target="_blank" rel="noopener">github.com → Settings → Developer settings → Fine-grained tokens → Generate new token</a>.</li>
      <li><b>Token name</b>: <code>yework-site</code>. <b>Expiration</b>: 1 рік (або скільки хочете; коли закінчиться, тут з’явиться підказка).</li>
      <li><b>Repository access</b>: «Only select repositories» і виберіть <code><?= e($cfg['repo']) ?></code>.</li>
      <li><b>Permissions → Repository permissions → Contents</b>: «Read-only». Більше нічого не вмикайте.</li>
      <li>Натисніть <b>Generate token</b>, скопіюйте його і вставте в поле вище. Більше ніде його не зберігайте й нікому не надсилайте.</li>
    </ol>
  </div>
</details>
<?php endif ?>
