<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Ads;
use App\Core\App;
use App\Core\Audit;
use App\Core\Crypto;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Media;
use App\Core\RateLimit;
use App\Core\Request;
use App\Core\Response;
use App\Core\Services;
use App\Core\ValidationException;

/** Розділ «Послуги»: каталог майстрів, сторінка майстра, замовлення, кабінет майстра (профіль, прайс, портфоліо), замовлення й відгуки. */
final class ServicesController
{
    public const PAGE = 20;

    private function me(): ?array { return App::auth()->user(); }

    private function cab(string $view, string $tab, array $data): Response
    {
        return (new CabinetController())->page($view, $tab, $data);
    }

    /* ================= публічна частина ================= */

    public function index(Request $req): Response
    {
        $db = App::db();
        $cats = Services::categories();
        [$settlements] = SiteController::reference();
        $f = [
            'q' => mb_substr(trim((string) ($req->query['q'] ?? '')), 0, 100),
            'category' => preg_replace('/[^a-z0-9_-]/', '', (string) ($req->query['category'] ?? '')),
            'settlement' => (int) ($req->query['settlement'] ?? 0),
            'sort' => in_array($req->query['sort'] ?? '', ['new', 'price'], true) ? (string) $req->query['sort'] : 'rating',
        ];
        $where = [Services::LIVE_SQL, "u.status = 'active'", 'u.deleted_at IS NULL'];
        $p = [];
        $cat = null;
        foreach ($cats as $c) if ($c['slug'] === $f['category']) $cat = $c;
        if ($cat) { $where[] = '(m.category_id = ? OR m.category2_id = ?)'; array_push($p, $cat['id'], $cat['id']); }
        if ($f['settlement']) { $where[] = Services::IN_SETTLEMENT_SQL; array_push($p, $f['settlement'], $f['settlement']); }
        if ($f['q'] !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $where[] = '(m.name LIKE ? OR m.about LIKE ? OR m.areas LIKE ? OR c.name LIKE ? OR EXISTS (SELECT 1 FROM {p}master_prices pq WHERE pq.master_id = m.id AND pq.title LIKE ?))';
            array_push($p, $like, $like, $like, $like, $like);
        }
        $from = ' FROM {p}masters m JOIN {p}users u ON u.id = m.user_id JOIN {p}service_categories c ON c.id = m.category_id LEFT JOIN {p}settlements s ON s.id = m.settlement_id WHERE ' . implode(' AND ', $where);
        $total = (int) $db->value('SELECT COUNT(*)' . $from, $p);
        $page = max(1, min((int) ($req->query['page'] ?? 1), max(1, (int) ceil($total / self::PAGE))));
        // «від …» у картці — ціна першої послуги в прайсі (майстер сам ставить головну послугу першою).
        $minPrice = "(SELECT pp.price_from FROM {p}master_prices pp WHERE pp.master_id = m.id AND pp.kind = 'service' AND pp.price_type <> 'deal' AND pp.price_from IS NOT NULL ORDER BY pp.sort, pp.id LIMIT 1)";
        $order = match ($f['sort']) {
            'new' => 'm.published_at DESC',
            'price' => "$minPrice IS NULL, $minPrice ASC, m.published_at DESC",
            default => '(m.rating_count > 0) DESC, (m.rating_sum + 4 * 3) / (m.rating_count + 3) DESC, m.orders_done DESC, m.published_at DESC',
        };
        $items = $db->all("SELECT m.id, m.name, m.photo, m.legal_form, m.experience_years, m.areas, m.travel_all, m.settlement_id, m.payments, m.rating_sum, m.rating_count, m.orders_done, m.published_at,
            c.name AS category, s.name AS settlement, $minPrice AS min_price,
            (SELECT COUNT(*) FROM {p}master_works w WHERE w.master_id = m.id) AS works,
            (SELECT f.file FROM {p}master_work_photos f JOIN {p}master_works w ON w.id = f.work_id WHERE w.master_id = m.id ORDER BY w.sort, w.id DESC, f.sort, f.id LIMIT 1) AS cover"
            . $from . " ORDER BY $order LIMIT " . self::PAGE . ' OFFSET ' . (($page - 1) * self::PAGE), $p);
        foreach ($items as &$it) $it['travel'] = Services::travelText($it, Services::travelAreas((int) $it['id']));
        unset($it);
        return new Response(view('site/services', ['f' => $f, 'cat' => $cat, 'cats' => $cats, 'settlements' => $settlements, 'items' => $items, 'total' => $total, 'page' => $page,
            'mine' => ($me = $this->me()) ? Services::ofUser((int) $me['id']) : null,
            'title' => $cat ? $cat['name'] . ' — майстри' : 'Послуги майстрів', 'description' => 'Майстри в Миронівці, Богуславі та Кагарлику: ціни, фото робіт і відгуки клієнтів.']));
    }

