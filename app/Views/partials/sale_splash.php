<?php /* Святкова заставка зі знижкою (0.5.26). $sale — з App\Core\Sale::splash(). */
use App\Core\Sale;
$c = $sale['c'];
$site = (string) App\Core\App::setting('site.name', 'YEWORK');
$pct = (int) $c['percent'];
$dates = 'з ' . date('d.m.y', strtotime($c['from'])) . ' по ' . date('d.m.y', strtotime($c['to']));
$deco = ['blackfriday' => '', 'newyear' => '❄', 'march8' => '✿', 'custom' => '★'][$c['event']];
?>
<div class="sale sale-<?= e($c['event']) ?>" id="sale" hidden role="dialog" aria-modal="true" aria-labelledby="saleTitle"
  data-sec="<?= (int) $c['seconds'] ?>" data-cookie="<?= e($sale['cookie']) ?>" data-path="<?= e(App\Core\App::basePath() . '/') ?>"
  data-url="<?= url('/sale/catch') ?>" data-csrf="<?= e(App\Core\Csrf::token()) ?>" data-preview="<?= $sale['preview'] ? 1 : 0 ?>" data-auto="<?= $sale['auto'] ? 1 : 0 ?>" data-show="<?= url('/sale/show') ?>">
  <div class="sale-glow" aria-hidden="true"></div>
  <div class="sale-sparks" aria-hidden="true"><?php for ($i = 0; $i < 28; $i++): ?><i style="--i:<?= $i ?>;--a:<?= ($i * 137) % 360 ?>deg;--d:<?= 38 + ($i * 29) % 34 ?>vmin;--s:<?= 0.6 + (($i * 7) % 10) / 10 ?>"><?= $deco ?></i><?php endfor ?></div>
  <button class="sale-x" type="button" aria-label="Закрити">×</button>
  <div class="sale-in">
    <div class="sale-brand"><span class="logo-chip">YE</span><?= e($site) ?><?php if ($sale['preview']): ?> <span class="sale-prev">попередній перегляд</span><?php endif ?></div>
    <div class="sale-dates"><?= $dates ?></div>
    <h2 class="sale-title" id="saleTitle"><?= nl2br(e(Sale::title($c))) ?></h2>
    <button class="sale-pct" type="button" data-sale-catch><b>−<?= $pct ?>%</b></button>
    <p class="sale-sub"><?= e(Sale::subtitle($c)) ?></p>
    <div class="sale-cta" data-state="wait">
      <span class="sale-tap"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M9 11V5a2 2 0 1 1 4 0v5m0-1a2 2 0 1 1 4 0v2m0-1a2 2 0 1 1 4 0v4a7 7 0 0 1-7 7h-1.5a6 6 0 0 1-4.6-2.2L4.6 14.4a1.8 1.8 0 0 1 2.7-2.4L9 14"/></svg> Тапніть, щоб упіймати знижку!</span>
      <span class="sale-bar"><i style="--t:<?= (int) $c['seconds'] ?>s"></i></span>
    </div>
    <div class="sale-res" data-state="ok-user" hidden>
      <b>Упіймали! −<?= $pct ?>% ваші 🎉</b>
      <p>Знижка на рекламу на сайті діє до <?= e(date('d.m', strtotime($c['to']))) ?> і вже чекає у вашому кабінеті.<?= $sale['monetization'] ? '' : ' Поки реклама безкоштовна, вона спрацює, щойно послуги стануть платними.' ?></p>
      <div class="sale-btns"><a class="sale-btn pri" href="<?= url('/cabinet/ads') ?>">Замовити банер</a><button class="sale-btn" type="button" data-sale-close>Чудово</button></div>
    </div>
    <div class="sale-res" data-state="ok-guest" hidden>
      <b>Упіймали! −<?= $pct ?>% 🎉</b>
      <p>Щоб закріпити знижку, зареєструйтеся або увійдіть просто зараз, у цьому ж браузері.</p>
      <div class="sale-btns"><a class="sale-btn pri" href="<?= url('/register') ?>">Зареєструватися</a><a class="sale-btn" href="<?= url('/login') ?>">Увійти</a></div>
    </div>
    <div class="sale-res" data-state="late" hidden><b>Ой, трохи не встигли</b><p data-msg>Спробуйте завтра!</p></div>
  </div>
</div>
<script>
(function(){var el=document.getElementById('sale');if(!el)return;var R=document.documentElement,sec=+el.dataset.sec||2,st='gone',t,prev=el.dataset.preview==='1';
function post(u){return fetch(u,{method:'POST',credentials:'same-origin',headers:{'X-CSRF':el.dataset.csrf,'Accept':'application/json'}}).then(function(r){return r.json()})}
function open(){if(st!=='gone')return;st='wait';clearTimeout(t);
el.classList.remove('in','out','caught','done','is-late','is-ok');el.querySelector('.sale-cta').hidden=false;el.querySelectorAll('.sale-res').forEach(function(x){x.hidden=true});
el.hidden=false;R.classList.add('sale-lock');void el.offsetWidth;requestAnimationFrame(function(){el.classList.add('in')});
var go=el.querySelector('[data-sale-catch]');try{go.focus({preventScroll:true})}catch(e){}
t=setTimeout(function(){if(st==='wait')close()},sec*1000)}
function close(){if(st==='gone')return;st='gone';clearTimeout(t);el.classList.add('out');setTimeout(function(){if(st==='gone'){el.hidden=true;R.classList.remove('sale-lock')}},450)}
function show(s,msg){el.querySelector('.sale-cta').hidden=true;el.querySelectorAll('.sale-res').forEach(function(x){x.hidden=x.dataset.state!==s});
if(msg){var m=el.querySelector('[data-state=late] [data-msg]');if(m)m.textContent=msg}el.classList.add('done',s==='late'?'is-late':'is-ok');
if(s==='late')t=setTimeout(close,2200);else{var b=el.querySelector('[data-state='+s+'] .sale-btn');if(b)b.focus({preventScroll:true})}}
function grab(){if(st!=='wait')return;st='caught';clearTimeout(t);el.classList.add('caught');
if(prev){setTimeout(function(){show('ok-user')},250);return}
post(el.dataset.url).then(function(d){show(d.ok?(d.guest?'ok-guest':'ok-user'):'late',d.ok?'':d.message)})
.catch(function(){show('late','Не вдалося зв’язатися з сайтом. Оновіть сторінку.')})}
el.addEventListener('click',function(e){if(e.target.closest('.sale-x,[data-sale-close]')){close();return}if(st==='wait')grab()});
document.addEventListener('keydown',function(e){if(e.key==='Escape')close()});
// «YE» в шапці знову відкриває заставку (0.5.27): сервер запам'ятовує новий час показу.
document.querySelectorAll('[data-sale-open]').forEach(function(b){b.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();
if(prev){open();return}post(el.dataset.show).then(function(d){if(d.ok)open()}).catch(function(){})})});
if(el.dataset.auto==='1'){if(!prev){try{document.cookie='yw_sale='+el.dataset.cookie+';path='+el.dataset.path+';max-age=172800;samesite=lax'}catch(e){}}open()}})();
</script>
