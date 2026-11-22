<?php use App\Core\Services as S; $OS = S::ORDER_STATUS;
$starForm = static function (array $o, ?array $rv): string {
    $cur = (int) ($rv['rating'] ?? 0);
    $h = '<form method="post" action="' . url('/cabinet/orders') . '" class="stack" style="gap:8px">' . csrf_field()
        . '<input type="hidden" name="order_id" value="' . (int) $o['id'] . '"><input type="hidden" name="do" value="review">'
        . '<b>' . ($rv ? 'Ваш відгук (можна змінити)' : 'Оцініть роботу майстра') . '</b><div class="star-in" role="radiogroup" aria-label="Оцінка">';
    for ($i = 5; $i >= 1; $i--) $h .= '<input type="radio" id="st' . $o['id'] . '_' . $i . '" name="rating" value="' . $i . '"' . ($cur === $i ? ' checked' : '') . ' required><label for="st' . $o['id'] . '_' . $i . '" title="' . $i . '">★</label>';
    return $h . '</div><textarea class="input" name="body" rows="3" maxlength="2000" placeholder="Як усе пройшло: вчасно, якісно, чи прибрав за собою, чи відповідала ціна домовленості">' . e($rv['body'] ?? '') . '</textarea>'
        . '<div><button class="btn pri sm">' . ($rv ? 'Оновити відгук' : 'Надіслати відгук') . '</button></div></form>';
};
?>
<div class="cab">
  <?= App\Core\View::partial('partials/cab_nav', compact('tab', 'counts', 'me')) ?>
  <div class="stack">
    <h1 style="font-size:28px">Замовлення послуг</h1>
    <?= form_alert() ?>
    <?php if ($m): ?><div class="tabs"><a class="<?= $otab === 'in' ? 'on' : '' ?>" href="<?= url('/cabinet/orders') ?>">Мені замовили (<?= count($incoming) ?>)</a><a class="<?= $otab === 'my' ? 'on' : '' ?>" href="<?= url('/cabinet/orders', ['tab' => 'my']) ?>">Я замовив (<?= count($mine) ?>)</a></div><?php endif ?>

    <?php if ($otab === 'in'): ?>
      <?php if (!$incoming): ?><div class="card empty"><h3>Замовлень поки немає</h3><p class="muted">Коли клієнт натисне «Замовити» на вашій сторінці, замовлення з’явиться тут і прийде вам на пошту або SMS. Порада: додайте прайс і фото робіт — так замовляють частіше.</p><a class="btn" href="<?= url('/cabinet/master') ?>">Мій профіль майстра</a></div>
      <?php else: ?><div class="card pad"><?php foreach ($incoming as $o): [$sl, $sc] = $OS[$o['status']]; ?>
        <div class="ord" id="o<?= (int) $o['id'] ?>">
          <div class="row"><b><?= e($o['client']) ?></b><span class="tag <?= $sc ?>"><?= e($sl) ?></span><span class="small muted"><?= e(ago($o['created_at'])) ?></span></div>
          <?php if ($o['price_title']): ?><div class="small">Послуга: <b><?= e($o['price_title']) ?></b></div><?php endif ?>
          <p class="prose small" style="margin:0"><?= e($o['message']) ?></p>
          <div class="small"><b>Телефон клієнта:</b> <a href="tel:<?= e(preg_replace('/[^\d+]/', '', (string) $o['contact'])) ?>"><?= e($o['contact']) ?></a></div>
          <div class="acts">
            <?php if ($o['status'] === 'new'): ?>
              <form method="post" action="<?= url('/cabinet/orders') ?>"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>"><button class="btn ok sm" name="do" value="accept">Прийняти</button></form>
            <?php endif ?>
            <?php if ($o['status'] === 'accepted'): ?>
              <form method="post" action="<?= url('/cabinet/orders') ?>"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>"><button class="btn ok sm" name="do" value="done">Виконано</button></form>
            <?php endif ?>
            <?php if (in_array($o['status'], ['new', 'accepted'], true)): ?>
              <form method="post" action="<?= url('/cabinet/orders') ?>" onsubmit="return confirm('Відмовитися від замовлення? Клієнт отримає повідомлення.')"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>"><button class="btn sm ghost bad" name="do" value="decline">Відмовитися</button></form>
            <?php endif ?>
          </div>
          <?php if ($o['review_id']): ?>
            <div class="rv-reply" style="margin-left:0;border-left-color:#f5a623"><?= S::stars((float) $o['rating'], 15) ?><?= $o['review_hidden'] ? ' <span class="tag muted">приховано модератором</span>' : '' ?><?php if ($o['review']): ?><div class="prose"><?= e($o['review']) ?></div><?php endif ?></div>
            <form method="post" action="<?= url('/cabinet/orders') ?>" class="row" style="flex-wrap:nowrap"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>"><input type="hidden" name="do" value="reply">
              <input class="input" style="flex:1" name="reply" maxlength="1000" value="<?= e($o['reply'] ?? '') ?>" placeholder="Публічна відповідь на відгук (необов’язково)" aria-label="Відповідь на відгук"><button class="btn sm">Відповісти</button></form>
          <?php elseif ($o['status'] === 'done'): ?><div class="small muted">Клієнт ще не залишив відгук.</div><?php endif ?>
        </div>
      <?php endforeach ?></div><?php endif ?>
    <?php else: ?>
      <?php if (!$mine): ?><div class="card empty"><h3>Ви ще нічого не замовляли</h3><p class="muted">Знайдіть майстра в розділі «Послуги» й натисніть «Замовити». Після виконаної роботи ви зможете поставити оцінку.</p><a class="btn pri" href="<?= url('/services') ?>">До послуг</a></div>
      <?php else: ?><div class="card pad"><?php foreach ($mine as $o): [$sl, $sc] = $OS[$o['status']]; $rv = $myReviews[(int) $o['master_id']] ?? null; ?>
        <div class="ord" id="o<?= (int) $o['id'] ?>">
          <div class="row"><a href="<?= url('/services/' . $o['master_id']) ?>"><b><?= e($o['master']) ?></b></a><span class="small muted"><?= e($o['category']) ?></span><span class="tag <?= $sc ?>"><?= e($sl) ?></span><span class="small muted"><?= e(ago($o['created_at'])) ?></span></div>
          <?php if ($o['price_title']): ?><div class="small">Послуга: <b><?= e($o['price_title']) ?></b></div><?php endif ?>
          <p class="prose small muted" style="margin:0"><?= e($o['message']) ?></p>
          <?php if (in_array($o['status'], ['new', 'accepted'], true)): ?>
            <?php if ($o['master_phone']): ?><div class="small"><b>Телефон майстра:</b> <a href="tel:<?= e($o['master_phone']) ?>"><?= e(phone_pretty($o['master_phone'])) ?></a></div><?php endif ?>
            <div class="acts">
              <?php if ($o['status'] === 'accepted'): ?><form method="post" action="<?= url('/cabinet/orders') ?>"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>"><button class="btn ok sm" name="do" value="done">Роботу виконано</button></form><?php endif ?>
              <form method="post" action="<?= url('/cabinet/orders') ?>" onsubmit="return confirm('Скасувати замовлення?')"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>"><button class="btn sm ghost" name="do" value="cancel">Скасувати</button></form>
            </div>
            <?php if ($o['status'] === 'new'): ?><div class="small muted">Чекаємо, поки майстер прийме замовлення. Можете й самі зателефонувати.</div><?php endif ?>
          <?php elseif ($o['status'] === 'done'): ?>
            <?php if (!$rv || (int) $rv['order_id'] === (int) $o['id'] || strtotime((string) $o['done_at']) >= strtotime((string) $rv['updated_at'])): ?><?= $starForm($o, $rv) ?><?php else: ?><div class="small muted">Відгук залишено.</div><?php endif ?>
          <?php endif ?>
        </div>
      <?php endforeach ?></div><?php endif ?>
    <?php endif ?>
  </div>
</div>
