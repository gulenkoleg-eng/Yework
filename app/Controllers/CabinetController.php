<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Audit;
use App\Core\Crypto;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Media;
use App\Core\Request;
use App\Core\Response;
use App\Core\Stats;
use App\Core\ValidationException;
use App\Core\Vacancies;

/** Особистий кабінет: профіль, пароль, профіль роботодавця, вакансії, відгуки. */
final class CabinetController
{

    private function me(): array { return App::auth()->user(); }

    private function counts(int $uid): array
    {
        $db = App::db();
        return [
            'ads' => (int) ($db->value('SELECT COUNT(*) FROM {p}banners b JOIN {p}employers e ON e.id = b.employer_id WHERE e.owner_id = ? AND b.status IN (\'pending\', \'approved\') AND b.ends_on >= ?', [$uid, date('Y-m-d')]) ?? 0),
            'vacancies' => (int) $db->value('SELECT COUNT(*) FROM {p}vacancies v JOIN {p}employers e ON e.id = v.employer_id WHERE e.owner_id = ? AND v.deleted_at IS NULL', [$uid]),
            'applications' => (int) $db->value('SELECT COUNT(*) FROM {p}applications WHERE user_id = ?', [$uid]),
        ];
    }

    public function page(string $view, string $tab, array $data): Response
    {
        $me = $this->me();
        return new Response(view('cabinet/' . $view, [...$data, 'me' => $me, 'tab' => $tab, 'counts' => $this->counts((int) $me['id'])]));
    }

    public function index(Request $req): Response
    {
        $me = $this->me();
        return $this->page('index', 'profile', [
            'title' => 'Кабінет',
            'phone' => Crypto::decrypt($me['phone_enc']), 'email' => Crypto::decrypt($me['email_enc']),
            'employer' => $this->employer((int) $me['id']),
            'resume' => \App\Core\Resumes::ofUser((int) $me['id']),
        ]);
    }

    public function profile(Request $req): Response
    {
        $name = $req->str('displayName', 80);
        if (mb_strlen($name) < 2) throw new ValidationException(['displayName' => 'Вкажіть ім’я']);
        App::db()->update('users', ['display_name' => $name, 'updated_at' => Db::now()], 'id = ?', [$this->me()['id']]);
        flash('ok', 'Профіль збережено.');
        return Response::redirect(url('/cabinet'));
    }

    public function password(Request $req): Response
    {
        $me = $this->me();
        $cur = (string) ($req->post['current'] ?? '');
        $pw = (string) ($req->post['password'] ?? '');
        $min = $me['is_staff'] ? 12 : AuthController::PASSWORD_MIN;
        if (!Crypto::verifyPassword($cur, $me['password_hash'])) throw new ValidationException(['current' => 'Невірний поточний пароль']);
        if (mb_strlen($pw) < $min || !preg_match('/\p{L}/u', $pw) || !preg_match('/\d/', $pw)) throw new ValidationException(['password' => "Щонайменше $min символів, літери та цифри"]);
        App::db()->update('users', ['password_hash' => Crypto::hashPassword($pw), 'updated_at' => Db::now()], 'id = ?', [$me['id']]);
        App::auth()->revokeAll((int) $me['id'], (int) $me['sid']);
        Audit::log('auth.password_changed', 'user', $me['id']);
        flash('ok', 'Пароль змінено. На інших пристроях потрібно буде увійти знову.');
        return Response::redirect(url('/cabinet'));
    }

    /* ---------------- роботодавець ---------------- */

    public function employer(int $uid): ?array
    {
        return App::db()->one('SELECT * FROM {p}employers WHERE owner_id = ? AND deleted_at IS NULL ORDER BY id LIMIT 1', [$uid]);
    }

    public function employerForm(Request $req): Response
    {
        [$settlements] = SiteController::reference();
        return $this->page('employer', 'employer', ['title' => 'Профіль роботодавця', 'employer' => $this->employer((int) $this->me()['id']), 'settlements' => $settlements, 'next' => (string) ($req->query['next'] ?? '')]);
    }

