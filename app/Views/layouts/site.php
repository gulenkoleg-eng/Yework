<?php
use App\Core\App; use App\Core\Theme;
$theme = Theme::active();
$me = App::auth()->user();
$site = (string) App::setting('site.name', 'YEWORK');
$slogan = (string) App::setting('site.slogan', 'Є робота!');
$path = $_SERVER['REQUEST_URI'] ?? '/';
$on = fn ($p) => str_starts_with(parse_url($path, PHP_URL_PATH) ?: '/', App::basePath() . $p) ? ' class="on"' : '';
?><!doctype html>
<html lang="uk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e(isset($title) ? "$title — $site" : "$site — $slogan") ?></title>
<meta name="description" content="<?= e($description ?? 'Робота, підробіток і послуги в Миронівці, Богуславі та Кагарлику') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= e(Theme::fontsUrl($theme)) ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="stylesheet" href="<?= asset('css/resume.css') ?>">
<style><?= Theme::css($theme) ?></style>
<script>try{var m=localStorage.getItem('yw-mode');if(m)document.documentElement.dataset.mode=m}catch(e){}</script>
</head>
<body>
<header class="hdr">
  <div class="wrap">
    <a class="logo" href="<?= url('/') ?>"><i>YE</i><span><?= e($site) ?><small><?= e($slogan) ?></small></span></a>
    <nav class="nav" id="nav">
      <a href="<?= url('/jobs') ?>"<?= $on('/jobs') ?>>Вакансії</a>
      <a href="<?= url('/jobs', ['employment' => 'one_time']) ?>">Підробіток</a>
      <a href="<?= url('/resumes') ?>"<?= $on('/resumes') ?>>Резюме</a>
      <a href="<?= url('/cabinet/vacancies/new') ?>">Розмістити вакансію</a>
      <a href="<?= url('/about') ?>"<?= $on('/about') ?>>Про нас</a>
    </nav>
    <div class="hdr-act">
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
    <div class="row"><a href="<?= url('/jobs') ?>">Вакансії</a><?php foreach (\App\Core\Pages::visible() as $pg): ?><a href="<?= url($pg['path']) ?>"><?= e($pg['slug'] === 'rules' ? 'Правила' : $pg['title']) ?></a><?php endforeach ?><?php if (!$me): ?><a href="<?= url('/login') ?>">Вхід</a><?php endif ?></div>
  </div>
</footer>
<script>
document.getElementById('modeBtn').onclick=function(){var r=document.documentElement,c=r.dataset.mode||getComputedStyle(r).colorScheme,n=(c==='dark')?'light':'dark';r.dataset.mode=n;try{localStorage.setItem('yw-mode',n)}catch(e){}};
</script>
</body>
</html>
