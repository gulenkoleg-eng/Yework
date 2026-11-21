<?php use App\Core\Pages; ?>
<div class="adm-h"><h1><?= e($page['title']) ?></h1><a class="btn sm" href="<?= url('/admin/pages') ?>">← Усі сторінки</a><a class="btn sm" href="<?= url($page['path']) ?>" target="_blank">Відкрити на сайті</a></div>
<?= form_alert() ?>
<form class="card pad stack pg-edit" method="post" action="<?= url('/admin/pages/' . $page['slug']) ?>"><?= csrf_field() ?>
  <?= input_field('title', 'Назва сторінки', ['value' => $page['title'], 'maxlength' => 80]) ?>
  <?= input_field('body', 'Текст', ['type' => 'textarea', 'value' => $page['body'], 'rows' => 22]) ?>
  <div class="pg-help">
    <b>Як оформити текст.</b> Порожній рядок починає новий абзац.
    <code>## Заголовок</code> і <code>### Підзаголовок</code> на початку рядка.
    Рядки, що починаються з <code>- </code>, стають списком.
    <code>**жирний текст**</code>. Посилання: <code>[текст](/jobs)</code> або <code>[текст](https://...)</code>, пошта <code>[пошта](mailto:адреса)</code>, телефон <code>[телефон](tel:+380...)</code>.
  </div>
  <label class="check"><input type="checkbox" name="visible" value="1" <?= (array_key_exists('visible', App\Core\App::$old) ? !empty(App\Core\App::$old['visible']) : $page['visible']) ? 'checked' : '' ?>> Показувати сторінку на сайті</label>
  <div class="row" style="gap:10px;flex-wrap:wrap"><button class="btn pri">Зберегти</button>
    <?php if ($page['custom']): ?><button class="btn" name="action" value="reset" formnovalidate onclick="return confirm('Повернути текст за замовчуванням? Ваші зміни буде втрачено.')">Повернути текст за замовчуванням</button><?php endif ?>
  </div>
  <?php if ($page['updated_at']): ?><p class="small muted">Востаннє змінено <?= e(local_date($page['updated_at'])) ?>.</p><?php endif ?>
</form>
<div class="card pad stack page-text"><p class="small muted">Так сторінка виглядає зараз (збережена версія):</p><h1 style="font-size:26px"><?= e($page['title']) ?></h1><?= Pages::toHtml($page['body']) ?></div>
