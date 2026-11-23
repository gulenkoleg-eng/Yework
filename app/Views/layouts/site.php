<?php
use App\Core\App; use App\Core\Theme;
$theme = Theme::active();
$me = App::auth()->user();
$site = (string) App::setting('site.name', 'YEWORK');
$slogan = (string) App::setting('site.slogan', 'Є робота!');
$path = $_SERVER['REQUEST_URI'] ?? '/';
$ny = \App\Core\Festive::current(); // новорічне оформлення (0.5.4)
$nyClass = $ny ? 'ny' . ($ny['snow'] ? ' ny-has-snow' : '') . ($ny['garland'] ? ' ny-has-garland' : '') . ($ny['logo'] ? ' ny-has-logo' : '') : '';
$on = fn ($p) => str_starts_with(parse_url($path, PHP_URL_PATH) ?: '/', App::basePath() . $p) ? ' class="on"' : '';
?><!doctype html>
<html lang="uk"<?= $nyClass ? ' class="' . $nyClass . '"' : '' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e(isset($title) ? "$title — $site" : "$site — $slogan") ?></title>
<?= \App\Core\Favicon::tags() ?>
<meta name="description" content="<?= e($description ?? 'Робота, підробіток і послуги в Миронівці, Богуславі та Кагарлику') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= e(Theme::fontsUrl($theme)) ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="stylesheet" href="<?= asset('css/resume.css') ?>">
<link rel="stylesheet" href="<?= asset('css/services.css') ?>">
<link rel="stylesheet" href="<?= asset('css/banners.css') ?>">
<?php if ($ny): ?><link rel="stylesheet" href="<?= asset('css/newyear.css') ?>"><?php endif ?>
<style><?= Theme::css($theme) ?></style>
<script>try{var m=localStorage.getItem('yw-mode');if(m)document.documentElement.dataset.mode=m}catch(e){}</script>
</head>
<body>
<header class="hdr">
  <?php if ($ny && $ny['garland']): ?><div class="ny-garland" aria-hidden="true"><?php for ($i = 0; $i < 64; $i++): ?><i style="--i:<?= $i ?>"></i><?php endfor ?></div><?php endif ?>
  <div class="wrap">
    <a class="logo" href="<?= url('/') ?>"><i>YE</i><span><?= e($site) ?><small><?= e($slogan) ?></small></span></a>
    <nav class="nav" id="nav">
      <a href="<?= url('/jobs') ?>"<?= $on('/jobs') ?>>Вакансії</a>
      <a href="<?= url('/jobs', ['employment' => 'one_time']) ?>">Підробіток</a>
      <a href="<?= url('/resumes') ?>"<?= $on('/resumes') ?>>Резюме</a>
      <a href="<?= url('/services') ?>"<?= $on('/services') ?>>Послуги</a>
      <a href="<?= url('/cabinet/vacancies/new') ?>">Розмістити вакансію</a>
      <a href="<?= url('/about') ?>"<?= $on('/about') ?>>Про нас</a>
      <?php if ($ny && $ny['snow']): ?><button class="ny-snow-btn ny-snow-nav" type="button" aria-pressed="true">❄ Сніг: вимкнути</button><?php endif ?>
    </nav>
    <div class="hdr-act">
      <?php if ($ny && $ny['snow']): ?><button class="btn ghost ny-snow-btn" type="button" aria-pressed="true" aria-label="Сніг на сторінці" title="Вимкнути сніг"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 2v20M3.3 7l17.4 10M3.3 17 20.7 7M9 4l3 2 3-2M9 20l3-2 3 2M4 10.5l3-1-.5-3.5M20 13.5l-3 1 .5 3.5M4 13.5l3 1-.5 3.5M20 10.5l-3-1 .5-3.5"/></svg></button><?php endif ?>
      <button class="btn ghost mode-btn" type="button" id="modeBtn" aria-label="Світла / темна тема" title="Світла / темна тема"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg></button>
      <?php if ($me): ?>
        <?php if ($me['is_staff']): ?><a class="btn sm hide-m" href="<?= url('/admin') ?>">Адмін-панель</a><?php endif ?>
        <a class="btn sm pri" href="<?= url('/cabinet') ?>"><?= e(mb_substr($me['display_name'], 0, 18)) ?></a>
      <?php else: ?>
        <a class="btn sm hide-m" href="<?= url('/login') ?>">Увійти</a>
        <a class="btn sm pri" href="<?= url('/register') ?>">Реєстрація</a>
      <?php endif ?>
      <button class="btn ghost menu-btn" type="button" aria-label="Меню" onclick="document.getElementById('nav').classList.toggle('open')"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
    </div>
  </div>
</header>
<main class="site"><div class="wrap"><?= flash_alert() ?><?= $content ?></div></main>
<footer class="ftr">
  <div class="wrap">
    <div><b><?= e($site) ?></b> — <?= e($slogan) ?><br>Миронівська, Богуславська та Кагарлицька громади</div>
    <div class="row"><a href="<?= url('/jobs') ?>">Вакансії</a><a href="<?= url('/services') ?>">Послуги</a><?php foreach (\App\Core\Pages::visible() as $pg): ?><a href="<?= url($pg['path']) ?>"><?= e($pg['slug'] === 'rules' ? 'Правила' : $pg['title']) ?></a><?php endforeach ?><?php if (!$me): ?><a href="<?= url('/login') ?>">Вхід</a><?php endif ?></div>
  </div>
</footer>
<script>
(function(){var r=document.documentElement,sh=document.getElementById('flt');if(!sh)return;var mq=document.querySelector('[data-mf-q]'),sq=sh.querySelector('input[name=q]');
function open(e){if(e)e.preventDefault();if(mq&&sq)sq.value=mq.value;r.classList.add('sheet-open')}
function close(e){if(e)e.preventDefault();r.classList.remove('sheet-open');if(location.hash==='#flt')history.replaceState(null,'',location.pathname+location.search)}
document.querySelectorAll('[data-sheet-open]').forEach(function(a){a.addEventListener('click',open)});document.querySelectorAll('[data-sheet-close]').forEach(function(a){a.addEventListener('click',close)});
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&r.classList.contains('sheet-open'))close()});
if(location.hash==='#flt'){history.replaceState(null,'',location.pathname+location.search);open()}})();
document.getElementById('modeBtn').onclick=function(){var r=document.documentElement,c=r.dataset.mode||getComputedStyle(r).colorScheme,n=(c==='dark')?'light':'dark';r.dataset.mode=n;try{localStorage.setItem('yw-mode',n)}catch(e){}};
</script>
<?php if ($ny && $ny['snow']): ?><script src="<?= asset('js/newyear.js') ?>" defer></script><?php endif ?>
</body>
</html>
