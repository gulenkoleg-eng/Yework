<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Audit;
use App\Core\Crypto;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Messaging;
use App\Core\Photo;
use App\Core\RateLimit;
use App\Core\Request;
use App\Core\Response;
use App\Core\Resumes;
use App\Core\ValidationException;

/** Профіль шукача, резюме в кабінеті, пошук резюме, PDF, фото, запрошення від роботодавців. */
final class ResumeController
{
    public const PAGE = 20;

    private function me(): array { return App::auth()->user(); }

    private function cabPage(string $view, array $data): Response
    {
        $me = $this->me();
        $db = App::db();
        $counts = [
            'vacancies' => (int) $db->value('SELECT COUNT(*) FROM {p}vacancies v JOIN {p}employers e ON e.id = v.employer_id WHERE e.owner_id = ? AND v.deleted_at IS NULL', [$me['id']]),
            'applications' => (int) $db->value('SELECT COUNT(*) FROM {p}applications WHERE user_id = ?', [$me['id']]),
            'orders' => \App\Core\Services::newOrdersFor((int) $me['id']),
        ];
        return new Response(view('cabinet/' . $view, [...$data, 'me' => $me, 'tab' => 'resume', 'counts' => $counts]));
    }

    /* ---------------- профіль шукача (фото, приватність) ---------------- */

    public function personSave(Request $req): Response
    {
        $me = $this->me();
        $uid = (int) $me['id'];
        $prof = Resumes::profile($uid);
        $db = App::db();
        $year = (int) preg_replace('/\D/', '', $req->str('birth_year', 4));
        $set = [
            'full_name' => $req->str('full_name', 120) ?: null,
            'birth_year' => $year ?: null,
            'settlement_id' => ((int) $req->input('settlement_id')) ?: null,
        ];
        $vis = static fn (string $k) => array_key_exists((string) ($req->post[$k] ?? ''), Resumes::VISIBILITY) ? (string) $req->post[$k] : 'employers';
        $set['photo_visibility'] = $vis('photo_visibility');
        $set['phone_visibility'] = $vis('phone_visibility');
        // Старі галочки тримаємо узгодженими (на випадок відкату на 0.4.3).
        $set['show_photo'] = $set['photo_visibility'] !== 'nobody' ? 1 : 0;
        $set['show_contacts'] = $set['phone_visibility'] !== 'nobody' ? 1 : 0;
        $err = [];
        if ($set['full_name'] !== null && mb_strlen($set['full_name']) < 3) $err['full_name'] = 'Вкажіть ім’я та прізвище';
        if ($set['birth_year'] !== null && ($year < (int) date('Y') - 90 || $year > (int) date('Y') - 14)) $err['birth_year'] = 'Перевірте рік народження';
        if ($set['settlement_id'] !== null && !$db->value('SELECT 1 FROM {p}settlements WHERE id = ?', [$set['settlement_id']])) $err['settlement_id'] = 'Оберіть населений пункт';
        if ($err) throw new ValidationException($err);

        $photoChanged = false;
        if (!empty($req->post['photo_delete']) && $prof['photo_file']) {
            Photo::delete($prof['photo_file']);
            $set['photo_file'] = null;
            $set['photo_updated_at'] = null;
            Audit::log('profile.photo_deleted', 'user', $uid);
        } elseif (isset($_FILES['photo']) && (int) ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            RateLimit::hit('photo:' . $uid, 20, 86400, 'Забагато завантажень фото за добу. Спробуйте завтра.');
            $set['photo_file'] = Photo::store($_FILES['photo']);
            $set['photo_updated_at'] = Db::now();
            Photo::delete($prof['photo_file']);
            $photoChanged = true;
            Audit::log('profile.photo_uploaded', 'user', $uid);
        }
        Resumes::saveProfile($uid, $set);

        // Нове фото в опублікованому резюме має побачити модератор.
        $r = Resumes::ofUser($uid);
        if ($photoChanged && $r && $r['status'] === 'published') {
            $db->update('resumes', ['status' => 'pending', 'submitted_at' => Db::now()], 'id = ?', [$r['id']]);
            Audit::log('resume.submitted', 'resume', $r['id'], ['photo' => true]);
            flash('ok', 'Профіль збережено. Резюме з новим фото повернеться в пошук після перевірки модератором.');
        } else {
            flash('ok', 'Профіль збережено.');
        }
        $back = $req->str('back', 20) === 'resume' ? '/cabinet/resume' : '/cabinet';
        return Response::redirect(url($back) . '#person');
    }

