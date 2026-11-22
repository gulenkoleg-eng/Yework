<?php
use App\Core\App;
use App\Core\Media;
use App\Core\Services as S;
$r = S::rating($m);
$svc = array_values(array_filter($prices, static fn ($p) => $p['kind'] === 'service'));
$goods = array_values(array_filter($prices, static fn ($p) => $p['kind'] === 'goods'));
$pays = S::payments($m['payments']);
$public = S::isPublic($m);
$visible = array_values(array_filter($reviews, static fn ($x) => !$x['is_hidden']));
$err = App::$errors;
?>
<nav class="crumbs"><a href="<?= url('/') ?>">Головна</a>›<a href="<?= url('/services') ?>">Послуги</a>›<a href="<?= url('/services', ['category' => $m['category_slug']]) ?>"><?= e($m['category']) ?></a>›<?= e($m['name']) ?></nav>
<?php if (!$public): ?><div class="alert warn" style="margin-bottom:14px">Статус: <b><?= e(S::STATUS[$m['status']][0] ?? $m['status']) ?><?= $m['is_hidden'] ? ', на паузі' : '' ?></b>. <?= $isOwner ? 'Так вашу сторінку бачитимуть клієнти, коли вона буде в каталозі.' : 'Сторінку бачите ви як модератор.' ?></div><?php endif ?>
<div class="mp">
  <div class="mp-main">
    <section class="card mp-head">
      <span class="m-ava lg"><?php if ($m['photo']): ?><img src="<?= e(S::photoUrl($m['photo'])) ?>" alt="<?= e($m['name']) ?>"><?php else: ?><?= e(initials((string) $m['name'])) ?><?php endif ?></span>
      <div class="stack" style="gap:8px;min-width:0">
        <h1><?= e($m['name']) ?></h1>
        <div class="rate"><?php if ($r !== null): ?><?= S::stars($r, 18) ?> <span style="font-size:17px"><?= e(number_format($r, 1, ',', '')) ?></span> <a class="muted" href="#reviews"><?= plural((int) $m['rating_count'], 'відгук', 'відгуки', 'відгуків') ?></a><?php else: ?><span class="muted">Ще немає відгуків</span><?php endif ?><?php if ((int) $m['orders_done']): ?> <span class="muted">· <?= plural((int) $m['orders_done'], 'замовлення', 'замовлення', 'замовлень') ?> через сайт</span><?php endif ?></div>
        <div class="facts">
          <span class="tag"><?= icon('tool', 14) ?><?= e($m['category']) ?></span>
          <?php if ($m['category2']): ?><span class="tag"><?= e($m['category2']) ?></span><?php endif ?>
          <?php if ($m['settlement']): ?><span class="tag"><?= icon('pin', 14) ?><?= e($m['settlement']) ?></span><?php endif ?>
          <?php if ($m['experience_years']): ?><span class="tag">Досвід <?= plural((int) $m['experience_years'], 'рік', 'роки', 'років') ?></span><?php endif ?>
          <?php if (in_array($m['legal_form'], ['ФОП', 'ТОВ', 'ПП'], true)): ?><span class="tag ok"><?= icon('shield', 13) ?><?= e($m['legal_form']) ?></span><?php endif ?>
        </div>
        <?php if ($m['areas']): ?><div class="small muted">Виїзд: <?= e($m['areas']) ?></div><?php endif ?>
      </div>
    </section>

    <?php if (trim((string) $m['about']) !== ''): ?><section class="card mp-sec"><h2>Про роботу</h2><div class="prose"><?= e($m['about']) ?></div></section><?php endif ?>

    <?php if ($prices): ?>
    <section class="card mp-sec" id="prices"><h2>Ціни</h2>
      <table class="ptable">
        <?php if ($svc && $goods): ?><tr><td colspan="2"><h4>Послуги</h4></td></tr><?php endif ?>
        <?php foreach ($svc as $p): ?><tr><td><?= e($p['title']) ?><?php if ($p['note']): ?><span class="note"><?= e($p['note']) ?></span><?php endif ?></td><td><?= e(S::priceText($p)) ?></td></tr><?php endforeach ?>
        <?php if ($goods): ?><tr><td colspan="2"><h4>Обладнання й матеріали</h4></td></tr><?php endif ?>
        <?php foreach ($goods as $p): ?><tr><td><?= e($p['title']) ?><?php if ($p['note']): ?><span class="note"><?= e($p['note']) ?></span><?php endif ?></td><td><?= e(S::priceText($p)) ?></td></tr><?php endforeach ?>
      </table>
      <p class="small muted" style="margin:0">Остаточну ціну майстер називає після огляду об’єкта.</p>
    </section>
    <?php endif ?>

    <?php if ($works): ?>
    <section class="card mp-sec" id="works"><h2>Виконані роботи</h2>
      <div class="works"><?php foreach ($works as $w): ?>
        <article class="work"><h3><?= e($w['title']) ?></h3>
          <?php if ($w['place'] || $w['done_on']): ?><div class="small muted"><?= e(implode(' · ', array_filter([$w['place'], $w['done_on']]))) ?></div><?php endif ?>
          <?php if (trim((string) $w['description']) !== ''): ?><div class="prose small" style="margin-top:4px"><?= e($w['description']) ?></div><?php endif ?>
          <div class="gal"><?php foreach ($w['photos'] as $p): ?><a href="<?= e(Media::url('work', $p['file'])) ?>" data-lb><img src="<?= e(Media::url('workthumb', $p['file'])) ?>" alt="<?= e($w['title']) ?>" loading="lazy"></a><?php endforeach ?></div>
        </article>
      <?php endforeach ?></div>
    </section>
    <?php endif ?>

    <section class="card mp-sec" id="reviews"><h2>Відгуки <?php if ($visible): ?><span class="muted"><?= count($visible) ?></span><?php endif ?></h2>
      <?php if ($visible): ?>
        <div class="rdist"><?php for ($i = 5; $i >= 1; $i--): $pc = count($visible) ? round($dist[$i] / count($visible) * 100) : 0; ?><div><span><?= $i ?> ★</span><i><b style="width:<?= $pc ?>%"></b></i><span class="muted"><?= (int) $dist[$i] ?></span></div><?php endfor ?></div>
      <?php endif ?>
      <?php if (!$reviews): ?><p class="muted" style="margin:0">Поки відгуків немає. Відгук може залишити лише клієнт, який замовив майстра через сайт і отримав роботу.</p><?php endif ?>
      <div><?php foreach ($reviews as $x): ?>
        <div class="rv"<?= $x['is_hidden'] ? ' style="opacity:.6"' : '' ?>>
          <div class="row"><?= S::stars((float) $x['rating'], 15) ?><b><?= e($x['client']) ?></b><span class="tag ok"><?= icon('check', 12) ?>замовлення через сайт</span><span class="small muted"><?= e(ago($x['created_at'])) ?></span><?php if ($x['is_hidden']): ?><span class="tag muted">приховано модератором</span><?php endif ?></div>
          <?php if ($x['body']): ?><div class="prose"><?= e($x['body']) ?></div><?php endif ?>
          <?php if ($x['reply']): ?><div class="rv-reply"><b>Відповідь майстра:</b> <span class="prose"><?= e($x['reply']) ?></span></div><?php endif ?>
        </div>
      <?php endforeach ?></div>
    </section>
  </div>

  <aside class="card side-card" id="order">
    <?php if ($m['contact_phone'] && ($public || $isOwner || $isMod)): ?>
      <button type="button" class="btn pri block" id="showPhone" data-url="<?= url('/services/' . $m['id'] . '/phone') ?>"><?= icon('phone') ?>Показати номер</button>
    <?php endif ?>
    <?php if ($pays): ?><div><b class="small">Оплата</b><div class="pays" style="margin-top:6px"><?php foreach ($pays as $l): ?><span class="tag"><?= e($l) ?></span><?php endforeach ?></div></div><?php endif ?>
    <hr style="border:0;border-top:1px solid var(--c-border);margin:4px 0;width:100%">
    <?php if ($isOwner): ?>
      <b>Це ваша сторінка</b>
      <p class="small muted" style="margin:0">Так її бачать клієнти. Змінити прайс, фото й опис можна в кабінеті.</p>
      <a class="btn block" href="<?= url('/cabinet/master') ?>">Редагувати</a>
    <?php elseif (!$public): ?>
      <p class="small muted" style="margin:0">Майстер зараз не приймає замовлення через сайт.</p>
    <?php elseif (!$me): ?>
      <b>Замовити через сайт</b>
      <p class="small muted" style="margin:0">Після виконаної роботи ви зможете поставити оцінку майстру. Потрібно увійти — це хвилина.</p>
      <a class="btn block" href="<?= url('/login', ['next' => '/services/' . $m['id']]) ?>">Увійти й замовити</a>
    <?php elseif ($active): ?>
      <div class="alert ok small">Ваше замовлення: <b><?= e(mb_strtolower(S::ORDER_STATUS[$active['status']][0])) ?></b> (<?= e(ago($active['created_at'])) ?>).</div>
      <a class="btn block" href="<?= url('/cabinet/orders', ['tab' => 'my']) ?>#o<?= (int) $active['id'] ?>">Мої замовлення</a>
    <?php else: ?>
      <form method="post" action="<?= url('/services/' . $m['id']) ?>" class="stack" style="gap:10px"><?= csrf_field() ?>
        <b>Замовити через сайт</b>
        <?= form_alert() ?>
        <?php if ($svc): ?><select class="input" name="price_id" aria-label="Послуга"><option value="">Оберіть послугу (необов’язково)</option><?php foreach ($prices as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (string) (App::$old['price_id'] ?? '') === (string) $p['id'] ? 'selected' : '' ?>><?= e(mb_strimwidth($p['title'], 0, 60, '…')) ?> — <?= e(S::priceText($p)) ?></option><?php endforeach ?></select><?php endif ?>
        <textarea class="input<?= isset($err['message']) ? ' err' : '' ?>" name="message" rows="4" maxlength="2000" required placeholder="Що потрібно зробити, адреса (село), коли зручно" aria-label="Опис замовлення"><?= old('message') ?></textarea><?= field_error('message') ?>
        <input class="input<?= isset($err['contact']) ? ' err' : '' ?>" name="contact" maxlength="40" required value="<?= old('contact', $myPhone ? phone_pretty($myPhone) : '') ?>" placeholder="Ваш телефон" aria-label="Ваш телефон"><?= field_error('contact') ?>
        <button class="btn pri block">Замовити</button>
        <span class="small muted">Майстер отримає повідомлення й зателефонує. Після роботи — оцінка й відгук.</span>
      </form>
      <?php if ($canReview): ?><a class="btn ghost block" href="<?= url('/cabinet/orders', ['tab' => 'my']) ?>"><?= $myReview ? 'Змінити мій відгук' : 'Оцінити майстра' ?></a><?php endif ?>
    <?php endif ?>
    <p class="small muted" style="margin:0"><?= icon('eye', 13) ?> На сайті з <?= e(local_date($m['published_at'] ?? $m['created_at'], 'd.m.Y')) ?></p>
  </aside>
</div>
<div class="lb" id="lb" aria-hidden="true"><button class="x" type="button" aria-label="Закрити">×</button><button class="p" type="button" aria-label="Попереднє">‹</button><img alt=""><button class="n" type="button" aria-label="Наступне">›</button></div>
<script>
(function(){
  var b=document.getElementById('showPhone');
  if(b)b.onclick=function(){var m=document.cookie.match(/(?:^|; )yw_csrf=([^;]*)/);b.disabled=true;
    fetch(b.dataset.url,{method:'POST',headers:{'Accept':'application/json','X-CSRF':m?decodeURIComponent(m[1]):''},credentials:'same-origin'}).then(function(r){return r.json()}).then(function(d){
      if(!d.phone){b.disabled=false;b.textContent=d.message||'Помилка';return}var a=document.createElement('a');a.className='btn pri block';a.href='tel:'+d.phone;a.textContent=d.pretty;b.replaceWith(a)}).catch(function(){b.disabled=false})};
  var lb=document.getElementById('lb'),img=lb.querySelector('img'),links=[].slice.call(document.querySelectorAll('[data-lb]')),i=0;
  function show(n){i=(n+links.length)%links.length;img.src=links[i].href;lb.classList.add('open');lb.setAttribute('aria-hidden','false')}
  function close(){lb.classList.remove('open');lb.setAttribute('aria-hidden','true');img.removeAttribute('src')}
  links.forEach(function(a,n){a.onclick=function(e){e.preventDefault();show(n)}});
  lb.querySelector('.x').onclick=close;lb.querySelector('.p').onclick=function(){show(i-1)};lb.querySelector('.n').onclick=function(){show(i+1)};
  lb.onclick=function(e){if(e.target===lb)close()};
  document.addEventListener('keydown',function(e){if(!lb.classList.contains('open'))return;if(e.key==='Escape')close();if(e.key==='ArrowLeft')show(i-1);if(e.key==='ArrowRight')show(i+1)});
})();
</script>
