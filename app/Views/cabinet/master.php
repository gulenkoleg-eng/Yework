<?php
use App\Core\App;
use App\Core\Media;
use App\Core\Services as S;
$old = App::$old;
$errs = App::$errors;
$st = $m['status'] ?? null;
$pv = fn (string $k, $def) => array_key_exists($k, $old) && ($old['section'] ?? 'profile') === 'profile' ? $old[$k] : $def;
$pays = (array) $pv('payments', $m ? array_keys(S::payments($m['payments'])) : ['cash']);
$legal = (string) $pv('legal_form', $m['legal_form'] ?? 'person');
$priceRows = ($old['section'] ?? '') === 'prices' ? array_values(array_filter((array) ($old['price'] ?? []), 'is_array')) : $prices;
if (!$priceRows) $priceRows = [['kind' => 'service', 'price_type' => 'range', 'unit' => 'job']];
$opt = static fn (array $list, $cur) => implode('', array_map(static fn ($k, $v) => '<option value="' . e($k) . '"' . ((string) $k === (string) $cur ? ' selected' : '') . '>' . e($v) . '</option>', array_keys($list), $list));
$prow = static function (int|string $i, array $p, array $errs) use ($opt): string {
    $f = static fn ($k) => e($p[$k] ?? '');
    $bad = isset($errs["price.$i.title"]) || isset($errs["price.$i.price"]);
    $type = (string) ($p['price_type'] ?? 'fixed');
    return '<div class="prow' . ($bad ? ' err' : '') . '" data-row>'
        . '<div class="prow-g"><div class="field"><label>Що це</label><select class="input" name="price[' . $i . '][kind]">' . $opt(S::KINDS, $p['kind'] ?? 'service') . '</select></div>'
        . '<div class="field"><label>Назва</label><input class="input" name="price[' . $i . '][title]" maxlength="160" value="' . $f('title') . '" placeholder="Встановлення кондиціонера, траса до 3 м"></div></div>'
        . '<div class="prow-p"><div class="field"><label>Ціна</label><select class="input" name="price[' . $i . '][price_type]" data-type>' . $opt(S::PRICE_TYPES, $type) . '</select></div>'
        . '<div class="field" data-fromf' . ($type === 'deal' ? ' hidden' : '') . '><label data-froml>' . ($type === 'range' ? 'Від, грн' : 'Сума, грн') . '</label><input class="input" name="price[' . $i . '][price_from]" inputmode="numeric" value="' . $f('price_from') . '" placeholder="7000"></div>'
        . '<div class="field" data-to' . ($type === 'range' ? '' : ' hidden') . '><label>До, грн</label><input class="input" name="price[' . $i . '][price_to]" inputmode="numeric" value="' . $f('price_to') . '" placeholder="8000"></div>'
        . '<div class="field"><label>Одиниця</label><select class="input" name="price[' . $i . '][unit]">' . $opt(S::UNITS, $p['unit'] ?? 'job') . '</select></div></div>'
        . '<div class="row" style="flex-wrap:nowrap"><input class="input" style="flex:1" name="price[' . $i . '][note]" maxlength="300" value="' . $f('note') . '" placeholder="Примітка: що входить у ціну (необов’язково)" aria-label="Примітка">'
        . '<button type="button" class="btn sm ghost" data-del>Прибрати</button></div></div>';
};
$free = !$paid;
$nWorks = count($works);
?>
<div class="cab">
  <?= \App\Core\View::partial('partials/cab_nav', compact('tab', 'counts', 'me')) ?>
  <div class="stack">
    <div class="rz-h">
      <h1 style="font-size:28px">Я майстер</h1>
      <?php if ($st): ?><span class="tag <?= S::STATUS[$st][1] ?>"><?= S::STATUS[$st][0] ?></span><?php if ($m['is_hidden']): ?><span class="tag muted">На паузі</span><?php endif ?><?php endif ?>
      <span class="spacer"></span>
      <?php if ($m): ?><a class="btn sm" href="<?= url('/services/' . $m['id']) ?>"><?= icon('eye', 16) ?>Як бачать клієнти</a><a class="btn sm" href="<?= url('/cabinet/orders') ?>">Замовлення<?php if ($newOrders): ?> <span class="tag warn"><?= (int) $newOrders ?></span><?php endif ?></a><?php endif ?>
    </div>

    <?php if (!$m): ?>
      <p class="muted">Займаєтеся кондиціонерами, відеоспостереженням, ремонтом чи іншою справою? Заповніть профіль, додайте прайс і фото робіт — клієнти з громади знайдуть вас у розділі «Послуги», зателефонують або замовлять через сайт. Після виконаного замовлення клієнт ставить вам зірки.</p>
    <?php elseif ($st === 'rejected'): ?><div class="alert bad">Модератор відхилив профіль: <?= e($m['reject_reason']) ?>. Виправте й надішліть ще раз.</div>
    <?php elseif ($st === 'pending'): ?><div class="alert info">Профіль на перевірці. Поки що додайте прайс і фото робіт — після схвалення все з’явиться в «Послугах».</div>
    <?php elseif ($st === 'published'): ?><div class="alert ok"><div>Профіль показується в «Послугах» до <b><?= e(date('d.m.Y', strtotime((string) $m['show_to']))) ?></b>. Переглядів: <?= (int) $m['views'] ?> · виконано замовлень: <?= (int) $m['orders_done'] ?> · <?= e(S::ratingText($m)) ?>.<?= $m['is_hidden'] ? ' <b>Зараз на паузі — у каталозі не видно.</b>' : '' ?></div></div>
    <?php elseif ($st === 'archived'): ?>
      <form class="card pad stack" method="post" action="<?= url('/cabinet/master') ?>"><?= csrf_field() ?><input type="hidden" name="section" value="restore">
        <div class="alert warn">Профіль в архіві (<?= e(mb_strtolower(S::ARCHIVE_REASONS[$m['archive_reason']] ?? 'знято')) ?><?= $m['archived_at'] ? ', ' . e(local_date($m['archived_at'], 'd.m.Y')) : '' ?>). Його не видно в каталозі. Прайс, фото й відгуки збережено.</div>
        <div class="row"><select class="input" name="period_months" aria-label="Термін показу" style="max-width:200px"><?= $opt(S::PERIODS, $m['period_months']) ?></select><button class="btn pri">Поновити</button><span class="small muted">Повертається одразу, без повторної перевірки.</span></div>
      </form>
    <?php endif ?>
    <?= form_alert() ?>

    <form id="profile" class="card pad stack" method="post" action="<?= url('/cabinet/master') ?>" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="section" value="profile">
      <div><h2 style="font-size:20px">Профіль майстра</h2><p class="small muted">Це бачать клієнти в каталозі «Послуги» і на вашій сторінці.</p></div>
      <div class="rz-person">
        <div class="rz-photo">
          <div class="m-ava lg"><?php if ($m && $m['photo']): ?><img src="<?= e(S::photoUrl($m['photo'])) ?>" alt="Ваше фото"><?php else: ?><?= icon('tool', 40) ?><?php endif ?></div>
          <input class="rz-file" type="file" name="photo" accept="image/jpeg,image/png,image/webp" aria-label="Фото або логотип">
          <span class="small muted">Ваше фото або логотип, до 3 МБ</span>
          <?php if ($m && $m['photo']): ?><label class="check small"><input type="checkbox" name="photo_delete" value="1"> Видалити</label><?php endif ?>
          <?= field_error('photo') ?>
        </div>
        <div class="stack" style="gap:12px">
          <?= input_field('name', 'Ім’я або назва', ['value' => $m['name'] ?? $me['display_name'], 'maxlength' => 100, 'required' => true, 'placeholder' => 'Сергій Коваль — кондиціонери', 'hint' => 'Як вас шукатимуть і запам’ятають клієнти']) ?>
          <fieldset class="field<?= err_class('legal_form') ?>" style="border:0;padding:0;margin:0"><legend class="lbl">Хто ви</legend>
            <div class="chips"><?php foreach (S::LEGAL as $k => $l): ?><label><input type="radio" name="legal_form" value="<?= e($k) ?>" <?= $legal === $k ? 'checked' : '' ?>> <?= e($l) ?></label><?php endforeach ?></div>
            <span class="hint">ФОП чи ТОВ видно клієнтам — це важливо тим, кому потрібні документи й рахунок.</span></fieldset>
        </div>
      </div>
      <div class="grid2">
        <?= input_field('category_id', 'Чим займаєтеся', ['type' => 'select', 'value' => $pv('category_id', $m['category_id'] ?? ''), 'options' => ['' => '— оберіть —'] + array_column($cats, 'name', 'id')]) ?>
        <?= input_field('category2_id', 'Ще один напрям (необов’язково)', ['type' => 'select', 'value' => $pv('category2_id', $m['category2_id'] ?? ''), 'options' => ['' => '—'] + array_column($cats, 'name', 'id')]) ?>
      </div>
      <div class="grid2">
        <?= input_field('settlement_id', 'Де ви працюєте', ['type' => 'select', 'value' => $pv('settlement_id', $m['settlement_id'] ?? ''), 'options' => ['' => '—'] + array_column($settlements, 'name', 'id')]) ?>
        <?= input_field('areas', 'Куди виїжджаєте', ['value' => $m['areas'] ?? '', 'maxlength' => 200, 'placeholder' => 'Миронівка й села до 30 км, Кагарлик']) ?>
      </div>
      <div class="grid2">
        <?= input_field('experience_years', 'Досвід, років', ['value' => $m['experience_years'] ?? '', 'inputmode' => 'numeric', 'maxlength' => 2, 'placeholder' => '8']) ?>
        <?= input_field('contact_phone', 'Робочий телефон', ['type' => 'tel', 'value' => $m ? phone_pretty($m['contact_phone']) : ($myPhone ? phone_pretty($myPhone) : ''), 'placeholder' => '+380 67 123 45 67', 'hint' => 'Клієнти бачать його після натискання «Показати номер»']) ?>
      </div>
      <?= input_field('about', 'Про вашу роботу', ['type' => 'textarea', 'rows' => 5, 'value' => $m['about'] ?? '', 'maxlength' => 4000, 'placeholder' => 'Встановлюю й обслуговую кондиціонери з 2016 року. Монтаж за один день, гарантія на роботу 2 роки. Можу підібрати й привезти кондиціонер. Працюю з ФОП і ТОВ за рахунком.']) ?>
      <fieldset class="field<?= err_class('payments') ?>" style="border:0;padding:0;margin:0"><legend class="lbl">Як можна оплатити</legend>
        <div class="chips"><?php foreach (S::PAYMENTS as $k => $l): ?><label><input type="checkbox" name="payments[]" value="<?= e($k) ?>" <?= in_array($k, $pays, true) ? 'checked' : '' ?>> <?= e($l) ?></label><?php endforeach ?></div><?= field_error('payments') ?></fieldset>
      <?= input_field('period_months', 'Скільки показувати в каталозі', ['type' => 'select', 'value' => $pv('period_months', $m['period_months'] ?? 3), 'options' => S::PERIODS, 'hint' => 'Після цього профіль сам перейде в архів — його можна поновити одним натиском. Зараз безкоштовно.']) ?>
      <div class="rz-actions">
        <?php if (!$m || in_array($st, ['draft', 'rejected'], true)): ?>
          <button class="btn pri" name="action" value="submit"><?= $premod ? 'Надіслати на перевірку' : 'Опублікувати' ?></button>
          <button class="btn" name="action" value="draft">Зберегти чернетку</button>
          <?php if ($premod): ?><span class="small muted">Перший раз профіль перевіряє модератор, далі зміни видно одразу.</span><?php endif ?>
        <?php else: ?>
          <button class="btn pri" name="action" value="save">Зберегти</button>
        <?php endif ?>
      </div>
    </form>

    <?php if ($m): ?>
    <form id="prices" class="card pad stack" method="post" action="<?= url('/cabinet/master') ?>"><?= csrf_field() ?><input type="hidden" name="section" value="prices">
      <div><h2 style="font-size:20px">Прайс</h2><p class="small muted">Послуги з ціною «від – до» або точною сумою, а також обладнання й матеріали, які ви продаєте разом із роботою (наприклад, сам кондиціонер). Зміни прайсу видно одразу.</p></div>
      <?= field_error('prices') ?>
      <div class="rz-rows" id="price-list" data-next="<?= count($priceRows) ?>"><?php foreach ($priceRows as $i => $p) echo $prow($i, $p, $errs) ?></div>
      <template id="price-tpl"><?= $prow(999, ['kind' => 'service', 'price_type' => 'fixed', 'unit' => 'job'], []) ?></template>
      <div class="row"><button type="button" class="btn sm" id="price-add"><?= icon('plus', 16) ?>Додати позицію</button><span class="small muted">До <?= S::MAX_PRICES ?> позицій</span><span class="spacer"></span><button class="btn pri">Зберегти прайс</button></div>
    </form>

    <section id="works" class="card pad stack">
      <div class="row"><div style="flex:1;min-width:220px"><h2 style="font-size:20px">Мої роботи <span class="muted"><?= $nWorks ?> з <?= (int) $limits['works'] ?></span></h2><p class="small muted">Виконані об’єкти з фото: до <?= plural((int) $limits['works'], 'об’єкта', 'об’єктів', 'об’єктів') ?>, у кожному до <?= (int) $limits['photos'] ?> фото. Фото роботи переконують краще за будь-який опис.</p></div>
        <?php if ($nWorks < $limits['works']): ?><a class="btn pri" href="<?= url('/cabinet/master/works/new') ?>"><?= icon('plus') ?>Додати об’єкт</a><?php endif ?></div>
      <?php if ($works): ?><div class="wk-list"><?php foreach ($works as $w): $ph = $w['photos'][0]['file'] ?? null; ?>
        <div class="wk">
          <?php if ($ph): ?><img src="<?= e(Media::url('workthumb', $ph)) ?>" alt="" loading="lazy"><?php else: ?><span class="ph"></span><?php endif ?>
          <div style="min-width:0"><b><?= e($w['title']) ?></b><div class="small muted"><?= e(implode(' · ', array_filter([$w['place'], $w['done_on'], plural(count($w['photos']), 'фото', 'фото', 'фото')]))) ?></div></div>
          <div class="row"><a class="btn sm" href="<?= url('/cabinet/master/works/' . $w['id']) ?>">Змінити</a>
            <form method="post" action="<?= url('/cabinet/master/works/' . $w['id'] . '/delete') ?>" onsubmit="return confirm('Видалити об’єкт разом із фото?')"><?= csrf_field() ?><button class="btn sm ghost bad">Видалити</button></form></div>
        </div>
      <?php endforeach ?></div>
      <?php else: ?><p class="muted small">Ще немає робіт. Додайте 2–3 найкращі об’єкти з фото «до» і «після».</p><?php endif ?>
      <div class="upsell">
        <div><b>Більше об’єктів у портфоліо</b><div class="small muted">+10 об’єктів: <?= $free ? '<s>' . money((int) $billPrices['master_more_works']) . ' грн/міс</s>' : money((int) $billPrices['master_more_works']) . ' грн/міс' ?></div></div>
        <button class="btn sm" type="button" disabled>Скоро</button>
      </div>
      <div class="upsell">
        <div><b>Підняти нагору каталогу</b><div class="small muted">На 7 днів ваш профіль стоїть першим у своїй категорії: <?= $free ? '<s>' . money((int) $billPrices['master_top']) . ' грн</s>' : money((int) $billPrices['master_top']) . ' грн' ?></div></div>
        <button class="btn sm" type="button" disabled>Скоро</button>
      </div>
    </section>

    <?php if ($st === 'published'): ?>
    <div class="card pad rz-actions">
      <form method="post" action="<?= url('/cabinet/master') ?>"><?= csrf_field() ?><input type="hidden" name="section" value="pause"><input type="hidden" name="hide" value="<?= $m['is_hidden'] ? '0' : '1' ?>">
        <button class="btn"><?= $m['is_hidden'] ? 'Зняти з паузи' : 'Поставити на паузу' ?></button></form>
      <span class="small muted" style="flex:1;min-width:200px"><?= $m['is_hidden'] ? 'Зараз профіль не видно в каталозі.' : 'Наприклад, у відпустці або коли завантажені роботою: профіль зникне з каталогу, але термін показу йде далі.' ?></span>
      <form method="post" action="<?= url('/cabinet/master') ?>" onsubmit="return confirm('Перенести профіль в архів? Його можна буде поновити.')"><?= csrf_field() ?><input type="hidden" name="section" value="archive"><button class="btn ghost">В архів</button></form>
    </div>
    <?php endif ?>
    <?php endif ?>
  </div>