    public function employerSave(Request $req): Response
    {
        $me = $this->me();
        $form = $req->str('legal_form', 16);
        if ($form !== '' && !array_key_exists($form, LEGAL_FORMS)) $form = 'other';
        $d = [
            'kind' => match ($form) { 'person' => 'person', 'ФОП' => 'fop', default => 'company' },
            'legal_form' => in_array($form, ['', 'person'], true) ? null : $form,
            'name' => $req->str('name', 120), 'edrpou' => preg_replace('/\D/', '', $req->str('edrpou', 12)) ?: null,
            'about' => $req->str('about', 3000), 'settlement_id' => ((int) $req->input('settlement_id')) ?: null,
            'website' => $req->str('website', 200) ?: null,
        ];
        $err = [];
        if (mb_strlen($d['name']) < 2) $err['name'] = 'Вкажіть назву або ім’я';
        if ($d['edrpou'] !== null && !preg_match('/^(\d{8}|\d{10})$/', $d['edrpou'])) $err['edrpou'] = 'ЄДРПОУ — 8 цифр, РНОКПП (ФОП) — 10';
        if ($d['website'] !== null && !preg_match('#^https?://[^\s]+$#', $d['website'])) $err['website'] = 'Адреса має починатися з https://';
        if ($err) throw new ValidationException($err);
        $db = App::db();
        $e = $this->employer((int) $me['id']);
        $logo = Media::store('logo', 'logo', 'e' . ($e['id'] ?? 'new'));
        if ($logo) $d += ['logo' => $logo, 'logo_updated_at' => Db::now()];
        if ($e) {
            $db->update('employers', [...$d, 'updated_at' => Db::now()], 'id = ?', [$e['id']]);
            if ($logo) Media::delete('logo', $e['logo']);
        } else {
            $id = $db->insert('employers', [...$d, 'owner_id' => $me['id']]);
            Audit::log('jobs.employer_created', 'employer', $id);
        }
        if ($logo) Audit::log('jobs.employer_logo', 'employer', $e['id'] ?? $id);
        flash('ok', $logo ? 'Профіль і логотип збережено.' : 'Профіль роботодавця збережено.');
        $next = (string) $req->input('next', '');
        return Response::redirect(url($next === 'vacancy' ? '/cabinet/vacancies/new' : '/cabinet/employer'));
    }

    public function logoDelete(Request $req): Response
    {
        $e = $this->employer((int) $this->me()['id']);
        if ($e && $e['logo']) {
            App::db()->update('employers', ['logo' => null, 'logo_updated_at' => Db::now(), 'updated_at' => Db::now()], 'id = ?', [$e['id']]);
            Media::delete('logo', $e['logo']);
            Audit::log('jobs.employer_logo_removed', 'employer', $e['id']);
            flash('ok', 'Логотип видалено.');
        }
        return Response::redirect(url('/cabinet/employer'));
    }

    /* ---------------- вакансії ---------------- */

