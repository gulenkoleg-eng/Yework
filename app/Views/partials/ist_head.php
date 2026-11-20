<header>
  <span class="ist-n">Крок <?= $step ?> із 6</span>
  <h2><?= e($title) ?></h2>
  <?php if (!empty($sub)): ?><p class="muted"><?= $sub ?></p><?php endif ?>
</header>
<?php if (!empty($error)): ?><div class="alert bad" role="alert"><?= e($error) ?></div><?php endif ?>
<?php if (!empty($ok)): ?><div class="alert ok" role="status">✓ <?= e($ok) ?></div><?php endif ?>
