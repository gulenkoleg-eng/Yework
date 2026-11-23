<?php /* Банер роботодавця в обраному шаблоні (0.5.12).
  $b — рядок банера з назвою/логотипом роботодавця і кількістю вакансій; $wide — широкий варіант над списком вакансій;
  $live — попередній перегляд у кабінеті: усі частини є в розмітці (порожні сховані), щоб скрипт міг їх оновлювати. */
use App\Core\Ads;
$live = !empty($live);
$t = $live ? (string) ($b['template'] ?? 'classic') : Ads::templateOf($b);
$en = employer_name($b['legal_form'] ?? null, $b['employer'] ?? '');
$perks = Ads::perks($b);
$badge = trim((string) ($b['badge'] ?? ''));
$img = $b['image_url'] ?? (!empty($b['image']) ? App\Core\Media::url('banner', $b['image']) : '');
$sample = !empty($sample); // зразок шаблону в адмінці — без посилання
$tag = $live || $sample ? 'div' : 'a';
$hide = fn (bool $empty) => $empty ? ' hidden' : '';
?>
<<?= $tag ?> class="bnr t-<?= e($t) ?><?= !empty($wide) ? ' wide' : '' ?>"<?= $live ? ' id="bnrLive" aria-label="Попередній перегляд банера"' : ($sample ? '' : ' href="' . url('/go/' . $b['id']) . '" rel="nofollow"') ?>>
  <?php if (($img && $t === 'photo') || $live): ?><img class="bnr-photo" src="<?= e($img) ?>" alt="" loading="lazy" decoding="async"<?= $hide(!$img) ?>><?php endif ?>
  <span class="bnr-deco" aria-hidden="true"></span>
  <?php if (($img && $t !== 'photo') || $live): ?><img class="bnr-img" src="<?= e($img) ?>" alt="" loading="lazy" decoding="async"<?= $hide(!$img || $t === 'photo') ?>><?php endif ?>
  <div class="bnr-top"><?= !empty($b['logo']) ? employer_logo($b['logo'], $en, 42) : '<span class="elogo ph">' . e(initials($b['employer'] ?? '')) . '</span>' ?>
    <span class="bnr-emp"><b><?= e($en) ?></b><small>Реклама</small></span>
    <?php if ($badge !== '' || $live): ?><span class="bnr-badge"<?= $hide($badge === '') ?>><i></i><span><?= e($badge) ?></span></span><?php endif ?></div>
  <h3><?= e($b['title']) ?></h3>
  <p><?= e($b['body']) ?></p>
  <?php if ($perks || $live): ?><ul class="bnr-perks"<?= $hide(!$perks) ?>><?php foreach ($perks as $pk): ?><li><?= e($pk) ?></li><?php endforeach ?></ul><?php endif ?>
  <div class="bnr-foot"><span class="bnr-cta"><span><?= e(Ads::ctaOf($b)) ?></span> <?= icon('arrow', 16) ?></span></div>
</<?= $tag ?>>
