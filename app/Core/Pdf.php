<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Невеликий генератор PDF без сторонніх бібліотек: сторінки A4, текст шрифтом TrueType
 * (кирилиця через Identity-H), прямокутники, лінії, фото JPEG.
 * У файл вбудовується лише підмножина гліфів, які справді використано, тож PDF важить десятки кілобайт.
 *
 * Координати в API — пункти (1/72 дюйма) від лівого верхнього кута сторінки.
 */
final class Pdf
{
    public const W = 595.28;
    public const H = 841.89;

    private array $pages = [];
    private int $page = -1;
    private array $fonts = [];
    private array $images = [];
    private ?string $font = null;
    private float $size = 10;
    private array $info = [];

    public function setInfo(string $title, string $author = 'YEWORK'): void
    {
        $this->info = ['Title' => $title, 'Author' => $author, 'Creator' => 'YEWORK'];
    }

    public function addPage(): void
    {
        $this->pages[] = '';
        $this->page = count($this->pages) - 1;
        if ($this->font !== null) $this->setFont($this->font, $this->size);
    }

    public function pageCount(): int { return count($this->pages); }

    private function out(string $s): void { $this->pages[$this->page] .= $s . "\n"; }

    private static function n(float $v): string { return rtrim(rtrim(sprintf('%.2F', $v), '0'), '.') ?: '0'; }

    /* ---------------- шрифти ---------------- */

    public function addFont(string $key, string $ttfPath): void
    {
        $this->fonts[$key] = TrueType::load($ttfPath) + ['used' => [0 => true], 'n' => count($this->fonts) + 1];
    }

    public function setFont(string $key, float $size): void
    {
        $this->font = $key;
        $this->size = $size;
        if ($this->page >= 0) $this->out('BT /F' . $this->fonts[$key]['n'] . ' ' . self::n($size) . ' Tf ET');
    }

    public function textWidth(string $s, ?string $font = null, ?float $size = null): float
    {
        $f = $this->fonts[$font ?? $this->font];
        $w = 0;
        foreach (mb_str_split($s) as $ch) {
            $gid = $f['cmap'][mb_ord($ch)] ?? 0;
            $w += $f['widths'][$gid] ?? $f['widths'][count($f['widths']) - 1] ?? 0;
        }
        return $w * ($size ?? $this->size) / $f['upm'];
    }

    /** Текст з базовою лінією на y (від верху сторінки). */
    public function text(float $x, float $y, string $s, array $rgb = [0, 0, 0]): void
    {
        if ($s === '') return;
        $f = &$this->fonts[$this->font];
        $hex = '';
        foreach (mb_str_split($s) as $ch) {
            $gid = $f['cmap'][mb_ord($ch)] ?? ($f['cmap'][0x3F] ?? 0);
            $f['used'][$gid] = true;
            $f['uni'][$gid] ??= mb_ord($ch);
            $hex .= sprintf('%04X', $gid);
        }
        $this->out(sprintf('BT %s rg /F%d %s Tf %s %s Td <%s> Tj ET', self::rgb($rgb), $f['n'], self::n($this->size), self::n($x), self::n(self::H - $y), $hex));
    }

    /**
     * Розбиває текст на рядки, що вміщуються в ширину $w (з урахуванням переносів рядків у тексті).
     * @return list<string>
     */
    public function wrap(string $s, float $w): array
    {
        $lines = [];
        foreach (preg_split('/\R/u', $s) as $para) {
            $para = trim(preg_replace('/[ \t]+/u', ' ', $para) ?? '');
            if ($para === '') { $lines[] = ''; continue; }
            $cur = '';
            foreach (explode(' ', $para) as $word) {
                $try = $cur === '' ? $word : "$cur $word";
                if ($this->textWidth($try) <= $w) { $cur = $try; continue; }
                if ($cur !== '') $lines[] = $cur;
                // Дуже довге слово (посилання) ріжемо посимвольно.
                while ($this->textWidth($word) > $w) {
                    $part = '';
                    foreach (mb_str_split($word) as $ch) { if ($this->textWidth($part . $ch) > $w) break; $part .= $ch; }
                    if ($part === '') break;
                    $lines[] = $part;
                    $word = mb_substr($word, mb_strlen($part));
                }
                $cur = $word;
            }
            $lines[] = $cur;
        }
        return $lines;
    }

