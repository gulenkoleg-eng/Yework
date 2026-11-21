<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Статичні сторінки сайту («Про нас», «Правила», «Контакти»).
 * Текст редагується в адмінці й зберігається в таблиці settings під ключем page.<slug>,
 * тож нова сторінка не потребує змін у базі. Поки текст не змінювали — показується текст за замовчуванням нижче.
 *
 * Розмітка тексту (проста, щоб не треба було знати HTML):
 *   ## Заголовок, ### Підзаголовок, рядки з «- » — список, порожній рядок — новий абзац,
 *   **жирний**, [текст посилання](https://адреса або /jobs).
 */
final class Pages
{
    /** Відомі сторінки: slug => [назва, адреса, показувати за замовчуванням]. Порядок = порядок у футері. */
    public const LIST = [
        'about' => ['title' => 'Про нас', 'path' => '/about', 'visible' => true],
        'rules' => ['title' => 'Правила сайту', 'path' => '/rules', 'visible' => true],
        'contacts' => ['title' => 'Контакти', 'path' => '/contacts', 'visible' => false],
    ];

    public static function exists(string $slug): bool
    {
        return isset(self::LIST[$slug]);
    }

    /** @return array{slug:string,title:string,path:string,body:string,visible:bool,custom:bool,updated_at:?string} */
    public static function get(string $slug): array
    {
        $def = self::LIST[$slug] ?? throw new \InvalidArgumentException("Невідома сторінка: $slug");
        $saved = App::setting('page.' . $slug);
        $saved = is_array($saved) ? $saved : [];
        return [
            'slug' => $slug,
            'title' => (string) ($saved['title'] ?? $def['title']),
            'path' => $def['path'],
            'body' => (string) ($saved['body'] ?? self::defaultBody($slug)),
            'visible' => (bool) ($saved['visible'] ?? $def['visible']),
            'custom' => $saved !== [],
            'updated_at' => $saved['updated_at'] ?? null,
        ];
    }

    public static function save(string $slug, string $title, string $body, bool $visible, ?int $actorId): void
    {
        if (!self::exists($slug)) throw new \InvalidArgumentException("Невідома сторінка: $slug");
        App::setSetting('page.' . $slug, ['title' => $title, 'body' => $body, 'visible' => $visible, 'updated_at' => gmdate('Y-m-d H:i:s')], $actorId);
    }

    public static function reset(string $slug, ?int $actorId): void
    {
        if (!self::exists($slug)) throw new \InvalidArgumentException("Невідома сторінка: $slug");
        App::db()->run('DELETE FROM {p}settings WHERE `key` = ?', ['page.' . $slug]);
    }

    /** Сторінки, які показуються відвідувачам (для футера). @return list<array{title:string,path:string}> */
    public static function visible(): array
    {
        $out = [];
        try {
            foreach (array_keys(self::LIST) as $slug) {
                $p = self::get($slug);
                if ($p['visible']) $out[] = ['slug' => $slug, 'title' => $p['title'], 'path' => $p['path']];
            }
        } catch (\Throwable) {
            // Футер не повинен ламати сторінку, навіть якщо база недоступна.
        }
        return $out;
    }

    /** Перетворює простий текст із розміткою на безпечний HTML (усе екранується). */
    public static function toHtml(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $html = '';
        $para = [];
        $list = [];
        $flushPara = static function () use (&$para, &$html): void {
            if ($para) $html .= '<p>' . implode('<br>', array_map([self::class, 'inline'], $para)) . "</p>\n";
            $para = [];
        };
        $flushList = static function () use (&$list, &$html): void {
            if ($list) $html .= '<ul>' . implode('', array_map(static fn ($li) => '<li>' . self::inline($li) . '</li>', $list)) . "</ul>\n";
            $list = [];
        };
        foreach (explode("\n", $text) as $line) {
            $t = trim($line);
            if ($t === '') { $flushPara(); $flushList(); continue; }
            if (preg_match('/^(#{2,3})\s+(.+)$/u', $t, $m)) {
                $flushPara(); $flushList();
                $h = strlen($m[1]);
                $html .= "<h$h>" . self::inline($m[2]) . "</h$h>\n";
                continue;
            }
            if (preg_match('/^[-•*]\s+(.+)$/u', $t, $m)) { $flushPara(); $list[] = $m[1]; continue; }
            $flushList();
            $para[] = $t;
        }
        $flushPara(); $flushList();
        return $html;
    }

    /** Жирний текст і посилання всередині рядка. */
    private static function inline(string $s): string
    {
        $s = e($s);
        $s = preg_replace('/\*\*(.+?)\*\*/u', '<b>$1</b>', $s);
        return preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/u', static function (array $m): string {
            $href = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
            if (str_starts_with($href, '/') && !str_starts_with($href, '//')) {
                return '<a href="' . e(url($href)) . '">' . $m[1] . '</a>';
            }
            if (preg_match('#^(https?://|mailto:|tel:)#i', $href)) {
                $ext = preg_match('#^https?://#i', $href) ? ' target="_blank" rel="noopener nofollow"' : '';
                return '<a href="' . e($href) . '"' . $ext . '>' . $m[1] . '</a>';
            }
            return $m[0];
        }, $s);
    }

    public static function defaultBody(string $slug): string
    {
        return match ($slug) {
            'about' => <<<'TXT'
**YEWORK — Є робота!** Це місцевий сайт, де мешканці Миронівської, Богуславської та Кагарлицької громад шукають роботу, підробіток і послуги поруч із домом.

## Навіщо ми це робимо
У невеликих містах і селах про роботу часто дізнаються від знайомих, з оголошень на стовпах або з десятка різних чатів. Великі сайти вакансій розраховані на Київ та обласні центри, і місцеві пропозиції там просто губляться. Ми хочемо, щоб усе було в одному місці: зрозуміло, чесно й поруч.

## Що тут можна знайти
- **Вакансії.** Постійна робота на місцевих підприємствах, у магазинах, господарствах, закладах і установах.
- **Підробіток.** Разова чи сезонна робота: допомогти з ремонтом, на городі чи на збиранні врожаю, підмінити на зміні.
- **Послуги.** Майстри та фахівці поруч із вами. Цей розділ ми запустимо незабаром.

## Як це працює
- Переглядати вакансії можна без реєстрації.
- Щоб відгукнутися або розмістити оголошення, достатньо зареєструватися за номером телефону чи email.
- Шукачі можуть створити резюме і відгукуватися з ним, а роботодавці знаходять кандидатів у розділі [«Резюме»](/resumes).
- Кожне оголошення та резюме перед публікацією перевіряє модератор.
- Поки сайт працює в тестовому режимі, розміщення вакансій безкоштовне.

## Безпека та чесність
Ми не пропускаємо оголошень, де від працівника вимагають передоплату, і пропозицій із сумнівними схемами. Ваш телефон та email зберігаються в зашифрованому вигляді, а роботодавець бачить ваші контакти лише тоді, коли ви самі відгукнулися на його вакансію. Детальніше в [правилах сайту](/rules).

## Ми лише починаємо
YEWORK зараз у бета-версії: ми щодня щось покращуємо і будемо раді вашим відгукам. Якщо ви роботодавець, розмістіть першу [вакансію](/cabinet/vacancies/new). Якщо шукаєте роботу, загляньте в [список вакансій](/jobs) і розкажіть про нас знайомим.
TXT,
            'rules' => <<<'TXT'
Публікуйте лише реальні вакансії від свого імені або від імені організації, яку ви представляєте. Зазначайте чесну оплату та умови.

Заборонено: вакансії, що вимагають передоплати від працівника, «роботу» з сумнівними схемами, дискримінаційні вимоги, чужі контакти.

Кожну вакансію перевіряє модератор. Ми можемо відхилити або зняти оголошення, що порушує правила, і заблокувати акаунт.

Персональні дані: ваш телефон і email зберігаються в зашифрованому вигляді. Роботодавець бачить ваші контакти лише тоді, коли ви самі відгукнулися на його вакансію.
TXT,
            'contacts' => <<<'TXT'
Маєте питання, пропозицію чи помітили помилку на сайті? Напишіть нам.

- Email: [info@example.com](mailto:info@example.com)
- Телефон: [+380 00 000 00 00](tel:+380000000000)
TXT,
            default => '',
        };
    }
}
