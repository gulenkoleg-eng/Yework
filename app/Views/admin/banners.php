<?php use App\Core\Ads; $tabs = ['pending' => 'Чекають', 'approved' => 'Схвалені', 'rejected' => 'Відхилені', 'cancelled' => 'Скасовані']; ?>
<div class="adm-h"><h1>Банери роботодавців</h1><span class="spacer"></span>
  <div class="seg"><?php foreach ($tabs as $k => $l): ?><a class="<?= $status === $k ? 'on' : '' ?>" href="<?= url('/admin/banners', ['status' => $k === 'pending' ? null : $k]) ?>"><?= $l ?></a><?php endforeach ?><a href="<?= url('/admin/banners/templates') ?>">Шаблони</a></div>
</div>
<p class="muted small">Схвалений банер показується в обрані роботодавцем дні, а його вакансії в цей час стоять першими в пошуку. Перевірте, що текст стосується роботи і не містить контактів для передоплати.</p>
<?php if (!$rows): ?><div class="card empty"><h3><?= $status === 'pending' ? 'Черга порожня' : 'Нічого немає' ?></h3></div><?php endif ?>
<?php foreach ($rows as $b): [$sl, $sc] = Ads::statusLabel($b); $own = (int) $b['owner_id'] === (int) $me['id'] || (int) $b['created_by'] === (int) $me['id']; ?>
  <article class="card mod">
    <div class="mod-h">
      <div><div class="row"><h3 style="font-size:19px"><?= e($b['title']) ?></h3><span class="tag <?= $sc ?>"><?= e($sl) ?></span></div>
        <div class="small muted"><?= e(employer_name($b['legal_form'], $b['employer'])) ?><?= $b['is_verified'] ? ' · ✓ перевірений' : '' ?> · <?= plural((int) $b['vacancies'], 'опублікована вакансія', 'опубліковані вакансії', 'опублікованих вакансій') ?> · <a href="<?= url('/jobs', ['employer' => $b['employer_id']]) ?>" target="_blank">вакансії</a></div></div>
      <div class="small muted" style="text-align:right">Замовив: <a href="<?= url('/admin/users/' . $b['created_by']) ?>"><?= e($b['author']) ?></a><br><?= e(ago($b['created_at'])) ?></div>
    </div>
    <div class="row small"><b><?= e(Ads::PLACEMENTS[$b['placement']] ?? '') ?></b> · <?= e(date('d.m.Y', strtotime($b['starts_on']))) ?>–<?= e(date('d.m.Y', strtotime($b['ends_on']))) ?> (<?= plural((int) $b['days'], 'день', 'дні', 'днів') ?>) · <?= (int) $b['price'] ? money((int) $b['price']) . ' грн' . ($b['paid_at'] ? ' · оплачено' : ' · <b style="color:var(--c-warning)">не оплачено</b>') : 'безкоштовно' ?><?php if ($b['status'] !== 'pending'): ?> · покази <?= (int) $b['impressions'] ?>, переходи <?= (int) $b['clicks'] ?><?php endif ?></div>
    <div class="bnrs one"><?= App\Core\View::partial('partials/promo', ['b' => $b]) ?></div>
    <?php if ($b['status'] === 'rejected'): ?><div class="alert bad">Причина: <?= e($b['reject_reason']) ?></div><?php endif ?>
    <div class="row">
      <?php if ($own && in_array($b['status'], ['pending', 'approved'], true)): ?><div class="alert info">Це ваш банер — його має перевірити інший модератор.</div>
      <?php else: ?>
        <?php if ($b['status'] === 'pending'): ?><form method="post" action="<?= url('/admin/banners/' . $b['id']) ?>"><?= csrf_field() ?><input type="hidden" name="decision" value="approve"><button class="btn ok">✓ Схвалити</button></form><?php endif ?>
        <?php if (in_array($b['status'], ['pending', 'approved'], true)): ?>
        <form method="post" action="<?= url('/admin/banners/' . $b['id']) ?>" class="row" style="flex:1;min-width:280px"><?= csrf_field() ?><input type="hidden" name="decision" value="reject"><input type="hidden" name="back" value="<?= e($status === 'pending' ? '' : $status) ?>">
          <input class="input" name="reason" placeholder="Причина (побачить роботодавець)" style="flex:1;min-height:44px" list="breasons"><button class="btn bad"><?= $b['status'] === 'approved' ? 'Зняти з показу' : 'Відхилити' ?></button></form>
        <?php endif ?>
      <?php endif ?>
      <?php if ($canPay && (int) $b['price'] > 0 && !$b['paid_at'] && in_array($b['status'], ['pending', 'approved'], true)): ?><form method="post" action="<?= url('/admin/banners/' . $b['id']) ?>"><?= csrf_field() ?><input type="hidden" name="decision" value="paid"><input type="hidden" name="back" value="<?= e($status === 'pending' ? '' : $status) ?>"><button class="btn sm">Позначити оплаченим</button></form><?php endif ?>
    </div>
  </article>
<?php endforeach ?>
<datalist id="breasons"><option value="Текст не стосується пошуку працівників"><option value="Немає опублікованих вакансій, на які веде банер"><option value="Контакти або посилання в тексті банера"><option value="Картинка неприйнятна або погано читається"><option value="Підозра на шахрайство"></datalist>
