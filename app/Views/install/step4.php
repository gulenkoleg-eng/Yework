<form class="ist-card" method="post" action="<?= url('/install', ['step' => 4]) ?>">
  <?= csrf_field() ?>
  <?= App\Core\View::partial('partials/ist_head', ['step' => 4, 'title' => 'Тема оформлення', 'sub' => 'Буде встановлено всі 6 тем. Обрана стане активною, змінити її можна будь-коли в розділі «Дизайн → Теми».', 'error' => $error]) ?>
  <div class="ist-themes">
    <?php foreach ($themes as $t): $c = $t[$t['defaultMode']]; ?>
      <label class="ist-theme">
        <input type="radio" name="theme" value="<?= e($t['id']) ?>" <?= $st['site']['theme'] === $t['id'] ? 'checked' : '' ?>>
        <span class="sw" style="background:<?= e($c['bgPage']) ?>"><i style="background:<?= e($c['bgCard']) ?>;border-color:<?= e($c['border']) ?>"><em style="background:<?= e($c['heading']) ?>"></em><b style="background:<?= e($c['button']) ?>"></b></i></span>
        <strong><?= e($t['name']) ?></strong><small><?= e($t['desc']) ?></small>
      </label>
    <?php endforeach ?>
  </div>
  <div class="ist-actions"><a class="btn" href="<?= url('/install', ['step' => 3]) ?>">Назад</a><button class="btn pri">Далі</button></div>
</form>
