<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Іконка сайту у вкладці браузера, на головному екрані телефона й у закладках (0.5.2).
 *
 * Стандартна іконка лежить в assets/img/ (жовтий квадрат «YE», як логотип у шапці) і /favicon.ico.
 * Адміністратор може завантажити свою картинку в «Налаштуваннях»: з неї робляться квадратні PNG
 * 32, 180, 192 і 512 пікселів у storage/uploads/favicon/ (віддаються через /media/favicon/...).
 * Без GD картинка зберігається як є й використовується для всіх розмірів.
 */
final class Favicon
{
    public const SIZES = [32, 180, 192, 512];
    private const KEY = 'site.favicon';

    /** Файли власної іконки за розмірами або null, якщо стоїть стандартна. @return array<int,string>|null */
    public static function custom(): ?array
    {
        try {
            $s = App::setting(self::KEY);
        } catch (\Throwable) {
            return null; // встановлення ще не завершено, бази немає
        }
        $files = is_array($s) ? (array) ($s['files'] ?? []) : [];
        foreach (self::SIZES as $n) if (!is_string($files[$n] ?? null) || $files[$n] === '') return null;
        return $files;
    }

    /** Адреса іконки потрібного розміру (32, 180, 192, 512). */
    public static function url(int $size): string
    {
        $c = self::custom();
        if ($c) return (string) Media::url('favicon', $c[$size]);
        return asset(match ($size) { 32 => 'img/favicon-32.png', 180 => 'img/apple-touch-icon.png', 192 => 'img/icon-192.png', default => 'img/icon-512.png' });
    }

    /** Теги для <head>. */
    public static function tags(): string
    {
        $e = static fn (string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $c = self::custom();
        $out = $c ? '' : '<link rel="icon" href="' . $e(asset('img/favicon.svg')) . '" type="image/svg+xml">';
        $out .= '<link rel="icon" href="' . $e(self::url(32)) . '" sizes="32x32" type="image/png">'
            . '<link rel="apple-touch-icon" href="' . $e(self::url(180)) . '">'
            . '<link rel="manifest" href="' . $e(url('/site.webmanifest')) . '">'
            . '<meta name="theme-color" content="#11141A">';
        return $out;
    }

    /** Опис сайту для «Додати на головний екран» на Android. */
    public static function manifest(string $name, string $short): array
    {
        return [
            'name' => $name, 'short_name' => mb_substr($short, 0, 12), 'start_url' => url('/'), 'display' => 'browser',
            'background_color' => '#FFFFFF', 'theme_color' => '#11141A',
            'icons' => [
                ['src' => self::url(192), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => self::url(512), 'sizes' => '512x512', 'type' => 'image/png'],
            ],
        ];
    }

    /**
     * Зберігає завантажену картинку як іконку сайту. Повертає false, якщо файл не вибрано.
     * @throws ValidationException
     */
    public static function store(string $field, ?int $by): bool
    {
        $f = $_FILES[$field] ?? null;
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return false;
        $fail = static fn (string $m) => throw new ValidationException([$field => $m]);
        if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || (int) $f['size'] > Media::MAX_BYTES) $fail('Файл завеликий. Максимум 3 МБ.');
        if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) $fail('Файл не завантажився. Спробуйте ще раз.');

        $files = self::resize($f['tmp_name'], $field);
        if ($files === null) {
            // Без GD: одна картинка як є (тип і розмір перевіряє Media).
            $one = Media::storeUploaded('favicon', $f, 'fav', $field);
            $files = array_fill_keys(self::SIZES, $one);
        }
        $old = self::custom();
        App::setSetting(self::KEY, ['files' => $files], $by);
        self::deleteFiles($old);
        return true;
    }

    /** Повертає стандартну іконку. */
    public static function reset(?int $by): void
    {
        $old = self::custom();
        App::setSetting(self::KEY, null, $by);
        self::deleteFiles($old);
    }

    /** Квадратні PNG усіх розмірів (картинка вписується в квадрат по центру). null — якщо GD немає. */
    private static function resize(string $path, string $field): ?array
    {
        $info = @getimagesize($path);
        $type = $info ? ([IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'][$info[2]] ?? null) : null;
        if (!$type) throw new ValidationException([$field => 'Підходять картинки PNG, JPG або WebP.']);
        if ($info[0] < 32 || $info[1] < 32) throw new ValidationException([$field => 'Картинка надто маленька. Потрібно хоча б 32×32, краще 512×512.']);
        if ($info[0] * $info[1] > 40_000_000) throw new ValidationException([$field => 'Картинка надто велика за розміром у пікселях.']);
        if (!extension_loaded('gd')) return null;
        $fn = ['png' => 'imagecreatefrompng', 'jpg' => 'imagecreatefromjpeg', 'webp' => 'imagecreatefromwebp', 'gif' => 'imagecreatefromgif'][$type];
        if (!function_exists($fn) || !(($img = @$fn($path)) instanceof \GdImage)) return null;

        $dir = Media::dir('favicon');
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) throw new \RuntimeException('Не вдалося створити папку storage/uploads.');
        $id = bin2hex(random_bytes(6));
        [$w, $h] = [imagesx($img), imagesy($img)];
        $files = [];
        foreach (self::SIZES as $n) {
            $out = imagecreatetruecolor($n, $n);
            if ($n === 180) {
                // iPhone не любить прозорість: підкладаємо білий фон.
                imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
                imagealphablending($out, true);
            } else {
                imagealphablending($out, false);
                imagesavealpha($out, true);
                imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
                imagealphablending($out, true);
            }
            $k = min($n / $w, $n / $h);
            $nw = max(1, (int) round($w * $k)); $nh = max(1, (int) round($h * $k));
            imagecopyresampled($out, $img, intdiv($n - $nw, 2), intdiv($n - $nh, 2), 0, 0, $nw, $nh, $w, $h);
            $file = 'fav' . $n . '-' . $id . '.png';
            $ok = imagepng($out, $dir . '/' . $file, 7);
            imagedestroy($out);
            if (!$ok) throw new \RuntimeException('Не вдалося зберегти картинку.');
            $files[$n] = $file;
        }
        imagedestroy($img);
        return $files;
    }

    private static function deleteFiles(?array $files): void
    {
        foreach (array_unique(array_values($files ?? [])) as $f) Media::delete('favicon', (string) $f);
    }
}
