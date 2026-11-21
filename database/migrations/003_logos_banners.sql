-- 0.4.0: логотип і форма власності роботодавця, банерна реклама зі статистикою.

ALTER TABLE {p}employers
  ADD COLUMN legal_form VARCHAR(16) NULL COMMENT 'ТОВ, ФОП, ПП, ФГ…' AFTER kind,
  ADD COLUMN logo VARCHAR(80) NULL COMMENT 'Файл у storage/uploads/logo' AFTER website,
  ADD COLUMN logo_updated_at DATETIME NULL AFTER logo;

UPDATE {p}employers SET legal_form = 'ФОП' WHERE kind = 'fop' AND legal_form IS NULL;

CREATE TABLE {p}banners (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  employer_id BIGINT UNSIGNED NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  title VARCHAR(80) NOT NULL,
  body VARCHAR(300) NOT NULL COMMENT 'Кого шукаємо',
  image VARCHAR(80) NULL COMMENT 'Файл у storage/uploads/banner',
  placement VARCHAR(8) NOT NULL DEFAULT 'both' COMMENT 'home | jobs | both',
  starts_on DATE NOT NULL,
  ends_on DATE NOT NULL,
  days SMALLINT UNSIGNED NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending' COMMENT 'pending | approved | rejected | cancelled',
  reject_reason VARCHAR(500) NULL,
  price INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ціна в гривнях на момент замовлення; 0 — безкоштовно',
  paid_at DATETIME NULL,
  moderated_by BIGINT UNSIGNED NULL,
  moderated_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY banners_live_idx (status, starts_on, ends_on),
  KEY banners_employer_idx (employer_id),
  CONSTRAINT {p}banners_employer_fk FOREIGN KEY (employer_id) REFERENCES {p}employers (id),
  CONSTRAINT {p}banners_user_fk FOREIGN KEY (created_by) REFERENCES {p}users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {p}banner_stats (
  banner_id BIGINT UNSIGNED NOT NULL,
  day DATE NOT NULL,
  impressions INT UNSIGNED NOT NULL DEFAULT 0,
  clicks INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (banner_id, day),
  CONSTRAINT {p}banner_stats_fk FOREIGN KEY (banner_id) REFERENCES {p}banners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
