-- YEWORK 0.5.26: святкова акція-заставка. sale_claims — хто встиг натиснути на заставку і впіймав знижку,
-- sale_uses — до яких банерів знижку вже застосовано (щоб після акції редагування банера її не скасувало).
-- IF NOT EXISTS — повторний запуск нічого не ламає.
CREATE TABLE IF NOT EXISTS {p}sale_claims (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  campaign VARCHAR(32) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  event VARCHAR(20) NOT NULL,
  name VARCHAR(80) NOT NULL,
  percent TINYINT UNSIGNED NOT NULL,
  ends_on DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY sale_claims_once (campaign, user_id),
  KEY sale_claims_user_idx (user_id, ends_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {p}sale_uses (
  banner_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  claim_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  percent TINYINT UNSIGNED NOT NULL,
  full_price INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY sale_uses_claim_idx (claim_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
