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
use App\Core\Sale;
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
        $targets = $employer ? Ads::targetVacancies([$employer['id']]) : [];
        return $this->cab()->page('ads', 'ads', ['title' => 'Реклама і послуги', 'employer' => $employer, 'rows' => $rows, 'published' => $published,
            'paid' => Ads::monetizationOn(), 'prices' => Ads::prices(), 'today' => Ads::today(), 'templates' => Ads::availableTemplates(), 'tplDefault' => Ads::templateSettings()['default'],
            'targets' => $targets, 'targetNow' => count($targets) === 1 ? (string) $targets[0]['id'] : '', 'occupancy' => Ads::occupancy(), 'sale' => Sale::activeFor((int) $me['id'])]);
    }

    public function order(Request $req): Response
    {
        $me = $this->me();
        $db = App::db();
        $employer = $this->cab()->employer((int) $me['id']);
        if (!$employer) return Response::redirect(url('/cabinet/employer'));
        if ($employer['status'] !== 'active') throw new HttpException(403, 'Профіль роботодавця заблоковано.');

        $d = Ads::bannerInput($req, array_keys(Ads::availableTemplates()));
        $today = Ads::today();
        $err = Ads::bannerErrors($d);
        [$d['vacancy_id'], $terr] = Ads::targetInput($req, (int) $employer['id']);
        if ($terr) $err['target'] = $terr;
        if ($err) throw new ValidationException($err);

        $d['ends_on'] = date('Y-m-d', strtotime($d['starts_on'] . ' +' . ($d['days'] - 1) . ' days'));
        $open = (int) $db->value("SELECT COUNT(*) FROM {p}banners WHERE employer_id = ? AND status IN ('pending','approved') AND ends_on >= ?", [$employer['id'], $today]);
        if ($open >= 2) throw new ValidationException([], 'Одночасно можна мати до 2 банерів. Дочекайтеся завершення або скасуйте один із них.');
        if ($full = Ads::firstFullDay($d['placement'], $d['starts_on'], $d['ends_on'])) {
            throw new ValidationException(['starts_on' => ' '], Ads::fullMessage($d['placement'], $full, $d['days']));
        }
        $image = Media::store('banner', 'image', 'e' . $employer['id']);
        if ($d['template'] === 'photo' && !$image) throw new ValidationException(['image' => 'Для шаблону «Фото» додайте картинку або оберіть інший шаблон']);
        $full = Ads::monetizationOn() ? Ads::bannerPrice($d['placement'], $d['days']) : 0;
        [$price, $sale] = Sale::bannerPrice($full, (int) $me['id']); // святкова знижка (0.5.26)
        $id = $db->insert('banners', [...$d, 'employer_id' => $employer['id'], 'created_by' => $me['id'], 'image' => $image, 'price' => $price, 'status' => 'pending']);
        if ($sale) Sale::remember($id, $sale, $full);
        Audit::log('ads.banner_ordered', 'banner', $id, ['days' => $d['days'], 'placement' => $d['placement'], 'price' => $price] + ($sale ? ['sale' => $sale['percent']] : []));
        flash('ok', 'Банер надіслано на перевірку. Після схвалення він з’явиться на сайті в обрані дні.' . ($price ? ' Вартість: ' . money($price) . ' грн' . ($sale ? ' зі знижкою «' . $sale['name'] . '» −' . $sale['percent'] . '%' : '') . '.' : ''));
        return Response::redirect(url('/cabinet/ads/' . $id));
    }

    public function view(Request $req): Response
    {
        $b = $this->own((int) $req->params['id']);
        $employer = $this->cab()->employer((int) $this->me()['id']);
        $stats = in_array($b['status'], ['approved', 'cancelled'], true) ? Ads::stats($b) : [];
        $total = ['impressions' => array_sum(array_column($stats, 'impressions')), 'clicks' => array_sum(array_column($stats, 'clicks'))];
        return $this->cab()->page('ad_view', 'ads', ['title' => $b['title'], 'b' => $b, 'target' => Ads::targetVacancy($b), 'employer' => $employer, 'stats' => $stats, 'total' => $total, 'paid' => Ads::monetizationOn(), 'saleUse' => Sale::bannerUse((int) $b['id'])]);
    }

    /** Редагування свого банера (0.5.15). Змінений текст чи вигляд схваленого банера знову йде на перевірку; зміна вакансії — ні. */
    public function edit(Request $req): Response
    {
        $b = $this->own((int) $req->params['id']);
        if (!Ads::employerCanEdit($b)) throw new HttpException(409, 'Цей банер уже завершився або знятий з показу, редагувати його не можна. Його можна видалити або замовити новий.');
        $employer = $this->cab()->employer((int) $this->me()['id']);
        if (!App::$old) App::$old = Ads::formValues($b);
        $templates = Ads::availableTemplates();
        if (!isset($templates[$b['template']]) && isset(Ads::TEMPLATES[$b['template']])) $templates[$b['template']] = Ads::TEMPLATES[$b['template']]; // свій шаблон лишається, навіть якщо його вимкнули
        return $this->cab()->page('ad_edit', 'ads', ['title' => 'Редагувати банер', 'b' => $b, 'employer' => $employer, 'templates' => $templates,
            'targets' => Ads::targetVacancies([$b['employer_id']]), 'locked' => Ads::datesLocked($b), 'paid' => Ads::monetizationOn(), 'prices' => Ads::prices(), 'occupancy' => Ads::occupancy((int) $b['id'])]);
    }

    public function update(Request $req): Response
    {
        $b = $this->own((int) $req->params['id']);
        if (!Ads::employerCanEdit($b)) throw new HttpException(409, 'Цей банер уже не можна редагувати.');
        $allowed = array_keys(Ads::availableTemplates());
        if (isset(Ads::TEMPLATES[$b['template']])) $allowed[] = $b['template'];
        $d = Ads::bannerInput($req, array_values(array_unique($allowed)));
        $locked = Ads::datesLocked($b);
        if ($locked) { $d['placement'] = $b['placement']; $d['starts_on'] = $b['starts_on']; $d['days'] = (int) $b['days']; }
        $err = Ads::bannerErrors($d);
        if ($locked || $d['starts_on'] === $b['starts_on']) unset($err['starts_on']); // дата вже стояла — не вимагаємо, щоб вона була в майбутньому
        [$d['vacancy_id'], $terr] = Ads::targetInput($req, (int) $b['employer_id']);
        if ($terr) $err['target'] = $terr;
        if ($err) throw new ValidationException($err);
        $d['ends_on'] = date('Y-m-d', strtotime($d['starts_on'] . ' +' . ($d['days'] - 1) . ' days'));
        if (!$locked && ($full = Ads::firstFullDay($d['placement'], $d['starts_on'], $d['ends_on'], (int) $b['id']))) {
            throw new ValidationException(['starts_on' => ' '], Ads::fullMessage($d['placement'], $full, $d['days'], (int) $b['id']));
        }
        $d['image'] = Ads::editImage($req, $b);
        if ($d['template'] === 'photo' && !$d['image']) throw new ValidationException(['image' => 'Для шаблону «Фото» додайте картинку або оберіть інший шаблон']);
        if (!$locked) {
            $full = Ads::monetizationOn() ? Ads::bannerPrice($d['placement'], $d['days']) : 0;
            [$d['price'], $sale] = Sale::bannerPrice($full, (int) $this->me()['id'], (int) $b['id']);
            Sale::remember((int) $b['id'], $sale, $full);
        }

        // Що змінилося, крім вакансії? Схвалений банер зі зміненим текстом чи виглядом знову перевіряє модератор.
        $content = ['title', 'body', 'badge', 'perks', 'cta', 'template', 'image', 'placement', 'starts_on', 'days'];
        $changed = array_values(array_filter($content, static fn ($k) => (string) ($d[$k] ?? '') !== (string) ($b[$k] ?? '')));
        $set = [...$d, 'updated_at' => Db::now()];
        $recheck = $b['status'] === 'rejected' || ($b['status'] === 'approved' && $changed);
        if ($recheck) $set += ['status' => 'pending', 'reject_reason' => null, 'moderated_by' => null, 'moderated_at' => null];
        App::db()->update('banners', $set, 'id = ?', [$b['id']]);
        Audit::log('ads.banner_edited', 'banner', $b['id'], ['changed' => $changed, 'vacancy' => $d['vacancy_id'], 'recheck' => $recheck]);
        flash('ok', $recheck ? 'Зміни збережено. Банер знову перевірить модератор, після схвалення він з’явиться на сайті.' : 'Зміни збережено.');
        return Response::redirect(url('/cabinet/ads/' . $b['id']));
    }

    public function delete(Request $req): Response
    {
        $b = $this->own((int) $req->params['id']);
        if (!Ads::employerCanDelete($b)) throw new HttpException(409, 'Оплачений банер не можна видалити: він потрібен для обліку оплат. Його можна зняти з показу.');
        Ads::delete($b);
        Audit::log('ads.banner_deleted', 'banner', $b['id'], ['title' => $b['title'], 'status' => $b['status']]);
        flash('ok', 'Банер «' . $b['title'] . '» видалено.');
        return Response::redirect(url('/cabinet/ads'));
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
        return new Response(View::render('cabinet/invoice', ['b' => $b, 'employer' => $employer, 'seller' => $seller, 'saleUse' => Sale::bannerUse((int) $b['id']), 'title' => 'Рахунок № Б-' . $b['id']], 'bare'));
    }
}
