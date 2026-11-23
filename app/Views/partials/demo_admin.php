<?php /* Приклади оголошень (0.5.14): блок в адмінці «Майстри» і «Резюме». $back — куди повернутися. */
use App\Core\Demo;
if (!App\Core\App::auth()->can('services.listing:moderate')) return;
$c = Demo::content(); $has = Demo::has(); ?>
<details class="card demo-box"<?= $has ? '' : ' open' ?> style="margin:0 0 16px">
  <summary style="cursor:pointer;font-weight:700"><?= $has ? 'На сайті є приклади оголошень: ' . count($c['masters']) . ' майстри в «Послугах» і ' . count($c['resumes']) . ' резюме' : 'Приклади оголошень, щоб сайт не виглядав порожнім' ?></summary>
  <?php if (!$has): ?>
    <p class="small muted" style="margin:10px 0">Одна кнопка додає 3 готові оголошення майстрів у «Послуги»: <b>монтаж кондиціонерів</b>, <b>відеонагляд</b> і <b>ремонт квартир</b>, з описом, прайсом, способами оплати, ФОП/ТОВ і портфоліо з фото обʼєктів. І одне <b>резюме</b> оператора виробничої лінії з Миронівки.
      Усі дані вигадані, фото намальовані для сайту. Відвідувачі бачать їх як звичайні оголошення, але замовити послугу, побачити номер чи запросити кандидата не можуть: сайт підкаже, що це приклад. Коли зʼявляться справжні майстри, видаліть приклади тут же.</p>
    <form method="post" action="<?= url('/admin/demo') ?>"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>"><input type="hidden" name="do" value="add"><button class="btn pri">+ Додати приклади</button></form>
  <?php else: ?>
    <p class="small muted" style="margin:10px 0">Приклади позначені тут міткою «Приклад». Видалення прибирає їх із сайту разом із фото й службовими користувачами. Справжніх майстрів і резюме це не зачіпає.</p>
    <form method="post" action="<?= url('/admin/demo') ?>" class="row"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>"><input type="hidden" name="do" value="remove">
      <label class="row small" style="gap:6px"><input type="checkbox" name="confirm" value="1" required> Так, видалити всі приклади</label>
      <button class="btn bad">Видалити приклади</button></form>
  <?php endif ?>
</details>
