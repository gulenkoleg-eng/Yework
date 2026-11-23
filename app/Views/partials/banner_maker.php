<?php /* Конструктор банера: шаблон, текст і живий перегляд (кабінет роботодавця й адмінка).
  $templates — доступні шаблони; $tplNow — обраний; $old/$oldPerks — введене раніше; $preview — дані для перегляду; $ctaDefault — текст кнопки за замовчуванням.
  $targets — опубліковані вакансії [id, employer_id, title], на які може вести кнопка (0.5.15); $targetNow — обране ('all' або id); $curImage — уже завантажена картинка (редагування). */
use App\Core\Ads;
$targets ??= [];
$targetNow = (string) ($old['target'] ?? ($targetNow ?? '')); ?>
<div class="bnr-maker">
  <div class="stack">
    <fieldset class="field<?= err_class('template') ?>"><legend class="lbl">Шаблон</legend>
      <div class="bnr-tpls">
        <?php foreach ($templates as $k => [$tl, $td]): ?>
          <label class="bnr-tpl"><input type="radio" name="template" value="<?= e($k) ?>" <?= $tplNow === $k ? 'checked' : '' ?>><span><em class="sw-<?= e($k) ?>"></em><?= e($tl) ?><small><?= e($td) ?></small></span></label>
        <?php endforeach ?>
      </div>
    </fieldset>
    <?= input_field('title', 'Заголовок', ['maxlength' => 80, 'required' => true, 'placeholder' => 'Шукаємо водія категорії C']) ?>
    <?= input_field('body', 'Кого шукаєте і що пропонуєте', ['type' => 'textarea', 'rows' => 3, 'maxlength' => 300, 'required' => true, 'placeholder' => 'Офіційне працевлаштування, доїзд з Миронівки. Досвід не обов’язковий, навчимо.']) ?>
    <div class="field"><span class="lbl">Переваги (до 3, коротко)</span>
      <div class="bnr-perk-in">
        <?php foreach (['від 18 000 грн', 'Офіційно', 'Доїзд'] as $i => $ph): ?><input class="input" name="perks[]" maxlength="24" placeholder="<?= e($ph) ?>" value="<?= e($oldPerks[$i] ?? '') ?>" aria-label="Перевага <?= $i + 1 ?>"><?php endforeach ?>
      </div><span class="hint">З’являться на банері кольоровими чипами. Можна залишити порожніми.</span></div>
    <div class="grid2">
      <div class="field"><label for="f_badge">Позначка</label><input id="f_badge" class="input" name="badge" maxlength="24" list="adBadges" placeholder="Без позначки" value="<?= e($old['badge'] ?? '') ?>"><span class="hint">Наприклад, «Терміново» чи «Новий набір».</span></div>
      <div class="field"><label for="f_cta">Текст кнопки</label><input id="f_cta" class="input" name="cta" maxlength="32" list="adCtas" data-all="<?= e($ctaDefault) ?>" placeholder="<?= e(ctype_digit($targetNow) ? 'Дивитися вакансію' : $ctaDefault) ?>" value="<?= e($old['cta'] ?? '') ?>"><span class="hint">Якщо залишити порожнім, кнопка покаже кількість вакансій.</span></div>
    </div>
    <div class="field<?= err_class('target') ?>"><label for="f_target">Куди веде кнопка банера</label>
      <select id="f_target" name="target" class="input" required>
        <option value=""<?= $targetNow === '' ? ' selected' : '' ?> disabled>Оберіть вакансію…</option>
        <?php foreach ($targets as $v): ?><option value="<?= (int) $v['id'] ?>" data-emp="<?= (int) $v['employer_id'] ?>"<?= $targetNow === (string) $v['id'] ? ' selected' : '' ?>><?= e($v['title']) ?></option><?php endforeach ?>
        <option value="all"<?= $targetNow === 'all' ? ' selected' : '' ?>>Усі вакансії компанії</option>
      </select>
      <span class="hint" id="f_target_hint">Людина, яка натисне на банер, одразу потрапить на цю вакансію. Якщо вакансію згодом знімуть з публікації, банер поведе на всі вакансії компанії.</span><?= field_error('target') ?></div>
    <datalist id="adBadges"><?php foreach (Ads::BADGES as $x): ?><option value="<?= e($x) ?>"><?php endforeach ?></datalist>
    <datalist id="adCtas"><?php foreach (Ads::CTAS as $x): ?><option value="<?= e($x) ?>"><?php endforeach ?></datalist>
    <div class="field<?= err_class('image') ?>"><label for="f_image">Картинка <span class="muted" id="adImgReq">(необов’язково)</span></label><input id="f_image" type="file" name="image" accept="image/png,image/jpeg,image/webp"><span class="hint">Фото підприємства чи команди, до 3 МБ. Для шаблону «Фото» картинка обов’язкова, вона стане фоном усього банера.<?= !empty($curImage) ? ' Нова картинка замінить поточну.' : '' ?></span><?= field_error('image') ?>
      <?php if (!empty($curImage)): ?><label class="row small" style="gap:8px;margin-top:6px"><input type="checkbox" name="drop_image" value="1"> Прибрати поточну картинку</label><?php endif ?></div>
  </div>
  <div class="bnr-live">
    <div class="bnr-live-h"><span class="dot"></span>Так банер виглядатиме на сайті</div>
    <?= App\Core\View::partial('partials/promo', ['b' => $preview, 'live' => true]) ?>
    <p class="small muted">Перегляд оновлюється, поки ви пишете. Над списком вакансій на комп’ютері банер буде широким, з кнопкою праворуч.</p>
  </div>
</div>
<script src="<?= asset('js/banners.js') ?>" defer></script>
