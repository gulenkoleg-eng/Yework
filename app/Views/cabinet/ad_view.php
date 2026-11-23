<?php use App\Core\Ads;
[$sl, $sc] = Ads::statusLabel($b);
$max = max([1, ...array_column($stats, 'impressions')]);
$ctr = $total['impressions'] ? round($total['clicks'] * 100 / $total['impressions'], 1) : 0;
$preview = [...$b, 'href' => Ads::targetUrl($b), 'employer' => $employer['name'], 'legal_form' => $employer['legal_form'], 'logo' => $employer['logo'],
  'vacancies' => (int) App\Core\App::db()->value("SELECT COUNT(*) FROM {p}vacancies WHERE employer_id = ? AND status = 'published' AND deleted_at IS NULL", [$employer['id']])];
?>
<div class="cab">
  <?= App\Core\View::partial('partials/cab_nav', compact('tab', 'counts', 'me')) ?>
  <div class="stack">
    <a class="small" href="<?= url('/cabinet/ads') ?>">← Реклама і послуги</a>
    <div class="row"><h1 style="font-size:26px"><?= e($b['title']) ?></h1><span class="tag <?= $sc ?>"><?= e($sl) ?></span></div>
    <?php if ($b['status'] === 'rejected' && $b['reject_reason']): ?><div class="alert bad">Модератор відхилив банер. Причина: <?= e($b['reject_reason']) ?></div><?php endif ?>
    <?php if ($b['status'] === 'pending'): ?><div class="alert info">Банер чекає перевірки модератором. Зазвичай це займає до кількох годин.</div><?php endif ?>

    <div class="kpis">
      <div class="card kpi"><b><?= number_format($total['impressions'], 0, ',', ' ') ?></b><span>показів</span></div>
      <div class="card kpi"><b><?= number_format($total['clicks'], 0, ',', ' ') ?></b><span>переходів на ваші вакансії</span></div>
      <div class="card kpi"><b><?= $ctr ?>%</b><span>людей, які побачили й натиснули</span></div>
      <div class="card kpi"><b><?= e(date('d.m', strtotime($b['starts_on']))) ?>–<?= e(date('d.m', strtotime($b['ends_on']))) ?></b><span><?= plural((int) $b['days'], 'день', 'дні', 'днів') ?> · <?= e(Ads::PLACEMENTS[$b['placement']] ?? '') ?></span></div>
    </div>

    <section class="card pad stack">
      <h2 style="font-size:20px">По днях</h2>
      <?php if (!$stats): ?><p class="muted">Статистика з’явиться, коли банер почне показуватися.</p>
      <?php else: ?>
        <div class="tbl-wrap"><table class="tbl ad-stats"><thead><tr><th>День</th><th>Покази</th><th>Переходи</th><th style="width:45%"></th></tr></thead><tbody>
          <?php foreach (array_reverse($stats, true) as $d => $s): ?>
            <tr><td><?= e(date('d.m', strtotime($d))) ?></td><td><?= (int) $s['impressions'] ?></td><td><?= (int) $s['clicks'] ?></td>
              <td><span class="bar" style="width:<?= round($s['impressions'] * 100 / $max) ?>%"></span></td></tr>
          <?php endforeach ?>
        </tbody></table></div>
        <p class="small muted">Покази рахуються, коли банер з’являється на сторінці у відвідувача. Ваші власні перегляди й пошукові роботи не враховуються.</p>
      <?php endif ?>
    </section>

    <div class="card pad row" style="justify-content:space-between">
      <div><b>Кнопка банера веде:</b>
        <?php if ($target): ?>на вакансію <a href="<?= url('/jobs/' . $target['id']) ?>" target="_blank">«<?= e($target['title']) ?>»</a>
        <?php else: ?><a href="<?= url('/jobs', ['employer' => $b['employer_id']]) ?>" target="_blank">на всі ваші вакансії</a>
          <?php if (!empty($b['vacancy_id'])): ?><p class="small" style="color:var(--c-warning)">Вакансію, яку ви обрали, знято з публікації, тому банер веде на всі ваші вакансії. Оберіть іншу в редагуванні.</p><?php endif ?>
        <?php endif ?></div>
      <?php if (Ads::employerCanEdit($b)): ?><a class="btn" href="<?= url('/cabinet/ads/' . $b['id'] . '/edit') ?>">Редагувати банер</a><?php endif ?>
    </div>

    <section class="stack" style="gap:10px"><h2 style="font-size:20px">Так банер виглядає на сайті</h2>
      <div class="bnrs one"><?= App\Core\View::partial('partials/promo', ['b' => $preview]) ?></div></section>

    <?php if ((int) $b['price'] > 0): ?>
      <div class="card pad row" style="justify-content:space-between"><div><b>Вартість: <?= money((int) $b['price']) ?> грн</b><?php if (!empty($saleUse)): ?> <span class="tag ok">−<?= (int) $saleUse['percent'] ?>% «<?= e($saleUse['name']) ?>»</span><p class="small muted">Без святкової знижки було б <s><?= money((int) $saleUse['full_price']) ?> грн</s>.</p><?php endif ?><p class="small muted"><?= $b['paid_at'] ? 'Оплачено ' . e(local_date($b['paid_at'], 'd.m.Y')) : 'Банер почне показуватися після оплати.' ?></p></div>
        <?php if (!$b['paid_at'] && $paid): ?><a class="btn" href="<?= url('/cabinet/ads/' . $b['id'] . '/invoice') ?>" target="_blank">Виставити рахунок</a><?php endif ?></div>
    <?php else: ?>
      <p class="small muted">Вартість: 0 грн — безкоштовно на період розвитку сайту.</p>
    <?php endif ?>

    <?php if (in_array($b['status'], ['pending', 'approved'], true) && $b['ends_on'] >= Ads::today()): ?>
      <form method="post" action="<?= url('/cabinet/ads/' . $b['id'] . '/cancel') ?>" onsubmit="return confirm('Зняти банер з показу? Повернути його не вийде, можна буде замовити новий.')"><?= csrf_field() ?><button class="btn ghost sm">Зняти банер з показу</button></form>
    <?php endif ?>
    <?php if (Ads::employerCanDelete($b)): ?>
      <form method="post" action="<?= url('/cabinet/ads/' . $b['id'] . '/delete') ?>" onsubmit="return confirm('Видалити банер разом зі статистикою? Повернути його не вийде.')"><?= csrf_field() ?><button class="btn ghost sm" style="color:var(--c-danger)">Видалити банер</button></form>
    <?php endif ?>
  </div>
</div>
