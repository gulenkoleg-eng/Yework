<div class="adm-h"><h1>Ролі та права</h1></div>
<?php if (!empty($canManage)): ?><div class="alert info"><div>Відкрийте роль, щоб увімкнути або вимкнути її права галочками. Наприклад, «Редагування оголошень (вакансії)» дає кнопку «Редагувати (адміністрація)» на сторінці будь-якої вакансії.</div></div><?php endif ?>
<div class="alert info"><div>Критичні права (позначені <span class="tag bad">критичне</span>) не входять у жодну роль: їх видає лише головний адміністратор окремо. Роль власника має всі права.</div></div>
<?php $byCode = array_column($perms, null, 'code'); ?>
<?php foreach ($roles as $r): ?>
  <details class="card pad"<?= (int) ($_GET['open'] ?? 0) === (int) $r['id'] ? ' open' : '' ?>><summary class="row" style="cursor:pointer"><b><?= e($r['name']) ?></b><span class="muted small"><?= e($r['description']) ?></span><span class="spacer"></span><span class="tag"><?= plural((int) $r['members'], 'людина', 'людини', 'людей') ?></span></summary>
    <?php if (!empty($canManage) && $r['slug'] !== 'owner'): $on = array_flip($rp[$r['id']] ?? []); ?>
      <form method="post" action="<?= url('/admin/roles/' . $r['id']) ?>" class="stack" style="margin-top:12px;gap:12px"><?= csrf_field() ?>
        <div class="perm-grid">
        <?php foreach ($perms as $p): if ($p['critical']) continue; ?>
          <label class="check small<?= $p['code'] === 'jobs.listing:update' ? ' perm-key' : '' ?>"><input type="checkbox" name="perms[]" value="<?= e($p['code']) ?>"<?= isset($on[$p['code']]) ? ' checked' : '' ?>> <span><?= e($p['description']) ?></span></label>
        <?php endforeach ?>
        </div>
        <div class="row"><button class="btn pri sm">Зберегти права ролі</button><span class="small muted">Діє одразу для всіх, у кого ця роль. Критичні права видаються окремо.</span></div>
      </form>
    <?php else: ?>
    <div style="margin-top:12px" class="row">
      <?php if ($r['slug'] === 'owner'): ?><span class="tag ok">Усі права, включно з критичними</span>
      <?php else: foreach ($rp[$r['id']] ?? [] as $c): ?><span class="tag muted" title="<?= e($c) ?>"><?= e($byCode[$c]['description'] ?? $c) ?></span><?php endforeach; endif ?>
    </div>
    <?php endif ?>
  </details>
<?php endforeach ?>
<div class="card"><div class="pad" style="padding-bottom:0"><h3>Усі права (<?= count($perms) ?>)</h3></div><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Код</th><th>Опис</th><th></th></tr></thead><tbody>
<?php foreach ($perms as $p): ?><tr><td class="mono small"><?= e($p['code']) ?></td><td><?= e($p['description']) ?></td><td><?= $p['critical'] ? '<span class="tag bad">критичне</span>' : '' ?></td></tr><?php endforeach ?>
</tbody></table></div></div>
