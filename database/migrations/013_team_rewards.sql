-- YEWORK 0.5.21: винагорода членам команди. Записи додає й видаляє лише головний адміністратор,
-- член команди бачить свої записи в кабінеті (вкладка «Винагорода»). IF NOT EXISTS — повторний запуск нічого не ламає.
CREATE TABLE IF NOT EXISTS {p}team_rewards (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  paid_on DATE NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  comment VARCHAR(300) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY team_rewards_user_idx (user_id, paid_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
