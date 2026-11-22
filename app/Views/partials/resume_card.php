<?php
use App\Core\Resumes;
$exp = Resumes::decodeList($x['experience'] ?? null);
$pvis = Resumes::vis($x, 'photo');
$showPhoto = $x['photo_file'] && ($full ? $pvis !== 'nobody' : $pvis === 'all');
$showName = $full || Resumes::openToAll($x);
$age = Resumes::age($x['birth_year'] !== null ? (int) $x['birth_year'] : null);
?>
<a class="card rcard" href="<?= url('/resumes/' . $x['id']) ?>">
  <span class="rz-ph sm"><?php if ($showPhoto): ?><img src="<?= e(Resumes::photoUrl(['user_id' => $x['user_id'], 'photo_file' => $x['photo_file']])) ?>" alt="" loading="lazy"><?php else: ?><?= icon('user', 26) ?><?php endif ?></span>
  <div class="stack" style="gap:6px">
    <div class="row" style="justify-content:space-between;align-items:flex-start;flex-wrap:nowrap"><h3><?= e($x['title']) ?></h3><span class="small muted" style="white-space:nowrap"><?= e(ago($x['published_at'])) ?></span></div>
    <?php if ($showName): ?><div><b><?= e($x['full_name'] ?: $x['display_name']) ?></b><?= $age ? ' <span class="muted">· ' . e($age) . '</span>' : '' ?></div><?php endif ?>
    <div class="meta">
      <?php if ($x['settlement']): ?><span><?= icon('pin', 15) ?><?= e($x['settlement']) ?></span><?php endif ?>
      <span><?= icon('clock', 15) ?><?= e(EMPLOYMENT_TYPES[$x['employment']] ?? '') ?></span>
      <?php if ($x['salary_from']): ?><span><b style="color:var(--c-text)">від <?= money((int) $x['salary_from']) ?> грн</b></span><?php endif ?>
      <?php if ($exp): ?><span><?= icon('briefcase', 15) ?><?= e(plural(count($exp), 'місце роботи', 'місця роботи', 'місць роботи')) ?></span><?php elseif ($x['no_experience']): ?><span class="tag">Перша робота</span><?php endif ?>
      <?php if ($x['driving']): ?><span>Водій <?= e(str_replace(',', ', ', $x['driving'])) ?></span><?php endif ?>
    </div>
  </div>
</a>
