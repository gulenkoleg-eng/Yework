<div class="adm-h"><h1>Сторінки сайту</h1></div>
<p class="muted">Тексти інформаційних сторінок. Видимі сторінки показуються внизу кожної сторінки сайту (у футері).</p>
<div class="card tbl-wrap"><table class="tbl">
  <thead><tr><th>Сторінка</th><th>Адреса</th><th>На сайті</th><th>Змінено</th></tr></thead>
  <tbody><?php foreach ($list as $p): ?><tr>
    <td><a href="<?= url('/admin/pages/' . $p['slug']) ?>"><?= e($p['title']) ?></a></td>
    <td class="small"><a href="<?= url($p['path']) ?>" target="_blank"><?= e($p['path']) ?></a></td>
    <td><span class="tag <?= $p['visible'] ? 'ok' : 'muted' ?>"><?= $p['visible'] ? 'Показується' : 'Приховано' ?></span></td>
    <td class="small muted"><?= $p['updated_at'] ? e(local_date($p['updated_at'])) : 'Текст за замовчуванням' ?></td>
  </tr><?php endforeach ?></tbody>
</table></div>
