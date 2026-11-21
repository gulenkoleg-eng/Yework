<?php /* Банер роботодавця. $b — рядок із Ads::forPlacement, $wide — широкий варіант (над списком вакансій). */
$en = employer_name($b['legal_form'], $b['employer']); ?>
<a class="card promo<?= !empty($wide) ? ' wide' : '' ?><?= $b['image'] ? ' with-img' : '' ?>" href="<?= url('/go/' . $b['id']) ?>" rel="nofollow">
  <?php if ($b['image']): ?><img class="promo-img" src="<?= e(App\Core\Media::url('banner', $b['image'])) ?>" alt="" loading="lazy" decoding="async"><?php endif ?>
  <div class="promo-b">
    <div class="promo-emp"><?= $b['logo'] ? employer_logo($b['logo'], $en, 40) : '<span class="elogo ph">' . e(initials($b['employer'])) . '</span>' ?><span><b><?= e($en) ?></b><small>Реклама</small></span></div>
    <h3><?= e($b['title']) ?></h3>
    <p><?= e($b['body']) ?></p>
    <span class="promo-go"><?= (int) $b['vacancies'] > 0 ? 'Дивитися ' . plural((int) $b['vacancies'], 'вакансію', 'вакансії', 'вакансій') : 'Дивитися вакансії' ?> <?= icon('arrow', 16) ?></span>
  </div>
</a>
