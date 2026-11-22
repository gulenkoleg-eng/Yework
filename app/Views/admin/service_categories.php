<div class="adm-h"><h1>Категорії послуг</h1></div>
<p class="small muted" style="margin:-6px 0 14px">Список напрямів, з якого майстри обирають, чим займаються. Приховану категорію не можна вибрати для нового профілю, а майстри з нею не показуються у фільтрі.</p>
<form class="card pad stack" method="post" action="<?= url('/admin/service-categories') ?>"><?= csrf_field() ?>
  <div class="tbl-wrap"><table class="tbl"><tr><th>Назва</th><th style="width:110px">Порядок</th><th style="width:100px">Показувати</th><th style="width:90px">Майстрів</th></tr>
  <?php foreach ($rows as $c): ?><tr>
    <td><input class="input" name="cat[<?= (int) $c['id'] ?>][name]" value="<?= e($c['name']) ?>" maxlength="80" aria-label="Назва"></td>
    <td><input class="input" name="cat[<?= (int) $c['id'] ?>][sort]" value="<?= (int) $c['sort'] ?>" inputmode="numeric" aria-label="Порядок"></td>
    <td><label class="check"><input type="checkbox" name="cat[<?= (int) $c['id'] ?>][active]" value="1" <?= $c['is_active'] ? 'checked' : '' ?>> так</label></td>
    <td><?= (int) $c['masters'] ?></td>
  </tr><?php endforeach ?></table></div>
  <div><button class="btn pri">Зберегти</button></div>
</form>
<form class="card pad row" method="post" action="<?= url('/admin/service-categories') ?>"><?= csrf_field() ?><input type="hidden" name="do" value="add">
  <input class="input" name="name" maxlength="80" placeholder="Нова категорія, напр. «Сонячні панелі»" style="flex:1;min-width:220px"><button class="btn">Додати</button>
</form>
