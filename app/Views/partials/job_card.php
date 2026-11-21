<?php $en = employer_name($j['legal_form'] ?? null, $j['employer']); $lg = $j['logo'] ?? null; ?>
<a class="card job<?= $lg ? ' has-logo' : '' ?><?= !empty($j['promoted']) ? ' promoted' : '' ?>" href="<?= url('/jobs/' . $j['id']) ?>">
  <?php if ($lg): ?><?= employer_logo($lg, $en, 52) ?><?php endif ?>
  <div class="job-b">
  <div class="row" style="justify-content:space-between;align-items:flex-start;flex-wrap:nowrap"><h3><?= e($j['title']) ?></h3><span class="small muted" style="white-space:nowrap"><?php if (!empty($j['promoted'])): ?><span class="tag promo-tag">Рекомендуємо</span> <?php endif ?><?= e(ago($j['published_at'])) ?></span></div>
  <div class="sal"><?= e(salary_text($j['salary_from'] !== null ? (int) $j['salary_from'] : null, $j['salary_to'] !== null ? (int) $j['salary_to'] : null, $j['salary_period'])) ?></div>
  <div class="meta">
    <span class="emp"><?= $lg ? '' : icon('briefcase', 15) ?><?= e($en) ?><?= $j['is_verified'] ? ' ' . icon('shield', 14) : '' ?></span>
    <span><?= icon('pin', 15) ?><?= e($j['settlement']) ?></span>
    <span><?= icon('clock', 15) ?><?= e(EMPLOYMENT_TYPES[$j['employment']] ?? '') ?></span>
    <?php if ($j['no_experience']): ?><span class="tag ok">Без досвіду</span><?php endif ?>
  </div>
  </div>
</a>
