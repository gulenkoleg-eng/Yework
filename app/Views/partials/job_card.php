<a class="card job" href="<?= url('/jobs/' . $j['id']) ?>">
  <div class="row" style="justify-content:space-between;align-items:flex-start;flex-wrap:nowrap"><h3><?= e($j['title']) ?></h3><span class="small muted" style="white-space:nowrap"><?= e(ago($j['published_at'])) ?></span></div>
  <div class="sal"><?= e(salary_text($j['salary_from'] !== null ? (int) $j['salary_from'] : null, $j['salary_to'] !== null ? (int) $j['salary_to'] : null, $j['salary_period'])) ?></div>
  <div class="meta">
    <span><?= icon('briefcase', 15) ?><?= e($j['employer']) ?><?= $j['is_verified'] ? ' ' . icon('shield', 14) : '' ?></span>
    <span><?= icon('pin', 15) ?><?= e($j['settlement']) ?></span>
    <span><?= icon('clock', 15) ?><?= e(EMPLOYMENT_TYPES[$j['employment']] ?? '') ?></span>
    <?php if ($j['no_experience']): ?><span class="tag ok">Без досвіду</span><?php endif ?>
  </div>
</a>