    /* ---------------- графіка ---------------- */

    private static function rgb(array $c): string
    {
        return implode(' ', array_map(static fn ($v) => self::n($v / 255), $c));
    }

    public function rect(float $x, float $y, float $w, float $h, array $fill): void
    {
        $this->out(sprintf('%s rg %s %s %s %s re f', self::rgb($fill), self::n($x), self::n(self::H - $y - $h), self::n($w), self::n($h)));
    }

    public function line(float $x1, float $y1, float $x2, float $y2, array $rgb, float $width = 0.6): void
    {
        $this->out(sprintf('%s RG %s w %s %s m %s %s l S', self::rgb($rgb), self::n($width), self::n($x1), self::n(self::H - $y1), self::n($x2), self::n(self::H - $y2)));
    }

    /** Вставляє JPEG (baseline). Повертає false, якщо файл не підходить. */
    public function jpeg(string $path, float $x, float $y, float $w, float $h): bool
    {
        $info = @getimagesize($path);
        if (!$info || $info[2] !== IMAGETYPE_JPEG) return false;
        $key = md5_file($path);
        if (!isset($this->images[$key])) {
            $cs = ($info['channels'] ?? 3) === 1 ? 'DeviceGray' : (($info['channels'] ?? 3) === 4 ? 'DeviceCMYK' : 'DeviceRGB');
            $this->images[$key] = ['n' => count($this->images) + 1, 'w' => $info[0], 'h' => $info[1], 'cs' => $cs, 'data' => (string) file_get_contents($path)];
        }
        $this->out(sprintf('q %s 0 0 %s %s %s cm /I%d Do Q', self::n($w), self::n($h), self::n($x), self::n(self::H - $y - $h), $this->images[$key]['n']));
        return true;
    }

    /* ---------------- збирання файлу ---------------- */

