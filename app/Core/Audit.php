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
        // Сповіщення в Telegram (якщо бота налаштовано). Помилка бота ніколи не ламає дію на сайті.
        try {
            Telegram::onAudit($action, $entityType, $entityId);
        } catch (\Throwable $e) {
            error_log('[YEWORK] telegram: ' . $e->getMessage());
        }
    }

    public const LABELS = [
        'install.completed' => 'Сайт встановлено', 'auth.login' => 'Вхід', 'auth.login_failed' => 'Невдала спроба входу', 'auth.logout' => 'Вихід',
        'auth.registered' => 'Реєстрація', 'auth.password_reset' => 'Відновлення пароля', 'auth.password_changed' => 'Зміна пароля',
        'user.blocked' => 'Користувача заблоковано', 'user.unblocked' => 'Користувача розблоковано',
        'team.reward_added' => 'Додано винагороду члену команди', 'team.reward_deleted' => 'Видалено запис винагороди',
        'access.role_granted' => 'Видано роль', 'access.role_revoked' => 'Знято роль',
        'jobs.employer_created' => 'Створено профіль роботодавця', 'jobs.employer_verified' => 'Роботодавця перевірено', 'jobs.employer_unverified' => 'Знято позначку «Перевірений роботодавець»',
        'jobs.vacancy_submitted' => 'Вакансію подано на модерацію', 'jobs.vacancy_published' => 'Вакансію опубліковано',
        'jobs.vacancy_approved' => 'Вакансію схвалено', 'jobs.vacancy_rejected' => 'Вакансію відхилено', 'jobs.vacancy_archived' => 'Вакансію перенесено в архів', 'jobs.vacancy_restored' => 'Вакансію поновлено з архіву',
        'jobs.vacancy_admin_edited' => 'Адміністрація виправила вакансію', 'identity.client_admin_edited' => 'Адміністрація виправила картку клієнта',
        'jobs.employer_admin_edited' => 'Адміністрація виправила профіль роботодавця', 'services.master_admin_edited' => 'Адміністрація виправила картку майстра', 'access.role_permissions' => 'Змінено права ролі',
        'theming.theme_published' => 'Опубліковано тему', 'theming.festive_updated' => 'Змінено новорічне оформлення', 'settings.updated' => 'Змінено налаштування', 'system.upgraded' => 'Оновлено базу даних',
        'system.updated' => 'Сайт оновлено', 'system.update_failed' => 'Оновлення не вдалося (повернуто як було)',
        'system.restored' => 'Відновлено з резервної копії', 'system.backup_created' => 'Створено резервну копію',
        'system.backup_deleted' => 'Видалено резервну копію',
        'system.backups_cleaned' => 'Видалено старі резервні копії', 'system.backups_keep' => 'Змінено, скільки резервних копій зберігати',
        'system.backup_downloaded' => 'Завантажено резервну копію', 'system.update_settings' => 'Змінено підключення до GitHub',
        'content.page_updated' => 'Змінено сторінку сайту', 'content.page_reset' => 'Сторінку повернуто до тексту за замовчуванням',
        'resume.created' => 'Створено резюме', 'resume.submitted' => 'Резюме подано на модерацію', 'resume.approved' => 'Резюме схвалено',
        'resume.rejected' => 'Резюме відхилено', 'resume.deleted' => 'Резюме видалено', 'resume.invited' => 'Запрошення за резюме',
        'profile.photo_uploaded' => 'Завантажено фото профілю', 'profile.photo_deleted' => 'Видалено фото профілю',
        'services.master_created' => 'Створено профіль майстра', 'services.master_submitted' => 'Профіль майстра подано на перевірку',
        'services.master_published' => 'Профіль майстра опубліковано', 'services.master_approved' => 'Профіль майстра схвалено',
        'services.master_rejected' => 'Профіль майстра відхилено', 'services.master_archived' => 'Профіль майстра в архіві',
        'services.master_restored' => 'Профіль майстра поновлено', 'services.master_paused' => 'Майстер поставив профіль на паузу',
        'services.master_resumed' => 'Майстер зняв профіль з паузи', 'services.prices_updated' => 'Майстер змінив прайс',
        'services.work_created' => 'Додано об’єкт у портфоліо', 'services.work_updated' => 'Змінено об’єкт у портфоліо', 'services.work_deleted' => 'Видалено об’єкт з портфоліо',
        'services.order_created' => 'Нове замовлення майстру', 'services.order_accept' => 'Майстер прийняв замовлення', 'services.order_decline' => 'Майстер відмовився від замовлення',
        'services.order_cancel' => 'Клієнт скасував замовлення', 'services.order_done' => 'Замовлення виконано',
        'services.review_saved' => 'Відгук про майстра', 'services.review_hidden' => 'Відгук приховано', 'services.review_shown' => 'Відгук повернуто',
        'services.category_added' => 'Додано категорію послуг', 'services.categories_updated' => 'Змінено категорії послуг', 'services.limits_updated' => 'Змінено ліміти портфоліо',
        'system.update_aborted' => 'Оновлення не встановлено (сайт не змінено)',
        'jobs.employer_logo' => 'Роботодавець змінив логотип', 'jobs.employer_logo_removed' => 'Роботодавець видалив логотип',
        'ads.banner_ordered' => 'Замовлено банер', 'ads.banner_edited' => 'Роботодавець змінив банер', 'ads.banner_deleted' => 'Роботодавець видалив банер',
        'ads.banner_cancelled' => 'Роботодавець скасував банер', 'ads.banner_paid' => 'Банер позначено оплаченим',
        'ads.banner_approved' => 'Банер схвалено', 'ads.banner_rejected' => 'Банер відхилено',
        'ads.banner_created_by_admin' => 'Адміністрація створила банер', 'ads.banner_edited_by_admin' => 'Адміністрація змінила банер',
        'ads.banner_deleted_by_admin' => 'Адміністрація видалила банер', 'ads.templates_updated' => 'Змінено шаблони банерів',
        'ads.slots_updated' => 'Змінено кількість банерів одночасно', 'billing.prices_updated' => 'Змінено ціни',
        'billing.sale_updated' => 'Змінено святкову акцію', 'billing.sale_caught' => 'Упіймано святкову знижку',
        'billing.monetization_enabled' => 'Увімкнено монетизацію', 'billing.monetization_disabled' => 'Вимкнено монетизацію',
        'resume.hidden' => 'Резюме приховано', 'resume.shown' => 'Резюме знову показується',
        'demo.added' => 'Додано приклади', 'demo.removed' => 'Видалено приклади',
    ];

    /** Дії, пов’язані з оновленням сайту: у журналах ведуть на сторінку «Оновлення». */
    public const UPDATE_ACTIONS = ['system.updated', 'system.update_failed', 'system.update_aborted', 'system.restored', 'system.upgraded'];

    public static function label(string $action): string
    {
        return self::LABELS[$action] ?? Telegram::LABELS[$action] ?? $action;
    }
}
