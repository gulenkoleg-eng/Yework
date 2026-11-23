<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Ads;
use App\Core\App;
use App\Core\Audit;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Media;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\View;

/** Кабінет роботодавця: банерна реклама та платні послуги (поки безкоштовні). */
final class AdsController
{
    private function cab(): CabinetController { return new CabinetController(); }

    private function me(): array { return App::auth()->user(); }

    private function own(int $id): array
    {
        $b = App::db()->one('SELECT b.* FROM {p}banners b JOIN {p}employers e ON e.id = b.employer_id WHERE b.id = ? AND e.owner_id = ?', [$id, $this->me()['id']]);
        if (!$b) throw new HttpException(404, 'Банер не знайдено.');
        return $b;
    }

    public function index(Request $req): Response
    {
        $me = $this->me();
        $employer = $this->cab()->employer((int) $me['id']);
        $rows = $employer ? App::db()->all('SELECT b.*, COALESCE(SUM(s.impressions), 0) AS impressions, COALESCE(SUM(s.clicks), 0) AS clicks
            FROM {p}banners b LEFT JOIN {p}banner_stats s ON s.banner_id = b.id WHERE b.employer_id = ? GROUP BY b.id ORDER BY b.id DESC', [$employer['id']]) : [];
        $published = $employer ? (int) App::db()->value("SELECT COUNT(*) FROM {p}vacancies WHERE employer_id = ? AND status = 'published' AND deleted_at IS NULL", [$employer['id']]) : 0;
        return $this->cab()->page('ads', 'ads', ['title' => 'Реклама і послуги', 'employer' => $employer, 'rows' => $rows, 'published' => $published,
            'paid' => Ads::monetizationOn(), 'prices' => Ads::prices(), 'today' => Ads::today(), 'templates' => Ads::availableTemplates(), 'tplDefault' => Ads::templateSettings()['default']]);
    }

    public function order(Request $req): Response
    {
        $me = $this->me();
        $db = App::db();
        $employer = $this->cab()->employer((int) $me['id']);
        if (!$employer) return Response::redirect(url('/cabinet/employer'));
        if ($employer['status'] !== 'active') throw new HttpException(403, 'Профіль роботодавця заблоковано.');

        $d = [
            'title' => $req->str('title', 80), 'body' => $req->str('body', 300),
            'placement' => array_key_exists($req->str('placement', 8), Ads::PLACEMENTS) ? $req->str('placement', 8) : '',
            'starts_on' => $req->str('starts_on', 10), 'days' => (int) $req->input('days', 0),
        ];
        $tpls = Ads::availableTemplates();
        $d['template'] = $req->str('template', 12);
        if (!isset($tpls[$d['template']])) $d['template'] = Ads::templateSettings()['default'];
        $d['badge'] = $req->str('badge', 24) ?: null;
        $d['cta'] = $req->str('cta', 32) ?: null;
        $perks = [];
        foreach ((array) ($req->post['perks'] ?? []) as $pk) {
            $pk = trim(mb_substr(preg_replace('/\s+/u', ' ', (string) $pk) ?? '', 0, 24));
            if ($pk !== '' && count($perks) < Ads::MAX_PERKS) $perks[] = $pk;
        }
        $d['perks'] = $perks ? json_encode($perks, JSON_UNESCAPED_UNICODE) : null;
        $err = [];
        $today = Ads::today();
        if (mb_strlen($d['title']) < 3) $err['title'] = 'Напишіть заголовок, наприклад «Шукаємо водія категорії C»';
        if (mb_strlen($d['body']) < 10) $err['body'] = 'Коротко опишіть, кого шукаєте';
        if ($d['placement'] === '') $err['placement'] = 'Оберіть місце показу';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['starts_on']) || !strtotime($d['starts_on'])) $err['starts_on'] = 'Оберіть дату початку';
        elseif ($d['starts_on'] < $today) $err['starts_on'] = 'Дата початку не може бути в минулому';
        elseif ($d['starts_on'] > date('Y-m-d', strtotime('+60 days'))) $err['starts_on'] = 'Замовити можна не раніше ніж за 60 днів';
        if ($d['days'] < 1 || $d['days'] > Ads::MAX_DAYS) $err['days'] = 'Від 1 до ' . Ads::MAX_DAYS . ' днів';
        if ($err) throw new ValidationException($err);

