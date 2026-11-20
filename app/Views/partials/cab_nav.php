<nav class="card cab-nav" aria-label="Кабінет">
  <a href="<?= url('/cabinet') ?>" class="<?= $tab === 'profile' ? 'on' : '' ?>">Профіль</a>
  <a href="<?= url('/cabinet/applications') ?>" class="<?= $tab === 'applications' ? 'on' : '' ?>">Мої відгуки <span class="muted"><?= (int) $counts['applications'] ?></span></a>
  <a href="<?= url('/cabinet/vacancies') ?>" class="<?= $tab === 'vacancies' ? 'on' : '' ?>">Мої вакансії <span class="muted"><?= (int) $counts['vacancies'] ?></span></a>
  <a href="<?= url('/cabinet/employer') ?>" class="<?= $tab === 'employer' ? 'on' : '' ?>">Роботодавець</a>
  <?php if ($me['is_staff']): ?><a href="<?= url('/admin') ?>">Адмін-панель →</a><?php endif ?>
  <form method="post" action="<?= url('/logout') ?>" style="margin-top:6px"><?= csrf_field() ?><button class="btn ghost block sm" style="justify-content:flex-start">Вийти</button></form>
</nav>