    /** Майстер для сторінки: публічний, або власник чи модератор бачать і неопубліковані. */
    private function load(Request $req): array
    {
        $m = Services::find((int) $req->params['id']);
        $me = $this->me();
        if (!$m) throw new HttpException(404, 'Майстра не знайдено.');
        $isOwner = $me && (int) $me['id'] === (int) $m['user_id'];
        $isMod = $me && App::auth()->can('services.listing:moderate');
        if (!Services::isPublic($m) && !$isOwner && !$isMod) throw new HttpException(404, 'Майстра не знайдено або він тимчасово не приймає замовлення.');
        return [$m, $isOwner, $isMod];
    }

    public function show(Request $req): Response
    {
        [$m, $isOwner, $isMod] = $this->load($req);
        $me = $this->me();
        $db = App::db();
        if (!$isOwner && Services::isPublic($m) && !preg_match('/bot|crawl|spider|slurp|preview|headless/i', $req->userAgent())) {
            $db->run('UPDATE {p}masters SET views = views + 1 WHERE id = ?', [$m['id']]);
        }
        $reviews = $db->all('SELECT r.*, u.display_name AS client FROM {p}master_reviews r JOIN {p}users u ON u.id = r.client_id
            WHERE r.master_id = ?' . ($isMod ? '' : ' AND r.is_hidden = 0') . ' ORDER BY r.created_at DESC LIMIT 100', [$m['id']]);
        $active = $myReview = null;
        $canReview = false;
        if ($me && !$isOwner) {
            $active = $db->one("SELECT * FROM {p}service_orders WHERE master_id = ? AND client_id = ? AND status IN ('new','accepted') ORDER BY id DESC LIMIT 1", [$m['id'], $me['id']]);
            $myReview = $db->one('SELECT * FROM {p}master_reviews WHERE master_id = ? AND client_id = ?', [$m['id'], $me['id']]);
            $canReview = (bool) $db->value("SELECT 1 FROM {p}service_orders WHERE master_id = ? AND client_id = ? AND status = 'done' LIMIT 1", [$m['id'], $me['id']]);
        }
        $dist = array_fill(1, 5, 0);
        foreach ($reviews as $r) if (!$r['is_hidden']) $dist[(int) $r['rating']]++;
        return new Response(view('site/master', [
            'travel' => Services::travelText($m, Services::travelAreas((int) $m['id'])),
            'm' => $m, 'isOwner' => $isOwner, 'isMod' => $isMod, 'me' => $me, 'prices' => Services::prices((int) $m['id']), 'works' => Services::works((int) $m['id']),
            'reviews' => $reviews, 'dist' => $dist, 'active' => $active, 'myReview' => $myReview, 'canReview' => $canReview,
            'myPhone' => $me ? Crypto::decrypt($me['phone_enc']) : null,
            'title' => $m['name'] . ' — ' . $m['category'], 'description' => mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $m['about']) ?? ''), 0, 160),
        ]));
    }

    /** Номер майстра для кнопки «Показати номер» (окремим запитом, щоб боти не збирали номери з HTML). */
    public function phone(Request $req): Response
    {
        RateLimit::hit('master-phone:' . $req->ip(), 60, 3600, 'Забагато запитів. Спробуйте пізніше.');
        [$m] = $this->load($req);
        $phone = $m['contact_phone'] ?: null;
        if (!$phone) return Response::json(['message' => 'Номер недоступний.'], 404);
        return Response::json(['phone' => $phone, 'pretty' => phone_pretty($phone)])->header('Cache-Control', 'private, no-store');
    }

    /** Замовлення з сторінки майстра. Помилки показуються на тій самій сторінці (форма зберігає текст). */
    public function order(Request $req): Response
    {
        $me = $this->me();
        $id = (int) $req->params['id'];
        if (!$me) return Response::redirect(url('/login', ['next' => '/services/' . $id]));
        [$m, $isOwner] = $this->load($req);
        $db = App::db();
        if ($isOwner) throw new ValidationException([], 'Це ваш профіль майстра — замовити в себе не можна.');
        if (!Services::isPublic($m)) throw new ValidationException([], 'Майстер зараз не приймає замовлення.');
        if ($db->value("SELECT 1 FROM {p}service_orders WHERE master_id = ? AND client_id = ? AND status IN ('new','accepted')", [$m['id'], $me['id']])) {
            throw new ValidationException([], 'У вас уже є відкрите замовлення в цього майстра. Дочекайтеся відповіді або скасуйте його в кабінеті.');
        }
        $msg = $req->str('message', 2000);
        $contact = $req->str('contact', 40);
        $priceId = ((int) $req->input('price_id')) ?: null;
        $price = $priceId ? $db->one('SELECT id, title FROM {p}master_prices WHERE id = ? AND master_id = ?', [$priceId, $m['id']]) : null;
        $err = [];
        if (mb_strlen($msg) < 10) $err['message'] = 'Опишіть коротко, що потрібно зробити, де і коли';
        if (mb_strlen(preg_replace('/\D/', '', $contact) ?? '') < 9) $err['contact'] = 'Вкажіть телефон, щоб майстер міг вам передзвонити';
        if ($err) throw new ValidationException($err, 'Перевірте поля замовлення.');
        RateLimit::hit('order:' . $me['id'], 10, 86400, 'Ліміт: до 10 замовлень на добу.');
        $oid = $db->insert('service_orders', ['master_id' => $m['id'], 'client_id' => $me['id'], 'price_id' => $price['id'] ?? null, 'price_title' => $price['title'] ?? null,
            'message' => $msg, 'contact' => $contact]);
        Audit::log('services.order_created', 'service_order', $oid, ['master' => (int) $m['id']]);
        $site = (string) App::setting('site.name', 'YEWORK');
        Services::notifyUser((int) $m['user_id'], 'Нове замовлення на ' . $site,
            "Вітаємо!\n\nНа $site нове замовлення від {$me['display_name']}" . ($price ? " («{$price['title']}»)" : '') . ".\n\n$msg\n\nТелефон клієнта: $contact\n\n"
            . 'Прийміть або відхиліть замовлення в кабінеті: ' . Services::siteBase() . url('/cabinet/orders') . "\nПісля виконання натисніть «Виконано» — тоді клієнт зможе залишити відгук.\n\n— $site",
            "$site: нове замовлення від " . mb_strimwidth((string) $me['display_name'], 0, 30, '…') . ", тел. $contact. Деталі в кабінеті.", 'service_order');
        flash('ok', 'Замовлення надіслано. Майстер отримав повідомлення й зателефонує вам. Стежте за статусом у кабінеті → «Замовлення послуг».');
        return Response::redirect(url('/services/' . $m['id']) . '#order');
    }

    /* ================= кабінет майстра ================= */

    public function masterForm(Request $req): Response
    {
        $me = App::auth()->user();
        $m = Services::ofUser((int) $me['id']);
        [$settlements] = SiteController::reference();
        $paid = Ads::monetizationOn();
        return $this->cab('master', 'master', [
            'title' => 'Я майстер', 'm' => $m, 'cats' => Services::categories(false), 'settlements' => $settlements,
            'travelIds' => $m ? array_map('intval', array_column(Services::travelAreas((int) $m['id']), 'id')) : [],
            'prices' => $m ? Services::prices((int) $m['id']) : [], 'works' => $m ? Services::works((int) $m['id']) : [],
            'limits' => Services::limits(), 'myPhone' => Crypto::decrypt($me['phone_enc']), 'premod' => Services::premoderation(),
            'paid' => $paid, 'billPrices' => Ads::prices(),
            'newOrders' => Services::newOrdersFor((int) $me['id']),
        ]);
    }

    public function masterSave(Request $req): Response
    {
        $section = $req->str('section', 12);
        return match ($section) {
            'prices' => $this->pricesSave($req),
            'pause' => $this->pause($req),
            'archive' => $this->archive($req),
            'restore' => $this->restore($req),
            default => $this->profileSave($req),
        };
    }

    private function own(): array
    {
        $m = Services::ofUser((int) App::auth()->id());
        if (!$m) throw new HttpException(404, 'Спочатку заповніть профіль майстра.');
        return $m;
    }

    private function profileSave(Request $req): Response
    {
        $me = App::auth()->user();
        $uid = (int) $me['id'];
        $db = App::db();
        $cur = Services::ofUser($uid);
        $submit = ($req->post['action'] ?? '') === 'submit';
        $phoneRaw = $req->str('contact_phone', 30);
        $phone = $phoneRaw !== '' ? Crypto::normalizePhone($phoneRaw) : null;
        $pays = array_values(array_intersect(array_keys(Services::PAYMENTS), array_map('strval', (array) ($req->post['payments'] ?? []))));
        $exp = preg_replace('/\D/', '', $req->str('experience_years', 3));
        $months = (int) $req->input('period_months', 1);
        $d = [
            'name' => $req->str('name', 100),
            'category_id' => (int) $req->input('category_id'),
            'category2_id' => ((int) $req->input('category2_id')) ?: null,
            'settlement_id' => ((int) $req->input('settlement_id')) ?: null,
            'areas' => $req->str('areas', 200) ?: null,
            'travel_all' => !empty($req->post['travel_all']) ? 1 : 0,
            'experience_years' => $exp === '' ? null : min(70, (int) $exp),
            'about' => $req->str('about', 4000) ?: null,
            'contact_phone' => $phone,
            'legal_form' => array_key_exists($req->str('legal_form', 10), Services::LEGAL) ? $req->str('legal_form', 10) : 'person',
            'payments' => $pays ? implode(',', $pays) : null,
            'period_months' => isset(Services::PERIODS[$months]) ? $months : 1,
        ];
        if ($d['category2_id'] === $d['category_id']) $d['category2_id'] = null;
        $err = [];
        if (mb_strlen($d['name']) < 3) $err['name'] = 'Як вас називати клієнтам: ім’я або назва, напр. «Сергій, монтаж кондиціонерів»';
        $catOk = static fn (?int $id) => $id && $db->value('SELECT 1 FROM {p}service_categories WHERE id = ? AND is_active = 1', [$id]);
        if (!$catOk($d['category_id'])) $err['category_id'] = 'Оберіть, чим ви займаєтеся';
        if ($d['category2_id'] !== null && !$catOk($d['category2_id'])) $err['category2_id'] = 'Оберіть категорію зі списку';
        if ($d['settlement_id'] !== null && !$db->value('SELECT 1 FROM {p}settlements WHERE id = ?', [$d['settlement_id']])) $err['settlement_id'] = 'Оберіть населений пункт';
        if ($phoneRaw !== '' && !$phone) $err['contact_phone'] = 'Номер у форматі +380 67 123 45 67';
        if ($submit) {
            if (!$phone) $err['contact_phone'] ??= 'Вкажіть робочий телефон, щоб клієнти могли зателефонувати';
            if (mb_strlen((string) $d['about']) < 30) $err['about'] = 'Розкажіть про свою роботу хоча б у двох-трьох реченнях (від 30 символів)';
            if (!$d['settlement_id']) $err['settlement_id'] = 'Вкажіть, де ви працюєте';
            if (!$pays) $err['payments'] = 'Позначте хоча б один спосіб оплати';
        }
        if ($err) throw new ValidationException($err);
        $travel = array_values(array_unique(array_filter(array_map('intval', (array) ($req->post['travel'] ?? [])))));
        if ($travel) $travel = array_map('intval', array_column($db->all('SELECT id FROM {p}settlements WHERE id IN (' . implode(',', $travel) . ')'), 'id'));
        $travel = array_values(array_filter($travel, static fn ($id) => $id !== $d['settlement_id']));

        $photo = null;
        if (!empty($req->post['photo_delete']) && $cur && $cur['photo']) {
            Media::delete('master', $cur['photo']);
            $d['photo'] = null;
        } else {
            $photo = Media::store('master', 'photo', 'm' . $uid);
            if ($photo) {
                RateLimit::hit('master-photo:' . $uid, 30, 86400, 'Забагато завантажень фото за добу. Спробуйте завтра.');
                $d['photo'] = $photo;
                if ($cur && $cur['photo']) Media::delete('master', $cur['photo']);
            }
        }

        $premod = Services::premoderation();
        if (!$cur) {
            $id = $db->insert('masters', [...$d, 'user_id' => $uid, 'status' => 'draft']);
            Audit::log('services.master_created', 'master', $id);
            $cur = ['id' => $id, 'status' => 'draft', 'period_months' => $d['period_months']];
            $isNew = true;
        } else {
            $id = (int) $cur['id'];
            $changed = false;
            foreach (['name', 'about', 'areas', 'category_id', 'category2_id'] as $k) if ((string) ($cur[$k] ?? '') !== (string) ($d[$k] ?? '')) $changed = true;
            if ($photo) $changed = true;
            $set = [...$d, 'updated_at' => Db::now()];
            if ($cur['status'] === 'published' && $changed && $premod) $set['needs_review'] = 1;
            // Змінили термін показу в опублікованому профілі — рахуємо новий термін від сьогодні.
            if ($cur['status'] === 'published' && (int) $cur['period_months'] !== $d['period_months']) $set['show_to'] = Services::showTo($d['period_months']);
            $db->update('masters', $set, 'id = ?', [$id]);
        }

        $db->run('DELETE FROM {p}master_areas WHERE master_id = ?', [$id]);
        foreach ($d['travel_all'] ? [] : $travel as $sid) $db->insert('master_areas', ['master_id' => $id, 'settlement_id' => $sid]);

        if ($submit && in_array($cur['status'], ['draft', 'rejected'], true)) {
            if ($premod) {
                $db->update('masters', ['status' => 'pending', 'submitted_at' => Db::now(), 'reject_reason' => null], 'id = ?', [$id]);
                Audit::log('services.master_submitted', 'master', $id);
                flash('ok', 'Профіль надіслано на перевірку. Зазвичай це займає кілька годин. Поки що додайте прайс і фото робіт.');
            } else {
                Services::goLive($id);
                Audit::log('services.master_published', 'master', $id);
                flash('ok', 'Профіль опубліковано в розділі «Послуги».');
            }
        } elseif (!$submit && $cur['status'] === 'draft') {
            flash('ok', !empty($isNew) ? 'Чернетку збережено. Тепер додайте прайс і фото робіт нижче, а потім натисніть «Надіслати на перевірку».' : 'Чернетку збережено. Коли все заповните, натисніть «Надіслати на перевірку».');
        } else {
            flash('ok', $cur['status'] === 'published' ? 'Зміни збережено й уже видно на сайті.' : 'Профіль збережено.');
        }
        return Response::redirect(url('/cabinet/master') . (!empty($isNew) ? '#prices' : ''));
    }

    private function pricesSave(Request $req): Response
    {
        $m = $this->own();
        $num = static fn ($v) => ($v = preg_replace('/\D/', '', (string) $v)) === '' ? null : min((int) $v, 100_000_000);
        $rows = [];
        $err = [];
        foreach (array_slice((array) ($req->post['price'] ?? []), 0, Services::MAX_PRICES) as $i => $p) {
            if (!is_array($p)) continue;
            $r = [
                'kind' => array_key_exists((string) ($p['kind'] ?? ''), Services::KINDS) ? (string) $p['kind'] : 'service',
                'title' => mb_substr(trim((string) ($p['title'] ?? '')), 0, 160),
                'price_type' => array_key_exists((string) ($p['price_type'] ?? ''), Services::PRICE_TYPES) ? (string) $p['price_type'] : 'fixed',
                'price_from' => $num($p['price_from'] ?? ''),
                'price_to' => $num($p['price_to'] ?? ''),
                'unit' => array_key_exists((string) ($p['unit'] ?? ''), Services::UNITS) ? (string) $p['unit'] : 'job',
                'note' => mb_substr(trim((string) ($p['note'] ?? '')), 0, 300) ?: null,
            ];
            if ($r['title'] === '' && $r['price_from'] === null && $r['price_to'] === null && $r['note'] === null) continue; // порожній рядок
            if (mb_strlen($r['title']) < 3) $err["price.$i.title"] = 'Назва послуги';
            if ($r['price_type'] === 'deal') { $r['price_from'] = $r['price_to'] = null; }
            elseif ($r['price_type'] === 'range') {
                if ($r['price_from'] === null || $r['price_to'] === null) $err["price.$i.price"] = 'Для «від – до» вкажіть обидві суми';
                elseif ($r['price_to'] < $r['price_from']) [$r['price_from'], $r['price_to']] = [$r['price_to'], $r['price_from']];
            } else {
                $r['price_to'] = null;
                if ($r['price_from'] === null) $err["price.$i.price"] = 'Вкажіть ціну або оберіть «Договірна»';
            }
            $rows[] = $r;
        }
        if ($err) {
            throw new ValidationException(['prices' => 'Перевірте виділені рядки прайсу: ' . implode(', ', array_unique(array_values($err))) . '.'] + $err, 'Перевірте прайс.');
        }
        App::db()->tx(function (Db $db) use ($m, $rows) {
            $db->run('DELETE FROM {p}master_prices WHERE master_id = ?', [$m['id']]);
            foreach ($rows as $i => $r) $db->insert('master_prices', [...$r, 'master_id' => $m['id'], 'sort' => $i]);
            $db->update('masters', ['updated_at' => Db::now()], 'id = ?', [$m['id']]);
        });
        Audit::log('services.prices_updated', 'master', $m['id'], ['rows' => count($rows)]);
        flash('ok', $rows ? 'Прайс збережено: ' . plural(count($rows), 'позиція', 'позиції', 'позицій') . '.' : 'Прайс очищено.');
        return Response::redirect(url('/cabinet/master') . '#prices');
    }

    private function pause(Request $req): Response
    {
        $m = $this->own();
        $hide = ($req->post['hide'] ?? '') === '1';
        App::db()->update('masters', ['is_hidden' => $hide ? 1 : 0, 'updated_at' => Db::now()], 'id = ?', [$m['id']]);
        Audit::log($hide ? 'services.master_paused' : 'services.master_resumed', 'master', $m['id']);
        flash('ok', $hide ? 'Профіль на паузі: його не видно в каталозі, нові замовлення не приходять. Увімкніть, коли будете готові.' : 'Профіль знову показується в каталозі.');
        return Response::redirect(url('/cabinet/master'));
    }

    private function archive(Request $req): Response
    {
        $m = $this->own();
        if ($m['status'] !== 'published') throw new HttpException(409, 'Перенести в архів можна лише опублікований профіль.');
        Services::archive((int) $m['id'], 'manual');
        Audit::log('services.master_archived', 'master', $m['id'], ['reason' => 'manual']);
        flash('ok', 'Профіль перенесено в архів. Прайс, фото й відгуки збережено — поновіть його будь-коли.');
        return Response::redirect(url('/cabinet/master'));
    }

    private function restore(Request $req): Response
    {
        $m = $this->own();
        if ($m['status'] !== 'archived') throw new HttpException(409, 'Профіль не в архіві.');
        $months = (int) $req->input('period_months', $m['period_months']);
        $months = isset(Services::PERIODS[$months]) ? $months : 1;
        App::db()->update('masters', ['period_months' => $months], 'id = ?', [$m['id']]);
        Services::goLive((int) $m['id']);
        Audit::log('services.master_restored', 'master', $m['id'], ['months' => $months]);
        flash('ok', 'Профіль знову в каталозі до ' . date('d.m.Y', strtotime(Services::showTo($months))) . '.');
        return Response::redirect(url('/cabinet/master'));
    }

    /* ---------------- портфоліо ---------------- */

    public function workForm(Request $req): Response
    {
        $m = Services::ofUser((int) App::auth()->id());
        if (!$m) { flash('info', 'Спочатку заповніть профіль майстра.'); return Response::redirect(url('/cabinet/master')); }
        $w = isset($req->params['id']) ? Services::work((int) $m['id'], (int) $req->params['id']) : null;
        if (isset($req->params['id']) && !$w) throw new HttpException(404, 'Об’єкт не знайдено.');
        $limits = Services::limits();
        $count = (int) App::db()->value('SELECT COUNT(*) FROM {p}master_works WHERE master_id = ?', [$m['id']]);
        if (!$w && $count >= $limits['works']) {
            flash('info', 'Безкоштовно можна додати ' . plural($limits['works'], 'об’єкт', 'об’єкти', 'об’єктів') . '. Видаліть старий або дочекайтеся платної опції «Більше об’єктів».');
            return Response::redirect(url('/cabinet/master') . '#works');
        }
        return $this->cab('master_work', 'master', ['title' => $w ? 'Об’єкт: ' . $w['title'] : 'Новий об’єкт', 'm' => $m, 'w' => $w, 'limits' => $limits]);
    }

    public function workSave(Request $req): Response
    {
        $uid = (int) App::auth()->id();
        $m = $this->own();
        $db = App::db();
        $limits = Services::limits();
        $w = isset($req->params['id']) ? Services::work((int) $m['id'], (int) $req->params['id']) : null;
        if (isset($req->params['id']) && !$w) throw new HttpException(404, 'Об’єкт не знайдено.');
        if (!$w && (int) $db->value('SELECT COUNT(*) FROM {p}master_works WHERE master_id = ?', [$m['id']]) >= $limits['works']) {
            throw new ValidationException([], 'Досягнуто ліміт об’єктів у портфоліо.');
        }
        $d = [
            'title' => $req->str('title', 160), 'description' => $req->str('description', 3000) ?: null,
            'place' => $req->str('place', 120) ?: null, 'done_on' => $req->str('done_on', 20) ?: null,
        ];
        $err = [];
        if (mb_strlen($d['title']) < 3) $err['title'] = 'Що зробили: напр. «Монтаж кондиціонера Gree 12 в приватному будинку»';
        $del = array_map('intval', (array) ($req->post['photo_delete'] ?? []));
        $keep = $w ? array_values(array_filter($w['photos'], static fn ($p) => !in_array((int) $p['id'], $del, true))) : [];
        $files = Media::files('photos');
        $room = $limits['photos'] - count($keep);
        if (count($files) > $room) $err['photos'] = 'В одному об’єкті до ' . plural($limits['photos'], 'фото', 'фото', 'фото') . ($room > 0 ? '. Можна додати ще ' . $room . '.' : '. Видаліть старі, щоб додати нові.');
        if (!$w && !$files) $err['photos'] = 'Додайте хоча б одне фото роботи';
        if ($w && !$keep && !$files) $err['photos'] = 'В об’єкті має лишитися хоча б одне фото';
        if ($err) throw new ValidationException($err);
        if ($files) RateLimit::hit('work-photo:' . $uid, 60, 86400, 'Забагато завантажень фото за добу. Спробуйте завтра.');

        // Спершу зберігаємо файли (якщо якийсь не підійде — нічого не змінюємо в базі).
        $stored = [];
        try {
            foreach ($files as $f) {
                $file = Media::storeUploaded('work', $f, 'w' . $m['id'], 'photos');
                Media::storePath('workthumb', Media::dir('work') . '/' . $file, 'w' . $m['id'], 'photos', pathinfo($file, PATHINFO_FILENAME));
                $stored[] = $file;
            }
        } catch (\Throwable $e) {
            foreach ($stored as $f) { Media::delete('work', $f); Media::delete('workthumb', $f); }
            throw $e;
        }
        if ($w) {
            $wid = (int) $w['id'];
            $db->update('master_works', [...$d, 'updated_at' => Db::now()], 'id = ?', [$wid]);
            foreach ($w['photos'] as $p) if (in_array((int) $p['id'], $del, true)) {
                $db->run('DELETE FROM {p}master_work_photos WHERE id = ?', [$p['id']]);
                Media::delete('work', $p['file']); Media::delete('workthumb', $p['file']);
            }
        } else {
            $wid = $db->insert('master_works', [...$d, 'master_id' => $m['id'], 'sort' => 0]);
        }
        $n = (int) $db->value('SELECT COALESCE(MAX(sort), 0) FROM {p}master_work_photos WHERE work_id = ?', [$wid]);
        foreach ($stored as $f) $db->insert('master_work_photos', ['work_id' => $wid, 'file' => $f, 'sort' => ++$n]);
        $set = ['updated_at' => Db::now()];
        if ($m['status'] === 'published' && Services::premoderation() && ($stored || !$w || $w['title'] !== $d['title'] || (string) $w['description'] !== (string) $d['description'])) $set['needs_review'] = 1;
        $db->update('masters', $set, 'id = ?', [$m['id']]);
        Audit::log($w ? 'services.work_updated' : 'services.work_created', 'master', $m['id'], ['work' => $wid, 'photos' => count($stored)]);
        flash('ok', $w ? 'Об’єкт збережено.' : 'Об’єкт додано в портфоліо.');
        return Response::redirect(url('/cabinet/master') . '#works');
    }

    public function workDelete(Request $req): Response
    {
        $m = $this->own();
        $w = Services::work((int) $m['id'], (int) $req->params['id']);
        if (!$w) throw new HttpException(404, 'Об’єкт не знайдено.');
        App::db()->run('DELETE FROM {p}master_works WHERE id = ?', [$w['id']]);
        foreach ($w['photos'] as $p) { Media::delete('work', $p['file']); Media::delete('workthumb', $p['file']); }
        Audit::log('services.work_deleted', 'master', $m['id'], ['work' => (int) $w['id']]);
        flash('ok', 'Об’єкт видалено з портфоліо.');
        return Response::redirect(url('/cabinet/master') . '#works');
    }

    /* ================= замовлення й відгуки ================= */

    public function orders(Request $req): Response
    {
        $me = App::auth()->user();
        $db = App::db();
        $m = Services::ofUser((int) $me['id']);
        $incoming = $m ? $db->all('SELECT o.*, u.display_name AS client, r.id AS review_id, r.rating, r.body AS review, r.reply, r.is_hidden AS review_hidden
            FROM {p}service_orders o JOIN {p}users u ON u.id = o.client_id
            LEFT JOIN {p}master_reviews r ON r.order_id = o.id
            WHERE o.master_id = ? ORDER BY FIELD(o.status, \'new\', \'accepted\') DESC, o.id DESC LIMIT 200', [$m['id']]) : [];
        $mine = $db->all('SELECT o.*, m.name AS master, m.contact_phone AS master_phone, m.status AS master_status, c.name AS category
            FROM {p}service_orders o JOIN {p}masters m ON m.id = o.master_id JOIN {p}service_categories c ON c.id = m.category_id
            WHERE o.client_id = ? ORDER BY o.id DESC LIMIT 200', [$me['id']]);
        $myReviews = [];
        foreach ($db->all('SELECT * FROM {p}master_reviews WHERE client_id = ?', [$me['id']]) as $r) $myReviews[(int) $r['master_id']] = $r;
        $tab = ($req->query['tab'] ?? '') === 'my' || !$m ? 'my' : 'in';
        return $this->cab('orders', 'orders', ['title' => 'Замовлення послуг', 'm' => $m, 'incoming' => $incoming, 'mine' => $mine, 'myReviews' => $myReviews, 'otab' => $tab]);
    }

    public function orderAction(Request $req): Response
    {
        $me = App::auth()->user();
        $uid = (int) $me['id'];
        $db = App::db();
        $do = $req->str('do', 10);
        $o = $db->one('SELECT o.*, m.user_id AS master_user, m.name AS master FROM {p}service_orders o JOIN {p}masters m ON m.id = o.master_id WHERE o.id = ?', [(int) $req->input('order_id')]);
        if (!$o) throw new HttpException(404, 'Замовлення не знайдено.');
        $isMaster = (int) $o['master_user'] === $uid;
        $isClient = (int) $o['client_id'] === $uid;
        if (!$isMaster && !$isClient) throw new HttpException(404, 'Замовлення не знайдено.');
        $back = url('/cabinet/orders', ['tab' => $isMaster ? null : 'my']) . '#o' . $o['id'];
        $site = (string) App::setting('site.name', 'YEWORK');
        $link = Services::siteBase() . url('/cabinet/orders', ['tab' => 'my']);
        $set = static fn (array $s) => $db->update('service_orders', [...$s, 'updated_at' => Db::now()], 'id = ?', [$o['id']]);

        switch ($do) {
            case 'accept':
                if (!$isMaster || $o['status'] !== 'new') throw new HttpException(409, 'Замовлення вже оброблено.');
                $set(['status' => 'accepted', 'accepted_at' => Db::now()]);
                Services::notifyUser((int) $o['client_id'], "Майстер прийняв ваше замовлення — $site",
                    "Вітаємо!\n\nМайстер «{$o['master']}» прийняв ваше замовлення на $site і зв’яжеться з вами.\n\nКоли роботу буде зроблено, ви зможете поставити оцінку й написати відгук: $link\n\n— $site",
                    "$site: майстер «" . mb_strimwidth((string) $o['master'], 0, 30, '…') . '» прийняв ваше замовлення.', 'service_order');
                flash('ok', 'Замовлення прийнято. Зателефонуйте клієнту, а після роботи натисніть «Виконано».');
                break;
            case 'decline':
                if (!$isMaster || !in_array($o['status'], ['new', 'accepted'], true)) throw new HttpException(409, 'Замовлення вже оброблено.');
                $set(['status' => 'declined']);
                Services::notifyUser((int) $o['client_id'], "Майстер не зможе виконати замовлення — $site",
                    "Вітаємо!\n\nНа жаль, майстер «{$o['master']}» не зможе виконати ваше замовлення. Знайдіть іншого майстра в розділі «Послуги»: " . Services::siteBase() . url('/services') . "\n\n— $site",
                    "$site: майстер не зможе виконати ваше замовлення. Оберіть іншого в розділі «Послуги».", 'service_order');
                flash('ok', 'Ви відмовилися від замовлення. Клієнт отримав повідомлення.');
                break;
            case 'done':
                if ($o['status'] !== 'accepted') throw new HttpException(409, $o['status'] === 'new' ? 'Спершу майстер має прийняти замовлення.' : 'Замовлення вже закрито.');
                $set(['status' => 'done', 'done_at' => Db::now(), 'done_by' => $isMaster ? 'master' : 'client']);
                Services::recalc((int) $o['master_id']);
                if ($isMaster) {
                    Services::notifyUser((int) $o['client_id'], "Оцініть роботу майстра — $site",
                        "Вітаємо!\n\nМайстер «{$o['master']}» позначив ваше замовлення виконаним. Поставте, будь ласка, оцінку і напишіть кілька слів — це допоможе іншим людям вибрати майстра: $link\n\n— $site",
                        "$site: оцініть роботу майстра «" . mb_strimwidth((string) $o['master'], 0, 30, '…') . '» у кабінеті.', 'service_review_ask');
                    flash('ok', 'Замовлення виконано. Клієнт отримає прохання залишити відгук.');
                } else {
                    flash('ok', 'Дякуємо! Тепер поставте оцінку майстру нижче.');
                }
                Audit::log('services.order_done', 'service_order', $o['id'], ['by' => $isMaster ? 'master' : 'client']);
                return Response::redirect($back);
            case 'cancel':
                if (!$isClient || !in_array($o['status'], ['new', 'accepted'], true)) throw new HttpException(409, 'Замовлення вже закрито.');
                $set(['status' => 'cancelled']);
                flash('ok', 'Замовлення скасовано.');
                break;
            case 'review':
                if (!$isClient || $o['status'] !== 'done') throw new HttpException(403, 'Відгук можна залишити після виконаного замовлення.');
                $rating = (int) $req->input('rating');
                $body = $req->str('body', 2000) ?: null;
                if ($rating < 1 || $rating > 5) throw new ValidationException(['rating' => 'Оберіть від 1 до 5 зірок'], 'Оберіть оцінку від 1 до 5 зірок.');
                if ($rating <= 3 && mb_strlen((string) $body) < 10) throw new ValidationException(['body' => 'Напишіть, що було не так'], 'Для оцінки 3 і нижче напишіть кілька слів, що було не так: це допоможе майстру і людям.');
                $ex = $db->one('SELECT id FROM {p}master_reviews WHERE master_id = ? AND client_id = ?', [$o['master_id'], $uid]);
                if ($ex) $db->update('master_reviews', ['rating' => $rating, 'body' => $body, 'order_id' => $o['id'], 'updated_at' => Db::now()], 'id = ?', [$ex['id']]);
                else $db->insert('master_reviews', ['master_id' => $o['master_id'], 'client_id' => $uid, 'order_id' => $o['id'], 'rating' => $rating, 'body' => $body]);
                Services::recalc((int) $o['master_id']);
                Audit::log('services.review_saved', 'master', $o['master_id'], ['order' => (int) $o['id'], 'rating' => $rating]);
                flash('ok', $ex ? 'Відгук оновлено.' : 'Дякуємо за відгук! Він уже на сторінці майстра.');
                break;
            case 'reply':
                if (!$isMaster) throw new HttpException(403, 'Відповідати на відгук може лише майстер.');
                $r = $db->one('SELECT * FROM {p}master_reviews WHERE order_id = ? AND master_id = ?', [$o['id'], $o['master_id']]);
                if (!$r) throw new HttpException(404, 'Відгук не знайдено.');
                $text = $req->str('reply', 1000);
                $db->update('master_reviews', ['reply' => $text !== '' ? $text : null, 'reply_at' => $text !== '' ? Db::now() : null], 'id = ?', [$r['id']]);
                flash('ok', $text !== '' ? 'Відповідь опубліковано під відгуком.' : 'Відповідь прибрано.');
                break;
            default:
                throw new HttpException(400, 'Невідома дія.');
        }
        if (in_array($do, ['accept', 'decline', 'cancel'], true)) Audit::log('services.order_' . $do, 'service_order', $o['id']);
        return Response::redirect($back);
    }
}
