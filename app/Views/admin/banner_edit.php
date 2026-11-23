<?php /* Редагування банера адміністратором (0.5.15). */
use App\Core\Ads;
$old = App\Core\App::$old;
$oldPerks = array_values((array) ($old['perks'] ?? []));
$templates = Ads::TEMPLATES;
$tplNow = isset($templates[$old['template'] ?? '']) ? $old['template'] : Ads::templateOf($b);
$preview = ['title' => ($old['title'] ?? '') ?: $b['title'], 'body' => ($old['body'] ?? '') ?: $b['body'], 'template' => $tplNow, 'badge' => $old['badge'] ?? '', 'cta' => $old['cta'] ?? '',
  'perks' => json_encode(array_values(array_filter($oldPerks)), JSON_UNESCAPED_UNICODE), 'employer' => $b['employer'], 'legal_form' => $b['legal_form'], 'logo' => $b['logo'],
  'vacancies' => count($targets), 'image' => $b['image']];
$days = [1 => '1 день', 3 => '3 дні', 7 => '7 днів', 14 => '14 днів', 30 => '30 днів', 60 => '60 днів', 90 => '90 днів'];
$days[(int) $b['days']] ??= plural((int) $b['days'], 'день', 'дні', 'днів');
ksort($days);
[$sl, $sc] = Ads::statusLabel($b);
$gone = !empty($b['vacancy_id']) && !in_array((int) $b['vacancy_id'], array_map('intval', array_column($targets, 'id')), true);
?>
<div class="adm-h"><h1>Редагувати банер</h1><span class="tag <?= $sc ?>"><?= e($sl) ?></span><span class="spacer"></span><a class="btn sm" href="<?= url('/admin/banners', ['status' => $back]) ?>">← Усі банери</a></div>
<p class="muted small">Роботодавець: <b><?= e(employer_name($b['legal_form'], $b['employer'])) ?></b>. Зміни адміністратора не відправляють банер на повторну перевірку, статус лишається тим самим.</p>
<?php if ($gone): ?><div class="alert warn">Вакансію, на яку вів банер, знято з публікації. Зараз кнопка веде на всі вакансії компанії. Оберіть іншу вакансію.</div><?php endif ?>
<?= form_alert() ?>
<form class="card pad stack" method="post" action="<?= url('/admin/banners/' . $b['id'] . '/edit') ?>" data-edit enctype="multipart/form-data"><?= csrf_field() ?>
  <?= App\Core\View::partial('partials/banner_maker', ['templates' => $templates, 'tplNow' => $tplNow, 'old' => $old, 'oldPerks' => $oldPerks, 'preview' => $preview,
    'ctaDefault' => Ads::ctaOf(['vacancies' => count($targets)]), 'targets' => $targets, 'curImage' => $b['image']]) ?>
  <fieldset class="field<?= err_class('placement') ?>"><legend>Де показувати</legend>
    <div class="place-opts">
      <?php foreach (Ads::PLACEMENTS as $k => $l): ?>
        <label class="place-opt"><input type="radio" name="placement" value="<?= $k ?>" <?= ($old['placement'] ?? $b['placement']) === $k ? 'checked' : '' ?>><span><b><?= e($l) ?></b></span></label>
      <?php endforeach ?>
    </div><?= field_error('placement') ?>
  </fieldset>
  <div class="grid2">
    <?= input_field('starts_on', 'Початок показу', ['type' => 'date', 'required' => true]) ?>
    <?= input_field('days', 'Скільки днів', ['type' => 'select', 'options' => $days]) ?>
  </div>
  <div class="row"><button class="btn pri">Зберегти</button><a class="btn ghost" href="<?= url('/admin/banners', ['status' => $back]) ?>">Скасувати</a></div>
</form>
<form method="post" action="<?= url('/admin/banners/' . $b['id'] . '/delete') ?>" onsubmit="return confirm('Видалити банер назавжди разом зі статистикою? Повернути його не вийде.')" style="margin-top:12px"><?= csrf_field() ?><button class="btn ghost sm" style="color:var(--c-danger)">Видалити банер</button></form>
