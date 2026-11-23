<?php use App\Core\Ads;
$tabs = ['pending' => 'Чекають', 'approved' => 'Схвалені', 'rejected' => 'Відхилені', 'cancelled' => 'Скасовані'];
$site = (string) App\Core\App::setting('site.name', 'YEWORK');
// Зразки тексту, щоб було видно, як шаблон виглядає з реальним оголошенням.
$demo = [
  'classic' => ['Шукаємо водія категорії C', 'Офіційне працевлаштування, доїзд службовим автобусом.', ['від 22 000 грн', 'Офіційно'], ''],
  'sun' => ['Пекарі й продавці на нову зміну', 'Свіжий хліб щоранку і дружня команда. Графік 2/2.', ['2/2', 'Без досвіду'], 'Новий набір'],
  'night' => ['Автослюсар у сучасний сервіс', 'Нове обладнання і відсоток від кожного замовлення.', ['від 25 000 грн', 'Ставка + %'], ''],
  'wave' => ['Медсестри та санітари', 'Повний соцпакет і навчання за рахунок закладу.', ['Офіційно', 'Гуртожиток'], 'Соцпакет'],
  'urgent' => ['Потрібні 5 різноробочих на сезон', 'Вихід уже з понеділка. Оплата щотижня.', ['Оплата щотижня', 'Харчування'], 'Терміново'],
  'photo' => ['Тракторист і механізатор', 'Сучасна техніка, житло для приїжджих.', ['від 28 000 грн', 'Житло'], ''],
];
?>
<div class="adm-h"><h1>Шаблони банерів</h1><span class="spacer"></span>
  <div class="seg"><?php foreach ($tabs as $k => $l): ?><a href="<?= url('/admin/banners', ['status' => $k === 'pending' ? null : $k]) ?>"><?= $l ?></a><?php endforeach ?><a class="on" href="<?= url('/admin/banners/templates') ?>">Шаблони</a></div>
  <a class="btn pri sm" href="<?= url('/admin/banners/new') ?>">+ Створити банер</a>
</div>
<p class="muted small">Тут усі шаблони, які є на сайті, у живому вигляді. Роботодавець у кабінеті обирає один із ввімкнених шаблонів і пише лише текст. Вимкнений шаблон зникне з вибору, але банери, які вже замовили в ньому, покажуться до кінця свого терміну. Шаблон «за замовчуванням» буде обраний у формі одразу.</p>
<?= form_alert() ?>
<form method="post" action="<?= url('/admin/banners/templates') ?>" class="stack"><?= csrf_field() ?>
  <div class="tpl-adm">
    <?php foreach (Ads::TEMPLATES as $k => [$tl, $td]): $isOn = in_array($k, $set['on'], true); [$h, $p, $pk, $bd] = $demo[$k];
      $b = ['title' => $h, 'body' => $p, 'perks' => json_encode($pk, JSON_UNESCAPED_UNICODE), 'badge' => $bd, 'template' => $k, 'employer' => $site, 'legal_form' => null, 'logo' => null, 'vacancies' => 3,
        'image' => $k === 'photo' ? 'sample' : null, 'image_url' => $k === 'photo' ? asset('img/banner-sample.svg') : null]; ?>
      <div class="card<?= $isOn ? '' : ' off' ?>">
        <div class="row" style="justify-content:space-between"><div><b><?= e($tl) ?></b><div class="small muted"><?= e($td) ?></div></div>
          <?php if (!empty($used[$k])): ?><span class="tag"><?= plural($used[$k], 'активний банер', 'активні банери', 'активних банерів') ?></span><?php endif ?></div>
        <?= App\Core\View::partial('partials/promo', ['b' => $b, 'sample' => true]) ?>
        <div class="row" style="justify-content:space-between">
          <label class="check"><input type="checkbox" name="on[]" value="<?= e($k) ?>" <?= $isOn ? 'checked' : '' ?>> Доступний роботодавцям</label>
          <label class="check"><input type="radio" name="default" value="<?= e($k) ?>" <?= $set['default'] === $k ? 'checked' : '' ?>> За замовчуванням</label>
        </div>
        <a class="btn sm" href="<?= url('/admin/banners/new', ['template' => $k]) ?>">Створити банер у цьому шаблоні</a>
      </div>
    <?php endforeach ?>
  </div>
  <div class="row"><button class="btn pri">Зберегти</button></div>
</form>
