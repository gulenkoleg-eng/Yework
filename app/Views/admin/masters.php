<?php use App\Core\Media; use App\Core\Services as S; use App\Controllers\Admin\ServicesAdminController as SA; ?>
<link rel="stylesheet" href="<?= asset('css/services.css') ?>">
<div class="adm-h"><h1>Майстри</h1><span class="spacer"></span>
  <div class="seg"><?php foreach (SA::TABS as $k => $l): ?><a class="<?= $status === $k ? 'on' : '' ?>" href="<?= url('/admin/masters', ['status' => $k === 'pending' ? null : $k]) ?>"><?= $l ?><?= !empty($counts[$k]) ? ' (' . (int) $counts[$k] . ')' : '' ?></a><?php endforeach ?></div>
</div>
<?= App\Core\View::partial('partials/demo_admin', ['back' => 'masters']) ?>
<?php if ($status === 'reviews'): ?>
  <p class="small muted" style="margin:-6px 0 14px">Кожен відгук прив’язаний до виконаного замовлення через сайт. Приховуйте лише образи, рекламу чи явну неправду: прихований відгук не впливає на рейтинг.</p>
  <?php if (!$reviews): ?><div class="card empty"><h3>Відгуків ще немає</h3></div><?php endif ?>
  <?php foreach ($reviews as $r): ?>
    <article class="card mod">
      <div class="row"><?= S::stars((float) $r['rating'], 15) ?><b><?= e($r['client']) ?></b> про <a href="<?= url('/services/' . $r['master_id']) ?>" target="_blank" rel="noopener"><?= e($r['master']) ?></a><span class="small muted"><?= e(ago($r['created_at'])) ?> · виконання підтвердив <?= $r['done_by'] === 'client' ? 'клієнт' : 'майстер' ?></span><?php if ($r['is_hidden']): ?><span class="tag muted">приховано: <?= e($r['hidden_reason']) ?></span><?php endif ?></div>
      <?php if ($r['body']): ?><div class="prose small"><?= e($r['body']) ?></div><?php endif ?>
      <?php if ($r['reply']): ?><div class="rv-reply">Відповідь майстра: <?= e($r['reply']) ?></div><?php endif ?>
      <form method="post" action="<?= url('/admin/reviews/' . $r['id']) ?>" class="row"><?= csrf_field() ?>
        <?php if ($r['is_hidden']): ?><input type="hidden" name="hide" value="0"><button class="btn sm">Показати знову</button>
        <?php else: ?><input type="hidden" name="hide" value="1"><input class="input" name="reason" placeholder="Причина (образа, реклама, неправда)" style="flex:1;min-height:40px"><button class="btn sm bad">Приховати</button><?php endif ?>
      </form>
    </article>
  <?php endforeach ?>
