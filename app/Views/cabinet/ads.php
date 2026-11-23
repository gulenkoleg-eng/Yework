<?php use App\Core\Ads;
$free = !$paid;
$old = App\Core\App::$old;
$oldPerks = array_values((array) ($old['perks'] ?? []));
$tplNow = isset($templates[$old['template'] ?? '']) ? $old['template'] : $tplDefault;
// Зразок для попереднього перегляду: поки поля порожні, показуємо приклад тексту (сірим у полях він теж є).
$preview = $employer ? ['title' => ($old['title'] ?? '') ?: 'Шукаємо водія категорії C', 'body' => ($old['body'] ?? '') ?: 'Офіційне працевлаштування, доїзд з Миронівки. Досвід не обов’язковий, навчимо.',
  'template' => $tplNow, 'badge' => $old['badge'] ?? '', 'cta' => $old['cta'] ?? '', 'perks' => json_encode(array_filter($oldPerks) ?: ['від 18 000 грн', 'Офіційно', 'Доїзд'], JSON_UNESCAPED_UNICODE),
  'employer' => $employer['name'], 'legal_form' => $employer['legal_form'], 'logo' => $employer['logo'], 'vacancies' => $published, 'image' => null] : [];
$days = [3 => '3 дні', 7 => '7 днів', 14 => '14 днів', 30 => '30 днів'];
$services = [
  ['vacancy_bump', 'Підняти вакансію', 'Вакансія знову стане першою у списку, ніби щойно опублікована.'],
  ['vacancy_highlight', 'Виділити вакансію', 'Кольорова рамка в списку на 7 днів — її помітять першою.'],
  ['vacancy_urgent', 'Позначка «Терміново»', 'Для тих, кому працівник потрібен уже завтра. Діє 7 днів.'],
];
?>
<div class="cab">
  <?= App\Core\View::partial('partials/cab_nav', compact('tab', 'counts', 'me')) ?>
  <div class="stack">
    <h1 style="font-size:28px">Реклама і послуги</h1>
    <?php if ($free): ?><div class="alert info free-note"><div><b>Поки сайт розвивається, усе безкоштовно.</b> Банер можна замовити вже зараз без оплати. Платні послуги з’являться пізніше, ціни нижче — для орієнтиру.</div></div><?php endif ?>
    <?= form_alert() ?>

    <?php if (!$employer): ?>
      <div class="card empty"><h3>Спочатку заповніть профіль роботодавця</h3><p class="muted">Банер показує вашу назву й логотип і веде на ваші вакансії.</p><a class="btn pri" href="<?= url('/cabinet/employer') ?>">Заповнити профіль</a></div>
    <?php else: ?>

    <?php if ($rows): ?>
      <section class="card list">
        <div><h2 style="font-size:20px">Мої банери</h2></div>
        <?php foreach ($rows as $r): [$sl, $sc] = Ads::statusLabel($r); ?>
          <div class="row" style="justify-content:space-between">
            <div style="min-width:0"><a href="<?= url('/cabinet/ads/' . $r['id']) ?>"><b><?= e($r['title']) ?></b></a>
              <div class="small muted"><?= e(Ads::PLACEMENTS[$r['placement']] ?? '') ?> · <?= e(date('d.m', strtotime($r['starts_on']))) ?>–<?= e(date('d.m.Y', strtotime($r['ends_on']))) ?> · <?= plural((int) $r['days'], 'день', 'дні', 'днів') ?></div>
              <?php if ($r['status'] === 'rejected' && $r['reject_reason']): ?><div class="small" style="color:var(--c-danger)">Причина: <?= e($r['reject_reason']) ?></div><?php endif ?></div>
            <div class="row"><span class="tag <?= $sc ?>"><?= e($sl) ?></span><span class="tag"><?= icon('eye', 13) ?> <?= (int) $r['impressions'] ?></span><span class="tag"><?= icon('arrow', 13) ?> <?= (int) $r['clicks'] ?></span></div>
          </div>
        <?php endforeach ?>
      </section>
    <?php endif ?>

    <form class="card pad stack" method="post" action="<?= url('/cabinet/ads') ?>" enctype="multipart/form-data" id="adForm"><?= csrf_field() ?>
      <div><h2 style="font-size:22px">Банер на сайті</h2>
        <p class="muted">Оберіть шаблон і напишіть текст: оформлення, кольори й анімацію ми зробимо самі, у стилі сайту. Натискання на банер веде на ваші вакансії, а поки банер діє, <b>ваші вакансії стоять першими в пошуку</b>. У кабінеті видно, скільки людей побачили банер і скільки перейшли.</p></div>
      <?php if (!$published): ?><div class="alert warn">У вас поки немає опублікованих вакансій. Банер веде саме на них, тож спершу <a href="<?= url('/cabinet/vacancies/new') ?>">розмістіть вакансію</a>.</div><?php endif ?>
      <?php if (!$employer['logo']): ?><div class="alert info">Порада: <a href="<?= url('/cabinet/employer') ?>">додайте логотип</a>, і він з’явиться на банері. Банер з логотипом помічають значно частіше.</div><?php endif ?>
      <div class="bnr-maker">
        <div class="stack">
          <fieldset class="field<?= err_class('template') ?>"><legend class="lbl">Шаблон</legend>
            <div class="bnr-tpls">
              <?php foreach ($templates as $k => [$tl, $td]): ?>
                <label class="bnr-tpl"><input type="radio" name="template" value="<?= e($k) ?>" <?= $tplNow === $k ? 'checked' : '' ?>><span><em class="sw-<?= e($k) ?>"></em><?= e($tl) ?><small><?= e($td) ?></small></span></label>
              <?php endforeach ?>
            </div>
          </fieldset>
          <?= input_field('title', 'Заголовок', ['maxlength' => 80, 'required' => true, 'placeholder' => 'Шукаємо водія категорії C']) ?>
          <?= input_field('body', 'Кого шукаєте і що пропонуєте', ['type' => 'textarea', 'rows' => 3, 'maxlength' => 300, 'required' => true, 'placeholder' => 'Офіційне працевлаштування, доїзд з Миронівки. Досвід не обов’язковий, навчимо.']) ?>
          <div class="field"><span class="lbl">Переваги (до 3, коротко)</span>
            <div class="bnr-perk-in">
              <?php foreach (['від 18 000 грн', 'Офіційно', 'Доїзд'] as $i => $ph): ?><input class="input" name="perks[]" maxlength="24" placeholder="<?= e($ph) ?>" value="<?= e($oldPerks[$i] ?? '') ?>" aria-label="Перевага <?= $i + 1 ?>"><?php endforeach ?>
            </div><span class="hint">З’являться на банері кольоровими чипами. Можна залишити порожніми.</span></div>
          <div class="grid2">
            <div class="field"><label for="f_badge">Позначка</label><input id="f_badge" class="input" name="badge" maxlength="24" list="adBadges" placeholder="Без позначки" value="<?= e($old['badge'] ?? '') ?>"><span class="hint">Наприклад, «Терміново» чи «Новий набір».</span></div>
            <div class="field"><label for="f_cta">Текст кнопки</label><input id="f_cta" class="input" name="cta" maxlength="32" list="adCtas" placeholder="<?= e(Ads::ctaOf(['vacancies' => $published])) ?>" value="<?= e($old['cta'] ?? '') ?>"><span class="hint">Порожньо: «<?= e(Ads::ctaOf(['vacancies' => $published])) ?>».</span></div>
          </div>
          <datalist id="adBadges"><?php foreach (Ads::BADGES as $x): ?><option value="<?= e($x) ?>"><?php endforeach ?></datalist>
          <datalist id="adCtas"><?php foreach (Ads::CTAS as $x): ?><option value="<?= e($x) ?>"><?php endforeach ?></datalist>
          <div class="field<?= err_class('image') ?>"><label for="f_image">Картинка <span class="muted" id="adImgReq">(необов’язково)</span></label><input id="f_image" type="file" name="image" accept="image/png,image/jpeg,image/webp"><span class="hint">Фото підприємства чи команди, до 3 МБ. Для шаблону «Фото» картинка обов’язкова, вона стане фоном усього банера.</span><?= field_error('image') ?></div>
        </div>
        <div class="bnr-live">
          <div class="bnr-live-h"><span class="dot"></span>Так банер виглядатиме на сайті</div>
          <?= App\Core\View::partial('partials/promo', ['b' => $preview, 'live' => true]) ?>
          <p class="small muted">Перегляд оновлюється, поки ви пишете. Над списком вакансій на комп’ютері банер буде широким, з кнопкою праворуч.</p>
        </div>
      </div>
      <fieldset class="field<?= err_class('placement') ?>"><legend>Де показувати</legend>
        <div class="place-opts">
          <?php foreach (Ads::PLACEMENTS as $k => $l): $pd = $prices['banner_' . $k . '_day']; ?>
            <label class="place-opt"><input type="radio" name="placement" value="<?= $k ?>" data-day="<?= (int) $pd ?>" <?= (App\Core\App::$old['placement'] ?? 'both') === $k ? 'checked' : '' ?>><span><b><?= e($l) ?></b><small><?= $free ? '<s>' . money($pd) . ' грн/день</s> безкоштовно' : money($pd) . ' грн/день' ?></small></span></label>
          <?php endforeach ?>
        </div><?= field_error('placement') ?>
      </fieldset>
      <div class="grid2">
        <?= input_field('starts_on', 'Початок показу', ['type' => 'date', 'value' => $today, 'required' => true]) ?>
        <?= input_field('days', 'Скільки днів', ['type' => 'select', 'value' => 7, 'options' => $days, 'hint' => 'Від 7 днів знижка ' . (int) $prices['discount_week'] . '%, від 30 — ' . (int) $prices['discount_month'] . '%']) ?>
      </div>
      <div class="price-line" id="adPrice" data-week="<?= (int) $prices['discount_week'] ?>" data-month="<?= (int) $prices['discount_month'] ?>" data-free="<?= $free ? 1 : 0 ?>"></div>
      <div class="row"><button class="btn pri"><?= $free ? 'Замовити банер безкоштовно' : 'Замовити банер' ?></button><span class="small muted">Банер з’явиться після перевірки модератором.</span></div>
    </form>
    <?php endif ?>

    <section class="stack" style="gap:12px">
      <h2 style="font-size:22px">Інші послуги</h2>
      <div class="svc-grid">
        <?php foreach ($services as [$k, $t, $d]): ?>
          <div class="card svc">
            <h3><?= e($t) ?></h3><p class="small muted"><?= e($d) ?></p>
            <div class="row" style="margin-top:auto"><b class="svc-price"><?= $free ? '<s>' . money($prices[$k]) . ' грн</s> 0 грн' : money($prices[$k]) . ' грн' ?></b><span class="spacer"></span><button class="btn sm" type="button" disabled title="<?= $free ? 'Безкоштовно на період розвитку' : 'Скоро' ?>">Купити</button></div>
            <span class="tag muted" style="align-self:flex-start"><?= $free ? 'Безкоштовно на період розвитку' : 'Скоро' ?></span>
          </div>
        <?php endforeach ?>
      </div>
      <div class="card pad row" style="justify-content:space-between">
        <div><b>Рахунок для оплати</b><p class="small muted"><?= $free ? 'Поки все безкоштовно, рахунки не потрібні. Згодом тут можна буде отримати рахунок для оплати з розрахункового рахунку підприємства.' : 'Рахунок на кожен платний банер — у його картці.' ?></p></div>
        <button class="btn" type="button" disabled>Виставити рахунок</button>
      </div>
    </section>
  </div>
