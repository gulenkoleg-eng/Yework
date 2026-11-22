-- YEWORK 0.5.0: розділ «Послуги» — майстри, прайс, портфоліо (об'єкти з фото), замовлення через сайт і відгуки.
-- Відгук і зірки може залишити лише клієнт, чиє замовлення через сайт виконано (один відгук від клієнта на майстра).

CREATE TABLE IF NOT EXISTS {p}service_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(40) NOT NULL,
  name VARCHAR(80) NOT NULL,
  sort INT NOT NULL DEFAULT 100,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY svc_cat_slug_uq (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Профіль майстра: один на користувача.
-- status: draft | pending | published | rejected | archived. needs_review = 1 — опублікований профіль змінено, модератор ще не переглянув.
CREATE TABLE IF NOT EXISTS {p}masters (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  category2_id BIGINT UNSIGNED NULL,
  settlement_id BIGINT UNSIGNED NULL,
  areas VARCHAR(200) NULL COMMENT 'Де працює: села, радіус виїзду',
  experience_years SMALLINT UNSIGNED NULL,
  about TEXT NULL,
  contact_phone VARCHAR(16) NULL COMMENT 'Робочий телефон майстра — публічний за задумом (за кнопкою «Показати номер»)',
  photo VARCHAR(64) NULL,
  legal_form VARCHAR(10) NULL COMMENT 'person | ФОП | ТОВ | ПП',
  payments VARCHAR(40) NULL COMMENT 'Способи оплати через кому: cash, card, invoice',
  period_months TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Термін показу: 1, 2, 3 або 12 місяців',
  show_to DATE NULL COMMENT 'Останній день показу (за Києвом); далі — в архів',
  archived_at DATETIME NULL,
  archive_reason VARCHAR(10) NULL COMMENT 'expired | manual',
  status VARCHAR(12) NOT NULL DEFAULT 'draft',
  needs_review TINYINT(1) NOT NULL DEFAULT 0,
  is_hidden TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Пауза: майстер тимчасово не приймає замовлення',
  reject_reason VARCHAR(500) NULL,
  submitted_at DATETIME NULL,
  moderated_by BIGINT UNSIGNED NULL,
  moderated_at DATETIME NULL,
  published_at DATETIME NULL,
  rating_sum INT UNSIGNED NOT NULL DEFAULT 0,
  rating_count INT UNSIGNED NOT NULL DEFAULT 0,
  orders_done INT UNSIGNED NOT NULL DEFAULT 0,
  views INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY masters_user_uq (user_id),
  KEY masters_list_idx (status, is_hidden, category_id),
  KEY masters_period_idx (status, show_to),
  CONSTRAINT {p}masters_user_fk FOREIGN KEY (user_id) REFERENCES {p}users (id),
  CONSTRAINT {p}masters_cat_fk FOREIGN KEY (category_id) REFERENCES {p}service_categories (id),
  CONSTRAINT {p}masters_settlement_fk FOREIGN KEY (settlement_id) REFERENCES {p}settlements (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Прайс. kind: service (послуга) | goods (обладнання, матеріали).
-- price_type: fixed (ціна) | range (від–до) | from (від) | deal (договірна).
CREATE TABLE IF NOT EXISTS {p}master_prices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  master_id BIGINT UNSIGNED NOT NULL,
  kind VARCHAR(8) NOT NULL DEFAULT 'service',
  title VARCHAR(160) NOT NULL,
  price_type VARCHAR(6) NOT NULL DEFAULT 'fixed',
  price_from INT UNSIGNED NULL,
  price_to INT UNSIGNED NULL,
  unit VARCHAR(8) NOT NULL DEFAULT 'job',
  note VARCHAR(300) NULL,
  sort INT NOT NULL DEFAULT 0,
  KEY master_prices_idx (master_id, sort),
  CONSTRAINT {p}master_prices_fk FOREIGN KEY (master_id) REFERENCES {p}masters (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Портфоліо: виконані об'єкти. Кількість безкоштовних об'єктів і фото — налаштування в адмінці (Монетизація).
CREATE TABLE IF NOT EXISTS {p}master_works (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  master_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT NULL,
  place VARCHAR(120) NULL,
  done_on VARCHAR(20) NULL,
  sort INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY master_works_idx (master_id, sort),
  CONSTRAINT {p}master_works_fk FOREIGN KEY (master_id) REFERENCES {p}masters (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {p}master_work_photos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  work_id BIGINT UNSIGNED NOT NULL,
  file VARCHAR(64) NOT NULL,
  sort INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY work_photos_idx (work_id, sort),
  CONSTRAINT {p}work_photos_fk FOREIGN KEY (work_id) REFERENCES {p}master_works (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Замовлення через сайт. status: new | accepted | done | declined | cancelled.
CREATE TABLE IF NOT EXISTS {p}service_orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  master_id BIGINT UNSIGNED NOT NULL,
  client_id BIGINT UNSIGNED NOT NULL,
  price_id BIGINT UNSIGNED NULL,
  price_title VARCHAR(160) NULL,
  message TEXT NOT NULL,
  contact VARCHAR(40) NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'new',
  done_by VARCHAR(8) NULL COMMENT 'master | client — хто позначив «Виконано»',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  accepted_at DATETIME NULL,
  done_at DATETIME NULL,
  KEY orders_master_idx (master_id, status),
  KEY orders_client_idx (client_id, status),
  CONSTRAINT {p}orders_master_fk FOREIGN KEY (master_id) REFERENCES {p}masters (id),
  CONSTRAINT {p}orders_client_fk FOREIGN KEY (client_id) REFERENCES {p}users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Відгуки: лише після виконаного замовлення, один від клієнта на майстра (повторне замовлення дозволяє оновити відгук).
CREATE TABLE IF NOT EXISTS {p}master_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  master_id BIGINT UNSIGNED NOT NULL,
  client_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  body TEXT NULL,
  reply TEXT NULL,
  reply_at DATETIME NULL,
  is_hidden TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Приховано модератором',
  hidden_reason VARCHAR(300) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY reviews_client_uq (master_id, client_id),
  KEY reviews_master_idx (master_id, is_hidden, created_at),
  CONSTRAINT {p}reviews_master_fk FOREIGN KEY (master_id) REFERENCES {p}masters (id),
  CONSTRAINT {p}reviews_client_fk FOREIGN KEY (client_id) REFERENCES {p}users (id),
  CONSTRAINT {p}reviews_order_fk FOREIGN KEY (order_id) REFERENCES {p}service_orders (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
