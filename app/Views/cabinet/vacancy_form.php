<?php $v = $v ?? []; use App\Core\Vacancies as VC;
  // Для архівної вакансії пропонуємо новий період, для решти — збережений.
  $keep = $v && !empty($v['show_to']) && $v['status'] !== 'archived' && $v['show_to'] >= VC::today();
  $pFrom = $keep ? $v['show_from'] : VC::today(); $pTo = $keep ? $v['show_to'] : VC::plusDays(VC::today(), VC::DEFAULT_DAYS - 1); ?>
<div class="cab">
  <?= App\Core\View::partial('partials/cab_nav', compact('tab', 'counts', 'me')) ?>
  <form class="card pad stack" method="post" action="<?= url($v ? '/cabinet/vacancies/' . $v['id'] . '/edit' : '/cabinet/vacancies/new') ?>"><?= csrf_field() ?>
    <div><h1 style="font-size:26px"><?= $v ? 'Редагування вакансії' : 'Нова вакансія' ?></h1><p class="muted">Від імені: <b><?= e($employer['name']) ?></b> · <a href="<?= url('/cabinet/employer') ?>">змінити профіль</a></p></div>
    <?php if ($v && in_array($v['status'], ['published', 'scheduled', 'archived'], true)): ?><div class="alert warn">Після змін вакансія знову пройде модерацію.<?= $v['status'] === 'archived' ? ' Щоб поновити без змін, скористайтеся кнопкою «Поновити» на сторінці вакансії.' : '' ?></div><?php endif ?>
    <?= form_alert() ?>
    <?= input_field('title', 'Посада', ['value' => $v['title'] ?? '', 'required' => true, 'placeholder' => 'Продавець-консультант', 'maxlength' => 120]) ?>
    <div class="grid2">
      <?= input_field('category_id', 'Категорія', ['type' => 'select', 'value' => $v['category_id'] ?? '', 'options' => ['' => 'Оберіть…'] + array_column($categories, 'name', 'id')]) ?>
      <?= input_field('employment', 'Зайнятість', ['type' => 'select', 'value' => $v['employment'] ?? 'full', 'options' => EMPLOYMENT_TYPES]) ?>
    </div>
    <div class="grid2">
      <?= input_field('settlement_id', 'Населений пункт', ['type' => 'select', 'value' => $v['settlement_id'] ?? ($employer['settlement_id'] ?? ''), 'options' => ['' => 'Оберіть…'] + array_column($settlements, 'name', 'id')]) ?>
      <?= input_field('address', 'Адреса (необов’язково)', ['value' => $v['address'] ?? '', 'placeholder' => 'вул. Соборна, 1']) ?>
    </div>
    <div class="field"><span class="lbl">Зарплата, грн</span>
      <div class="grid3" style="grid-template-columns:1fr 1fr 1fr">
        <?= input_field('salary_from', 'від', ['value' => $v['salary_from'] ?? '', 'inputmode' => 'numeric']) ?>
        <?= input_field('salary_to', 'до', ['value' => $v['salary_to'] ?? '', 'inputmode' => 'numeric']) ?>
        <?= input_field('salary_period', 'за', ['type' => 'select', 'value' => $v['salary_period'] ?? 'month', 'options' => ['month' => 'місяць', 'day' => 'день', 'hour' => 'годину', 'piece' => 'роботу']]) ?>
      </div><span class="hint">Вакансії з зарплатою отримують у 2–3 рази більше відгуків</span>
    </div>
    <?= input_field('description', 'Опис роботи', ['type' => 'textarea', 'value' => $v['description'] ?? '', 'rows' => 6, 'required' => true, 'placeholder' => 'Що потрібно робити, графік, де саме']) ?>
    <?= input_field('requirements', 'Вимоги', ['type' => 'textarea', 'value' => $v['requirements'] ?? '', 'rows' => 3]) ?>
    <?= input_field('offers', 'Що ви пропонуєте', ['type' => 'textarea', 'value' => $v['offers'] ?? '', 'rows' => 3, 'placeholder' => 'Офіційне працевлаштування, обіди, доїзд…']) ?>
    <label class="check"><input type="checkbox" name="no_experience" value="1" <?= !empty(App\Core\App::$old['no_experience'] ?? $v['no_experience'] ?? null) ? 'checked' : '' ?>> Можна без досвіду</label>
    <div class="grid2">
      <?= input_field('contact_name', 'Контактна особа', ['value' => $v['contact_name'] ?? $me['display_name']]) ?>
      <?= input_field('contact_phone', 'Телефон для кандидатів', ['type' => 'tel', 'value' => isset($v['contact_phone']) ? phone_pretty($v['contact_phone']) : '', 'placeholder' => '067 123 45 67', 'hint' => 'Буде показано у вакансії. Можна не вказувати — тоді лише відгуки на сайті']) ?>
    </div>
    <div class="field"><span class="lbl">Термін показу на сайті</span>
      <div class="grid2">
        <?= input_field('show_from', 'з', ['type' => 'date', 'value' => $pFrom]) ?>
        <?= input_field('show_to', 'по (включно)', ['type' => 'date', 'value' => $pTo]) ?>
      </div><span class="hint">До <?= VC::MAX_DAYS ?> днів. Після цієї дати вакансія сама перейде в архів. Якщо знайдете працівника раніше, перенесіть її в архів вручну, а потім за потреби поновіть.</span>
    </div>
    <div class="row"><button class="btn pri" name="action" value="submit"><?= $premoderation ? 'Надіслати на перевірку' : 'Опублікувати' ?></button><button class="btn" name="action" value="draft">Зберегти чернетку</button></div>
  </form>
</div>
