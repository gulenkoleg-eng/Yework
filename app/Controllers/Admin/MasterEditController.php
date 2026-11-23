<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\ServicesController;
use App\Controllers\SiteController;
use App\Core\Ads;
use App\Core\App;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Services;

/**
 * Адміністрація виправляє анкету майстра (0.5.24): профіль, опис, прайс, зону виїзду, фото й галерею робіт.
 * Використовує ту саму форму й ту саму логіку збереження, що й кабінет майстра. Стан профілю не змінюється
 * (повторної перевірки немає), власник отримує сповіщення з переліком змін. Потрібне право «Редагування оголошень (послуги)».
 */
final class MasterEditController
{
    public const PERM = 'services.listing:update';

    /** Звідки відкрили: рядок фільтрів списку «Майстри» або «client» (картка клієнта). */
    private static function back(Request $req): ?string
    {
        $b = $req->query['back'] ?? $req->post['back'] ?? null;
        return is_string($b) && $b !== '' ? mb_substr($b, 0, 600) : null;
    }

    public static function backUrl(?string $back, array $m): string
    {
        if ($back === 'client') return url('/admin/users/' . $m['user_id'] . '/edit') . '#master';
        if ($back === null) return url('/admin/masters', ['status' => 'all', 'q' => (string) $m['id']]);
        parse_str($back, $q);
        return ServicesAdminController::listUrl(ServicesAdminController::filters(is_array($q) ? $q : []));
    }

    /** Контекст для ServicesController: чужий профіль, адреси форм в адмінці. */
    private static function ctx(Request $req): array
    {
        $id = (int) $req->params['id'];
        $m = App::db()->one('SELECT * FROM {p}masters WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$m) throw new HttpException(404, 'Майстра не знайдено.');
        $back = self::back($req);
        $q = ['back' => $back];
        return ['uid' => (int) $m['user_id'], 'm' => $m, 'admin' => true, 'q' => $q, 'back' => $back,
            'base' => url('/admin/masters/' . $id . '/edit', $q), 'works' => '/admin/masters/' . $id . '/works', 'backUrl' => self::backUrl($back, $m)];
    }

    public function form(Request $req): Response
    {
        $ctx = self::ctx($req);
        $m = Services::find((int) $ctx['m']['id']) ?? $ctx['m'];
        [$settlements] = SiteController::reference();
        $owner = App::db()->one('SELECT id, display_name, login FROM {p}users WHERE id = ?', [$m['user_id']]);
        return new Response(view('cabinet/master', [
            'title' => 'Анкета майстра: ' . $m['name'], 'm' => $m, 'adminEdit' => true, 'ctx' => $ctx, 'owner' => $owner,
            'cats' => Services::categories(false), 'settlements' => $settlements,
            'travelIds' => array_map('intval', array_column(Services::travelAreas((int) $m['id']), 'id')),
            'prices' => Services::prices((int) $m['id']), 'works' => Services::works((int) $m['id']),
            'limits' => Services::limits(), 'myPhone' => null, 'premod' => Services::premoderation(),
            'paid' => Ads::monetizationOn(), 'billPrices' => Ads::prices(), 'newOrders' => 0, 'me' => App::auth()->user(),
        ]));
    }

    public function save(Request $req): Response
    {
        $ctx = self::ctx($req);
        $sc = new ServicesController();
        return $req->str('section', 12) === 'prices' ? $sc->pricesSave($req, $ctx) : $sc->profileSave($req, $ctx);
    }

    public function workForm(Request $req): Response { return (new ServicesController())->workFormFor($req, self::ctx($req)); }

    public function workSave(Request $req): Response { return (new ServicesController())->workSaveFor($req, self::ctx($req)); }

    public function workDelete(Request $req): Response { return (new ServicesController())->workDeleteFor($req, self::ctx($req)); }
}