</div>
<script>
(function(){var f=document.getElementById('adForm'),o=document.getElementById('adPrice');if(!f||!o)return;
function upd(){var r=f.querySelector('input[name=placement]:checked'),d=+f.querySelector('[name=days]').value||0;if(!r){o.textContent='';return}
var disc=d>=30?+o.dataset.month:(d>=7?+o.dataset.week:0),sum=Math.round(+r.dataset.day*d*(100-disc)/100),t=sum.toLocaleString('uk-UA')+' грн';
o.innerHTML=o.dataset.free==='1'?'Вартість: <s>'+t+'</s> <b>0 грн</b> — безкоштовно на період розвитку':'Вартість: <b>'+t+'</b>'+(disc?' (знижка '+disc+'%)':'');}
f.addEventListener('change',upd);upd();})();
(function(){var f=document.getElementById('adForm'),b=document.getElementById('bnrLive');if(!f||!b)return;
var q=function(s){return b.querySelector(s)},h3=q('h3'),p=q('p'),ul=q('.bnr-perks'),bd=q('.bnr-badge'),cta=q('.bnr-cta>span'),ph=q('.bnr-photo'),im=q('.bnr-img'),req=document.getElementById('adImgReq');
var E=f.elements,ex={title:h3.textContent,body:p.textContent,cta:cta.textContent},exPerks=f.querySelectorAll('[name="perks[]"]'),url='';
function tpl(){var r=f.querySelector('input[name=template]:checked');return r?r.value:'classic'}
function upd(){var t=tpl();b.className=b.className.replace(/\bt-\w+/,'t-'+t);
h3.textContent=E.title.value.trim()||ex.title;p.textContent=E.body.value.trim()||ex.body;
var ps=[].map.call(exPerks,function(i){return i.value.trim()}).filter(Boolean),any=[].some.call(exPerks,function(i){return i.value.trim()});
if(!any)ps=[].map.call(exPerks,function(i){return i.placeholder});ul.innerHTML='';ps.forEach(function(x){var li=document.createElement('li');li.textContent=x;ul.appendChild(li)});ul.hidden=!ps.length;
var bv=E.badge.value.trim();bd.hidden=!bv;bd.querySelector('span').textContent=bv;
cta.textContent=E.cta.value.trim()||ex.cta;
ph.hidden=!url;im.hidden=!url||t==='photo';if(url){ph.src=url;im.src=url}
req.textContent=t==='photo'?'(обов’язково для «Фото»)':'(необов’язково)';}
E.image.addEventListener('change',function(){if(url)URL.revokeObjectURL(url);var x=E.image.files&&E.image.files[0];url=x&&/^image\//.test(x.type)?URL.createObjectURL(x):'';upd()});
f.addEventListener('input',upd);f.addEventListener('change',upd);upd();})();
</script>
