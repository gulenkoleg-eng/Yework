<?php use App\Core\Rewards; ?>
<div class="cab">
  <?= App\Core\View::partial('partials/cab_nav', compact('tab', 'counts', 'me')) ?>
  <div class="stack">
    <h1 style="font-size:28px">Винагорода</h1>
    <p class="muted" style="margin-top:-6px">Суми, які вам виплачено за роботу в проєкті. Записи додає головний адміністратор, їх бачите лише ви. Натисніть на місяць, щоб розгорнути або згорнути.</p>
    <?php if (!$months): ?><div class="card empty"><h3>Записів поки немає</h3><p class="muted">Коли головний адміністратор додасть виплату, вона з’явиться тут з датою й сумою.</p></div>
    <?php else: ?>
      <div class="card pad rw-sum"><span class="muted">Усього отримано</span><b><?= e(Rewards::uah(Rewards::total($months))) ?></b></div>
      <?= App\Core\View::partial('partials/reward_months', ['months' => $months, 'deleteBase' => null]) ?>
    <?php endif ?>
  </div>
</div>
