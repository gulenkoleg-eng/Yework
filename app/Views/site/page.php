<?php use App\Core\Pages; ?>
<article class="card pad stack page-text" style="max-width:820px">
  <?php if (!$page['visible']): ?><div class="alert warn">Цю сторінку не видно відвідувачам. Увімкніть її в адмінці, розділ «Сторінки».</div><?php endif ?>
  <h1 style="font-size:30px"><?= e($page['title']) ?></h1>
  <?= Pages::toHtml($page['body']) ?>
  <?php if (App\Core\App::auth()->can('content.page:update')): ?><p class="small muted"><a href="<?= url('/admin/pages/' . $page['slug']) ?>">Редагувати сторінку</a></p><?php endif ?>
</article>
