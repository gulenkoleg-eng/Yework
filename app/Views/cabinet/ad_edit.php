<?php /* Редагування банера роботодавцем (0.5.15). */
use App\Core\Ads;
$old = App\Core\App::$old;
$oldPerks = array_values((array) ($old['perks'] ?? []));
$tplNow = isset($templates[$old['template'] ?? '']) ? $old['template'] : $b['template'];
$preview = ['title' => ($old['title'] ?? '') ?: $b['title'], 'body' => ($old['body'] ?? '') ?: $b['body'], 'template' => $tplNow, 'badge' => $old['badge'] ?? '', 'cta' => $old['cta'] ?? '',
  'perks' => json_encode(array_values(array_filter($oldPerks)), JSON_UNESCAPED_UNICODE), 'employer' => $employer['name'], 'legal_form' => $employer['legal_form'], 'logo' => $employer['logo'],
  'vacancies' => count($targets), 'image' => $b['image']];
$days = [3 => '3 дні', 7 => '7 днів', 14 => '14 днів', 30 => '30 днів'];
$days[(int) $b['days']] ??= plural((int) $b['days'], 'день', 'дні', 'днів');
ksort($days);
$gone = !empty($b['vacancy_id']) && !in_array((int) $b['vacancy_id'], array_map('intval', array_column($targets, 'id')), true);
?>
<div class="cab">
  <?= App\Core\View::partial('partials/cab_nav', compact('tab', 'counts', 'me')) ?>
  <div class="stack">
    <a class="small" href="<?= url('/cabinet/ads/' . $b['id']) ?>">← <?= e($b['title']) ?></a>
    <h1 style="font-size:26px">Редагувати банер</h1>
    <?php if ($b['status'] === 'approved'): ?><div class="alert info">Якщо змінити текст, вигляд, картинку чи дати, банер знову перевірить модератор і до схвалення він не показуватиметься. Вакансію, на яку веде кнопка, можна змінити без перевірки.</div>
    <?php elseif ($b['status'] === 'rejected'): ?><div class="alert warn">Модератор відхилив банер<?= $b['reject_reason'] ? ': ' . e($b['reject_reason']) : '' ?>. Виправте його, і він знову піде на перевірку.</div><?php endif ?>
    <?php if ($gone): ?><div class="alert warn">Вакансію, на яку вів банер, знято з публікації. Оберіть іншу або «Усі вакансії компанії».</div><?php endif ?>
    <?= form_alert() ?>
    <form class="card pad stack" method="post" action="<?= url('/cabinet/ads/' . $b['id'] . '/edit') ?>" data-edit enctype="multipart/form-data"><?= csrf_field() ?>
      <?= App\Core\View::partial('partials/banner_maker', ['templates' => $templates, 'tplNow' => $tplNow, 'old' => $old, 'oldPerks' => $oldPerks, 'preview' => $preview,
        'ctaDefault' => Ads::ctaOf(['vacancies' => count($targets)]), 'targets' => $targets, 'curImage' => $b['image']]) ?>
      <?php if ($locked): ?>
        <div class="field"><span class="lbl">Де і коли показується</span>
          <div><b><?= e(Ads::PLACEMENTS[$b['placement']] ?? '') ?></b> · <?= e(date('d.m.Y', strtotime($b['starts_on']))) ?>–<?= e(date('d.m.Y', strtotime($b['ends_on']))) ?> (<?= plural((int) $b['days'], 'день', 'дні', 'днів') ?>)</div>
          <span class="hint"><?= $b['paid_at'] ? 'Банер оплачено, тому дати й місце показу вже не змінити.' : 'Банер уже показується, тому дати й місце показу не змінити. Щоб показати його в інші дні, замовте новий банер.' ?></span></div>
      <?php else: ?>
        <div class="field"><span class="lbl">Коли місця вільні</span>
          <?= App\Core\View::partial('partials/ad_places', ['occupancy' => $occupancy]) ?>
          <span class="hint">Ваш банер тут не враховано, тож видно, куди його можна перенести.</span></div>
        <fieldset class="field<?= err_class('placement') ?>"><legend>Де показувати</legend>
          <div class="place-opts">
            <?php foreach (Ads::PLACEMENTS as $k => $l): ?>
              <label class="place-opt"><input type="radio" name="placement" value="<?= $k ?>" <?= ($old['placement'] ?? $b['placement']) === $k ? 'checked' : '' ?>><span><b><?= e($l) ?></b><small><?= e(Ads::PLACEMENT_HINTS[$k]) ?></small><?php if ($paid): ?><small><?= money($prices['banner_' . $k . '_day']) ?> грн/день</small><?php endif ?><em class="place-free" data-for="<?= $k ?>"></em></span></label>
            <?php endforeach ?>
          </div><?= field_error('placement') ?>
        </fieldset>
        <div class="grid2">
          <?= input_field('starts_on', 'Початок показу', ['type' => 'date', 'required' => true]) ?>
          <?= input_field('days', 'Скільки днів', ['type' => 'select', 'options' => $days]) ?>
        </div>
        <?= App\Core\View::partial('partials/ad_free', ['occupancy' => $occupancy]) ?>
      <?php endif ?>
      <div class="row"><button class="btn pri">Зберегти зміни</button><a class="btn ghost" href="<?= url('/cabinet/ads/' . $b['id']) ?>">Скасувати</a></div>
    </form>
  </div>
</div>
