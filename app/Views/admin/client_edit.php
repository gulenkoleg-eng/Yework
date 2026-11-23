<?php /* Виправлення картки клієнта адміністратором (0.5.16). */

$e = $employer ?? []; $m = $master; $old = \App\Core\App::$old; $posted = (bool) $old;
$chk = static fn (string $k, bool $def) => $posted ? !empty($old[$k]) : $def;
$form = array_key_exists('legal_form', $old) ? $old['legal_form'] : (($e['kind'] ?? '') === 'person' ? 'person' : ($e['legal_form'] ?? ''));
$isDemo = in_array((int) $u['id'], \App\Core\Demo::content()['users'], true); ?>
<div class="adm-h"><a href="<?= url('/admin/users/' . $u['id']) ?>" class="btn sm ghost">←</a><h1>Редагування картки</h1><span class="muted"><?= e($u['display_name']) ?></span></div>
<form method="post" action="<?= url('/admin/users/' . $u['id'] . '/edit') ?>" enctype="multipart/form-data" class="stack" style="max-width:760px"><?= csrf_field() ?>
  <?= form_alert() ?>
  <div class="card pad stack">
    <h3>Акаунт</h3>
    <?= input_field('display_name', 'Ім’я в акаунті', ['value' => $u['display_name'], 'required' => true, 'maxlength' => 80]) ?>
    <p class="small muted">Логін <span class="mono"><?= e($u['login']) ?></span>. Телефон, email і пароль клієнт змінює сам.</p>
  </div>

  <div class="card pad stack" id="employer">
    <h3>Профіль роботодавця</h3>
    <?php if (!$e): ?>
      <label class="row small" style="gap:8px;flex-wrap:nowrap"><input type="checkbox" name="emp_create" value="1" <?= $chk('emp_create', false) ? 'checked' : '' ?> onchange="document.getElementById('emp-fields').style.display=this.checked?'':'none'"> <span>У клієнта ще немає профілю роботодавця. <b>Створити його</b> (наприклад, щоб одразу вставити логотип).</span></label>
    <?php endif ?>
    <div id="emp-fields" class="stack"<?= !$e && !$chk('emp_create', false) ? ' style="display:none"' : '' ?>>
      <div class="logo-field<?= err_class('logo') ?>">
        <div class="logo-prev"><?= !empty($e['logo']) ? employer_logo($e['logo'], $e['name'], 96, 'elogo xl') : '<span class="elogo xl ph">' . e(initials($e['name'] ?? $u['display_name']) ?: '?') . '</span>' ?></div>
        <div class="stack" style="gap:6px">
          <label for="f_logo"><b><?= !empty($e['logo']) ? 'Замінити логотип' : 'Вставити логотип' ?></b></label>
          <input id="f_logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp">
          <span class="hint small muted">PNG, JPG або WebP до 3 МБ. Найкраще квадратний, на світлому або прозорому тлі.</span>
          <?= field_error('logo') ?>
          <?php if (!empty($e['logo'])): ?><label class="row small" style="gap:6px"><input type="checkbox" name="logo_delete" value="1" <?= $chk('logo_delete', false) ? 'checked' : '' ?>> Видалити логотип</label><?php endif ?>
        </div>
      </div>
      <?= input_field('legal_form', 'Форма власності', ['type' => 'select', 'value' => $form, 'options' => ['' => '— оберіть —'] + LEGAL_FORMS]) ?>
      <?= input_field('emp_name', 'Назва (або ім’я для ФОП і приватної особи)', ['value' => $e['name'] ?? '', 'placeholder' => 'Агро-Миронівка', 'hint' => 'Без «ТОВ» чи «ФОП»: форма власності додається сама']) ?>
      <div class="grid2">
        <?= input_field('edrpou', 'ЄДРПОУ / РНОКПП', ['value' => $e['edrpou'] ?? '', 'inputmode' => 'numeric']) ?>
        <?= input_field('settlement_id', 'Населений пункт', ['type' => 'select', 'value' => $e['settlement_id'] ?? '', 'options' => ['' => '—'] + array_column($settlements, 'name', 'id')]) ?>
      </div>
      <?= input_field('about', 'Коротко про компанію', ['type' => 'textarea', 'value' => $e['about'] ?? '', 'rows' => 4]) ?>
      <?= input_field('website', 'Сайт або сторінка у соцмережах', ['value' => $e['website'] ?? '', 'placeholder' => 'https://']) ?>
      <label class="row small" style="gap:6px"><input type="checkbox" name="is_verified" value="1" <?= $chk('is_verified', !empty($e['is_verified'])) ? 'checked' : '' ?>> <?= icon('shield', 14) ?> Перевірений роботодавець (позначка довіри у вакансіях)</label>
    </div>
  </div>

  <?php if ($m): ?>
  <div class="card pad stack" id="master">
    <h3>Картка майстра</h3>
    <div class="logo-field<?= err_class('master_photo') ?>">
      <div class="logo-prev"><?= $m['photo'] ? '<img class="elogo xl" src="' . e(\App\Core\Media::url('master', $m['photo'])) . '" alt="" width="96" height="96">' : '<span class="elogo xl ph">' . e(initials($m['name'])) . '</span>' ?></div>
      <div class="stack" style="gap:6px">
        <label for="f_master_photo"><b><?= $m['photo'] ? 'Замінити фото або логотип' : 'Вставити фото або логотип' ?></b></label>
        <input id="f_master_photo" type="file" name="master_photo" accept="image/png,image/jpeg,image/webp">
        <span class="hint small muted">Показується в каталозі «Послуги» і на сторінці майстра.</span>
        <?= field_error('master_photo') ?>
        <?php if ($m['photo']): ?><label class="row small" style="gap:6px"><input type="checkbox" name="master_photo_delete" value="1" <?= $chk('master_photo_delete', false) ? 'checked' : '' ?>> Видалити фото</label><?php endif ?>
      </div>
    </div>
    <?= input_field('master_name', 'Ім’я або назва майстра', ['value' => $m['name'], 'maxlength' => 100]) ?>
  </div>

  <?php /* 0.5.24: анкета майстра (опис, прайс, фото, виїзд) прямо в картці клієнта + кнопка повної правки. */
    $mf = \App\Core\Services::find((int) $m['id']) ?? $m; $mPrices = \App\Core\Services::prices((int) $m['id']); $mWorks = \App\Core\Services::works((int) $m['id']);
    $mTravel = \App\Core\Services::travelText($mf, \App\Core\Services::travelAreas((int) $m['id']));
    $mEdit = \App\Core\App::auth()->can(\App\Controllers\Admin\MasterEditController::PERM); $mSt = \App\Core\Services::STATUS[$m['status']] ?? ['—', 'muted']; ?>
  <link rel="stylesheet" href="<?= asset('css/services.css') ?>">
  <div class="card pad stack ce-master">
    <div class="row" style="align-items:flex-start">
      <div style="flex:1;min-width:220px"><h3>Анкета майстра <span class="tag <?= $mSt[1] ?>" style="vertical-align:middle"><?= $mSt[0] ?></span></h3>
        <p class="small muted" style="margin:4px 0 0"><?= e($mf['category'] ?? '') ?><?= !empty($mf['category2']) ? ' + ' . e($mf['category2']) : '' ?><?= !empty($mf['settlement']) ? ' · ' . e($mf['settlement']) : '' ?><?= $mTravel ? ' · ' . e(mb_strtolower($mTravel)) : '' ?><?= $m['areas'] ? ' · ' . e($m['areas']) : '' ?></p></div>
      <?php if ($mEdit): ?><a class="btn pri" href="<?= url('/admin/masters/' . $m['id'] . '/edit', ['back' => 'client']) ?>"><?= icon('tool', 16) ?>Редагувати опис, прайс і фото</a><?php endif ?>
    </div>
    <?php if (trim((string) $m['about']) !== ''): ?><div class="prose small ce-about"><?= e($m['about']) ?></div><?php else: ?><p class="small muted" style="margin:0">Опису ще немає.</p><?php endif ?>
    <?php if ($mPrices): ?><details><summary class="small" style="cursor:pointer;color:var(--c-primary);font-weight:600">Прайс (<?= count($mPrices) ?>)</summary><table class="ptable small"><?php foreach ($mPrices as $pr): ?><tr><td><?= e($pr['title']) ?><?php if ($pr['note']): ?><span class="note"><?= e($pr['note']) ?></span><?php endif ?></td><td><?= e(\App\Core\Services::priceText($pr)) ?></td></tr><?php endforeach ?></table></details>
    <?php else: ?><p class="small muted" style="margin:0">Прайсу ще немає.</p><?php endif ?>
    <?php if ($mWorks): ?><div class="ms-photos"><?php foreach ($mWorks as $wk) foreach ($wk['photos'] as $ph): ?><img src="<?= e(\App\Core\Media::url('workthumb', $ph['file'])) ?>" alt="" title="<?= e($wk['title']) ?>" loading="lazy"><?php endforeach ?></div><?php endif ?>
    <p class="small muted" style="margin:0"><?= $mEdit ? 'Кнопка «Редагувати» відкриває повну анкету: назва й напрям, місце роботи й виїзд, опис, телефон, оплата, прайс і галерея робіт. Власник отримає сповіщення про зміни.' : 'Виправляти анкету майстра може адміністратор із правом «Редагування оголошень (послуги)».' ?> <a href="<?= url('/admin/masters', ['status' => 'all', 'q' => (string) $m['id']]) ?>">Знайти в розділі «Майстри»</a> · <a href="<?= url('/services/' . $m['id']) ?>" target="_blank" rel="noopener">сторінка майстра</a></p>
  </div>
  <?php endif ?>

  <div class="card pad stack">
    <?php if ($isDemo): ?><p class="small muted">Це приклад оголошення, тож сповіщення нікому не надсилається.</p>
    <?php else: ?><?= input_field('admin_note', 'Коментар для клієнта (необов’язково)', ['type' => 'textarea', 'rows' => 2, 'maxlength' => 500, 'placeholder' => 'Наприклад: додали логотип вашої компанії', 'hint' => 'Клієнт отримає сповіщення в кабінеті й лист (або SMS) з переліком змін і цим коментарем']) ?><?php endif ?>
    <div class="row"><button class="btn pri">Зберегти</button><a class="btn" href="<?= url('/admin/users/' . $u['id']) ?>">Скасувати</a></div>
  </div>
</form>