    public function output(): string
    {
        $objs = [];
        $add = static function (string $body) use (&$objs): int { $objs[] = $body; return count($objs); };
        $stream = static fn (string $dict, string $data) => '<<' . $dict . ' /Length ' . strlen($data) . ">>\nstream\n" . $data . "\nendstream";

        $catalog = $add('');
        $pagesId = $add('');

        $fontRefs = '';
        foreach ($this->fonts as $f) {
            $tag = substr(strtoupper(str_pad(base_convert(substr(md5(implode(',', array_keys($f['used']))), 0, 8), 16, 26), 6, '0')), 0, 6);
            $tag = strtr($tag, '0123456789', 'ABCDEFGHIJ');
            $name = $tag . '+' . $f['name'];
            $sub = TrueType::subset($f, array_keys($f['used']));
            $z = gzcompress($sub, 6);
            $file = $add($stream(' /Filter /FlateDecode /Length1 ' . strlen($sub), $z));
            $scale = 1000 / $f['upm'];
            $desc = $add(sprintf('<< /Type /FontDescriptor /FontName /%s /Flags 32 /FontBBox [%s] /ItalicAngle 0 /Ascent %d /Descent %d /CapHeight %d /StemV 80 /FontFile2 %d 0 R >>',
                $name, implode(' ', array_map(static fn ($v) => (int) round($v * $scale), $f['bbox'])), (int) round($f['ascent'] * $scale), (int) round($f['descent'] * $scale), (int) round($f['capHeight'] * $scale), $file));
            $gids = array_keys($f['used']);
            sort($gids);
            $w = '';
            foreach ($gids as $g) $w .= $g . ' [' . (int) round(($f['widths'][$g] ?? 0) * $scale) . '] ';
            $cid = $add(sprintf('<< /Type /Font /Subtype /CIDFontType2 /BaseFont /%s /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor %d 0 R /DW 600 /W [%s] /CIDToGIDMap /Identity >>', $name, $desc, trim($w)));
            $toUni = $add($stream('', self::toUnicode($f['uni'] ?? [])));
            $font = $add(sprintf('<< /Type /Font /Subtype /Type0 /BaseFont /%s /Encoding /Identity-H /DescendantFonts [%d 0 R] /ToUnicode %d 0 R >>', $name, $cid, $toUni));
            $fontRefs .= '/F' . $f['n'] . ' ' . $font . ' 0 R ';
        }
        $imgRefs = '';
        foreach ($this->images as $im) {
            $id = $add($stream(sprintf(' /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /%s /BitsPerComponent 8 /Filter /DCTDecode', $im['w'], $im['h'], $im['cs']) . ($im['cs'] === 'DeviceCMYK' ? ' /Decode [1 0 1 0 1 0 1 0]' : ''), $im['data']));
            $imgRefs .= '/I' . $im['n'] . ' ' . $id . ' 0 R ';
        }
        $res = $add('<< /Font << ' . $fontRefs . '>> /XObject << ' . $imgRefs . '>> /ProcSet [/PDF /Text /ImageC] >>');
        $kids = [];
        foreach ($this->pages as $content) {
            $c = $add($stream(' /Filter /FlateDecode', gzcompress($content, 6)));
            $kids[] = $add(sprintf('<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %s %s] /Resources %d 0 R /Contents %d 0 R >>', $pagesId, self::n(self::W), self::n(self::H), $res, $c));
        }
        $objs[$pagesId - 1] = '<< /Type /Pages /Kids [' . implode(' ', array_map(static fn ($k) => "$k 0 R", $kids)) . '] /Count ' . count($kids) . ' >>';
        $objs[$catalog - 1] = '<< /Type /Catalog /Pages ' . $pagesId . ' 0 R >>';
        $info = '';
        foreach ($this->info as $k => $v) $info .= '/' . $k . ' <FEFF' . strtoupper(bin2hex(mb_convert_encoding($v, 'UTF-16BE', 'UTF-8'))) . '> ';
        $infoId = $add('<< ' . $info . '/CreationDate (D:' . gmdate('YmdHis') . "Z) >>");

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objs as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $o) $pdf .= sprintf("%010d 00000 n \n", $o);
        $pdf .= 'trailer' . "\n<< /Size " . (count($objs) + 1) . ' /Root ' . $catalog . ' 0 R /Info ' . $infoId . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
        return $pdf;
    }

    /** CMap, щоб текст з PDF можна було копіювати й шукати. */
    private static function toUnicode(array $map): string
    {
        $lines = [];
        foreach ($map as $gid => $u) {
            if ($u > 0xFFFF) continue;
            $lines[] = sprintf('<%04X> <%04X>', $gid, $u);
        }
        $body = '';
        foreach (array_chunk($lines, 100) as $chunk) $body .= count($chunk) . " beginbfchar\n" . implode("\n", $chunk) . "\nendbfchar\n";
        return "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n"
            . "1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n" . $body . "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";
    }
}

/**
 * Читання шрифту TrueType (метрики, таблиця символів) і створення підмножини гліфів для вбудовування в PDF.
 * Номери гліфів не змінюються: невикористані гліфи просто стають порожніми.
 */
final class TrueType
{
    public static function load(string $path): array
    {
        $cacheFile = YW_ROOT . '/storage/cache/font-' . md5($path . '|' . @filemtime($path)) . '.php';
        $meta = is_file($cacheFile) ? @include $cacheFile : null;
        $data = @file_get_contents($path);
        if ($data === false) throw new \RuntimeException("Шрифт не знайдено: $path");
        if (!is_array($meta)) {
            $meta = self::parse($data);
            @file_put_contents($cacheFile, '<?php return ' . var_export($meta, true) . ';', LOCK_EX);
        }
        return $meta + ['data' => $data];
    }

    private static function tables(string $d): array
    {
        $num = unpack('n', $d, 4)[1];
        $t = [];
        for ($i = 0; $i < $num; $i++) {
            $r = unpack('a4tag/Nsum/Noff/Nlen', $d, 12 + $i * 16);
            $t[$r['tag']] = [$r['off'], $r['len']];
        }
        return $t;
    }

