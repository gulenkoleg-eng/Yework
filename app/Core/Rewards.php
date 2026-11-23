<?php
declare(strict_types=1);

namespace App\Core;

/** Винагорода членам команди (0.5.21): дата + сума в гривнях + коментар. */
final class Rewards
{
    public const MONTHS = [1 => 'Січень', 'Лютий', 'Березень', 'Квітень', 'Травень', 'Червень', 'Липень', 'Серпень', 'Вересень', 'Жовтень', 'Листопад', 'Грудень'];

    /** Записи людини, згруповані за місяцями (новіші вгорі). Якщо таблиці ще немає — порожньо, сторінка не падає. */
    public static function months(int $userId): array
    {
        try {
            $rows = App::db()->all('SELECT id, paid_on, amount, comment, created_at FROM {p}team_rewards WHERE user_id = ? ORDER BY paid_on DESC, id DESC', [$userId]);
        } catch (\PDOException) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $ym = substr((string) $r['paid_on'], 0, 7);
            $out[$ym] ??= ['label' => self::MONTHS[(int) substr($ym, 5, 2)] . ' ' . substr($ym, 0, 4), 'total' => 0.0, 'rows' => []];
            $out[$ym]['total'] += (float) $r['amount'];
            $out[$ym]['rows'][] = $r;
        }
        return $out;
    }

    public static function total(array $months): float
    {
        return array_sum(array_column($months, 'total'));
    }

    /** Суми за поточний місяць по людях — для списку «Команда». */
    public static function thisMonth(): array
    {
        try {
            $rows = App::db()->all('SELECT user_id, SUM(amount) AS s FROM {p}team_rewards WHERE paid_on >= ? GROUP BY user_id', [date('Y-m-01')]);
        } catch (\PDOException) {
            return [];
        }
        return array_column($rows, 's', 'user_id');
    }

    /** 1 500 або 1 500,50 */
    public static function uah(float|string $v): string
    {
        $v = (float) $v;
        return number_format($v, fmod($v, 1.0) == 0.0 ? 0 : 2, ',', ' ') . ' грн';
    }
}