<?php else: ?>
  <p class="small muted" style="margin:-6px 0 14px">Перевірте, що це справжній майстер: зрозумілий опис, реальні фото робіт (не з інтернету), без образ і сторонніх посилань. <?= $status === 'changed' ? 'Тут опубліковані профілі, які майстер змінив після схвалення: зміни вже на сайті, перегляньте їх.' : '' ?></p>
  <?php if (!$rows): ?><div class="card empty"><h3><?= in_array($status, ['pending', 'changed'], true) ? 'Черга порожня' : 'Нічого немає' ?></h3></div><?php endif ?>
  <?php foreach ($rows as $m): $own = (int) $m['user_id'] === (int) $me['id']; ?>
    <article class="card mod">
      <div class="mod-h">
        <div class="row" style="flex-wrap:nowrap;align-items:flex-start">
          <span class="m-ava sm"><?php if ($m['photo']): ?><img src="<?= e(S::photoUrl($m['photo'])) ?>" alt=""><?php else: ?><?= e(initials((string) $m['name'])) ?><?php endif ?></span>
          <div><h3 style="font-size:19px"><?= e($m['name']) ?><?php if (App\Core\Demo::isDemoMaster((int) $m['id'])): ?> <span class="tag warn" style="font-size:12px;vertical-align:middle">Приклад</span><?php endif ?></h3>
            <div class="small muted"><?= e($m['category']) ?><?= $m['category2'] ? ' + ' . e($m['category2']) : '' ?><?= $m['settlement'] ? ' · ' . e($m['settlement']) : '' ?> · <?= e(S::LEGAL[$m['legal_form']] ?? 'Приватна особа') ?> · оплата: <?= e(mb_strtolower(implode(', ', S::payments($m['payments'])) ?: '—')) ?> · термін <?= e(S::PERIODS[(int) $m['period_months']] ?? '') ?></div>
            <div class="small muted">Телефон: <?= e(phone_pretty($m['contact_phone'])) ?><?= ($t = S::travelText($m, S::travelAreas((int) $m['id']))) ? ' · ' . e(mb_strtolower($t)) : '' ?><?= $m['areas'] ? ' · ' . e($m['areas']) : '' ?><?= $m['status'] === 'published' ? ' · до ' . e(date('d.m.Y', strtotime((string) $m['show_to']))) : '' ?></div>
          </div>
        </div>
        <div class="small muted" style="text-align:right">Автор: <a href="<?= url('/admin/users/' . $m['user_id']) ?>"><?= e($m['display_name']) ?></a><?php if ($m['rejected_before']): ?><br><span class="tag bad"><?= (int) $m['rejected_before'] ?> відхилень раніше</span><?php endif ?><br><?= e(ago($m['submitted_at'] ?? $m['updated_at'])) ?></div>
      </div>
      <?php if (trim((string) $m['about']) !== ''): ?><div class="prose small"><?= e($m['about']) ?></div><?php endif ?>
      <?php if ($m['prices']): ?><details><summary class="small">Прайс (<?= count($m['prices']) ?>)</summary><table class="ptable small"><?php foreach ($m['prices'] as $p): ?><tr><td><?= e($p['title']) ?><?php if ($p['note']): ?><span class="note"><?= e($p['note']) ?></span><?php endif ?></td><td><?= e(S::priceText($p)) ?></td></tr><?php endforeach ?></table></details><?php endif ?>
      <?php if ($m['works']): ?><div class="ph-grid"><?php foreach ($m['works'] as $w) foreach ($w['photos'] as $p): ?><a href="<?= e(Media::url('work', $p['file'])) ?>" target="_blank" rel="noopener" title="<?= e($w['title']) ?>"><img src="<?= e(Media::url('workthumb', $p['file'])) ?>" alt="" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:10px"></a><?php endforeach ?></div><?php endif ?>
      <div class="row small"><a href="<?= url('/services/' . $m['id']) ?>" target="_blank" rel="noopener">Сторінка майстра</a></div>
      <?php if ($m['status'] === 'rejected'): ?><div class="alert bad">Причина: <?= e($m['reject_reason']) ?></div><?php endif ?>
      <?php if ($own): ?><div class="alert info">Це ваш профіль — його має перевірити інший модератор.</div>
      <?php elseif (in_array($m['status'], ['pending', 'published'], true)): ?>
      <div class="row">
        <?php if ($m['status'] === 'pending' || $m['needs_review']): ?><form method="post" action="<?= url('/admin/masters/' . $m['id']) ?>"><?= csrf_field() ?><input type="hidden" name="decision" value="approve"><input type="hidden" name="back" value="<?= e($status === 'pending' ? '' : $status) ?>"><button class="btn ok"><?= $m['status'] === 'pending' ? '✓ Опублікувати' : '✓ Зміни в порядку' ?></button></form><?php endif ?>
        <form method="post" action="<?= url('/admin/masters/' . $m['id']) ?>" class="row" style="flex:1;min-width:280px"><?= csrf_field() ?><input type="hidden" name="decision" value="reject"><input type="hidden" name="back" value="<?= e($status === 'pending' ? '' : $status) ?>">
          <input class="input" name="reason" placeholder="Причина (побачить майстер)" style="flex:1;min-height:44px" list="ms-reasons"><button class="btn bad"><?= $m['status'] === 'published' ? 'Зняти з каталогу' : 'Відхилити' ?></button></form>
      </div>
      <?php endif ?>
    </article>
  <?php endforeach ?>
  <datalist id="ms-reasons"><option value="Фото робіт взяті з інтернету — додайте власні"><option value="Опишіть зрозуміло, які послуги надаєте"><option value="Реклама сторонніх сайтів або посилання в описі"><option value="Образливий або неприйнятний зміст"><option value="Вкажіть реальний робочий телефон"></datalist>
<?php endif ?>
