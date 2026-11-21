<?php $s = $seller; $en = employer_name($employer['legal_form'], $employer['name']); ?>
<div class="invoice card pad stack">
  <div class="row" style="justify-content:space-between"><h1 style="font-size:24px">Рахунок № Б-<?= (int) $b['id'] ?></h1><span class="muted">від <?= e(local_date($b['created_at'], 'd.m.Y')) ?></span></div>
  <dl class="kv">
    <dt>Постачальник</dt><dd><?= e($s['name'] ?: '—') ?></dd>
    <dt>ЄДРПОУ / РНОКПП</dt><dd><?= e($s['code'] ?: '—') ?></dd>
    <dt>IBAN</dt><dd class="mono"><?= e($s['iban'] ?: '—') ?></dd>
    <dt>Банк</dt><dd><?= e($s['bank'] ?: '—') ?></dd>
    <dt>Платник</dt><dd><?= e($en) ?><?= $employer['edrpou'] ? ', код ' . e($employer['edrpou']) : '' ?></dd>
  </dl>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Послуга</th><th>Кількість</th><th>Сума, грн</th></tr></thead><tbody>
    <tr><td>Розміщення рекламного банера «<?= e($b['title']) ?>» на сайті <?= e(App\Core\App::setting('site.name', 'YEWORK')) ?>, <?= e(date('d.m.Y', strtotime($b['starts_on']))) ?>–<?= e(date('d.m.Y', strtotime($b['ends_on']))) ?></td><td><?= plural((int) $b['days'], 'день', 'дні', 'днів') ?></td><td><b><?= money((int) $b['price']) ?></b></td></tr>
  </tbody></table></div>
  <p>Призначення платежу: <b>Оплата за рахунком № Б-<?= (int) $b['id'] ?>, без ПДВ</b></p>
  <?php if (!empty($s['note'])): ?><p class="small muted"><?= e($s['note']) ?></p><?php endif ?>
  <div class="row no-print"><button class="btn pri" onclick="window.print()">Надрукувати або зберегти PDF</button></div>
</div>
