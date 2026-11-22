<?php $e = $employer ?? []; $lg = logo_settings(); $form = array_key_exists('legal_form', App\Core\App::$old) ? App\Core\App::$old['legal_form'] : (($e['kind'] ?? '') === 'person' ? 'person' : ($e['legal_form'] ?? '')); ?>
<div class="cab">
  <?= App\Core\View::partial('partials/cab_nav', compact('tab', 'counts', 'me')) ?>
  <div class="stack">
  <form class="card pad stack" method="post" action="<?= url('/cabinet/employer') ?>" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
    <h1 style="font-size:26px">Профіль роботодавця</h1>
    <p class="muted">Ця інформація показується у ваших вакансіях. Перевірені роботодавці отримують позначку довіри.</p>
    <?= form_alert() ?>
    <?php if (empty($e['logo']) && $lg['hint']): ?>
      <div class="logo-nudge"><span class="ln-ic"><?= icon('eye', 20) ?></span><div><b>З логотипом вас помітять швидше</b><p class="small"><?= e($lg['hintText']) ?></p><?php if ($lg['perk'] && $lg['perkText'] !== ''): ?><p class="small"><b>Бонус:</b> <?= e($lg['perkText']) ?></p><?php endif ?></div></div>
    <?php endif ?>
    <div class="logo-field<?= err_class('logo') ?>">
      <div class="logo-prev"><?= !empty($e['logo']) ? employer_logo($e['logo'], $e['name'], 96, 'elogo xl') : '<span class="elogo xl ph">' . e(initials($e['name'] ?? '') ?: '?') . '</span>' ?></div>
      <div class="stack" style="gap:6px">
        <label for="f_logo"><b>Логотип</b></label>
        <input id="f_logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp">
        <span class="hint small muted">PNG, JPG або WebP до 3 МБ. Найкраще — квадратний, на світлому або прозорому тлі.</span>
        <?= field_error('logo') ?>
      </div>
    </div>
    <?= input_field('legal_form', 'Форма власності', ['type' => 'select', 'value' => $form, 'options' => ['' => '— оберіть —'] + LEGAL_FORMS, 'hint' => 'Показується поруч із назвою: ТОВ «Агро-Миронівка», ФОП Іваненко']) ?>
    <?= input_field('name', 'Назва (або ім’я для ФОП і приватної особи)', ['value' => $e['name'] ?? '', 'required' => true, 'placeholder' => 'Агро-Миронівка', 'hint' => 'Без «ТОВ» чи «ФОП» — форму власності додамо самі']) ?>
    <div class="grid2">
      <?= input_field('edrpou', 'ЄДРПОУ / РНОКПП', ['value' => $e['edrpou'] ?? '', 'inputmode' => 'numeric', 'hint' => 'Необов’язково, допомагає пройти перевірку']) ?>
      <?= input_field('settlement_id', 'Населений пункт', ['type' => 'select', 'value' => $e['settlement_id'] ?? '', 'options' => ['' => '—'] + array_column($settlements, 'name', 'id')]) ?>
    </div>
    <?= input_field('about', 'Коротко про вас', ['type' => 'textarea', 'value' => $e['about'] ?? '', 'rows' => 4, 'placeholder' => 'Чим займається компанія, скільки працівників, що цінуєте в людях']) ?>
    <?= input_field('website', 'Сайт або сторінка у соцмережах', ['value' => $e['website'] ?? '', 'placeholder' => 'https://']) ?>
    <div class="row"><button class="btn pri"><?= $next === 'vacancy' ? 'Зберегти й перейти до вакансії' : 'Зберегти' ?></button><?php if (!empty($e['is_verified'])): ?><span class="tag ok"><?= icon('shield', 13) ?> Перевірений роботодавець</span><?php endif ?></div>
  </form>
  <?php if (!empty($e['logo'])): ?>
    <form method="post" action="<?= url('/cabinet/employer/logo-delete') ?>" class="row small"><?= csrf_field() ?><span class="muted">Логотип показується у ваших вакансіях і банерах.</span><button class="btn sm ghost">Видалити логотип</button></form>
  <?php endif ?>
  </div>
</div>
