<?php $canPub = App\Core\App::auth()->can('theming.theme:publish'); ?>
<div class="adm-h"><h1>Теми оформлення</h1></div>
<p class="muted">Активна тема застосовується до всього сайту одразу. Попередню завжди можна повернути. Редактор кольорів і компонентів з’явиться в наступному оновленні (макет уже погоджено).</p>
<div class="theme-grid">
<?php foreach ($rows as $t): $tok = json_decode($t['tokens'], true); $c = $tok[$t['default_mode']]; ?>
  <div class="card theme-card"<?= $t['active'] ? ' style="border-color:#3D5AFE;box-shadow:0 0 0 2px #3D5AFE"' : '' ?>>
    <div class="pv" style="background:<?= e($c['bgPage']) ?>;font-family:<?= e(App\Core\Theme::fontStack($tok['base']['headingFont'])) ?>"><div style="background:<?= e($c['bgCard']) ?>;border:1px solid <?= e($c['border']) ?>;border-radius:<?= (int) $tok['base']['radiusCard'] ?>px;padding:10px;display:flex;flex-direction:column;gap:6px">
      <strong style="color:<?= e($c['heading']) ?>;font-size:15px">Продавець-консультант</strong><span style="color:<?= e($c['muted']) ?>;font-size:12px">Миронівка · 15 000 грн</span>
      <b style="background:<?= e($c['button']) ?>;color:<?= e($c['buttonText']) ?>;border-radius:<?= (int) $tok['base']['radiusBtn'] ?>px;font:700 12px/22px system-ui;text-align:center">Відгукнутися</b></div></div>
    <div class="row"><b><?= e($t['name']) ?></b><?php if ($t['active']): ?><span class="tag ok">На сайті</span><?php endif ?></div>
    <span class="small muted"><?= e($t['description']) ?></span>
    <?php if (!$t['active'] && $canPub): ?><form method="post" action="<?= url('/admin/themes') ?>"><?= csrf_field() ?><input type="hidden" name="version_id" value="<?= (int) $t['vid'] ?>"><button class="btn sm pri block">Застосувати до сайту</button></form><?php endif ?>
  </div>
<?php endforeach ?>
</div>
<div class="card"><div class="pad" style="padding-bottom:0"><h3>Історія публікацій</h3></div><div class="tbl-wrap"><table class="tbl"><tbody>
<?php foreach ($history as $h): ?><tr><td class="small muted"><?= e(local_date($h['published_at'])) ?></td><td><?= e($h['name']) ?></td><td><?= e($h['display_name'] ?? '—') ?></td><td><?= $h['active_flag'] ? '<span class="tag ok">активна</span>' : '' ?></td></tr><?php endforeach ?>
</tbody></table></div></div>