        $d['ends_on'] = date('Y-m-d', strtotime($d['starts_on'] . ' +' . ($d['days'] - 1) . ' days'));
        $open = (int) $db->value("SELECT COUNT(*) FROM {p}banners WHERE employer_id = ? AND status IN ('pending','approved') AND ends_on >= ?", [$employer['id'], $today]);
        if ($open >= 2) throw new ValidationException([], 'Одночасно можна мати до 2 банерів. Дочекайтеся завершення або скасуйте один із них.');
        if ($full = Ads::firstFullDay($d['placement'], $d['starts_on'], $d['ends_on'])) {
            throw new ValidationException(['starts_on' => ' '], 'На ' . date('d.m', strtotime($full)) . ' усі рекламні місця вже зайняті. Оберіть інші дати або інше місце показу.');
        }
        $image = Media::store('banner', 'image', 'e' . $employer['id']);
        if ($d['template'] === 'photo' && !$image) throw new ValidationException(['image' => 'Для шаблону «Фото» додайте картинку або оберіть інший шаблон']);
        $price = Ads::monetizationOn() ? Ads::bannerPrice($d['placement'], $d['days']) : 0;
        $id = $db->insert('banners', [...$d, 'employer_id' => $employer['id'], 'created_by' => $me['id'], 'image' => $image, 'price' => $price, 'status' => 'pending']);
        Audit::log('ads.banner_ordered', 'banner', $id, ['days' => $d['days'], 'placement' => $d['placement'], 'price' => $price]);
        flash('ok', 'Банер надіслано на перевірку. Після схвалення він з’явиться на сайті в обрані дні.' . ($price ? ' Вартість: ' . money($price) . ' грн.' : ''));
        return Response::redirect(url('/cabinet/ads/' . $id));
    }

    public function view(Request $req): Response
    {
        $b = $this->own((int) $req->params['id']);
        $employer = $this->cab()->employer((int) $this->me()['id']);
        $stats = in_array($b['status'], ['approved', 'cancelled'], true) ? Ads::stats($b) : [];
        $total = ['impressions' => array_sum(array_column($stats, 'impressions')), 'clicks' => array_sum(array_column($stats, 'clicks'))];
        return $this->cab()->page('ad_view', 'ads', ['title' => $b['title'], 'b' => $b, 'employer' => $employer, 'stats' => $stats, 'total' => $total, 'paid' => Ads::monetizationOn()]);
    }

    public function cancel(Request $req): Response
    {
        $b = $this->own((int) $req->params['id']);
        if (!in_array($b['status'], ['pending', 'approved'], true) || $b['ends_on'] < Ads::today()) throw new HttpException(409, 'Цей банер уже не можна скасувати.');
        App::db()->update('banners', ['status' => 'cancelled', 'updated_at' => Db::now()], 'id = ?', [$b['id']]);
        Audit::log('ads.banner_cancelled', 'banner', $b['id']);
        flash('ok', 'Банер знято з показу.');
        return Response::redirect(url('/cabinet/ads/' . $b['id']));
    }

    /** Рахунок на оплату (лише коли ввімкнено монетизацію і банер платний). */
    public function invoice(Request $req): Response
    {
        $b = $this->own((int) $req->params['id']);
        if (!Ads::monetizationOn() || (int) $b['price'] === 0) throw new HttpException(404, 'Поки сайт розвивається, реклама безкоштовна — рахунок не потрібен.');
        $employer = App::db()->one('SELECT * FROM {p}employers WHERE id = ?', [$b['employer_id']]);
        $seller = (array) App::setting('billing.requisites', []);
        return new Response(View::render('cabinet/invoice', ['b' => $b, 'employer' => $employer, 'seller' => $seller, 'title' => 'Рахунок № Б-' . $b['id']], 'bare'));
    }
}
