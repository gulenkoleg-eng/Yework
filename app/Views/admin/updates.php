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

<div class="card pad stack"><div class="row" style="justify-content:space-between;gap:10px"><h3>Історія версій</h3>
  <div class="upd-links"><a href="<?= e($repoUrl) ?>" target="_blank" rel="noopener">Репозиторій ↗</a><a href="<?= e($repoUrl . '/blob/' . rawurlencode($cfg['branch']) . '/CHANGELOG.md') ?>" target="_blank" rel="noopener">Усі зміни (CHANGELOG) ↗</a><a href="<?= e($repoUrl . '/pulls?q=is%3Apr+is%3Amerged') ?>" target="_blank" rel="noopener">Злиті PR ↗</a><a href="<?= e($repoUrl . '/commits/' . rawurlencode($cfg['branch'])) ?>" target="_blank" rel="noopener">Коміти ↗</a></div></div>
  <p class="small muted">Натисніть на версію, щоб побачити, що в ній змінилося. Посилання «PR» відкривають цю версію на GitHub: опис, обговорення і всі змінені файли.</p>
  <?php if (!$all): ?><p class="muted">Поки немає даних.</p><?php endif ?>
  <?php foreach ($all as $v): $cmp = version_compare($v['version'], $version); ?>
    <details class="upd-notes"><summary><b><?= e($v['version']) ?></b>
      <?php if ($cmp === 0): ?><span class="tag ok">встановлено</span><?php elseif ($cmp > 0): ?><span class="tag warn">нова</span><?php endif ?>
      <?php if ($v['date']): ?><span class="muted small"><?= e($fmtDate($v['date'])) ?></span><?php endif ?>
      <?php foreach ($v['prs'] ?? [] as $n): ?><span class="tag">PR #<?= (int) $n ?></span><?php endforeach ?></summary>
      <div class="upd-md small"><?= $v['notes'] !== '' ? Updater::markdown($v['notes']) : '<p class="muted">Опису змін немає.</p>' ?></div>
      <?= $gh($v) ?>
    </details>
  <?php endforeach ?>
</div>

<div class="card pad stack"><div class="row" style="justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap"><h3>Резервні копії і відкат</h3>
  <form method="post" action="<?= url('/admin/updates/backup') ?>" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Створюємо…'"><?= csrf_field() ?><button class="btn sm">Створити копію зараз</button></form></div>
  <p class="small muted">Копія містить файли сайту і всю базу даних. Зберігаються останні 6 копій у папці <code>storage/backups</code> на хостингу. Файл конфігурації <code>config/…</code> не копіюється і не змінюється.</p>
  <?php if (!$backups): ?><p class="muted">Копій ще немає. Вона з’явиться автоматично перед першим оновленням.</p><?php endif ?>
  <?php foreach ($backups as $i => $b): $mig = $b['migrations'] ?? []; $dbDefault = !($b['kind'] === 'update' && isset($b['migrations']) && !$mig); ?>
    <div class="upd-bk">
      <div class="upd-bk-h">
        <div><b>Версія <?= e($b['version']) ?></b> · <?= e(date('d.m.Y H:i', strtotime($b['createdAt']))) ?>
          <div class="small muted"><?= e($b['note'] ?? ($kinds[$b['kind']] ?? '')) ?><?php if (!empty($b['by']) && isset($users[(int) $b['by']])): ?> · <?= e($users[(int) $b['by']]) ?><?php endif ?> · <?= e(Updater::humanSize((int) ($b['size'] ?? 0))) ?></div>
          <?php if (!empty($b['to'])): ?><div class="small">Після неї встановлено <?= e($b['to']) ?><?= $mig ? ', змінено структуру бази' : ', база не змінювалась' ?>.</div><?php endif ?>
        </div>
      </div>
      <details class="upd-act"><summary class="btn sm<?= $i === 0 && !empty($b['to']) ? ' pri' : '' ?>">Повернути цю копію</summary>
        <form class="stack" method="post" action="<?= url('/admin/updates/restore') ?>" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Відновлюємо…'"><?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= e($b['id']) ?>">
          <p class="small">Файли сайту стануть такими, як у версії <b><?= e($b['version']) ?></b>. Перед цим автоматично збережеться копія поточного стану, тож відновлення теж можна скасувати.</p>
          <label class="check"><input type="checkbox" name="with_db" value="1" <?= $dbDefault ? 'checked' : '' ?>> <span>Також повернути базу даних на <?= e(date('d.m.Y H:i', strtotime($b['createdAt']))) ?>.<br><span class="small muted">Вакансії, користувачі й відгуки, додані після цього часу, зникнуть. Журнал дій збережеться повністю.<?= !$dbDefault ? ' Для цієї копії базу повертати не обов’язково: оновлення її не змінювало.' : '' ?></span></span></label>
          <label class="check"><input type="checkbox" name="confirm" value="1" required> <span>Так, я розумію, повернути</span></label>
          <div><button class="btn bad">Повернути версію <?= e($b['version']) ?></button></div>
        </form>
        <form method="post" action="<?= url('/admin/updates/delete') ?>" onsubmit="return confirm('Видалити цю резервну копію?')" style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($b['id']) ?>"><button class="btn sm ghost">Видалити копію</button></form>
      </details>
    </div>
  <?php endforeach ?>
</div>

<?php if ($history): ?>
<div class="card"><div class="pad" style="padding-bottom:0"><h3>Журнал оновлень</h3></div><div class="tbl-wrap"><table class="tbl"><tbody>
<?php foreach ($history as $r): $d = $r['data'] ? json_decode($r['data'], true) : []; ?><tr>
  <td class="muted small" style="white-space:nowrap"><?= e(local_date($r['created_at'])) ?></td>
  <td><?= e(App\Core\Audit::label($r['action'])) ?><?php if (!empty($d['from']) || !empty($d['to'])): ?> <span class="small muted"><?= e(($d['from'] ?? '?') . ' → ' . ($d['to'] ?? '?')) ?></span><?php endif ?>
    <?php if (!empty($d['error'])): ?><div class="small muted"><?= e($d['error']) ?></div><?php endif ?></td>
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
