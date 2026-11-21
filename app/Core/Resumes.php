<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Профіль шукача й резюме: завантаження, права перегляду, PDF.
 *
 * Хто що бачить у резюме:
 *  - власник і модератори — усе;
 *  - роботодавець, якому людина відгукнулася з резюме, — усе, контакти теж (він їх і так отримує з відгуком);
 *  - користувач із профілем роботодавця — опубліковане резюме; контакти лише з галочкою «показувати контакти»;
 *  - гості та користувачі без профілю роботодавця — лише посаду, місто, графік і зарплату.
 * Фото бачать лише за галочки «показувати фото» (власник і модератори — завжди).
 */
final class Resumes
{
    public const MAX_EXPERIENCE = 10;
    public const MAX_EDUCATION = 5;
    public const DRIVING = ['A', 'B', 'C', 'D', 'E'];
    public const STATUS = ['draft' => ['Чернетка', 'muted'], 'pending' => ['На модерації', 'warn'], 'published' => ['Опубліковано', 'ok'], 'rejected' => ['Відхилено', 'bad']];

    public static function profile(int $uid): array
    {
        $p = App::db()->one('SELECT p.*, s.name AS settlement FROM {p}profiles p LEFT JOIN {p}settlements s ON s.id = p.settlement_id WHERE p.user_id = ?', [$uid]);
        return $p ?: ['user_id' => $uid, 'full_name' => null, 'birth_year' => null, 'settlement_id' => null, 'settlement' => null, 'photo_file' => null, 'photo_updated_at' => null, 'show_photo' => 0, 'show_contacts' => 0];
    }

    public static function saveProfile(int $uid, array $set): void
    {
        $db = App::db();
        if ($db->value('SELECT 1 FROM {p}profiles WHERE user_id = ?', [$uid])) $db->update('profiles', [...$set, 'updated_at' => Db::now()], 'user_id = ?', [$uid]);
        else $db->insert('profiles', [...$set, 'user_id' => $uid]);
    }

    public static function ofUser(int $uid): ?array
    {
        return App::db()->one('SELECT * FROM {p}resumes WHERE user_id = ? AND deleted_at IS NULL', [$uid]);
    }

