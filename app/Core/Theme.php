<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Активна тема → CSS-змінні. Результат кешується у storage/cache/theme-<id>.css і перебудовується при публікації.
 */
final class Theme
{
    public const FONTS = [
        'Inter' => 'Inter:wght@400;500;600;700;800',
        'Unbounded' => 'Unbounded:wght@500;600;700',
        'IBM Plex Sans' => 'IBM+Plex+Sans:wght@400;500;600;700',
        'Manrope' => 'Manrope:wght@400;500;600;700;800',
        'Playfair Display' => 'Playfair+Display:wght@600;700',
        'Nunito' => 'Nunito:wght@400;600;700;800',
    ];

    /** @return array{id:int, slug:string, name:string, mode:string, tokens:array} */
    public static function active(): array
    {
        static $t = null;
        if ($t) return $t;
        $row = App::db()->one('SELECT tv.id, tv.tokens, t.slug, t.name, t.default_mode FROM {p}theme_publications p JOIN {p}theme_versions tv ON tv.id = p.theme_version_id JOIN {p}themes t ON t.id = tv.theme_id WHERE p.active_flag = 1');
        if (!$row) {
            $all = json_decode((string) file_get_contents(YW_ROOT . '/database/seed/themes.json'), true);
            $x = $all[0];
            return $t = ['id' => 0, 'slug' => $x['id'], 'name' => $x['name'], 'mode' => $x['defaultMode'], 'tokens' => ['base' => $x['base'], 'light' => $x['light'], 'dark' => $x['dark']]];
        }
        return $t = ['id' => (int) $row['id'], 'slug' => $row['slug'], 'name' => $row['name'], 'mode' => $row['default_mode'], 'tokens' => json_decode($row['tokens'], true)];
    }

    public static function vars(array $c): string
    {
        $map = ['primary' => 'primary', 'secondary' => 'secondary', 'bgPage' => 'bg', 'bgCard' => 'card', 'text' => 'text', 'muted' => 'muted', 'link' => 'link',
            'button' => 'button', 'buttonText' => 'button-ink', 'menu' => 'menu', 'menuText' => 'menu-ink', 'heading' => 'heading', 'border' => 'border',
            'message' => 'message', 'success' => 'success', 'warning' => 'warning', 'danger' => 'danger', 'info' => 'info'];
        $out = [];
        foreach ($map as $k => $v) if (isset($c[$k]) && preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $c[$k])) $out[] = "--c-$v:{$c[$k]}";
        return implode(';', $out);
    }

    /** CSS для <style> у шаблоні: базові параметри + кольори світлого й темного режимів. */
    public static function css(?array $theme = null): string
    {
        $theme ??= self::active();
        $tok = $theme['tokens'];
        $b = $tok['base'];
        $font = self::fontStack($b['font'] ?? 'Inter');
        $head = self::fontStack($b['headingFont'] ?? ($b['font'] ?? 'Inter'));
        $shadow = match ($b['shadow'] ?? 'soft') {
            'none' => 'none',
            'medium' => '0 2px 6px rgba(16,24,40,.06),0 12px 32px rgba(16,24,40,.10)',
            default => '0 1px 2px rgba(16,24,40,.05),0 6px 20px rgba(16,24,40,.06)',
        };
        $base = sprintf('--font:%s;--font-head:%s;--fs:%dpx;--fw-head:%d;--lh:%s;--r-btn:%dpx;--r-card:%dpx;--shadow:%s;--bw:%dpx',
            $font, $head, (int) ($b['size'] ?? 16), (int) ($b['weight'] ?? 700), (string) ($b['lineHeight'] ?? 1.5),
            (int) ($b['radiusBtn'] ?? 10), (int) ($b['radiusCard'] ?? 12), $shadow, (int) ($b['borderW'] ?? 1));
        $def = $theme['mode'] === 'dark' ? 'dark' : 'light';
        $alt = $def === 'dark' ? 'light' : 'dark';
        return ':root{' . $base . ';' . self::vars($tok[$def]) . ";color-scheme:$def}"
            . ":root[data-mode=$alt]{" . self::vars($tok[$alt]) . ";color-scheme:$alt}";
    }

    public static function fontStack(string $f): string
    {
        $serif = $f === 'Playfair Display';
        return "'" . str_replace("'", '', $f) . "'," . ($serif ? 'Georgia,serif' : 'system-ui,-apple-system,"Segoe UI",Roboto,sans-serif');
    }

    public static function fontsUrl(?array $theme = null): string
    {
        $theme ??= self::active();
        $b = $theme['tokens']['base'];
        $fams = array_unique(array_filter([self::FONTS[$b['font'] ?? 'Inter'] ?? null, self::FONTS[$b['headingFont'] ?? ''] ?? null]));
        return 'https://fonts.googleapis.com/css2?family=' . implode('&family=', $fams) . '&display=swap';
    }
}
