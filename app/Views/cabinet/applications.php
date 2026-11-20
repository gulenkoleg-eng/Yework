<div class="cab">
  <?= App\Core\View::partial('partials/cab_nav', compact('tab', 'counts', 'me')) ?>
  <div class="stack">
    <h1 style="font-size:28px">Мої відгуки</h1>
    <?php if (!$rows): ?><div class="card empty"><h3>Ви ще не відгукувалися</h3><p class="muted">Знайдіть вакансію і натисніть «Відгукнутися» — роботодавець отримає ваші контакти.</p><a class="btn pri" href="<?= url('/jobs') ?>">Шукати вакансії</a></div>
    <?php else: ?><div class="card list"><?php foreach ($rows as $r): ?>
      <div class="row" style="justify-content:space-between"><div><a href="<?= url('/jobs/' . $r['vacancy_id']) ?>"><b><?= e($r['title']) ?></b></a><div class="small muted"><?= e($r['employer']) ?> · <?= e(ago($r['created_at'])) ?></div></div>
      <span class="tag <?= $r['status'] === 'new' ? 'muted' : 'ok' ?>"><?= $r['status'] === 'new' ? 'Надіслано' : 'Переглянуто' ?></span></div>
    <?php endforeach ?></div><?php endif ?>
  </div>
</div>
