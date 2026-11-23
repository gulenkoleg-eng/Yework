<!doctype html>
<html lang="uk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title>Встановлення YEWORK</title>
<link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml"><link rel="icon" href="<?= asset('img/favicon-32.png') ?>" sizes="32x32" type="image/png">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
<div class="ist">
  <aside class="ist-side">
    <div class="logo"><i>YE</i><span>YEWORK<small>Є робота!</small></span></div>
    <div class="ist-side-t">Встановлення платформи</div>
    <ol class="ist-steps">
      <?php foreach ($steps as $n => $label): ?>
        <li class="<?= $n < $step ? 'done' : ($n === $step ? 'cur' : '') ?>"><b><?= $n < $step ? '✓' : $n ?></b><?= e($label) ?></li>
      <?php endforeach ?>
    </ol>
    <div class="ist-side-f">Версія <?= YW_VERSION ?> · PHP + MySQL</div>
  </aside>
  <main class="ist-main"><?= $content ?></main>
</div>
</body>
</html>
