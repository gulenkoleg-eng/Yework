<?php
use App\Core\Pages;
// Інші видимі сторінки (Про нас, Правила, Контакти) — для бічної картки, щоб між ними легко переходити.
$others = array_values(array_filter(Pages::visible(), static fn ($p) => $p['slug'] !== $page['slug']));
?>
<nav class="crumbs"><a href="<?= url('/') ?>">Головна</a>›<span><?= e($page['title']) ?></span></nav>
<div class="pg">
  <article class="card pg-main page-text">
    <?php if (!$page['visible']): ?><div class="alert warn">Цю сторінку не видно відвідувачам. Увімкніть її в адмінці, розділ «Сторінки».</div><?php endif ?>
    <h1><?= e($page['title']) ?></h1>
    <?= Pages::toHtml($page['body']) ?>
    <?php if (App\Core\App::auth()->can('content.page:update')): ?><p class="small muted pg-edit-link"><a href="<?= url('/admin/pages/' . $page['slug']) ?>">Редагувати сторінку</a></p><?php endif ?>
  </article>
  <aside class="card side-card pg-side">
    <b class="pg-side-h">YEWORK — Є робота!</b>
    <p class="small muted">Робота, підробіток і послуги поруч із домом у Миронівській, Богуславській та Кагарлицькій громадах.</p>
    <a class="btn pri block" href="<?= url('/jobs') ?>"><?= icon('search', 16) ?>Шукати вакансії</a>
    <a class="btn block" href="<?= url('/cabinet/vacancies/new') ?>"><?= icon('plus', 16) ?>Розмістити вакансію</a>
    <?php if ($others): ?>
      <nav class="pg-links" aria-label="Інші сторінки">
        <span class="small muted">Корисне</span>
        <?php foreach ($others as $o): ?><a href="<?= url($o['path']) ?>"><?= e($o['title']) ?><?= icon('arrow', 15) ?></a><?php endforeach ?>
      </nav>
    <?php endif ?>
  </aside>
</div>
