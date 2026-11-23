<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Сповіщення людині в кабінеті (0.5.3). Поки що одне: «Адміністрація сайту виправила ваше оголошення».
 * Сповіщення видно вгорі кабінету й на сторінці оголошення, доки людина не натисне «Зрозуміло».
 */
final class Notices
{
    public static function add(int $userId, string $kind, string $title, string $body, ?string $link = null, ?string $entityType = null, ?int $entityId = null): void
    {
        App::db()->insert('user_notices', ['user_id' => $userId, 'kind' => $kind, 'title' => mb_substr($title, 0, 200), 'body' => $body,
            'link' => $link, 'entity_type' => $entityType, 'entity_id' => $entityId]);
    }

    /** Непрочитані сповіщення людини (усі або лише щодо одного оголошення). До оновлення бази повертає порожній список. */
    public static function unread(int $userId, ?string $entityType = null, ?int $entityId = null): array
    {
        try {
            $sql = 'SELECT * FROM {p}user_notices WHERE user_id = ? AND read_at IS NULL';
            $p = [$userId];
            if ($entityType !== null) { $sql .= ' AND entity_type = ? AND entity_id = ?'; array_push($p, $entityType, $entityId); }
            return App::db()->all($sql . ' ORDER BY id DESC LIMIT 20', $p);
        } catch (\PDOException) {
            return [];
        }
    }

    public static function markRead(int $userId, int $id): void
    {
        App::db()->run('UPDATE {p}user_notices SET read_at = UTC_TIMESTAMP() WHERE id = ? AND user_id = ? AND read_at IS NULL', [$id, $userId]);
    }
}