    private static function parse(string $d): array
    {
        $t = self::tables($d);
        foreach (['head', 'hhea', 'hmtx', 'maxp', 'cmap', 'loca', 'glyf'] as $need) if (!isset($t[$need])) throw new \RuntimeException("У шрифті немає таблиці $need");
        $s16 = static fn (int $o) => ($v = unpack('n', $d, $o)[1]) >= 0x8000 ? $v - 0x10000 : $v;
        $u16 = static fn (int $o) => unpack('n', $d, $o)[1];
        $head = $t['head'][0];
        $upm = $u16($head + 18);
        $bbox = [$s16($head + 36), $s16($head + 38), $s16($head + 40), $s16($head + 42)];
        $hhea = $t['hhea'][0];
        $ascent = $s16($hhea + 4); $descent = $s16($hhea + 6);
        $nh = $u16($hhea + 34);
        $numGlyphs = $u16($t['maxp'][0] + 4);
        $widths = [];
        for ($i = 0; $i < $nh; $i++) $widths[$i] = $u16($t['hmtx'][0] + $i * 4);
        for ($i = $nh; $i < $numGlyphs; $i++) $widths[$i] = $widths[$nh - 1];
        $capHeight = $ascent;
        if (isset($t['OS/2']) && $t['OS/2'][1] >= 90 && $u16($t['OS/2'][0]) >= 2) $capHeight = $s16($t['OS/2'][0] + 88);

        // cmap: формат 4 (Unicode BMP, платформа 3/1 або 0/x)
        $cm = $t['cmap'][0];
        $n = $u16($cm + 2);
        $sub = null;
        for ($i = 0; $i < $n; $i++) {
            $pid = $u16($cm + 4 + $i * 8); $eid = $u16($cm + 6 + $i * 8); $off = unpack('N', $d, $cm + 8 + $i * 8)[1];
            if ($u16($cm + $off) === 4 && ($pid === 3 && $eid === 1 || $pid === 0)) { $sub = $cm + $off; if ($pid === 3) break; }
        }
        if ($sub === null) throw new \RuntimeException('У шрифті немає таблиці символів Unicode');
        $segX2 = $u16($sub + 6);
        $ends = $sub + 14; $starts = $ends + $segX2 + 2; $deltas = $starts + $segX2; $ranges = $deltas + $segX2;
        $cmap = [];
        for ($i = 0; $i < $segX2 / 2; $i++) {
            $end = $u16($ends + $i * 2); $start = $u16($starts + $i * 2); $delta = $s16($deltas + $i * 2); $ro = $u16($ranges + $i * 2);
            if ($start === 0xFFFF) break;
            // Зберігаємо лише потрібні для резюме діапазони: латиниця, кирилиця, пунктуація, символи валют.
            for ($c = $start; $c <= $end; $c++) {
                if (!($c < 0x250 || ($c >= 0x400 && $c < 0x530) || ($c >= 0x2000 && $c < 0x2200))) continue;
                if ($ro === 0) $g = ($c + $delta) & 0xFFFF;
                else { $g = $u16($ranges + $i * 2 + $ro + ($c - $start) * 2); if ($g !== 0) $g = ($g + $delta) & 0xFFFF; }
                if ($g) $cmap[$c] = $g;
            }
        }
        $name = 'Font';
        if (isset($t['name'])) {
            $nm = $t['name'][0]; $cnt = $u16($nm + 2); $so = $nm + $u16($nm + 4);
            for ($i = 0; $i < $cnt; $i++) {
                $r = $nm + 6 + $i * 12;
                if ($u16($r + 6) === 6) { $raw = substr($d, $so + $u16($r + 10), $u16($r + 8)); $name = $u16($r) === 3 ? mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE') : $raw; break; }
            }
        }
        return ['name' => preg_replace('/[^A-Za-z0-9-]/', '', $name) ?: 'Font', 'upm' => $upm, 'bbox' => $bbox, 'ascent' => $ascent, 'descent' => $descent,
            'capHeight' => $capHeight, 'widths' => $widths, 'cmap' => $cmap, 'numGlyphs' => $numGlyphs];
    }

    /** @param list<int> $gids */
    public static function subset(array $f, array $gids): string
    {
        $d = $f['data'];
        $t = self::tables($d);
        $u16 = static fn (int $o) => unpack('n', $d, $o)[1];
        $longLoca = $u16($t['head'][0] + 50) === 1;
        $n = $f['numGlyphs'];
        $loca = [];
        for ($i = 0; $i <= $n; $i++) $loca[$i] = $longLoca ? unpack('N', $d, $t['loca'][0] + $i * 4)[1] : $u16($t['loca'][0] + $i * 2) * 2;
        $glyf = $t['glyf'][0];

        // Складені гліфи (напр. «й», «ї») посилаються на інші — додаємо їх теж.
        $want = array_fill_keys($gids, true);
        $queue = $gids;
        while ($queue) {
            $g = array_pop($queue);
            if ($g >= $n || $loca[$g + 1] <= $loca[$g]) continue;
            $p = $glyf + $loca[$g];
            if (unpack('n', $d, $p)[1] < 0x8000) continue; // простий гліф
            $p += 10;
            do {
                $flags = $u16($p); $comp = $u16($p + 2);
                if (!isset($want[$comp])) { $want[$comp] = true; $queue[] = $comp; }
                $p += 4 + (($flags & 1) ? 4 : 2) + (($flags & 8) ? 2 : (($flags & 0x40) ? 4 : (($flags & 0x80) ? 8 : 0)));
            } while ($flags & 0x20);
        }

        $newGlyf = '';
        $newLoca = '';
        for ($g = 0; $g < $n; $g++) {
            $newLoca .= pack('N', strlen($newGlyf));
            if (isset($want[$g]) && $loca[$g + 1] > $loca[$g]) {
                $newGlyf .= substr($d, $glyf + $loca[$g], $loca[$g + 1] - $loca[$g]);
                $newGlyf .= str_repeat("\0", (4 - strlen($newGlyf) % 4) % 4);
            }
        }
        $newLoca .= pack('N', strlen($newGlyf));

        $out = [];
        foreach (['cvt ', 'fpgm', 'prep', 'hhea', 'hmtx', 'maxp'] as $tag) if (isset($t[$tag])) $out[$tag] = substr($d, $t[$tag][0], $t[$tag][1]);
        $head = substr($d, $t['head'][0], $t['head'][1]);
        $head = substr_replace($head, "\0\0\0\0", 8, 4);        // checkSumAdjustment
        $head = substr_replace($head, pack('n', 1), 50, 2);    // indexToLocFormat = long
        $out['head'] = $head;
        $out['loca'] = $newLoca;
        $out['glyf'] = $newGlyf;
        ksort($out, SORT_STRING);

        $num = count($out);
        $es = (int) floor(log($num, 2));
        $sr = (2 ** $es) * 16;
        $file = pack('Nnnnn', 0x00010000, $num, $sr, $es, $num * 16 - $sr);
        $offset = 12 + $num * 16;
        $dir = '';
        $body = '';
        foreach ($out as $tag => $data) {
            $dir .= $tag . pack('NNN', self::checksum($data), $offset, strlen($data));
            $padded = $data . str_repeat("\0", (4 - strlen($data) % 4) % 4);
            $body .= $padded;
            $offset += strlen($padded);
        }
        $font = $file . $dir . $body;
        // Контрольна сума всього файлу → head.checkSumAdjustment
        $adj = (0xB1B0AFBA - self::checksum($font)) & 0xFFFFFFFF;
        $headPos = 12 + $num * 16;
        foreach ($out as $tag => $data) { if ($tag === 'head') break; $headPos += strlen($data) + (4 - strlen($data) % 4) % 4; }
        return substr_replace($font, pack('N', $adj), $headPos + 8, 4);
    }

    private static function checksum(string $data): int
    {
        $data .= str_repeat("\0", (4 - strlen($data) % 4) % 4);
        $sum = 0;
        foreach (unpack('N*', $data) as $v) $sum = ($sum + $v) & 0xFFFFFFFF;
        return $sum;
    }
}
