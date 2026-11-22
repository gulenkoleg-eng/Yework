<?php
use App\Core\Resumes;
$old = \App\Core\App::$old;
$pv = fn (string $k, $def) => array_key_exists($k, $old) ? $old[$k] : $def;
$st = $r['status'] ?? null;
$expRows = array_values(array_filter((array) $pv('exp', $r['experience'] ?? []), 'is_array'));
if (!$expRows) $expRows = [[]];
$eduRows = array_values(array_filter((array) $pv('edu', $r['education'] ?? []), 'is_array'));
if (!$eduRows) $eduRows = [[]];
$driving = (array) $pv('driving', $r && $r['driving'] ? explode(',', $r['driving']) : []);
$opt = static fn (array $list, $cur) => implode('', array_map(static fn ($k, $v) => '<option value="' . e($k) . '"' . ((string) $k === (string) $cur ? ' selected' : '') . '>' . e($v) . '</option>', array_keys($list), $list));
$expRow = static function (int $i, array $x): string {
    $f = static fn ($k) => e($x[$k] ?? '');
    return '<div class="rz-row" data-row><div class="rz-row-h"><span>Місце роботи</span><button type="button" class="btn sm ghost" data-del>Прибрати</button></div>'
        . '<div class="grid2"><div class="field"><label>Посада</label><input class="input" name="exp[' . $i . '][position]" maxlength="120" value="' . $f('position') . '" placeholder="Тракторист-машиніст"></div>'
        . '<div class="field"><label>Де працювали</label><input class="input" name="exp[' . $i . '][place]" maxlength="120" value="' . $f('place') . '" placeholder="Назва підприємства або «ФОП»"></div></div>'
        . '<div class="grid2"><div class="field"><label>З (рік або місяць.рік)</label><input class="input" name="exp[' . $i . '][from]" maxlength="20" value="' . $f('from') . '" placeholder="2019"></div>'
        . '<div class="field"><label>По</label><input class="input" name="exp[' . $i . '][to]" maxlength="20" value="' . $f('to') . '" placeholder="2024 або порожньо, якщо працюєте зараз"></div></div>'
        . '<div class="field"><label>Що робили</label><textarea class="input" rows="3" name="exp[' . $i . '][duties]" maxlength="2000" placeholder="Обов’язки, досягнення, техніка, з якою працювали">' . $f('duties') . '</textarea></div></div>';
};
$eduRow = static function (int $i, array $x): string {
    $f = static fn ($k) => e($x[$k] ?? '');
    return '<div class="rz-row" data-row><div class="rz-row-h"><span>Навчальний заклад</span><button type="button" class="btn sm ghost" data-del>Прибрати</button></div>'
        . '<div class="field"><label>Заклад</label><input class="input" name="edu[' . $i . '][place]" maxlength="160" value="' . $f('place') . '" placeholder="Миронівський аграрний коледж"></div>'
        . '<div class="grid2"><div class="field"><label>Спеціальність</label><input class="input" name="edu[' . $i . '][specialty]" maxlength="120" value="' . $f('specialty') . '"></div>'
        . '<div class="field"><label>Рік закінчення</label><input class="input" name="edu[' . $i . '][year]" maxlength="20" value="' . $f('year') . '" inputmode="numeric"></div></div></div>';
};
?>
<div class="cab">
  <?= App\Core\View::partial('partials/cab_nav', compact('tab', 'counts', 'me')) ?>
  <div class="stack">
    <div class="rz-h">
      <h1 style="font-size:28px">Моє резюме</h1>
      <?php if ($st): ?><span class="tag <?= Resumes::STATUS[$st][1] ?>"><?= Resumes::STATUS[$st][0] ?></span><?php if ($r['is_hidden']): ?><span class="tag muted">Приховано з пошуку</span><?php endif ?><?php endif ?>
      <span class="spacer"></span>
      <?php if ($r): ?>
        <a class="btn sm" href="<?= url('/resumes/' . $r['id']) ?>"><?= icon('eye', 16) ?>Як бачать роботодавці</a>
        <a class="btn sm" href="<?= url('/cabinet/resume/pdf') ?>" target="_blank" rel="noopener">PDF</a>
        <a class="btn sm" href="<?= url('/cabinet/resume/pdf', ['dl' => 1]) ?>">Завантажити PDF</a>
      <?php endif ?>
    </div>
    <?php if ($st === 'rejected'): ?><div class="alert bad">Модератор відхилив резюме: <?= e($r['reject_reason']) ?>. Виправте його та надішліть ще раз.</div><?php endif ?>
    <?php if ($st === 'pending'): ?><div class="alert info">Резюме на перевірці. Після схвалення воно з’явиться в пошуку для роботодавців.</div><?php endif ?>
    <?php if ($st === 'published' && !$r['is_hidden']): ?><div class="alert ok">Резюме опубліковане: роботодавці бачать його в розділі «Резюме». Переглядів: <?= (int) $r['views'] ?>.</div><?php endif ?>
    <?php if (!$r): ?><p class="muted">Заповніть профіль і резюме один раз, і відгукуйтеся на вакансії з резюме в один клік. Роботодавець отримає його у PDF на пошту.</p><?php endif ?>
    <?= form_alert() ?>

    <form id="person" class="card pad stack" method="post" action="<?= url('/cabinet/person') ?>" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="back" value="resume">
      <div><h2 style="font-size:20px">Мій профіль</h2><p class="small muted">Ці дані підставляються в резюме, PDF і відгуки. Змінюєте тут один раз, і вони оновлюються всюди.</p></div>
      <div class="rz-person">
        <div class="rz-photo">
          <div class="rz-ph"><?php if ($person['photo_file']): ?><img src="<?= e(Resumes::photoUrl($person)) ?>" alt="Ваше фото"><?php else: ?><?= icon('user', 44) ?><?php endif ?></div>
          <input class="rz-file" type="file" name="photo" accept="image/jpeg,image/png,image/webp" aria-label="Фото">
          <span class="small muted">JPG, PNG або WEBP, до 2 МБ</span>
          <?php if ($person['photo_file']): ?><label class="check small"><input type="checkbox" name="photo_delete" value="1"> Видалити фото</label><?php endif ?>
          <?= field_error('photo') ?>
        </div>
        <div class="stack" style="gap:12px">
          <?= input_field('full_name', 'Ім’я та прізвище', ['value' => $person['full_name'] ?? $me['display_name'], 'maxlength' => 120, 'placeholder' => 'Олена Коваленко', 'autocomplete' => 'name']) ?>
          <div class="grid2">
            <?= input_field('birth_year', 'Рік народження', ['value' => $person['birth_year'] ?? '', 'inputmode' => 'numeric', 'maxlength' => 4, 'placeholder' => '1990', 'hint' => 'Необов’язково. У резюме показується вік']) ?>
            <?= input_field('settlement_id', 'Населений пункт', ['type' => 'select', 'value' => $person['settlement_id'] ?? '', 'options' => ['' => '—'] + array_column($settlements, 'name', 'id')]) ?>
          </div>
          <dl class="kv small"><dt>Телефон</dt><dd><?= $phone ? e(phone_pretty($phone)) : '<span class="muted">не вказано</span>' ?></dd><dt>Email</dt><dd><?= $email ? e($email) : '<span class="muted">не вказано</span>' ?></dd></dl>
        </div>
      </div>
      <fieldset class="rz-privacy">
        <legend>Що бачать роботодавці</legend>
        <label class="check"><input type="checkbox" name="show_photo" value="1" <?= $person['show_photo'] ? 'checked' : '' ?>><span><b>Показувати фото</b><small>Якщо вимкнути, роботодавці бачать замість фото нейтральний значок, а в PDF фото не буде. Ви своє фото бачите завжди.</small></span></label>
        <label class="check"><input type="checkbox" name="show_contacts" value="1" <?= $person['show_contacts'] ? 'checked' : '' ?>><span><b>Показувати телефон і email у резюме</b><small>Якщо вимкнути, роботодавець надсилає вам запрошення через сайт, а ви самі вирішуєте, кому відповісти. Коли ви відгукуєтеся на вакансію, роботодавець отримує ваші контакти в будь-якому разі.</small></span></label>
      </fieldset>
      <div><button class="btn pri">Зберегти профіль</button></div>
    </form>

    <form class="card pad stack" method="post" action="<?= url('/cabinet/resume') ?>" id="resume"><?= csrf_field() ?>
      <h2 style="font-size:20px">Резюме</h2>
      <div class="grid2">
        <?= input_field('title', 'Бажана посада', ['value' => $r['title'] ?? '', 'required' => true, 'maxlength' => 120, 'placeholder' => 'Продавець-консультант']) ?>
        <?= input_field('category_id', 'Сфера', ['type' => 'select', 'value' => $r['category_id'] ?? '', 'options' => ['' => '—'] + array_column($categories, 'name', 'id')]) ?>
      </div>
      <div class="grid2">
        <?= input_field('employment', 'Графік', ['type' => 'select', 'value' => $r['employment'] ?? 'full', 'options' => EMPLOYMENT_TYPES]) ?>
        <?= input_field('salary_from', 'Бажана зарплата від, грн/міс', ['value' => $r['salary_from'] ?? '', 'inputmode' => 'numeric', 'placeholder' => '15000', 'hint' => 'Необов’язково']) ?>
      </div>

      <div class="rz-sec">
        <h3>Досвід роботи</h3>
        <label class="check"><input type="checkbox" name="no_experience" value="1" <?= $pv('no_experience', $r['no_experience'] ?? 0) ? 'checked' : '' ?>> Шукаю першу роботу (без досвіду)</label>
        <?= field_error('exp') ?>
        <div class="rz-rows" id="exp-list" data-next="<?= count($expRows) ?>"><?php foreach ($expRows as $i => $x) echo $expRow($i, $x) ?></div>
        <div><button type="button" class="btn sm" data-add="exp"><?= icon('plus', 16) ?>Додати місце роботи</button></div>
        <template id="exp-tpl"><?= $expRow(999, []) ?></template>
      </div>

      <div class="rz-sec">
        <h3>Освіта</h3>
        <div class="rz-rows" id="edu-list" data-next="<?= count($eduRows) ?>"><?php foreach ($eduRows as $i => $x) echo $eduRow($i, $x) ?></div>
        <div><button type="button" class="btn sm" data-add="edu"><?= icon('plus', 16) ?>Додати навчання</button></div>
        <template id="edu-tpl"><?= $eduRow(999, []) ?></template>
      </div>

      <?= input_field('skills', 'Навички', ['type' => 'textarea', 'rows' => 3, 'value' => $r['skills'] ?? '', 'maxlength' => 3000, 'placeholder' => 'Робота з касою, 1С, зварювання, англійська (базова)…']) ?>
      <div class="field"><label>Посвідчення водія</label><div class="rz-drive"><?php foreach (Resumes::DRIVING as $c): ?><label><input type="checkbox" name="driving[]" value="<?= $c ?>" <?= in_array($c, $driving, true) ? 'checked' : '' ?>> <?= $c ?></label><?php endforeach ?></div></div>
      <?= input_field('about', 'Про себе', ['type' => 'textarea', 'rows' => 4, 'value' => $r['about'] ?? '', 'maxlength' => 3000, 'placeholder' => 'Коротко: чим ви сильні, яку роботу шукаєте, коли готові почати']) ?>

      <div class="rz-actions">
        <?php if (in_array($st, ['published', 'pending'], true)): ?>
          <button class="btn pri" name="action" value="submit">Зберегти зміни</button>
          <span class="small muted">Зміни перевірить модератор, після цього резюме знову з’явиться в пошуку.</span>
        <?php else: ?>
          <button class="btn pri" name="action" value="submit">Опублікувати</button>
          <button class="btn" name="action" value="draft">Зберегти чернетку</button>
          <span class="small muted">Перед публікацією резюме перевіряє модератор.</span>
        <?php endif ?>
      </div>
    </form>

    <?php if ($r): ?>
    <div class="card pad stack" id="invites">
      <h2 style="font-size:20px">Запрошення від роботодавців <span class="muted"><?= count($invites) ?></span></h2>
      <?php if (!$invites): ?><p class="muted">Поки запрошень немає. Коли роботодавець натисне «Запросити» у вашому резюме, повідомлення з’явиться тут і прийде вам на пошту або SMS.</p>
      <?php else: ?><div class="list"><?php foreach ($invites as $i): ?>
        <div class="stack" style="gap:6px">
          <div class="row"><b><?= e($i['employer']) ?></b><?php if ($i['is_verified']): ?><span class="tag ok"><?= icon('shield', 12) ?> перевірений</span><?php endif ?><?php if ($i['status'] === 'new'): ?><span class="tag warn">нове</span><?php endif ?><span class="small muted"><?= e(ago($i['created_at'])) ?></span></div>
          <?php if ($i['vacancy_id'] && $i['vacancy_status'] === 'published'): ?><div class="small">Вакансія: <a href="<?= url('/jobs/' . $i['vacancy_id']) ?>"><?= e($i['vacancy_title']) ?></a></div><?php endif ?>
          <p class="prose small" style="margin:0"><?= e($i['message']) ?></p>
          <?php if ($i['contact']): ?><div class="small"><b>Контакт:</b> <?= e($i['contact']) ?></div><?php endif ?>
        </div>
      <?php endforeach ?></div><?php endif ?>
    </div>

    <div class="card pad rz-actions">
      <form method="post" action="<?= url('/cabinet/resume/visibility') ?>"><?= csrf_field() ?><input type="hidden" name="hide" value="<?= $r['is_hidden'] ? '0' : '1' ?>">
        <button class="btn"><?= $r['is_hidden'] ? 'Показувати резюме в пошуку' : 'Приховати резюме з пошуку' ?></button></form>
      <span class="small muted" style="flex:1;min-width:200px"><?= $r['is_hidden'] ? 'Зараз резюме бачите лише ви та роботодавці, яким ви відгукнулися.' : 'Наприклад, коли вже знайшли роботу. Дані не видаляються.' ?></span>
      <form method="post" action="<?= url('/cabinet/resume/delete') ?>" onsubmit="return confirm('Видалити резюме? Профіль і фото залишаться.')"><?= csrf_field() ?><button class="btn ghost bad">Видалити резюме</button></form>
    </div>
    <?php endif ?>
  </div>
</div>
<script>
(function(){
  function wire(root){root.querySelectorAll('[data-del]').forEach(function(b){b.onclick=function(){var row=b.closest('[data-row]'),list=row.parentNode;if(list.querySelectorAll('[data-row]').length>1){row.remove()}else{row.querySelectorAll('input,textarea').forEach(function(i){i.value=''})}}})}
  document.querySelectorAll('[data-add]').forEach(function(b){b.onclick=function(){var k=b.dataset.add,list=document.getElementById(k+'-list'),max=k==='exp'?<?= Resumes::MAX_EXPERIENCE ?>:<?= Resumes::MAX_EDUCATION ?>;
    if(list.querySelectorAll('[data-row]').length>=max)return;var n=+list.dataset.next;list.dataset.next=n+1;
    var html=document.getElementById(k+'-tpl').innerHTML.replace(/\[999\]/g,'['+n+']'),t=document.createElement('div');t.innerHTML=html;var row=t.firstElementChild;list.appendChild(row);wire(row);var f=row.querySelector('input');if(f)f.focus()}});
  wire(document);
})();
</script>