    public function vacancies(Request $req): Response
    {
        $me = $this->me();
        $all = App::db()->all("SELECT v.*, s.name AS settlement, (SELECT COUNT(*) FROM {p}applications a WHERE a.vacancy_id = v.id) AS apps
            FROM {p}vacancies v JOIN {p}employers e ON e.id = v.employer_id JOIN {p}settlements s ON s.id = v.settlement_id
            WHERE e.owner_id = ? AND v.deleted_at IS NULL ORDER BY v.updated_at DESC", [$me['id']]);
        $archive = ($req->query['tab'] ?? '') === 'archive';
        $archived = array_values(array_filter($all, static fn ($r) => $r['status'] === 'archived'));
        $active = array_values(array_filter($all, static fn ($r) => $r['status'] !== 'archived'));
        return $this->page('vacancies', 'vacancies', ['title' => 'Мої вакансії', 'rows' => $archive ? $archived : $active, 'archive' => $archive,
            'nActive' => count($active), 'nArchived' => count($archived), 'employer' => $this->employer((int) $me['id'])]);
    }

    private function ownVacancy(int $id): array
    {
        $v = App::db()->one('SELECT v.* FROM {p}vacancies v JOIN {p}employers e ON e.id = v.employer_id WHERE v.id = ? AND e.owner_id = ? AND v.deleted_at IS NULL', [$id, $this->me()['id']]);
        if (!$v) throw new HttpException(404, 'Вакансію не знайдено.');
        return $v;
    }

    public function vacancyForm(Request $req): Response
    {
        $me = $this->me();
        $employer = $this->employer((int) $me['id']);
        if (!$employer) {
            flash('info', 'Спочатку заповніть коротку інформацію про роботодавця — її побачать кандидати.');
            return Response::redirect(url('/cabinet/employer', ['next' => 'vacancy']));
        }
        $v = isset($req->params['id']) ? $this->ownVacancy((int) $req->params['id']) : null;
        [$settlements, $categories] = SiteController::reference();
        return $this->page('vacancy_form', 'vacancies', ['title' => $v ? 'Редагування вакансії' : 'Нова вакансія', 'v' => $v, 'employer' => $employer, 'settlements' => $settlements, 'categories' => $categories,
            'premoderation' => (bool) (App::setting('moderation.premoderation', ['jobs' => true])['jobs'] ?? true)]);
    }

    public function vacancySave(Request $req): Response
    {
        $me = $this->me();
        $db = App::db();
        $employer = $this->employer((int) $me['id']);
        if (!$employer) return Response::redirect(url('/cabinet/employer', ['next' => 'vacancy']));
        if ($employer['status'] !== 'active') throw new HttpException(403, 'Профіль роботодавця заблоковано.');
        $cur = isset($req->params['id']) ? $this->ownVacancy((int) $req->params['id']) : null;

        $num = static fn ($v) => ($v = preg_replace('/\D/', '', (string) $v)) === '' ? null : min((int) $v, 10_000_000);
        $d = [
            'title' => $req->str('title', 120), 'category_id' => (int) $req->input('category_id'), 'settlement_id' => (int) $req->input('settlement_id'),
            'address' => $req->str('address', 200) ?: null, 'employment' => $req->str('employment', 12),
            'salary_from' => $num($req->input('salary_from')), 'salary_to' => $num($req->input('salary_to')), 'salary_period' => $req->str('salary_period', 8),
            'description' => $req->str('description', 8000), 'requirements' => $req->str('requirements', 4000), 'offers' => $req->str('offers', 4000),
            'no_experience' => !empty($req->post['no_experience']) ? 1 : 0, 'contact_name' => $req->str('contact_name', 80) ?: null, 'contact_phone' => null,
        ];
        $err = [];
        if (mb_strlen($d['title']) < 3) $err['title'] = 'Вкажіть назву посади';
        if (!$db->value('SELECT 1 FROM {p}job_categories WHERE id = ? AND is_active = 1', [$d['category_id']])) $err['category_id'] = 'Оберіть категорію';
        if (!$db->value('SELECT 1 FROM {p}settlements WHERE id = ?', [$d['settlement_id']])) $err['settlement_id'] = 'Оберіть населений пункт';
        if (!array_key_exists($d['employment'], EMPLOYMENT_TYPES)) $err['employment'] = 'Оберіть тип зайнятості';
        if (!in_array($d['salary_period'], ['month', 'day', 'hour', 'piece'], true)) $d['salary_period'] = 'month';
        if ($d['salary_from'] && $d['salary_to'] && $d['salary_to'] < $d['salary_from']) $err['salary_to'] = '«До» менше за «від»';
        if (mb_strlen($d['description']) < 30) $err['description'] = 'Опишіть роботу детальніше (від 30 символів)';
        $phone = $req->str('contact_phone', 20);
        if ($phone !== '') { $d['contact_phone'] = Crypto::normalizePhone($phone); if (!$d['contact_phone']) $err['contact_phone'] = 'Формат: 067 123 45 67'; }
        try {
            [$d['show_from'], $d['show_to']] = Vacancies::period($req, $cur['show_from'] ?? null);
        } catch (ValidationException $e) {
            $err += $e->errors;
        }
        if ($err) throw new ValidationException($err);

        $submit = ($req->post['action'] ?? '') === 'submit';
        if ($cur) {
            // Будь-яка зміна опублікованої, запланованої, відхиленої чи архівної вакансії знову проходить модерацію.
            $status = in_array($cur['status'], ['published', 'scheduled', 'rejected', 'archived'], true) ? 'draft' : $cur['status'];
            $db->update('vacancies', [...$d, 'status' => $status, 'updated_at' => Db::now()], 'id = ?', [$cur['id']]);
            $db->run('UPDATE {p}vacancies SET version = version + 1 WHERE id = ?', [$cur['id']]);
            $id = (int) $cur['id'];
        } else {
            $n = (int) $db->value('SELECT COUNT(*) FROM {p}vacancies WHERE author_id = ? AND created_at > UTC_TIMESTAMP() - INTERVAL 1 DAY', [$me['id']]);
            if ($n >= 20) throw new ValidationException([], 'Ліміт: до 20 нових вакансій на добу.');
            $id = $db->insert('vacancies', [...$d, 'employer_id' => $employer['id'], 'author_id' => $me['id'], 'status' => 'draft']);
        }
        if ($submit) {
            $pre = (bool) (App::setting('moderation.premoderation', ['jobs' => true])['jobs'] ?? true);
            if ($pre) {
                $db->update('vacancies', ['status' => 'pending', 'submitted_at' => Db::now(), 'reject_reason' => null], 'id = ?', [$id]);
                Audit::log('jobs.vacancy_submitted', 'vacancy', $id);
                flash('ok', 'Вакансію надіслано на перевірку. Зазвичай це займає до кількох годин.');
            } else {
                $st = Vacancies::goLive($id, ['submitted_at' => Db::now()]);
                Audit::log('jobs.vacancy_published', 'vacancy', $id);
                flash('ok', $st === 'scheduled' ? 'Вакансію збережено. Вона з’явиться на сайті з ' . date('d.m', strtotime($d['show_from'])) . '.' : 'Вакансію опубліковано.');
            }
        } else {
            flash('ok', 'Чернетку збережено.');
        }
        return Response::redirect(url('/cabinet/vacancies/' . $id));
    }

    public function vacancyView(Request $req): Response
    {
        $v = $this->ownVacancy((int) $req->params['id']);
        $db = App::db();
        $v['settlement'] = $db->value('SELECT name FROM {p}settlements WHERE id = ?', [$v['settlement_id']]);
        // Контакти кандидата бачить лише автор вакансії, на яку той сам відгукнувся.
        $apps = $db->all('SELECT a.*, u.display_name, u.phone_enc, u.email_enc FROM {p}applications a JOIN {p}users u ON u.id = a.user_id WHERE a.vacancy_id = ? ORDER BY a.id DESC', [$v['id']]);
        foreach ($apps as &$a) { $a['phone'] = Crypto::decrypt($a['phone_enc']); $a['email'] = Crypto::decrypt($a['email_enc']); unset($a['phone_enc'], $a['email_enc']); }
        $db->run("UPDATE {p}applications SET status = 'viewed' WHERE vacancy_id = ? AND status = 'new'", [$v['id']]);
        return $this->page('vacancy_view', 'vacancies', ['title' => $v['title'], 'v' => $v, 'apps' => $apps]);
    }

    public function vacancyArchive(Request $req): Response
    {
        $v = $this->ownVacancy((int) $req->params['id']);
        if (!in_array($v['status'], ['published', 'scheduled'], true)) throw new HttpException(409, 'Цю вакансію вже знято з показу.');
        $reason = $req->str('reason', 10) === 'hired' ? 'hired' : 'manual';
        Vacancies::archive((int) $v['id'], $reason);
        Audit::log('jobs.vacancy_archived', 'vacancy', $v['id'], ['reason' => $reason]);
        flash('ok', $reason === 'hired' ? 'Вітаємо з новим працівником! Вакансію перенесено в архів. Її можна поновити будь-коли.' : 'Вакансію перенесено в архів. Її можна поновити будь-коли.');
        return Response::redirect(url('/cabinet/vacancies', ['tab' => 'archive']));
    }

    /** Поновлення з архіву на новий період. Текст не змінювався — тому без повторної модерації. */
    public function vacancyRestore(Request $req): Response
    {
        $v = $this->ownVacancy((int) $req->params['id']);
        if ($v['status'] !== 'archived') throw new HttpException(409, 'Поновити можна лише вакансію з архіву.');
        $employer = $this->employer((int) $this->me()['id']);
        if (!$employer || $employer['status'] !== 'active') throw new HttpException(403, 'Профіль роботодавця заблоковано.');
        [$from, $to] = Vacancies::period($req);
        App::db()->update('vacancies', ['show_from' => $from, 'show_to' => $to, 'updated_at' => Db::now()], 'id = ?', [$v['id']]);
        $st = Vacancies::goLive((int) $v['id']);
        Audit::log('jobs.vacancy_restored', 'vacancy', $v['id'], ['from' => $from, 'to' => $to]);
        flash('ok', $st === 'scheduled'
            ? 'Вакансію поновлено. Вона з’явиться на сайті ' . date('d.m', strtotime($from)) . ' і буде до ' . date('d.m', strtotime($to)) . ' включно.'
            : 'Вакансію поновлено. Її показуватимуть до ' . date('d.m.Y', strtotime($to)) . ' включно.');
        return Response::redirect(url('/cabinet/vacancies/' . $v['id']));
    }

    public function applications(Request $req): Response
    {
        $rows = App::db()->all('SELECT a.*, v.title, v.status AS vstatus, e.name AS employer FROM {p}applications a JOIN {p}vacancies v ON v.id = a.vacancy_id JOIN {p}employers e ON e.id = v.employer_id WHERE a.user_id = ? ORDER BY a.id DESC', [$this->me()['id']]);
        return $this->page('applications', 'applications', ['title' => 'Мої відгуки', 'rows' => $rows]);
    }
}
