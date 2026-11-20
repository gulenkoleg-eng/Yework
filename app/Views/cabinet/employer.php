<?php $e = $employer ?? []; ?>
<div class="cab">
  <?= App\Core\View::partial('partials/cab_nav', compact('tab', 'counts', 'me')) ?>
  <form class="card pad stack" method="post" action="<?= url('/cabinet/employer') ?>"><?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
    <h1 style="font-size:26px">Профіль роботодавця</h1>
    <p class="muted">Ця інформація показується у ваших вакансіях. Перевірені роботодавці отримують позначку довіри.</p>
    <?= form_alert() ?>
    <?= input_field('kind', 'Хто ви', ['type' => 'select', 'value' => $e['kind'] ?? 'company', 'options' => ['company' => 'Підприємство / організація', 'fop' => 'ФОП', 'person' => 'Приватна особа']]) ?>
    <?= input_field('name', 'Назва (або ім’я для приватної особи)', ['value' => $e['name'] ?? '', 'required' => true, 'placeholder' => 'ТОВ «Агро-Миронівка»']) ?>
    <div class="grid2">
      <?= input_field('edrpou', 'ЄДРПОУ / РНОКПП', ['value' => $e['edrpou'] ?? '', 'inputmode' => 'numeric', 'hint' => 'Необов’язково, допомагає пройти перевірку']) ?>
      <?= input_field('settlement_id', 'Населений пункт', ['type' => 'select', 'value' => $e['settlement_id'] ?? '', 'options' => ['' => '—'] + array_column($settlements, 'name', 'id')]) ?>
    </div>
    <?= input_field('about', 'Коротко про вас', ['type' => 'textarea', 'value' => $e['about'] ?? '', 'rows' => 4, 'placeholder' => 'Чим займається компанія, скільки працівників, що цінуєте в людях']) ?>
    <?= input_field('website', 'Сайт або сторінка у соцмережах', ['value' => $e['website'] ?? '', 'placeholder' => 'https://']) ?>
    <div class="row"><button class="btn pri"><?= $next === 'vacancy' ? 'Зберегти й перейти до вакансії' : 'Зберегти' ?></button><?php if (!empty($e['is_verified'])): ?><span class="tag ok"><?= icon('shield', 13) ?> Перевірений роботодавець</span><?php endif ?></div>
  </form>
</div>
