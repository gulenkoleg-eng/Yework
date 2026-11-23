<?php use App\Core\Ads;
$old = App\Core\App::$old;
$oldPerks = array_values((array) ($old['perks'] ?? []));
$templates = Ads::TEMPLATES; // адміністратор може обрати будь-який шаблон, навіть вимкнений для роботодавців
$want = $old['template'] ?? ($_GET['template'] ?? '');
$tplNow = isset($templates[$want]) ? $want : Ads::templateSettings()['default'];
$pickId = (int) ($old['employer_id'] ?? $pick);
$cur = null;
foreach ($employers as $em) if ((int) $em['id'] === $pickId) $cur = $em;
$cur ??= $employers[0] ?? null;
$preview = ['title' => ($old['title'] ?? '') ?: 'Шукаємо водія категорії C', 'body' => ($old['body'] ?? '') ?: 'Офіційне працевлаштування, доїзд з Миронівки. Досвід не обов’язковий, навчимо.',
  'template' => $tplNow, 'badge' => $old['badge'] ?? '', 'cta' => $old['cta'] ?? '', 'perks' => json_encode(array_filter($oldPerks) ?: ['від 18 000 грн', 'Офіційно', 'Доїзд'], JSON_UNESCAPED_UNICODE),
  'employer' => $cur['name'] ?? 'Роботодавець', 'legal_form' => $cur['legal_form'] ?? null, 'logo' => $cur['logo'] ?? null, 'vacancies' => (int) ($cur['vacancies'] ?? 0), 'image' => null];
$days = [1 => '1 день', 3 => '3 дні', 7 => '7 днів', 14 => '14 днів', 30 => '30 днів', 60 => '60 днів', 90 => '90 днів'];
?>
<div class="adm-h"><h1>Новий банер</h1><span class="spacer"></span><a class="btn sm" href="<?= url('/admin/banners') ?>">← Усі банери</a></div>
<p class="muted small">Банер від адміністрації одразу схвалений і безкоштовний. Він веде на вакансії обраного роботодавця, а поки діє, вакансії цього роботодавця стоять першими в пошуку. Ліміт місць для адміністратора не діє.</p>
<?= form_alert() ?>
<?php if (!$employers): ?>
  <div class="card empty"><h3>Ще немає жодного роботодавця</h3><p class="muted">Банер веде на вакансії роботодавця, тож спершу має з’явитися хоча б один профіль роботодавця.</p></div>
<?php else: ?>
<form class="card pad stack" method="post" action="<?= url('/admin/banners/new') ?>" enctype="multipart/form-data"><?= csrf_field() ?>
  <div class="field<?= err_class('employer_id') ?>"><label for="f_employer_id">Роботодавець</label>
    <select id="f_employer_id" name="employer_id" class="input">
      <?php foreach ($employers as $em): $en = employer_name($em['legal_form'], $em['name']); ?>
        <option value="<?= (int) $em['id'] ?>" data-name="<?= e($en) ?>" data-ini="<?= e(initials($em['name'])) ?>" data-logo="<?= e($em['logo'] ? App\Core\Media::url('logo', $em['logo']) : '') ?>"
          data-cta="<?= e(Ads::ctaOf(['vacancies' => (int) $em['vacancies']])) ?>"<?= $cur && (int) $cur['id'] === (int) $em['id'] ? ' selected' : '' ?>><?= e($en) ?> · <?= plural((int) $em['vacancies'], 'вакансія', 'вакансії', 'вакансій') ?></option>
      <?php endforeach ?>
    </select><span class="hint">Натискання на банер відкриє вакансії цього роботодавця.</span><?= field_error('employer_id') ?></div>
  <?= App\Core\View::partial('partials/banner_maker', ['templates' => $templates, 'tplNow' => $tplNow, 'old' => $old, 'oldPerks' => $oldPerks, 'preview' => $preview, 'ctaDefault' => Ads::ctaOf(['vacancies' => (int) ($cur['vacancies'] ?? 0)])]) ?>
  <fieldset class="field<?= err_class('placement') ?>"><legend>Де показувати</legend>
    <div class="place-opts">
      <?php foreach (Ads::PLACEMENTS as $k => $l): ?>
        <label class="place-opt"><input type="radio" name="placement" value="<?= $k ?>" <?= ($old['placement'] ?? 'both') === $k ? 'checked' : '' ?>><span><b><?= e($l) ?></b></span></label>
      <?php endforeach ?>
    </div><?= field_error('placement') ?>
  </fieldset>
  <div class="grid2">
    <?= input_field('starts_on', 'Початок показу', ['type' => 'date', 'value' => $today, 'required' => true]) ?>
    <?= input_field('days', 'Скільки днів', ['type' => 'select', 'value' => 7, 'options' => $days]) ?>
  </div>
  <div class="row"><button class="btn pri">Створити й показати</button><span class="small muted">Банер з’явиться на сайті в перший день показу.</span></div>
</form>
<?php endif ?>
