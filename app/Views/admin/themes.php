<?php $canPub = App\Core\App::auth()->can('theming.theme:publish'); ?>
<div class="adm-h"><h1>Теми оформлення</h1></div>
<p class="muted">Активна тема застосовується до всього сайту одразу. Попередню завжди можна повернути. Редактор кольорів і компонентів з’явиться в наступному оновленні (макет уже погоджено).</p>
<?php $F = App\Core\Festive::class; $nyOn = $F::isOn($festive); ?>
<form class="card pad stack ny-admin" method="post" action="<?= url('/admin/themes/festive') ?>"><?= csrf_field() ?>
  <div class="row" style="justify-content:space-between"><h3>❄ Новорічне оформлення</h3><?= $nyOn && App\Core\Festive::current() ? '<span class="tag ok">Зараз на сайті</span>' : '<span class="tag">Зараз вимкнено</span>' ?></div>
  <p class="small muted">Сніжинки, гірлянда в шапці, шапочка й світіння логотипа, снігові замети над низом сторінки. Працює з будь-якою темою. Відвідувач може вимкнути сніг для себе кнопкою ❄ у шапці. Тим, у кого в телефоні чи комп’ютері ввімкнено «зменшити рух», анімацію не показуємо.</p>
  <fieldset class="field"><legend class="small" style="font-weight:600;margin-bottom:6px">Коли показувати</legend><div class="row" style="gap:6px 18px">
    <?php foreach ($F::MODES as $k => $label): ?><label class="check"><input type="radio" name="mode" value="<?= $k ?>" <?= $festive['mode'] === $k ? 'checked' : '' ?><?= $canPub ? '' : ' disabled' ?>> <?= e($label) ?></label><?php endforeach ?>
  </div></fieldset>
  <div class="grid2" style="max-width:420px">
    <?= input_field('from', 'З (день.місяць)', ['value' => $F::human($festive['from']), 'placeholder' => '15.12', 'inputmode' => 'decimal']) ?>
    <?= input_field('to', 'По (день.місяць)', ['value' => $F::human($festive['to']), 'placeholder' => '15.01', 'inputmode' => 'decimal']) ?>
  </div>
  <p class="small muted" style="margin-top:-8px">Дати діють щороку. Для режиму «Автоматично за датами».</p>
  <div class="stack" style="gap:6px">
    <label class="check"><input type="checkbox" name="snow" value="1" <?= $festive['snow'] ? 'checked' : '' ?>> Падаючі сніжинки</label>
    <label class="check"><input type="checkbox" name="garland" value="1" <?= $festive['garland'] ? 'checked' : '' ?>> Гірлянда з вогників під шапкою сайту</label>
    <label class="check"><input type="checkbox" name="logo" value="1" <?= $festive['logo'] ? 'checked' : '' ?>> Новорічна шапочка й світіння логотипа, замети внизу сторінки</label>
    <label class="check"><input type="checkbox" name="theme" value="1" <?= $festive['theme'] ? 'checked' : '' ?>> На свята показувати тему «Новорічна» (після свят сайт сам повернеться до теми «<?= e($published) ?>»)</label>
  </div>
  <?php if ($canPub): ?><div class="row"><button class="btn pri">Зберегти</button><a class="btn" href="<?= url('/') ?>" target="_blank" rel="noopener">Подивитися на сайті</a></div><?php endif ?>
</form>
<div class="theme-grid">
<?php foreach ($rows as $t): $tok = json_decode($t['tokens'], true); $c = $tok[$t['default_mode']]; ?>
  <div class="card theme-card"<?= $t['active'] ? ' style="border-color:#3D5AFE;box-shadow:0 0 0 2px #3D5AFE"' : '' ?>>
    <div class="pv" style="background:<?= e($c['bgPage']) ?>;font-family:<?= e(App\Core\Theme::fontStack($tok['base']['headingFont'])) ?>"><div style="background:<?= e($c['bgCard']) ?>;border:1px solid <?= e($c['border']) ?>;border-radius:<?= (int) $tok['base']['radiusCard'] ?>px;padding:10px;display:flex;flex-direction:column;gap:6px">
      <strong style="color:<?= e($c['heading']) ?>;font-size:15px">Продавець-консультант</strong><span style="color:<?= e($c['muted']) ?>;font-size:12px">Миронівка · 15 000 грн</span>
      <b style="background:<?= e($c['button']) ?>;color:<?= e($c['buttonText']) ?>;border-radius:<?= (int) $tok['base']['radiusBtn'] ?>px;font:700 12px/22px system-ui;text-align:center">Відгукнутися</b></div></div>
    <div class="row"><b><?= e($t['name']) ?></b><?php if ($t['active']): ?><span class="tag ok">На сайті</span><?php endif ?><?php if ($t['slug'] === App\Core\Festive::THEME_SLUG && $festive['theme']): ?><span class="tag">На свята</span><?php endif ?></div>
    <span class="small muted"><?= e($t['description']) ?></span>
    <?php if (!$t['active'] && $canPub): ?><form method="post" action="<?= url('/admin/themes') ?>"><?= csrf_field() ?><input type="hidden" name="version_id" value="<?= (int) $t['vid'] ?>"><button class="btn sm pri block">Застосувати до сайту</button></form><?php endif ?>
  </div>
<?php endforeach ?>
</div>
<div class="card"><div class="pad" style="padding-bottom:0"><h3>Історія публікацій</h3></div><div class="tbl-wrap"><table class="tbl"><tbody>
<?php foreach ($history as $h): ?><tr><td class="small muted"><?= e(local_date($h['published_at'])) ?></td><td><?= e($h['name']) ?></td><td><?= e($h['display_name'] ?? '—') ?></td><td><?= $h['active_flag'] ? '<span class="tag ok">активна</span>' : '' ?></td></tr><?php endforeach ?>
</tbody></table></div></div>