    /** Резюме разом із профілем і даними користувача. */
    public static function find(int $id): ?array
    {
        $r = App::db()->one('SELECT r.*, c.name AS category, u.display_name, u.phone_enc, u.email_enc, u.status AS user_status
            FROM {p}resumes r JOIN {p}users u ON u.id = r.user_id LEFT JOIN {p}job_categories c ON c.id = r.category_id
            WHERE r.id = ? AND r.deleted_at IS NULL AND u.deleted_at IS NULL', [$id]);
        return $r ? self::hydrate($r) : null;
    }

    public static function hydrate(array $r): array
    {
        $r['experience'] = self::decodeList($r['experience'] ?? null);
        $r['education'] = self::decodeList($r['education'] ?? null);
        $r['profile'] = self::profile((int) $r['user_id']);
        $r['name'] = $r['profile']['full_name'] ?: ($r['display_name'] ?? '');
        return $r;
    }

    public static function decodeList(?string $json): array
    {
        $v = $json ? json_decode($json, true) : null;
        return is_array($v) ? array_values(array_filter($v, 'is_array')) : [];
    }

    public static function isPublic(array $r): bool
    {
        return $r['status'] === 'published' && !(int) $r['is_hidden'] && ($r['user_status'] ?? 'active') === 'active';
    }

    public static function employerOf(int $uid): ?array
    {
        return App::db()->one('SELECT * FROM {p}employers WHERE owner_id = ? AND deleted_at IS NULL ORDER BY id LIMIT 1', [$uid]);
    }

    /** Чи відгукувалася людина з цим резюме на вакансію цього роботодавця. */
    public static function appliedTo(array $r, int $viewerId): bool
    {
        return (bool) App::db()->value('SELECT 1 FROM {p}applications a JOIN {p}vacancies v ON v.id = a.vacancy_id JOIN {p}employers e ON e.id = v.employer_id
            WHERE a.user_id = ? AND a.resume_id IS NOT NULL AND e.owner_id = ? LIMIT 1', [$r['user_id'], $viewerId]);
    }

    /**
     * Що може бачити поточний відвідувач.
     * @return array{level:string, contacts:bool, photo:bool, reason:string}  level: none | preview | full
     */
    public static function access(array $r, ?array $viewer): array
    {
        $prof = $r['profile'];
        $hasPhoto = (bool) $prof['photo_file'];
        $isOwner = $viewer && (int) $viewer['id'] === (int) $r['user_id'];
        if ($isOwner) return ['level' => 'full', 'contacts' => true, 'photo' => $hasPhoto, 'reason' => 'owner'];
        if ($viewer && App::auth()->can('jobs.listing:moderate')) return ['level' => 'full', 'contacts' => (bool) $prof['show_contacts'], 'photo' => $hasPhoto, 'reason' => 'moderator'];
        if ($viewer && self::appliedTo($r, (int) $viewer['id'])) return ['level' => 'full', 'contacts' => true, 'photo' => $hasPhoto && $prof['show_photo'], 'reason' => 'applicant'];
        if (!self::isPublic($r)) return ['level' => 'none', 'contacts' => false, 'photo' => false, 'reason' => 'hidden'];
        if ($viewer && self::employerOf((int) $viewer['id'])) return ['level' => 'full', 'contacts' => (bool) $prof['show_contacts'], 'photo' => $hasPhoto && $prof['show_photo'], 'reason' => 'employer'];
        return ['level' => 'preview', 'contacts' => false, 'photo' => false, 'reason' => $viewer ? 'no_employer' : 'guest'];
    }

    public static function contacts(array $r): array
    {
        return ['phone' => Crypto::decrypt($r['phone_enc'] ?? null), 'email' => Crypto::decrypt($r['email_enc'] ?? null)];
    }

    public static function age(?int $birthYear): ?string
    {
        if (!$birthYear) return null;
        $a = (int) date('Y') - $birthYear;
        return $a > 0 && $a < 100 ? plural($a, 'рік', 'роки', 'років') : null;
    }

    public static function photoUrl(array $profile): string
    {
        return url('/photo/' . (int) $profile['user_id'], ['v' => substr(md5((string) $profile['photo_file']), 0, 8)]);
    }

    /** Ім'я файлу: Resume_Ivan_Petrenko.pdf з транслітерацією (деякі поштові клієнти погано показують кирилицю в назвах). */
    public static function fileName(array $r): string
    {
        $tr = ['а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'h', 'ґ' => 'g', 'д' => 'd', 'е' => 'e', 'є' => 'ie', 'ж' => 'zh', 'з' => 'z', 'и' => 'y', 'і' => 'i', 'ї' => 'i', 'й' => 'i',
            'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'shch', 'ь' => '', 'ю' => 'iu', 'я' => 'ia', 'ы' => 'y', 'э' => 'e', 'ё' => 'io', 'ъ' => ''];
        $s = '';
        foreach (mb_str_split(mb_strtolower($r['name'] ?: 'kandydat')) as $ch) $s .= $tr[$ch] ?? $ch;
        $s = trim(preg_replace('/[^a-z0-9]+/', '_', $s) ?? '', '_');
        return 'Rezume_' . (implode('_', array_map('ucfirst', explode('_', $s))) ?: 'Kandydat') . '.pdf';
    }

    /* ---------------- PDF ---------------- */

    /**
     * @param bool $withContacts показувати телефон і email
     * @param bool $withPhoto вставити фото (лише якщо людина дозволила)
     */
    public static function pdf(array $r, bool $withContacts, bool $withPhoto): string
    {
        $pdf = new Pdf();
        $pdf->setInfo('Резюме: ' . $r['name'] . ' — ' . $r['title'], (string) App::setting('site.name', 'YEWORK'));
        $pdf->addFont('r', yw_file('app/Fonts/DejaVuSans.ttf'));
        $pdf->addFont('b', yw_file('app/Fonts/DejaVuSans-Bold.ttf'));
        $L = 48.0; $R = Pdf::W - 48; $W = $R - $L;
        $ink = [20, 22, 28]; $muted = [100, 106, 118]; $accent = [37, 99, 235]; $rule = [222, 226, 232];
        $prof = $r['profile'];
        $site = (string) App::setting('site.name', 'YEWORK');
        $siteUrl = (string) App::setting('site.url', '');

        $y = 0.0;
        $footer = function () use ($pdf, $L, $R, $muted, $rule, $site, $siteUrl) {
            $pdf->line($L, Pdf::H - 40, $R, Pdf::H - 40, $rule);
            $pdf->setFont('r', 8);
            $pdf->text($L, Pdf::H - 28, 'Резюме створено на ' . $site . ($siteUrl !== '' ? ' · ' . preg_replace('#^https?://#', '', $siteUrl) : '') . ' · ' . date('d.m.Y'), $muted);
            $pdf->text($R - $pdf->textWidth('стор. ' . $pdf->pageCount()), Pdf::H - 28, 'стор. ' . $pdf->pageCount(), $muted);
        };
        $newPage = function () use ($pdf, &$y, $footer) { $pdf->addPage(); $footer(); $y = 56.0; };
        $need = function (float $h) use (&$y, $newPage) { if ($y + $h > Pdf::H - 60) $newPage(); };
        $para = function (string $text, string $font, float $size, array $color, float $x, float $w, float $lh) use ($pdf, &$y, $need) {
            $pdf->setFont($font, $size);
            foreach ($pdf->wrap($text, $w) as $line) { $need($lh); $pdf->text($x, $y + $size, $line, $color); $y += $lh; }
        };
        $section = function (string $title) use ($pdf, &$y, $need, $L, $R, $accent, $rule) {
            $need(48);
            $y += 14;
            $pdf->setFont('b', 12.5);
            $pdf->text($L, $y + 12, mb_strtoupper($title), $accent);
            $y += 20;
            $pdf->line($L, $y, $R, $y, $rule);
            $y += 8;
        };

        $newPage();
        // Шапка: фото праворуч, ім'я й посада ліворуч.
        $photoPath = $withPhoto ? Photo::path($prof['photo_file']) : null;
        $textW = $photoPath ? $W - 118 : $W;
        if ($photoPath) $pdf->jpeg($photoPath, $R - 102, $y - 6, 102, 136);
        $top = $y;
        $para($r['name'], 'b', 22, $ink, $L, $textW, 27);
        $y += 2;
        $para($r['title'], 'r', 14, $accent, $L, $textW, 18);
        $y += 8;
        $facts = array_filter([
            $prof['settlement'] ?? null,
            self::age($prof['birth_year'] !== null ? (int) $prof['birth_year'] : null),
            EMPLOYMENT_TYPES[$r['employment']] ?? null,
            $r['salary_from'] ? 'від ' . money((int) $r['salary_from']) . ' грн/міс' : null,
        ]);
        $para(implode('  ·  ', $facts), 'r', 10, $muted, $L, $textW, 14);
        if ($withContacts) {
            $c = self::contacts($r);
            $line = implode('  ·  ', array_filter([$c['phone'] ? 'Тел.: ' . phone_pretty($c['phone']) : null, $c['email'] ? 'Email: ' . $c['email'] : null]));
            if ($line !== '') { $y += 2; $para($line, 'b', 10, $ink, $L, $textW, 14); }
        } else {
            $y += 2;
            $para('Контакти доступні через ' . $site . ' (кнопка «Запросити» на сторінці резюме).', 'r', 9, $muted, $L, $textW, 13);
        }
        if ($photoPath) $y = max($y, $top + 136);
        $y += 4;

        if ($r['experience']) {
            $section('Досвід роботи');
            foreach ($r['experience'] as $e) {
                $need(40);
                $period = trim(($e['from'] ?? '') . ((($e['from'] ?? '') !== '' || ($e['to'] ?? '') !== '') ? ' — ' : '') . (($e['to'] ?? '') !== '' ? $e['to'] : (($e['from'] ?? '') !== '' ? 'тепер' : '')));
                $pdf->setFont('r', 9.5);
                $pw = $period !== '' ? $pdf->textWidth($period) : 0;
                $pdf->text($R - $pw, $y + 11, $period, $muted);
                $para((string) ($e['position'] ?? ''), 'b', 11, $ink, $L, $W - $pw - 12, 15);
                if (($e['place'] ?? '') !== '') $para((string) $e['place'], 'r', 10, $muted, $L, $W, 14);
                if (($e['duties'] ?? '') !== '') { $y += 2; $para((string) $e['duties'], 'r', 10, $ink, $L, $W, 14); }
                $y += 8;
            }
        } elseif ($r['no_experience']) {
            $section('Досвід роботи');
            $para('Без досвіду роботи. Готовий(а) навчатися.', 'r', 10.5, $ink, $L, $W, 15);
        }
        if ($r['education']) {
            $section('Освіта');
            foreach ($r['education'] as $e) {
                $need(30);
                $year = (string) ($e['year'] ?? '');
                $pdf->setFont('r', 9.5);
                $yw = $year !== '' ? $pdf->textWidth($year) : 0;
                $pdf->text($R - $yw, $y + 11, $year, $muted);
                $para((string) ($e['place'] ?? ''), 'b', 11, $ink, $L, $W - $yw - 12, 15);
                if (($e['specialty'] ?? '') !== '') $para((string) $e['specialty'], 'r', 10, $muted, $L, $W, 14);
                $y += 6;
            }
        }
        if (trim((string) $r['skills']) !== '' || $r['driving']) {
            $section('Навички');
            if (trim((string) $r['skills']) !== '') $para((string) $r['skills'], 'r', 10.5, $ink, $L, $W, 15);
            if ($r['driving']) { $y += 4; $para('Посвідчення водія: категорії ' . str_replace(',', ', ', $r['driving']), 'r', 10.5, $ink, $L, $W, 15); }
        }
        if (trim((string) $r['about']) !== '') {
            $section('Про себе');
            $para((string) $r['about'], 'r', 10.5, $ink, $L, $W, 15);
        }
        return $pdf->output();
    }
}
