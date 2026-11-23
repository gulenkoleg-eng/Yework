<?php
declare(strict_types=1);

use App\Core\App;
use App\Core\Csrf;
use App\Core\View;

/** Екранування для HTML. */
function e(mixed $v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Абсолютний шлях на сайті з урахуванням підпапки (якщо сайт не в корені домену). */
function url(string $path = '/', array $query = []): string
{
    $base = App::basePath();
    $u = $base . '/' . ltrim($path, '/');
    if ($query) {
        $q = http_build_query(array_filter($query, static fn ($v) => $v !== null && $v !== ''));
        if ($q !== '') $u .= '?' . $q;
    }
    return $u === '' ? '/' : $u;
}

function asset(string $path): string
{
    $file = YW_ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr((string) filemtime($file), -6) : YW_VERSION;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function view(string $name, array $data = [], ?string $layout = 'site'): string
{
    return View::render($name, $data, $layout);
}

/** Форматування суми: 15 000 */
function money(?int $v): string
{
    return $v === null ? '' : number_format($v, 0, ',', ' ');
}

function salary_text(?int $from, ?int $to, string $period = 'month'): string
{
    $p = ['month' => 'грн/міс', 'day' => 'грн/день', 'hour' => 'грн/год', 'piece' => 'грн за роботу'][$period] ?? 'грн';
    if ($from && $to) return money($from) . ' – ' . money($to) . ' ' . $p;
    if ($from) return 'від ' . money($from) . ' ' . $p;
    if ($to) return 'до ' . money($to) . ' ' . $p;
    return 'Зарплата за домовленістю';
}

const EMPLOYMENT_TYPES = [
    'full' => 'Повна зайнятість', 'part' => 'Часткова зайнятість', 'shift' => 'Позмінно / вахта',
    'seasonal' => 'Сезонна робота', 'remote' => 'Віддалено', 'one_time' => 'Разова робота',
];

/** «5 хвилин тому», «вчора», «12 бер» */
function ago(?string $ts): string
{
    if (!$ts) return '';
    $t = strtotime($ts . ' UTC');
    $d = time() - $t;
    if ($d < 60) return 'щойно';
    if ($d < 3600) return intdiv($d, 60) . ' хв тому';
    if ($d < 86400 && date('Y-m-d') === date('Y-m-d', $t)) return 'сьогодні о ' . date('H:i', $t);
    if (date('Y-m-d', strtotime('-1 day')) === date('Y-m-d', $t)) return 'вчора о ' . date('H:i', $t);
    $m = ['', 'січ', 'лют', 'бер', 'кві', 'тра', 'чер', 'лип', 'сер', 'вер', 'жов', 'лис', 'гру'][(int) date('n', $t)];
    return date('j', $t) . ' ' . $m . (date('Y', $t) !== date('Y') ? ' ' . date('Y', $t) : '');
}

function plural(int $n, string $one, string $few, string $many): string
{
    $n10 = $n % 10; $n100 = $n % 100;
    $w = ($n10 === 1 && $n100 !== 11) ? $one : (($n10 >= 2 && $n10 <= 4 && ($n100 < 10 || $n100 >= 20)) ? $few : $many);
    return $n . ' ' . $w;
}

/** Повідомлення «на один показ» після перенаправлення. */
function flash(?string $type = null, ?string $text = null): ?array
{
    if ($type !== null) {
        App::session()->set('flash', ['type' => $type, 'text' => $text]);
        return null;
    }
    $f = App::session()->get('flash');
    if ($f) App::session()->set('flash', null);
    return $f;
}

function old(string $key, mixed $default = ''): string
{
    $o = App::$old;
    return e($o[$key] ?? $default);
}

function field_error(string $key): string
{
    $err = App::$errors[$key] ?? null;
    return $err ? '<div class="ferr">' . e($err) . '</div>' : '';
}

function err_class(string $key): string
{
    return isset(App::$errors[$key]) ? ' err' : '';
}

/** Маска контакту: +380 67 *** ** 67, ol***@gmail.com */
function mask_contact(?string $v): ?string
{
    if (!$v) return null;
    if (str_contains($v, '@')) {
        [$a, $d] = explode('@', $v, 2);
        return mb_substr($a, 0, 2) . '***@' . $d;
    }
    return substr($v, 0, 6) . ' ' . substr($v, 6, 2) . ' *** ** ' . substr($v, -2);
}

function phone_pretty(?string $p): string
{
    if (!$p || !preg_match('/^\+380(\d{2})(\d{3})(\d{2})(\d{2})$/', $p, $m)) return (string) $p;
    return "+380 {$m[1]} {$m[2]} {$m[3]} {$m[4]}";
}

/** Дата з БД (UTC) у місцевому часі. */
function local_date(?string $ts, string $fmt = 'd.m.Y H:i'): string
{
    return $ts ? date($fmt, strtotime($ts . ' UTC')) : '';
}

/** Загальне повідомлення про помилку форми + flash. */
function form_alert(): string
{
    $out = '';
    if (App::$formError) $out .= '<div class="alert bad" role="alert">' . e(App::$formError) . '</div>';
    return $out;
}

function flash_alert(): string
{
    $f = flash();
    return $f ? '<div class="alert ' . e($f['type']) . '" role="status">' . e($f['text']) . '</div>' : '';
}

/** Поле форми з підписом, підказкою й помилкою. */
function input_field(string $name, string $label, array $o = []): string
{
    $type = $o['type'] ?? 'text';
    $val = array_key_exists($name, App::$old) ? App::$old[$name] : ($o['value'] ?? '');
    $attrs = '';
    foreach (['placeholder', 'autocomplete', 'inputmode', 'maxlength', 'pattern'] as $a) if (isset($o[$a])) $attrs .= " $a=\"" . e($o[$a]) . '"';
    if (!empty($o['required'])) $attrs .= ' required';
    if (!empty($o['autofocus'])) $attrs .= ' autofocus';
    $id = 'f_' . $name;
    $ctl = match ($type) {
        'textarea' => '<textarea id="' . $id . '" name="' . e($name) . '" class="input"' . $attrs . ' rows="' . ($o['rows'] ?? 5) . '">' . e($val) . '</textarea>',
        'select' => '<select id="' . $id . '" name="' . e($name) . '" class="input"' . $attrs . '>' . implode('', array_map(
            static fn ($k, $v) => '<option value="' . e($k) . '"' . ((string) $k === (string) $val ? ' selected' : '') . '>' . e($v) . '</option>', array_keys($o['options']), $o['options'])) . '</select>',
        default => '<input id="' . $id . '" type="' . e($type) . '" name="' . e($name) . '" class="input" value="' . ($type === 'password' ? '' : e($val)) . '"' . $attrs . '>',
    };
    $err = App::$errors[$name] ?? null;
    return '<div class="field' . ($err ? ' err' : '') . '"><label for="' . $id . '">' . e($label) . '</label>' . $ctl
        . (!empty($o['hint']) ? '<span class="hint">' . e($o['hint']) . '</span>' : '')
        . ($err && trim($err) !== '' ? '<div class="ferr">' . e($err) . '</div>' : '') . '</div>';
}

/** Невеликий набір іконок (лінійні SVG). */
function icon(string $name, int $size = 18): string
{
    $p = [
        'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 13h18"/>',
        'bolt' => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
        'tool' => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/>',
        'pin' => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'check' => '<path d="m5 12 5 5L20 7"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'eye' => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'shield' => '<path d="M12 3 4 6v6c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'sliders' => '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
        'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 17-5-5L5 20"/>',
    ][$name] ?? '<circle cx="12" cy="12" r="9"/>';
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

/** Форми власності роботодавця: код у базі => підпис у формі. */
const LEGAL_FORMS = [
    'person' => 'Приватна особа (наймаю для себе)',
    'ФОП' => 'ФОП — фізична особа-підприємець',
    'ТОВ' => 'ТОВ — товариство з обмеженою відповідальністю',
    'ПП' => 'ПП — приватне підприємство',
    'ФГ' => 'ФГ — фермерське господарство',
    'СФГ' => 'СФГ — селянське (фермерське) господарство',
    'АТ' => 'АТ / ПрАТ — акціонерне товариство',
    'КП' => 'КП — комунальне підприємство',
    'ДП' => 'ДП — державне підприємство',
    'Заклад' => 'Бюджетна установа (школа, лікарня, ЦНАП)',
    'ГО' => 'ГО — громадська або благодійна організація',
    'other' => 'Інше',
];

/** Назва роботодавця з формою власності: ТОВ «Агро-Миронівка», ФОП Іваненко І. І. */
function employer_name(?string $form, ?string $name): string
{
    $name = trim((string) $name);
    $short = in_array($form, ['ФОП', 'ТОВ', 'ПП', 'ФГ', 'СФГ', 'АТ', 'КП', 'ДП', 'ГО'], true) ? $form : null;
    if (!$short || $name === '') return $name;
    // Якщо форму вже написали в назві («ТОВ Агро», «фоп Петренко») — не дублюємо.
    if (preg_match('/^(' . preg_quote($short, '/') . '|ПрАТ|ПАТ)(\s|«|"|$)/iu', $name)) return $name;
    if ($short === 'ФОП' || preg_match('/[«"“„\']/u', $name)) return $short . ' ' . $name;
    return $short . ' «' . $name . '»';
}

/** Логотип роботодавця (img) або порожній рядок, якщо його немає. */
function employer_logo(?string $file, string $name, int $size = 44, string $class = 'elogo'): string
{
    if (!$file) return '';
    return '<img class="' . e($class) . '" src="' . e(\App\Core\Media::url('logo', $file)) . '" alt="Логотип: ' . e($name) . '" width="' . $size . '" height="' . $size . '" loading="lazy" decoding="async">';
}

/** Перші літери назви для заглушки замість логотипу. */
function initials(string $name): string
{
    $name = preg_replace('/^(ТОВ|ФОП|ПП|ФГ|СФГ|АТ|ПрАТ|КП|ДП|ГО)\s+/u', '', trim($name)) ?? $name;
    $w = preg_split('/[\s«»"\'\-]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
    return mb_strtoupper(mb_substr($w[0], 0, 1) . (isset($w[1]) ? mb_substr($w[1], 0, 1) : ''));
}

/** Налаштування підказки про логотип і майбутнього бонусу (адмінка → Налаштування). */
function logo_settings(): array
{
    $s = (array) App::setting('employers.logo', []);
    return [
        'hint' => (bool) ($s['hint'] ?? true),
        'hintText' => (string) ($s['hintText'] ?? 'Вакансії з логотипом виділяються в списку, а кандидати швидше впізнають вашу компанію і більше їй довіряють.'),
        'perk' => (bool) ($s['perk'] ?? false),
        'perkText' => (string) ($s['perkText'] ?? ''),
    ];
}

/**
 * Перемикач «Перевірений роботодавець» в один клік (0.5.23): кнопка-позначка у списках адмінки.
 * $back — адреса в адмінці, куди повернутися після натискання (з фільтрами й сторінкою).
 * $mini — лише іконка (для тісних рядків модерації).
 */
function verify_toggle(int $employerId, bool $on, string $back, bool $mini = false): string
{
    $label = $on ? 'Перевірений' : 'Позначити перевіреним';
    $title = $on ? 'Перевірений роботодавець. Натисніть, щоб зняти позначку' : 'Натисніть, щоб позначити роботодавця перевіреним';
    return '<form method="post" class="vt-form" action="' . e(url('/admin/employers/' . $employerId . '/verified')) . '">' . csrf_field()
        . '<input type="hidden" name="on" value="' . ($on ? '0' : '1') . '"><input type="hidden" name="back" value="' . e($back) . '">'
        . '<button class="vt' . ($on ? ' on' : '') . ($mini ? ' mini' : '') . '" title="' . e($title) . '" aria-pressed="' . ($on ? 'true' : 'false') . '">'
        . icon('shield', 14) . ($mini ? '' : '<span>' . $label . '</span>') . '</button></form>';
}