    /** Фото профілю. Віддається лише тим, кому людина дозволила його бачити. */
    public function photo(Request $req): Response
    {
        $uid = (int) $req->params['id'];
        $me = App::auth()->user();
        $prof = Resumes::profile($uid);
        $path = Photo::path($prof['photo_file']);
        if (!$path) throw new HttpException(404, 'Фото не знайдено.');
        $ok = $me && (int) $me['id'] === $uid;
        if (!$ok) {
            $rid = (int) App::db()->value('SELECT id FROM {p}resumes WHERE user_id = ? AND deleted_at IS NULL', [$uid]);
            $r = $rid ? Resumes::find($rid) : null;
            $ok = $r && Resumes::access($r, $me)['photo'];
        }
        if (!$ok) throw new HttpException(404, 'Фото не знайдено.');
        return (new Response((string) file_get_contents($path)))
            ->header('Content-Type', 'image/jpeg')
            ->header('Cache-Control', 'private, max-age=86400');
    }

    /* ---------------- резюме в кабінеті ---------------- */

    public function form(Request $req): Response
    {
        $me = $this->me();
        $uid = (int) $me['id'];
        $r = Resumes::ofUser($uid);
        if ($r) $r = Resumes::hydrate($r + ['display_name' => $me['display_name']]);
        [$settlements, $categories] = SiteController::reference();
        $invites = [];
        if ($r) {
            $invites = App::db()->all('SELECT i.*, e.name AS employer, e.is_verified, v.title AS vacancy_title, v.status AS vacancy_status
                FROM {p}resume_invites i JOIN {p}employers e ON e.id = i.employer_id LEFT JOIN {p}vacancies v ON v.id = i.vacancy_id
                WHERE i.resume_id = ? ORDER BY i.id DESC LIMIT 50', [$r['id']]);
            App::db()->run("UPDATE {p}resume_invites SET status = 'seen' WHERE resume_id = ? AND status = 'new'", [$r['id']]);
        }
        return $this->cabPage('resume', [
            'title' => 'Моє резюме', 'r' => $r, 'person' => Resumes::profile($uid), 'settlements' => $settlements, 'categories' => $categories,
            'invites' => $invites, 'phone' => Crypto::decrypt($me['phone_enc']), 'email' => Crypto::decrypt($me['email_enc']),
        ]);
    }

    public function save(Request $req): Response
    {
        $me = $this->me();
        $uid = (int) $me['id'];
        $db = App::db();
        $cur = Resumes::ofUser($uid);
        $num = static fn ($v) => ($v = preg_replace('/\D/', '', (string) $v)) === '' ? null : min((int) $v, 10_000_000);

        $exp = [];
        foreach (array_slice((array) ($req->post['exp'] ?? []), 0, Resumes::MAX_EXPERIENCE) as $e) {
            if (!is_array($e)) continue;
            $row = [];
            foreach (['place' => 120, 'position' => 120, 'from' => 20, 'to' => 20, 'duties' => 2000] as $k => $max) $row[$k] = mb_substr(trim((string) ($e[$k] ?? '')), 0, $max);
            if ($row['place'] !== '' || $row['position'] !== '') $exp[] = $row;
        }
        $edu = [];
        foreach (array_slice((array) ($req->post['edu'] ?? []), 0, Resumes::MAX_EDUCATION) as $e) {
            if (!is_array($e)) continue;
            $row = [];
            foreach (['place' => 160, 'specialty' => 120, 'year' => 20] as $k => $max) $row[$k] = mb_substr(trim((string) ($e[$k] ?? '')), 0, $max);
            if ($row['place'] !== '') $edu[] = $row;
        }
        $driving = array_values(array_intersect(Resumes::DRIVING, array_map('strval', (array) ($req->post['driving'] ?? []))));
        $d = [
            'title' => $req->str('title', 120), 'category_id' => ((int) $req->input('category_id')) ?: null,
            'employment' => $req->str('employment', 12), 'salary_from' => $num($req->input('salary_from')),
            'experience' => $exp ? json_encode($exp, JSON_UNESCAPED_UNICODE) : null,
            'education' => $edu ? json_encode($edu, JSON_UNESCAPED_UNICODE) : null,
            'skills' => $req->str('skills', 3000) ?: null, 'about' => $req->str('about', 3000) ?: null,
            'driving' => $driving ? implode(',', $driving) : null,
            'no_experience' => !empty($req->post['no_experience']) ? 1 : 0,
        ];
        $submit = ($req->post['action'] ?? '') === 'submit';
        $err = [];
        if (mb_strlen($d['title']) < 3) $err['title'] = 'Вкажіть бажану посаду';
        if ($d['category_id'] !== null && !$db->value('SELECT 1 FROM {p}job_categories WHERE id = ? AND is_active = 1', [$d['category_id']])) $err['category_id'] = 'Оберіть сферу';
        if (!array_key_exists($d['employment'], EMPLOYMENT_TYPES)) $err['employment'] = 'Оберіть графік';
        if ($submit) {
            $prof = Resumes::profile($uid);
            if (!$prof['full_name']) $err['full_name'] = 'Щоб опублікувати резюме, вкажіть ім’я та прізвище в блоці «Мій профіль» вище';
            if (!$prof['settlement_id']) $err['settlement_id'] = 'Щоб опублікувати резюме, вкажіть населений пункт у блоці «Мій профіль» вище';
            if (!$exp && !$d['no_experience']) $err['exp'] = 'Додайте хоча б одне місце роботи або позначте «Шукаю першу роботу»';
        }
        if ($err) throw new ValidationException($err, isset($err['full_name']) || isset($err['settlement_id']) ? 'Заповніть профіль і перевірте виділені поля.' : 'Перевірте виділені поля.');

        if ($cur) {
            $db->update('resumes', [...$d, 'updated_at' => Db::now()], 'id = ?', [$cur['id']]);
            $db->run('UPDATE {p}resumes SET version = version + 1 WHERE id = ?', [$cur['id']]);
            $id = (int) $cur['id'];
            // Зміни в опублікованому резюме теж перевіряє модератор; до перевірки воно не показується в пошуку.
            if (!$submit && in_array($cur['status'], ['published', 'pending'], true)) $submit = true;
        } else {
            $id = $db->insert('resumes', [...$d, 'user_id' => $uid, 'status' => 'draft']);
            Audit::log('resume.created', 'resume', $id);
        }
        if ($submit) {
            if (!$cur || $cur['status'] !== 'pending') {
                $db->update('resumes', ['status' => 'pending', 'submitted_at' => Db::now(), 'reject_reason' => null], 'id = ?', [$id]);
                Audit::log('resume.submitted', 'resume', $id);
            }
            flash('ok', 'Резюме надіслано на перевірку. Після схвалення його побачать роботодавці.');
        } else {
            flash('ok', 'Чернетку резюме збережено.');
        }
        return Response::redirect(url('/cabinet/resume'));
    }

    public function visibility(Request $req): Response
    {
        $r = Resumes::ofUser((int) $this->me()['id']);
        if (!$r) throw new HttpException(404, 'Резюме ще не створено.');
        $hide = ($req->post['hide'] ?? '') === '1';
        App::db()->update('resumes', ['is_hidden' => $hide ? 1 : 0, 'updated_at' => Db::now()], 'id = ?', [$r['id']]);
        Audit::log($hide ? 'resume.hidden' : 'resume.shown', 'resume', $r['id']);
        flash('ok', $hide ? 'Резюме приховано з пошуку. Відгуки з резюме, які ви вже надіслали, роботодавці бачать і далі.' : 'Резюме знову показується в пошуку.');
        return Response::redirect(url('/cabinet/resume'));
    }

    public function delete(Request $req): Response
    {
        $r = Resumes::ofUser((int) $this->me()['id']);
        if (!$r) throw new HttpException(404, 'Резюме ще не створено.');
        // Видаляємо повністю, щоб людина могла почати заново (унікальний user_id).
        App::db()->run('DELETE FROM {p}resumes WHERE id = ?', [$r['id']]);
        App::db()->run('UPDATE {p}applications SET resume_id = NULL WHERE resume_id = ?', [$r['id']]);
        Audit::log('resume.deleted', 'resume', $r['id']);
        flash('ok', 'Резюме видалено. Профіль і фото збережено.');
        return Response::redirect(url('/cabinet/resume'));
    }

    public function myPdf(Request $req): Response
    {
        $me = $this->me();
        $row = Resumes::ofUser((int) $me['id']);
        if (!$row) throw new HttpException(404, 'Спочатку створіть резюме.');
        $r = Resumes::find((int) $row['id']);
        return self::pdfResponse($r, true, true, Resumes::vis($r['profile'], 'photo') !== 'nobody', ($req->query['dl'] ?? '') === '1');
    }

    private static function pdfResponse(array $r, bool $phone, bool $email, bool $photo, bool $download): Response
    {
        $name = Resumes::fileName($r);
        return (new Response(Resumes::pdf($r, $phone, $email, $photo)))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', ($download ? 'attachment' : 'inline') . '; filename="' . $name . '"')
            ->header('Cache-Control', 'private, no-store');
    }

    /* ---------------- публічний пошук резюме ---------------- */

    public function index(Request $req): Response
    {
        $db = App::db();
        [$settlements, $categories] = SiteController::reference();
        $f = [
            'q' => mb_substr(trim((string) ($req->query['q'] ?? '')), 0, 100),
            'settlement' => (int) ($req->query['settlement'] ?? 0),
            'category' => (int) ($req->query['category'] ?? 0),
            'employment' => array_key_exists((string) ($req->query['employment'] ?? ''), EMPLOYMENT_TYPES) ? (string) $req->query['employment'] : '',
            'salary' => max(0, (int) ($req->query['salary'] ?? 0)),
            'driving' => in_array((string) ($req->query['driving'] ?? ''), Resumes::DRIVING, true) ? (string) $req->query['driving'] : '',
        ];
        $where = ["r.status = 'published'", 'r.is_hidden = 0', 'r.deleted_at IS NULL', "u.status = 'active'", 'u.deleted_at IS NULL'];
        $p = [];
        if ($f['q'] !== '') { $where[] = '(r.title LIKE ? OR r.skills LIKE ? OR r.experience LIKE ?)'; $like = '%' . addcslashes($f['q'], '%_\\') . '%'; array_push($p, $like, $like, $like); }
        if ($f['settlement']) { $where[] = 'pr.settlement_id = ?'; $p[] = $f['settlement']; }
        if ($f['category']) { $where[] = 'r.category_id = ?'; $p[] = $f['category']; }
        if ($f['employment'] !== '') { $where[] = 'r.employment = ?'; $p[] = $f['employment']; }
        if ($f['salary']) { $where[] = '(r.salary_from IS NULL OR r.salary_from <= ?)'; $p[] = $f['salary']; }
        if ($f['driving'] !== '') { $where[] = 'FIND_IN_SET(?, r.driving) > 0'; $p[] = $f['driving']; }
        $from = ' FROM {p}resumes r JOIN {p}users u ON u.id = r.user_id LEFT JOIN {p}profiles pr ON pr.user_id = r.user_id LEFT JOIN {p}settlements s ON s.id = pr.settlement_id LEFT JOIN {p}job_categories c ON c.id = r.category_id WHERE ' . implode(' AND ', $where);
        $total = (int) $db->value('SELECT COUNT(*)' . $from, $p);
        $page = max(1, min((int) ($req->query['page'] ?? 1), max(1, (int) ceil($total / self::PAGE))));
        $items = $db->all('SELECT r.id, r.user_id, r.title, r.employment, r.salary_from, r.experience, r.no_experience, r.published_at, r.driving, u.display_name,
            pr.full_name, pr.birth_year, pr.photo_file, pr.show_photo, pr.show_contacts, pr.photo_visibility, pr.phone_visibility, s.name AS settlement, c.name AS category' . $from
            . ' ORDER BY r.published_at DESC LIMIT ' . self::PAGE . ' OFFSET ' . (($page - 1) * self::PAGE), $p);
        $me = App::auth()->user();
        $isEmployer = $me && Resumes::employerOf((int) $me['id']);
        return new Response(view('site/resumes', ['f' => $f, 'items' => $items, 'total' => $total, 'page' => $page, 'settlements' => $settlements,
            'categories' => $categories, 'isEmployer' => (bool) $isEmployer, 'me' => $me, 'title' => 'Резюме']));
    }

    private function load(Request $req): array
    {
        $r = Resumes::find((int) $req->params['id']);
        if (!$r) throw new HttpException(404, 'Резюме не знайдено або його приховано.');
        $acc = Resumes::access($r, App::auth()->user());
        if ($acc['level'] === 'none') throw new HttpException(404, 'Резюме не знайдено або його приховано.');
        return [$r, $acc];
    }

    public function show(Request $req): Response
    {
        [$r, $acc] = $this->load($req);
        $me = App::auth()->user();
        $db = App::db();
        if ($acc['level'] === 'full' && in_array($acc['reason'], ['employer', 'applicant', 'public'], true)) $db->run('UPDATE {p}resumes SET views = views + 1 WHERE id = ?', [$r['id']]);
        $employer = $me ? Resumes::employerOf((int) $me['id']) : null;
        $vacancies = $employer ? $db->all("SELECT id, title, contact_phone FROM {p}vacancies WHERE employer_id = ? AND status = 'published' AND deleted_at IS NULL ORDER BY published_at DESC", [$employer['id']]) : [];
        $lastInvite = $employer ? $db->value('SELECT created_at FROM {p}resume_invites WHERE resume_id = ? AND employer_id = ? ORDER BY id DESC LIMIT 1', [$r['id'], $employer['id']]) : null;
        $myPhone = $me ? Crypto::decrypt($me['phone_enc']) : null;
        return new Response(view('site/resume', [
            'r' => $r, 'acc' => $acc, 'contacts' => $acc['contacts'] ? array_filter(Resumes::contacts($r), static fn ($v, $k) => $acc[$k], ARRAY_FILTER_USE_BOTH) : null, 'me' => $me, 'employer' => $employer,
            'vacancies' => $vacancies, 'lastInvite' => $lastInvite, 'myPhone' => $myPhone,
            'title' => $r['title'] . ($acc['level'] === 'full' ? ' — ' . $r['name'] : ''),
        ]));
    }

    public function pdf(Request $req): Response
    {
        [$r, $acc] = $this->load($req);
        if ($acc['level'] !== 'full') throw new HttpException(403, 'PDF резюме доступний роботодавцям. Заповніть профіль роботодавця в кабінеті.');
        return self::pdfResponse($r, $acc['phone'], $acc['email'], $acc['photo'], ($req->query['dl'] ?? '') === '1');
    }

    /**
     * Номер для кнопки «Показати номер» (лише якщо людина відкрила телефон усім).
     * Окремий запит, щоб номера не було в HTML сторінки і боти не збирали його масово.
     */
    public function phone(Request $req): Response
    {
        RateLimit::hit('phone-reveal:' . $req->ip(), 40, 3600, 'Забагато запитів. Спробуйте пізніше.');
        [$r, $acc] = $this->load($req);
        $phone = $acc['phone'] ? Resumes::contacts($r)['phone'] : null;
        if (!$phone) return Response::json(['message' => 'Номер недоступний.'], 404);
        return Response::json(['phone' => $phone, 'pretty' => phone_pretty($phone)])->header('Cache-Control', 'private, no-store');
    }

    public function invite(Request $req): Response
    {
        // Форма запрошення живе на сторінці резюме, тож помилки показуємо там же.
        try {
            return $this->doInvite($req);
        } catch (ValidationException $e) {
            flash('bad', $e->errors ? implode(' ', $e->errors) : $e->getMessage());
            return Response::redirect(url('/resumes/' . (int) $req->params['id']) . '#invite');
        }
    }

    private function doInvite(Request $req): Response
    {
        [$r, $acc] = $this->load($req);
        $me = App::auth()->user();
        $db = App::db();
        $employer = Resumes::employerOf((int) $me['id']);
        if (!$employer) return Response::redirect(url('/cabinet/employer'));
        if ($employer['status'] !== 'active') throw new HttpException(403, 'Профіль роботодавця заблоковано.');
        if ((int) $r['user_id'] === (int) $me['id']) throw new ValidationException([], 'Це ваше резюме.');
        if (!Resumes::isPublic($r) && $acc['reason'] !== 'applicant') throw new HttpException(404, 'Резюме приховано.');
        $vid = ((int) $req->input('vacancy_id')) ?: null;
        if ($vid && !$db->value("SELECT 1 FROM {p}vacancies WHERE id = ? AND employer_id = ? AND status = 'published' AND deleted_at IS NULL", [$vid, $employer['id']])) $vid = null;
        $msg = $req->str('message', 1500);
        $contact = $req->str('contact', 120);
        $err = [];
        if (mb_strlen($msg) < 10) $err['message'] = 'Напишіть кілька слів: яка робота і чому саме ця людина';
        if (mb_strlen($contact) < 5) $err['contact'] = 'Вкажіть телефон або інший спосіб зв’язатися з вами';
        if ($err) throw new ValidationException($err);
        if ($db->value('SELECT 1 FROM {p}resume_invites WHERE resume_id = ? AND employer_id = ? AND created_at > UTC_TIMESTAMP() - INTERVAL 7 DAY', [$r['id'], $employer['id']])) {
            throw new ValidationException([], 'Ви вже запрошували цю людину протягом тижня. Дочекайтеся відповіді.');
        }
        RateLimit::hit('invite:' . $employer['id'], 30, 86400, 'Ліміт: до 30 запрошень на добу.');
        $id = $db->insert('resume_invites', ['resume_id' => $r['id'], 'employer_id' => $employer['id'], 'vacancy_id' => $vid, 'sender_id' => $me['id'], 'message' => $msg, 'contact' => $contact]);
        Audit::log('resume.invited', 'resume', $r['id'], ['invite' => $id, 'employer' => $employer['id']]);

        // Повідомлення людині: email, а якщо його немає — SMS.
        $site = (string) App::setting('site.name', 'YEWORK');
        $base = rtrim((string) App::setting('site.url', ''), '/');
        $vt = $vid ? (string) $db->value('SELECT title FROM {p}vacancies WHERE id = ?', [$vid]) : null;
        $c = Resumes::contacts($r);
        if ($c['email']) {
            $text = "Вітаємо!\n\nРоботодавець «{$employer['name']}» переглянув ваше резюме «{$r['title']}» на $site і запрошує вас"
                . ($vt ? " на вакансію «{$vt}»" : '') . ".\n\nПовідомлення:\n$msg\n\nЯк зв’язатися: $contact\n\n"
                . "Усі запрошення — у кабінеті: " . $base . url('/cabinet/resume') . "\n\n— $site";
            Messaging::send('email', $c['email'], $text, 'resume_invite', "Запрошення на роботу від «{$employer['name']}»");
        } elseif ($c['phone']) {
            Messaging::send('sms', $c['phone'], "$site: «" . mb_strimwidth($employer['name'], 0, 40, '…') . "» запрошує вас на роботу. Зв’язок: " . mb_substr($contact, 0, 40) . '. Деталі в кабінеті.', 'resume_invite');
        }
        flash('ok', 'Запрошення надіслано. Людина отримає ваше повідомлення й контакт.');
        return Response::redirect(url('/resumes/' . $r['id']));
    }
}
