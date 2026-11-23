<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Новорічне оформлення (0.5.4): сніжинки, гірлянда в шапці, шапочка й світіння логотипа, замети над низом сторінки.
 * Працює з будь-якою темою. Режим: вимкнено / увімкнено / автоматично за датами (щороку, напр. 15.12–15.01).
 * Окремою галочкою на час свят можна підмінити активну тему на «Новорічну» без зміни публікацій:
 * після свят сайт сам показує звичайну тему.
 */
final class Festive
{
    public const KEY = 'festive.newyear';
    public const THEME_SLUG = 'new-year';
    public const MODES = ['off' => 'Вимкнено', 'auto' => 'Автоматично за датами', 'on' => 'Увімкнено зараз'];

    public const DEFAULTS = ['mode' => 'auto', 'from' => '12-15', 'to' => '01-15', 'snow' => true, 'garland' => true, 'logo' => true, 'theme' => false];

    /** Налаштування з бази з типовими значеннями. */
    public static function config(): array
    {
        try {
            $s = App::setting(self::KEY);
        } catch (\Throwable) {
            $s = null; // сайт ще не встановлено
        }
        $c = self::DEFAULTS;
        if (is_array($s)) {
            if (isset(self::MODES[$s['mode'] ?? ''])) $c['mode'] = $s['mode'];
            foreach (['from', 'to'] as $k) if (self::validDay((string) ($s[$k] ?? ''))) $c[$k] = $s[$k];
            foreach (['snow', 'garland', 'logo', 'theme'] as $k) if (array_key_exists($k, $s)) $c[$k] = (bool) $s[$k];
        }
        return $c;
    }

    /** «ММ-ДД», напр. 12-15. */
    public static function validDay(string $d): bool
    {
        return (bool) preg_match('/^(\d\d)-(\d\d)$/', $d, $m) && checkdate((int) $m[1], (int) $m[2], 2024);
    }

    /** Чи діє оформлення в цей день. Період може переходити через Новий рік (15.12–15.01). */
    public static function isOn(?array $c = null, ?\DateTimeInterface $now = null): bool
    {
        $c ??= self::config();
        if ($c['mode'] === 'on') return true;
        if ($c['mode'] !== 'auto') return false;
        $today = ($now ?? new \DateTimeImmutable('now'))->format('m-d');
        return $c['from'] <= $c['to']
            ? $today >= $c['from'] && $today <= $c['to']
            : $today >= $c['from'] || $today <= $c['to'];
    }

    /** Ефекти, що діють зараз (порожньо, якщо свято не триває). @return array{snow:bool,garland:bool,logo:bool,theme:bool}|null */
    public static function current(): ?array
    {
        static $cur = false;
        if ($cur !== false) return $cur;
        $c = self::config();
        if (!self::isOn($c)) return $cur = null;
        $fx = ['snow' => $c['snow'], 'garland' => $c['garland'], 'logo' => $c['logo'], 'theme' => $c['theme']];
        return $cur = in_array(true, $fx, true) ? $fx : null;
    }

    /** Чи підміняти зараз тему на «Новорічну». */
    public static function themeOverride(): bool
    {
        return (bool) (self::current()['theme'] ?? false);
    }

    /** «15.12» для показу людині. */
    public static function human(string $md): string
    {
        [$m, $d] = explode('-', $md);
        return "$d.$m";
    }

    /** «15.12» або «15-12» → «12-15»; null, якщо дата неправильна. */
    public static function parseHuman(string $s): ?string
    {
        if (!preg_match('/^\s*(\d{1,2})\s*[.\-\/]\s*(\d{1,2})\s*$/', $s, $m)) return null;
        $md = sprintf('%02d-%02d', (int) $m[2], (int) $m[1]);
        return self::validDay($md) ? $md : null;
    }
}