</div>
<script>
(function(){
  function wire(root){
    root.querySelectorAll('[data-del]').forEach(function(b){b.onclick=function(){var row=b.closest('[data-row]'),list=row.parentNode;if(list.querySelectorAll('[data-row]').length>1){row.remove()}else{row.querySelectorAll('input').forEach(function(i){i.value=''})}}});
    root.querySelectorAll('[data-type]').forEach(function(s){function upd(){var row=s.closest('[data-row]'),v=s.value;row.querySelector('[data-to]').hidden=v!=='range';row.querySelector('[data-fromf]').hidden=v==='deal';row.querySelector('[data-froml]').textContent=v==='range'?'Від, грн':'Сума, грн'}s.onchange=upd;upd()});
  }
  var add=document.getElementById('price-add');
  if(add)add.onclick=function(){var list=document.getElementById('price-list');if(list.querySelectorAll('[data-row]').length>=<?= S::MAX_PRICES ?>)return;var n=+list.dataset.next;list.dataset.next=n+1;
    var t=document.createElement('div');t.innerHTML=document.getElementById('price-tpl').innerHTML.replace(/\[999\]/g,'['+n+']');var row=t.firstElementChild;list.appendChild(row);wire(row);row.querySelector('input[name$="[title]"]').focus()};
  wire(document);
})();
</script>
