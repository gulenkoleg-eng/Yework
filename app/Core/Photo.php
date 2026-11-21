<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Фото профілю. Файл перекодовується через GD у JPEG 480×640 (портрет 3:4), тож службові дані
 * (EXIF, геолокація, модель телефону) не зберігаються. Файли лежать у storage/uploads/photos,
 * куди браузер напряму не має доступу: їх віддає PHP після перевірки прав.
 */
final class Photo
{
    public const MAX_BYTES = 2 * 1024 * 1024;
    public const W = 480;
    public const H = 640;

    public static function dir(): string
    {
        $d = YW_ROOT . '/storage/uploads/photos';
        if (!is_dir($d)) @mkdir($d, 0755, true);
        return $d;
    }

    public static function path(?string $file): ?string
    {
        if (!$file || !preg_match('/^[a-f0-9]{32}\.jpg$/', $file)) return null;
        $p = self::dir() . '/' . $file;
        return is_file($p) ? $p : null;
    }

    public static function supported(): bool
    {
        return function_exists('imagecreatefromstring') && function_exists('imagejpeg');
    }

    /**
     * Перевіряє завантажений файл і зберігає нормалізований JPEG.
     * @param array $f елемент $_FILES
     * @return string ім'я нового файлу
     */
    public static function store(array $f): string
    {
        if (!self::supported()) throw new ValidationException(['photo' => 'На сервері вимкнено розширення GD, тому фото поки не можна завантажити.']);
        $err = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) throw new ValidationException(['photo' => 'Файл завеликий. Максимум 2 МБ.']);
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($f['tmp_name'] ?? ''))) throw new ValidationException(['photo' => 'Не вдалося завантажити файл. Спробуйте ще раз.']);
        if ((int) $f['size'] > self::MAX_BYTES) throw new ValidationException(['photo' => 'Файл завеликий. Максимум 2 МБ.']);
        return self::storeFile((string) $f['tmp_name']);
    }

    /** Окремо від store(), щоб можна було перевірити без HTTP-завантаження. */
    public static function storeFile(string $tmp): string
    {
        $info = @getimagesize($tmp);
        $type = $info[2] ?? 0;
        if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) throw new ValidationException(['photo' => 'Підходять лише фото JPG, PNG або WEBP.']);
        [$w, $h] = $info;
        if ($w < 120 || $h < 120) throw new ValidationException(['photo' => 'Фото замале. Потрібно щонайменше 120×120 пікселів.']);
        if ($w * $h > 40_000_000) throw new ValidationException(['photo' => 'Фото має завелику роздільність.']);
        $src = @imagecreatefromstring((string) file_get_contents($tmp));
        if (!$src) throw new ValidationException(['photo' => 'Не вдалося прочитати зображення.']);

        // Телефони пишуть поворот у EXIF: повертаємо зображення, щоб воно не лежало на боці.
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $o = (int) (@exif_read_data($tmp)['Orientation'] ?? 1);
            $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
            if ($rot) { $r = imagerotate($src, $rot, 0); if ($r) { imagedestroy($src); $src = $r; } }
        }
        $w = imagesx($src); $h = imagesy($src);

        // Обрізаємо по центру до 3:4 і зменшуємо.
        $ratio = self::W / self::H;
        if ($w / $h > $ratio) { $cw = (int) round($h * $ratio); $ch = $h; $cx = intdiv($w - $cw, 2); $cy = 0; }
        else { $cw = $w; $ch = (int) round($w / $ratio); $cx = 0; $cy = (int) max(0, round(($h - $ch) * 0.3)); }
        $dst = imagecreatetruecolor(self::W, self::H);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, $cx, $cy, self::W, self::H, $cw, $ch);
        imagedestroy($src);
        imageinterlace($dst, false); // PDF вбудовує лише звичайний (не progressive) JPEG

        $name = bin2hex(random_bytes(16)) . '.jpg';
        $ok = imagejpeg($dst, self::dir() . '/' . $name, 86);
        imagedestroy($dst);
        if (!$ok) throw new \RuntimeException('Не вдалося записати фото в storage/uploads/photos');
        return $name;
    }

    public static function delete(?string $file): void
    {
        $p = self::path($file);
        if ($p) @unlink($p);
    }
}
