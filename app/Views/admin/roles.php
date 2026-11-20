<div class="adm-h"><h1>Ролі та права</h1></div>
<div class="alert info"><div>Критичні права (позначені <span class="tag bad">критичне</span>) не входять у жодну роль: їх видає лише головний адміністратор окремо. Роль власника має всі права.</div></div>
<?php $byCode = array_column($perms, null, 'code'); ?>
<?php foreach ($roles as $r): ?>
  <details class="card pad"><summary class="row" style="cursor:pointer"><b><?= e($r['name']) ?></b><span class="muted small"><?= e($r['description']) ?></span><span class="spacer"></span><span class="tag"><?= plural((int) $r['members'], 'людина', 'людини', 'людей') ?></span></summary>
    <div style="margin-top:12px" class="row">
      <?php if ($r['slug'] === 'owner'): ?><span class="tag ok">Усі права, включно з критичними</span>
      <?php else: foreach ($rp[$r['id']] ?? [] as $c): ?><span class="tag muted" title="<?= e($c) ?>"><?= e($byCode[$c]['description'] ?? $c) ?></span><?php endforeach; endif ?>
    </div>
  </details>
<?php endforeach ?>
<div class="card"><div class="pad" style="padding-bottom:0"><h3>Усі права (<?= count($perms) ?>)</h3></div><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Код</th><th>Опис</th><th></th></tr></thead><tbody>
<?php foreach ($perms as $p): ?><tr><td class="mono small"><?= e($p['code']) ?></td><td><?= e($p['description']) ?></td><td><?= $p['critical'] ? '<span class="tag bad">критичне</span>' : '' ?></td></tr><?php endforeach ?>
</tbody></table></div></div>
