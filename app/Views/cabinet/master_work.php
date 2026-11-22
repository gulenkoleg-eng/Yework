<?php use App\Core\Media; $nPh = $w ? count($w['photos']) : 0; ?>
<div class="cab">
  <?= App\Core\View::partial('partials/cab_nav', compact('tab', 'counts', 'me')) ?>
  <div class="stack">
    <nav class="crumbs"><a href="<?= url('/cabinet/master') ?>">Я майстер</a>›<a href="<?= url('/cabinet/master') ?>#works">Галерея робіт</a>›<?= $w ? e($w['title']) : 'Новий об’єкт' ?></nav>
    <h1 style="font-size:28px"><?= $w ? 'Об’єкт' : 'Новий об’єкт' ?></h1>
    <?= form_alert() ?>
    <form class="card pad stack" method="post" action="<?= url($w ? '/cabinet/master/works/' . $w['id'] : '/cabinet/master/works/new') ?>" enctype="multipart/form-data"><?= csrf_field() ?>
      <?= input_field('title', 'Що зробили', ['value' => $w['title'] ?? '', 'maxlength' => 160, 'required' => true, 'placeholder' => 'Монтаж двох кондиціонерів у приватному будинку']) ?>
      <div class="grid2">
        <?= input_field('place', 'Де', ['value' => $w['place'] ?? '', 'maxlength' => 120, 'placeholder' => 'Миронівка, приватний будинок']) ?>
        <?= input_field('done_on', 'Коли', ['value' => $w['done_on'] ?? '', 'maxlength' => 20, 'placeholder' => 'Липень 2026']) ?>
      </div>
      <?= input_field('description', 'Опис роботи', ['type' => 'textarea', 'rows' => 4, 'value' => $w['description'] ?? '', 'maxlength' => 3000, 'placeholder' => 'Що входило в роботу, скільки тривало, які матеріали й обладнання, особливості об’єкта.']) ?>
      <?php if ($w && $w['photos']): ?>
        <div class="field"><span class="lbl">Фото об’єкта</span><div class="ph-grid"><?php foreach ($w['photos'] as $p): ?>
          <label><img src="<?= e(Media::url('workthumb', $p['file'])) ?>" alt=""><span class="check"><input type="checkbox" name="photo_delete[]" value="<?= (int) $p['id'] ?>"> Видалити</span></label>
        <?php endforeach ?></div></div>
      <?php endif ?>
      <div class="field<?= err_class('photos') ?>"><label for="f_photos"><?= $w ? 'Додати фото' : 'Фото' ?></label>
        <input id="f_photos" class="rz-file" style="max-width:none" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
        <span class="hint">До <?= (int) $limits['photos'] ?> фото в об’єкті<?= $w ? ' (зараз ' . $nPh . ')' : '' ?>, кожне до 3 МБ. Можна вибрати кілька одразу. Найкраще — «до» і «після».</span><?= field_error('photos') ?></div>
      <div class="row"><button class="btn pri"><?= $w ? 'Зберегти' : 'Додати в портфоліо' ?></button><a class="btn ghost" href="<?= url('/cabinet/master') ?>#works">Скасувати</a></div>
    </form>
  </div>
</div>
