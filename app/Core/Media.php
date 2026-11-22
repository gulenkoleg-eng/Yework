<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Завантажені картинки: логотипи роботодавців, банери, фото майстрів і їхніх робіт.
 * Файли лежать у storage/uploads/<вид>/ (закрито від браузера) і віддаються через /media/<вид>/<файл>.
 * Якщо на хостингу є GD, картинка перекодовується й зменшується — так з неї зникає все зайве (метадані, вкладення).
 */
final class Media
{
    /** вид => [макс. ширина, макс. висота, формат збереження] */
    private const KINDS = [
        'logo' => [320, 320, 'png'],
        'banner' => [1200, 500, 'jpg'],
        'master' => [600, 600, 'jpg'],      // фото майстра (0.5.0)
        'work' => [1600, 1600, 'jpg'],      // фото виконаних робіт
        'workthumb' => [480, 480, 'jpg'],   // мініатюра того ж фото для списків
    ];
    public const MAX_BYTES = 3 * 1024 * 1024;
    private const MIME = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif'];

    public static function dir(string $kind): string
    {
        return YW_ROOT . '/storage/uploads/' . $kind;
    }

    public static function url(string $kind, ?string $file): ?string
    {
        return $file ? url('/media/' . $kind . '/' . $file) : null;
    }

    /**
     * Зберігає файл із $_FILES[$field]. Повертає назву файлу, null якщо файл не вибрано.
     * @throws ValidationException
     */
    public static function store(string $kind, string $field, string $prefix): ?string
    {
        $f = $_FILES[$field] ?? null;
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        return self::storeUploaded($kind, $f, $prefix, $field);
    }

    /**
     * Кілька файлів з одного поля (<input type="file" name="photos[]" multiple>) у вигляді звичайних елементів $_FILES.
     * @return list<array{name:string,type:string,tmp_name:string,error:int,size:int}>
     */
    public static function files(string $field): array
    {
        $f = $_FILES[$field] ?? null;
        if (!is_array($f) || !is_array($f['name'] ?? null)) return is_array($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? [$f] : [];
        $out = [];
        foreach (array_keys($f['name']) as $i) {
            $one = ['name' => $f['name'][$i], 'type' => $f['type'][$i] ?? '', 'tmp_name' => $f['tmp_name'][$i] ?? '', 'error' => (int) ($f['error'][$i] ?? UPLOAD_ERR_NO_FILE), 'size' => (int) ($f['size'][$i] ?? 0)];
            if ($one['error'] !== UPLOAD_ERR_NO_FILE) $out[] = $one;
        }
        return $out;
    }

    /**
     * Перевіряє й зберігає один завантажений файл. $name — готова назва без розширення (щоб мініатюра мала ту саму назву).
     * @throws ValidationException
     */
    public static function storeUploaded(string $kind, array $f, string $prefix, string $field, ?string $name = null): string
    {
        $fail = static fn (string $m) => throw new ValidationException([$field => $m]);
        if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || (int) $f['size'] > self::MAX_BYTES) $fail('Файл завеликий. Максимум 3 МБ.');
        if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) $fail('Файл не завантажився. Спробуйте ще раз.');
        return self::storePath($kind, $f['tmp_name'], $prefix, $field, $name, true);
    }

    /** Зберігає картинку з локального шляху (для мініатюр і перевірок без HTTP-завантаження). */
    public static function storePath(string $kind, string $path, string $prefix, string $field, ?string $name = null, bool $uploaded = false): string
    {
        $fail = static fn (string $m) => throw new ValidationException([$field => $m]);
        $info = @getimagesize($path);
        $type = $info ? ([IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'][$info[2]] ?? null) : null;
        if (!$type) $fail('Підходять картинки PNG, JPG або WebP.');
        if ($info[0] < 32 || $info[1] < 32) $fail('Картинка надто маленька.');
        if ($info[0] * $info[1] > 40_000_000) $fail('Картинка надто велика за розміром у пікселях.');

        [$maxW, $maxH, $outType] = self::KINDS[$kind];
        $dir = self::dir($kind);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) throw new \RuntimeException('Не вдалося створити папку storage/uploads.');
        $name ??= $prefix . '-' . bin2hex(random_bytes(6));

        $img = self::load($path, $type);
        if ($img) {
            [$w, $h] = [imagesx($img), imagesy($img)];
            $k = min(1, $maxW / $w, $maxH / $h);
            $nw = max(1, (int) round($w * $k)); $nh = max(1, (int) round($h * $k));
            $out = imagecreatetruecolor($nw, $nh);
            if ($outType === 'png') {
                imagealphablending($out, false);
                imagesavealpha($out, true);
                imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
            } else {
                imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
            }
            imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $file = $name . '.' . $outType;
            $ok = $outType === 'png' ? imagepng($out, $dir . '/' . $file, 7) : imagejpeg($out, $dir . '/' . $file, 84);
            imagedestroy($img); imagedestroy($out);
            if (!$ok) throw new \RuntimeException('Не вдалося зберегти картинку.');
            return $file;
        }
        // Без GD зберігаємо як є (тип уже перевірено за вмістом файлу).
        $file = $name . '.' . $type;
        $ok = $uploaded ? move_uploaded_file($path, $dir . '/' . $file) : copy($path, $dir . '/' . $file);
        if (!$ok) throw new \RuntimeException('Не вдалося зберегти картинку.');
        return $file;
    }

    private static function load(string $path, string $type): ?\GdImage
    {
        if (!extension_loaded('gd')) return null;
        $fn = ['png' => 'imagecreatefrompng', 'jpg' => 'imagecreatefromjpeg', 'webp' => 'imagecreatefromwebp', 'gif' => 'imagecreatefromgif'][$type];
        if (!function_exists($fn)) return null;
        $img = @$fn($path);
        if (!$img instanceof \GdImage) return null;
        // Телефони пишуть поворот у EXIF: повертаємо фото, щоб воно не лежало на боці.
        if ($type === 'jpg' && function_exists('exif_read_data')) {
            $rot = [3 => 180, 6 => -90, 8 => 90][(int) (@exif_read_data($path)['Orientation'] ?? 1)] ?? 0;
            if ($rot && ($r = imagerotate($img, $rot, 0)) instanceof \GdImage) { imagedestroy($img); $img = $r; }
        }
        return $img;
    }

    public static function delete(string $kind, ?string $file): void
    {
        if ($file && self::validName($file)) @unlink(self::dir($kind) . '/' . $file);
    }

    private static function validName(string $file): bool
    {
        return (bool) preg_match('/^[a-z0-9]+-[0-9a-f]{12}\.(png|jpg|webp|gif)$/', $file);
    }

    /** Віддає файл браузеру. */
    public static function serve(string $kind, string $file): Response
    {
        if (!isset(self::KINDS[$kind]) || !self::validName($file)) throw new HttpException(404, 'Файл не знайдено.');
        $path = self::dir($kind) . '/' . $file;
        if (!is_file($path)) throw new HttpException(404, 'Файл не знайдено.');
        $r = new Response((string) file_get_contents($path));
        $r->header('Content-Type', self::MIME[pathinfo($file, PATHINFO_EXTENSION)]);
        $r->header('Cache-Control', 'public, max-age=2592000, immutable'); // назва змінюється при кожній заміні
        $r->header('Content-Security-Policy', "default-src 'none'");
        return $r;
    }
}
