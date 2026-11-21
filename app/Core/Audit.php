<?php
declare(strict_types=1);

namespace App\Core;

/** Журнал дій: лише дописування. */
final class Audit
{
    public static function log(string $action, ?string $entityType = null, int|string|null $entityId = null, array $data = [], ?int $actorId = null): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        App::db()->insert('audit_log', [
            'actor_id' => $actorId ?? App::auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId === null ? null : (string) $entityId,
            'ip' => $ip,
            'data' => $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    public const LABELS = [
        'install.completed' => 'Сайт встановлено', 'auth.login' => 'Вхід', 'auth.login_failed' => 'Невдала спроба входу', 'auth.logout' => 'Вихід',
        'auth.registered' => 'Реєстрація', 'auth.password_reset' => 'Відновлення пароля', 'auth.password_changed' => 'Зміна пароля',
        'user.blocked' => 'Користувача заблоковано', 'user.unblocked' => 'Користувача розблоковано',
        'access.role_granted' => 'Видано роль', 'access.role_revoked' => 'Знято роль',
        'jobs.employer_created' => 'Створено профіль роботодавця', 'jobs.employer_verified' => 'Роботодавця перевірено',
        'jobs.vacancy_submitted' => 'Вакансію подано на модерацію', 'jobs.vacancy_published' => 'Вакансію опубліковано',
        'jobs.vacancy_approved' => 'Вакансію схвалено', 'jobs.vacancy_rejected' => 'Вакансію відхилено', 'jobs.vacancy_archived' => 'Вакансію знято',
        'theming.theme_published' => 'Опубліковано тему', 'settings.updated' => 'Змінено налаштування', 'system.upgraded' => 'Оновлено базу даних',
        'system.updated' => 'Сайт оновлено', 'system.update_failed' => 'Оновлення не вдалося (повернуто як було)',
        'system.restored' => 'Відновлено з резервної копії', 'system.backup_created' => 'Створено резервну копію',
        'system.backup_deleted' => 'Видалено резервну копію', 'system.update_settings' => 'Змінено підключення до GitHub',
    ];

    public static function label(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }
}
