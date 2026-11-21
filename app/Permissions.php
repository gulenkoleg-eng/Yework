<?php
declare(strict_types=1);

namespace App;

/**
 * Каталог прав YEWORK. Джерело істини — цей файл; при встановленні та оновленні синхронізується в таблицю permissions.
 * Формат коду: <модуль>.<ресурс>:<дія>. Критичні права не входять у жодну роль — видаються окремо (critical_grants).
 */
final class Permissions
{
    /** @return list<array{code:string,module:string,resource:string,action:string,critical:bool,description:string}> */
    public static function all(): array
    {
        static $list = null;
        if ($list !== null) return $list;
        $p = static fn (string $m, string $r, string $a, string $d, bool $c = false) => ['code' => "$m.$r:$a", 'module' => $m, 'resource' => $r, 'action' => $a, 'critical' => $c, 'description' => $d];
        $list = [
            $p('identity', 'user', 'view', 'Перегляд користувачів'),
            $p('identity', 'user', 'update', 'Редагування та блокування користувачів'),
            $p('identity', 'user', 'view_contacts', 'Перегляд телефонів і email користувачів'),
            $p('access', 'role', 'view', 'Перегляд ролей і прав'),
            $p('access', 'role', 'manage_settings', 'Створення та зміна ролей, призначення ролей', true),
            $p('access', 'critical', 'assign', 'Видача критичних прав', true),
        ];
        foreach (['jobs' => 'вакансії', 'gigs' => 'підробіток', 'services' => 'послуги'] as $m => $title) {
            $list[] = $p($m, 'listing', 'view', "Перегляд оголошень ($title)");
            $list[] = $p($m, 'listing', 'update', "Редагування оголошень ($title)");
            $list[] = $p($m, 'listing', 'delete', "Видалення оголошень ($title)");
            $list[] = $p($m, 'listing', 'moderate', "Модерація оголошень ($title)");
        }
        array_push($list,
            $p('moderation', 'policy', 'manage_settings', 'Налаштування модерації'),
            $p('ads', 'banner', 'moderate', 'Модерація банерної реклами роботодавців'),
            $p('messaging', 'support', 'view', 'Звернення в підтримку'),
            $p('theming', 'theme', 'view', 'Перегляд тем оформлення'),
            $p('theming', 'theme', 'update', 'Створення та редагування тем (чернетки)'),
            $p('theming', 'theme', 'publish', 'Публікація теми для всього сайту', true),
            $p('billing', 'plan', 'view', 'Перегляд тарифів і платежів'),
            $p('billing', 'plan', 'update', 'Редагування тарифів'),
            $p('billing', 'monetization', 'manage_settings', 'Увімкнення та вимкнення монетизації', true),
            $p('ai', 'provider', 'manage_settings', 'Налаштування AI-провайдерів і ключів', true),
            $p('ai', 'usage', 'view', 'Перегляд витрат на AI'),
            $p('stats', 'dashboard', 'view', 'Статистика (агреговані дані)'),
            $p('data', 'export', 'run', 'Експорт у межах свого скоупу'),
            $p('data', 'export_global', 'run', 'Глобальний експорт усіх даних', true),
            $p('data', 'backup', 'manage_settings', 'Керування резервними копіями', true),
            $p('data', 'restore', 'run', 'Відновлення з резервної копії', true),
            $p('settings', 'system', 'manage_settings', 'Системні налаштування', true),
            $p('audit', 'log', 'view', 'Перегляд журналу дій'),
            $p('system', 'update', 'run', 'Оновлення сайту з GitHub, резервні копії та відкат'),
        );
        return $list;
    }

    public static function codes(?callable $filter = null): array
    {
        $out = [];
        foreach (self::all() as $x) if (!$x['critical'] && (!$filter || $filter($x))) $out[] = $x['code'];
        return $out;
    }

    public static function critical(): array
    {
        return array_values(array_map(static fn ($x) => $x['code'], array_filter(self::all(), static fn ($x) => $x['critical'])));
    }

    /** Системні ролі. owner = усі права (окремо, не через role_permissions). */
    public static function roles(): array
    {
        $content = static fn (array $mods, array $acts) => self::codes(static fn ($x) => in_array($x['module'], $mods, true) && in_array($x['action'], $acts, true));
        return [
            ['slug' => 'owner', 'name' => 'Головний адміністратор', 'description' => 'Власник платформи. Єдиний, хто видає критичні права.', 'permissions' => '*'],
            ['slug' => 'senior_admin', 'name' => 'Старший адміністратор', 'description' => 'Користувачі, контент, модерація, налаштування без критичних прав.', 'permissions' => self::codes()],
            ['slug' => 'moderator_jobs', 'name' => 'Модератор вакансій', 'description' => 'Модерація вакансій і рекламних банерів.', 'permissions' => [...$content(['jobs'], ['view', 'moderate']), 'ads.banner:moderate']],
            ['slug' => 'moderator_services', 'name' => 'Модератор послуг', 'description' => 'Модерація карток послуг і відгуків.', 'permissions' => $content(['services'], ['view', 'moderate'])],
            ['slug' => 'moderator_regional', 'name' => 'Регіональний модератор', 'description' => 'Модерація всіх напрямків у призначеному регіоні.', 'permissions' => $content(['jobs', 'gigs', 'services'], ['view', 'moderate'])],
            ['slug' => 'operator', 'name' => 'Оператор підтримки', 'description' => 'Звернення та перегляд користувачів без контактів.', 'permissions' => ['identity.user:view', 'messaging.support:view']],
            ['slug' => 'finance_admin', 'name' => 'Фінансовий адміністратор', 'description' => 'Тарифи, ціни й оплати банерів. Перемикач монетизації — лише окремим критичним правом.', 'permissions' => ['billing.plan:view', 'billing.plan:update']],
            ['slug' => 'analyst', 'name' => 'Аналітик', 'description' => 'Лише агрегована статистика, без персональних даних.', 'permissions' => ['stats.dashboard:view', 'ai.usage:view']],
            ['slug' => 'designer', 'name' => 'Дизайнер', 'description' => 'Чернетки тем без права публікації.', 'permissions' => ['theming.theme:view', 'theming.theme:update']],
        ];
    }
}
