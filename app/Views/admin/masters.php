<?php /* Майстри (0.5.25.1): компактний список із пошуком і фільтрами, подробиці розгортаються, «Редагувати» відкриває повну анкету. */
use App\Core\Media; use App\Core\Services as S; use App\Controllers\Admin\ServicesAdminController as SA;
$backQ = (string) (parse_url(SA::listUrl($f), PHP_URL_QUERY) ?? '') ?: 'status=' . $status;
$filtered = $f['q'] !== '' || $f['cat'] || $f['place'] !== '' || $f['kind'] !== '';
$byCommunity = [];
foreach ($places ?? [] as $pl) $byCommunity[$pl['community_id']][] = $pl; ?>
<link rel="stylesheet" href="<?= asset('css/services.css') ?>">
<div class="adm-h"><h1>Майстри</h1><span class="spacer"></span>
  <div class="seg"><?php foreach (SA::TABS as $k => $l): ?><a class="<?= $status === $k ? 'on' : '' ?>" href="<?= $k === 'reviews' ? url('/admin/masters', ['status' => 'reviews']) : SA::listUrl($f, ['status' => $k, 'page' => 1]) ?>"><?= $l ?><?php if ($k !== 'reviews' && isset($counts[$k]) && ($counts[$k] || $status !== 'reviews')): ?> <span class="mq-n"><?= (int) $counts[$k] ?></span><?php endif ?></a><?php endforeach ?></div>
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
<form class="card mq-filters" method="get" action="<?= url('/admin/masters') ?>">
  <?php if ($status !== 'pending'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif ?>
  <label class="mq-search"><?= icon('search', 16) ?><input class="input" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Назва майстра, власник, логін, телефон, опис або №"></label>
  <select class="input" name="cat" onchange="this.form.submit()" aria-label="Категорія послуг">
    <option value="">Усі категорії</option>
    <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $f['cat'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?>
  </select>
  <select class="input" name="place" onchange="this.form.submit()" aria-label="Громада або населений пункт">
    <option value="">Усі громади й села</option>
    <?php foreach ($byCommunity as $cid => $list): ?><optgroup label="<?= e($list[0]['community']) ?> громада">
      <option value="c:<?= (int) $cid ?>"<?= $f['place'] === "c:$cid" ? ' selected' : '' ?>>Уся громада</option>
      <?php foreach ($list as $pl): ?><option value="s:<?= (int) $pl['id'] ?>"<?= $f['place'] === 's:' . $pl['id'] ? ' selected' : '' ?>><?= e($pl['name']) ?></option><?php endforeach ?>
    </optgroup><?php endforeach ?>
  </select>
  <select class="input" name="kind" onchange="this.form.submit()" aria-label="Справжні чи приклади">
    <option value="">Справжні й приклади</option>
    <?php foreach (SA::KINDS as $k => $l): ?><option value="<?= $k ?>"<?= $f['kind'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach ?>
  </select>
  <button class="btn sm pri">Знайти</button>
  <?php if ($filtered): ?><a class="btn sm ghost" href="<?= SA::listUrl(['status' => $status]) ?>"><?= icon('x', 15) ?>Скинути</a><?php endif ?>
</form>
<?php if ($status === 'changed'): ?><p class="small muted" style="margin:-4px 0 12px">Тут опубліковані анкети, які майстер змінив після схвалення: зміни вже на сайті, перегляньте їх і натисніть «Зміни в порядку».</p><?php endif ?>

<?php if (!$rows): ?>
<div class="card empty"><h3><?= $filtered ? 'Нічого не знайдено' : (in_array($status, ['pending', 'changed'], true) ? 'Черга порожня' : 'Нічого немає') ?></h3><p class="muted"><?= $filtered ? 'Змініть або скиньте фільтри.' : '' ?></p></div>
<?php else: ?>
<div class="card mq ms">
  <div class="mq-head"><span>Майстер</span><span>Категорія · місце</span><span>Прайс · фото</span><span>Власник</span><span><?= $status === 'pending' ? 'Надіслано' : ($status === 'all' ? 'Стан' : 'Оновлено') ?></span><span></span></div>
  <?php foreach ($rows as $m): $own = (int) $m['user_id'] === (int) $me['id'];
    $when = $status === 'pending' ? ($m['submitted_at'] ?: $m['updated_at']) : $m['updated_at'];
    $edit = url('/admin/masters/' . $m['id'] . '/edit', ['back' => $backQ]);
    $nPh = (int) $m['n_photos']; ?>
  <details class="mq-row">
    <summary>
      <span class="ms-main">
        <span class="m-ava xs"><?php if ($m['photo']): ?><img src="<?= e(S::photoUrl($m['photo'])) ?>" alt="" loading="lazy"><?php else: ?><?= e(initials((string) $m['name'])) ?><?php endif ?></span>
        <span class="mq-main"><b class="mq-title"><?= e($m['name']) ?></b>
          <span class="small muted"><?= e(S::LEGAL[$m['legal_form']] ?? 'Приватна особа') ?><?= $m['is_demo'] ? ' · <span class="tag warn">Приклад</span>' : '' ?><?= $m['is_hidden'] ? ' · на паузі' : '' ?><?= $m['user_status'] !== 'active' ? ' · <span class="tag bad">власника заблоковано</span>' : '' ?></span></span>
      </span>
      <span class="small mq-cell"><span><?= e($m['category']) ?><?= $m['category2'] ? ' + ' . e($m['category2']) : '' ?></span><span class="muted"><?= e($m['settlement'] ?: '—') ?></span></span>
      <span class="small mq-cell"><span><?= (int) $m['n_prices'] ? plural((int) $m['n_prices'], 'позиція', 'позиції', 'позицій') : '<span class="muted">без прайсу</span>' ?></span><span class="muted"><?= (int) $m['n_works'] ? plural((int) $m['n_works'], 'об’єкт', 'об’єкти', 'об’єктів') . ', ' . $nPh . ' фото' : 'без фото робіт' ?></span></span>
      <span class="small mq-cell"><span><?= e($m['display_name']) ?></span><?php if ($m['rejected_before']): ?><span class="muted"><span class="tag bad"><?= (int) $m['rejected_before'] ?> відхилено</span></span><?php else: ?><span class="muted mono"><?= e($m['login']) ?></span><?php endif ?></span>
      <span class="small mq-when"><?php if ($status === 'all' || $status === 'changed'): ?><span class="tag <?= S::STATUS[$m['status']][1] ?>"><?= S::STATUS[$m['status']][0] ?></span><?php if ($m['needs_review'] && $m['status'] === 'published'): ?> <span class="tag warn">змінено</span><?php endif ?>
        <?php else: ?><span class="muted" title="<?= e(local_date($when)) ?>"><?= e(ago($when)) ?></span><?php endif ?>
        <?php if ($m['status'] === 'published' && $m['show_to']): ?><span class="muted" style="display:block">до <?= e(date('d.m.Y', strtotime((string) $m['show_to']))) ?></span><?php endif ?></span>
      <span class="mq-act">
        <?php if (!$own && ($m['status'] === 'pending' || ($m['status'] === 'published' && $m['needs_review']))): ?><form method="post" action="<?= url('/admin/masters/' . $m['id']) ?>"><?= csrf_field() ?><input type="hidden" name="decision" value="approve"><input type="hidden" name="back" value="<?= e($backQ) ?>"><button class="btn sm ok" title="<?= $m['status'] === 'pending' ? 'Опублікувати' : 'Зміни в порядку' ?>"><?= icon('check', 15) ?><span class="mq-lbl"><?= $m['status'] === 'pending' ? 'Опублікувати' : 'Зміни в порядку' ?></span></button></form><?php endif ?>
        <?php if ($canEdit): ?><a class="btn sm ms-edit" href="<?= $edit ?>" title="Редагувати анкету: опис, прайс, фото, виїзд"><?= icon('tool', 15) ?><span class="mq-lbl">Редагувати</span></a><?php endif ?>
        <span class="mq-chev" aria-hidden="true"></span>
      </span>
    </summary>
    <div class="mq-body">
      <div class="small muted mq-meta">
        <span>№ <?= (int) $m['id'] ?></span>
        <?php if ($m['contact_phone']): ?><span><?= icon('phone', 14) ?> <?= e(phone_pretty($m['contact_phone'])) ?></span><?php endif ?>
        <?php if ($m['travel'] || $m['areas']): ?><span><?= icon('pin', 14) ?> <?= e(implode(' · ', array_filter([$m['travel'], $m['areas']]))) ?></span><?php endif ?>
        <span>оплата: <?= e(mb_strtolower(implode(', ', S::payments($m['payments'])) ?: '—')) ?></span>
        <span><?= icon('clock', 14) ?> термін <?= e(S::PERIODS[(int) $m['period_months']] ?? '') ?></span>
        <a href="<?= url('/services/' . $m['id']) ?>" target="_blank" rel="noopener"><?= icon('eye', 14) ?> сторінка майстра</a>
        <a href="<?= url('/admin/users/' . $m['user_id']) ?>"><?= icon('user', 14) ?> картка власника</a>
      </div>
      <?php if (trim((string) $m['about']) !== ''): ?><div class="prose"><?= e($m['about']) ?></div><?php else: ?><p class="small muted" style="margin:0">Опису немає.</p><?php endif ?>
      <?php if ($m['prices']): ?><details class="ms-prices"><summary class="small">Прайс (<?= count($m['prices']) ?>)</summary><table class="ptable small"><?php foreach ($m['prices'] as $pr): ?><tr><td><?= e($pr['title']) ?><?php if ($pr['note']): ?><span class="note"><?= e($pr['note']) ?></span><?php endif ?></td><td><?= e(S::priceText($pr)) ?></td></tr><?php endforeach ?></table></details><?php endif ?>
      <?php if ($m['works']): ?><div class="ms-photos"><?php foreach ($m['works'] as $wk) foreach ($wk['photos'] as $ph): ?><a href="<?= e(Media::url('work', $ph['file'])) ?>" target="_blank" rel="noopener" title="<?= e($wk['title']) ?>"><img src="<?= e(Media::url('workthumb', $ph['file'])) ?>" alt="" loading="lazy"></a><?php endforeach ?></div><?php endif ?>
      <?php if ($m['status'] === 'rejected'): ?><div class="alert bad">Причина: <?= e($m['reject_reason']) ?></div><?php endif ?>
      <?php if ($own): ?><div class="alert info">Це ваш профіль — його має перевірити інший модератор.</div>
      <?php elseif (in_array($m['status'], ['pending', 'published'], true)): ?>
      <div class="mq-do">
        <form method="post" action="<?= url('/admin/masters/' . $m['id']) ?>" class="mq-reject"><?= csrf_field() ?><input type="hidden" name="decision" value="reject"><input type="hidden" name="back" value="<?= e($backQ) ?>">
          <input class="input" name="reason" placeholder="Причина (побачить майстер)" list="ms-reasons" required minlength="5"><button class="btn sm bad"><?= $m['status'] === 'published' ? 'Зняти з каталогу' : 'Відхилити' ?></button></form>
      </div>
      <?php endif ?>
    </div>
  </details>
  <?php endforeach ?>
</div>
<?php if ($pages > 1): ?><nav class="pager"><?php for ($i = 1; $i <= $pages; $i++): ?><?= $i === $f['page'] ? "<span class=\"on\">$i</span>" : '<a href="' . SA::listUrl($f, ['page' => $i]) . "\">$i</a>" ?><?php endfor ?></nav><?php endif ?>
<p class="small muted" style="text-align:center;margin-top:10px">Показано <?= count($rows) ?> з <?= (int) $total ?>. Натисніть на рядок, щоб прочитати опис, переглянути прайс і фото.</p>
<?php endif ?>
<datalist id="ms-reasons"><option value="Фото робіт взяті з інтернету — додайте власні"><option value="Опишіть зрозуміло, які послуги надаєте"><option value="Реклама сторонніх сайтів або посилання в описі"><option value="Образливий або неприйнятний зміст"><option value="Вкажіть реальний робочий телефон"></datalist>
<?php endif ?>
