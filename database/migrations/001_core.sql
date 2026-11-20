-- YEWORK: базова структура. MySQL 8.0+ / MariaDB 10.6+, utf8mb4.
-- {p} — префікс таблиць, який обирається в інсталяторі.

CREATE TABLE {p}users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  login VARCHAR(32) NOT NULL,
  display_name VARCHAR(80) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  phone_hash CHAR(64) NULL COMMENT 'HMAC номера для пошуку; сам номер лише зашифрований',
  phone_enc VARCHAR(255) NULL,
  phone_verified_at DATETIME NULL,
  email_hash CHAR(64) NULL,
  email_enc VARCHAR(512) NULL,
  email_verified_at DATETIME NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'active',
  owner_flag TINYINT NULL COMMENT '1 лише в головного адміністратора; унікальний індекс гарантує одного власника',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY users_login_uq (login),
  UNIQUE KEY users_phone_uq (phone_hash),
  UNIQUE KEY users_email_uq (email_hash),
  UNIQUE KEY users_single_owner_uq (owner_flag),
  KEY users_created_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {p}user_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(300) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  UNIQUE KEY sessions_token_uq (token_hash),
  KEY sessions_user_idx (user_id),
  CONSTRAINT {p}sessions_user_fk FOREIGN KEY (user_id) REFERENCES {p}users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {p}verification_codes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  purpose VARCHAR(20) NOT NULL,
  channel VARCHAR(10) NOT NULL,
  target_hash CHAR(64) NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  code_hash CHAR(64) NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY vc_target_idx (target_hash, purpose, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- права доступу ----------
CREATE TABLE {p}permissions (
  code VARCHAR(80) NOT NULL PRIMARY KEY,
  module VARCHAR(40) NOT NULL,
  resource VARCHAR(40) NOT NULL,
  action VARCHAR(30) NOT NULL,
  is_critical TINYINT(1) NOT NULL DEFAULT 0,
  description VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {p}roles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(64) NOT NULL,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(500) NOT NULL DEFAULT '',
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY roles_slug_uq (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {p}role_permissions (
  role_id BIGINT UNSIGNED NOT NULL,
  permission_code VARCHAR(80) NOT NULL,
  PRIMARY KEY (role_id, permission_code),
  CONSTRAINT {p}rp_role_fk FOREIGN KEY (role_id) REFERENCES {p}roles (id) ON DELETE CASCADE,
  CONSTRAINT {p}rp_perm_fk FOREIGN KEY (permission_code) REFERENCES {p}permissions (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {p}user_roles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  granted_by BIGINT UNSIGNED NULL,
  granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  UNIQUE KEY user_roles_uq (user_id, role_id),
  CONSTRAINT {p}ur_user_fk FOREIGN KEY (user_id) REFERENCES {p}users (id),
  CONSTRAINT {p}ur_role_fk FOREIGN KEY (role_id) REFERENCES {p}roles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Немає рядків = роль діє глобально. scope_type: region | category | direction.
CREATE TABLE {p}role_assignment_scopes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_role_id BIGINT UNSIGNED NOT NULL,
  scope_type VARCHAR(20) NOT NULL,
  scope_id VARCHAR(64) NOT NULL,
  KEY ras_ur_idx (user_role_id),
  CONSTRAINT {p}ras_ur_fk FOREIGN KEY (user_role_id) REFERENCES {p}user_roles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {p}critical_grants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  permission_code VARCHAR(80) NOT NULL,
  granted_by BIGINT UNSIGNED NULL,
  reason VARCHAR(500) NOT NULL,
  granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  revoked_at DATETIME NULL,
  KEY cg_user_idx (user_id),
  CONSTRAINT {p}cg_user_fk FOREIGN KEY (user_id) REFERENCES {p}users (id),
  CONSTRAINT {p}cg_perm_fk FOREIGN KEY (permission_code) REFERENCES {p}permissions (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- журнал дій (лише дописування) ----------
CREATE TABLE {p}audit_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actor_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(40) NULL,
  entity_id VARCHAR(64) NULL,
  ip VARCHAR(45) NULL,
  data JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY audit_actor_idx (actor_id, created_at),
  KEY audit_entity_idx (entity_type, entity_id),
  KEY audit_action_idx (action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- налаштування ----------
CREATE TABLE {p}settings (
  `key` VARCHAR(100) NOT NULL PRIMARY KEY,
  `value` JSON NOT NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- теми оформлення ----------
CREATE TABLE {p}themes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(64) NOT NULL,
  name VARCHAR(80) NOT NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  based_on_id BIGINT UNSIGNED NULL,
  default_mode VARCHAR(8) NOT NULL DEFAULT 'light',
  draft JSON NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY themes_slug_uq (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {p}theme_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  theme_id BIGINT UNSIGNED NOT NULL,
  version INT UNSIGNED NOT NULL,
  schema_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  tokens JSON NOT NULL,
  comment VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY tv_uq (theme_id, version),
  CONSTRAINT {p}tv_theme_fk FOREIGN KEY (theme_id) REFERENCES {p}themes (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {p}theme_publications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  theme_version_id BIGINT UNSIGNED NOT NULL,
  published_by BIGINT UNSIGNED NULL,
  published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  active_flag TINYINT NULL COMMENT '1 лише в активної публікації',
  UNIQUE KEY tp_one_active_uq (active_flag),
  CONSTRAINT {p}tp_version_fk FOREIGN KEY (theme_version_id) REFERENCES {p}theme_versions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- довідник територій ----------
CREATE TABLE {p}regions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  parent_id BIGINT UNSIGNED NULL,
  kind VARCHAR(10) NOT NULL,
  name VARCHAR(120) NOT NULL,
  katottg VARCHAR(20) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {p}communities (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  region_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  katottg VARCHAR(20) NULL,
  is_launch TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT {p}communities_region_fk FOREIGN KEY (region_id) REFERENCES {p}regions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {p}settlements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  community_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  kind VARCHAR(10) NOT NULL DEFAULT 'city',
  lat DECIMAL(9,6) NULL,
  lon DECIMAL(9,6) NULL,
  CONSTRAINT {p}settlements_community_fk FOREIGN KEY (community_id) REFERENCES {p}communities (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- вакансії ----------
CREATE TABLE {p}job_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(40) NOT NULL,
  name VARCHAR(80) NOT NULL,
  icon VARCHAR(20) NOT NULL DEFAULT 'briefcase',
  sort INT NOT NULL DEFAULT 100,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY jc_slug_uq (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {p}employers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_id BIGINT UNSIGNED NOT NULL,
  kind VARCHAR(10) NOT NULL DEFAULT 'company',
  name VARCHAR(120) NOT NULL,
  edrpou VARCHAR(10) NULL,
  about TEXT NULL,
  settlement_id BIGINT UNSIGNED NULL,
  website VARCHAR(200) NULL,
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(16) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY employers_owner_idx (owner_id),
  CONSTRAINT {p}employers_owner_fk FOREIGN KEY (owner_id) REFERENCES {p}users (id),
  CONSTRAINT {p}employers_settlement_fk FOREIGN KEY (settlement_id) REFERENCES {p}settlements (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {p}vacancies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  employer_id BIGINT UNSIGNED NOT NULL,
  author_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  settlement_id BIGINT UNSIGNED NOT NULL,
  address VARCHAR(200) NULL,
  employment VARCHAR(12) NOT NULL DEFAULT 'full',
  salary_from INT UNSIGNED NULL,
  salary_to INT UNSIGNED NULL,
  salary_period VARCHAR(8) NOT NULL DEFAULT 'month',
  description TEXT NOT NULL,
  requirements TEXT NULL,
  offers TEXT NULL,
  no_experience TINYINT(1) NOT NULL DEFAULT 0,
  contact_name VARCHAR(80) NULL,
  contact_phone VARCHAR(16) NULL COMMENT 'Робочий телефон вакансії — публічний за задумом',
  status VARCHAR(12) NOT NULL DEFAULT 'draft',
  reject_reason VARCHAR(500) NULL,
  moderated_by BIGINT UNSIGNED NULL,
  moderated_at DATETIME NULL,
  submitted_at DATETIME NULL,
  published_at DATETIME NULL,
  expires_at DATETIME NULL,
  views INT UNSIGNED NOT NULL DEFAULT 0,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  KEY vacancies_public_idx (status, published_at),
  KEY vacancies_settlement_idx (settlement_id, status),
  KEY vacancies_category_idx (category_id, status),
  KEY vacancies_employer_idx (employer_id),
  FULLTEXT KEY vacancies_ft (title, description),
  CONSTRAINT {p}vacancies_employer_fk FOREIGN KEY (employer_id) REFERENCES {p}employers (id),
  CONSTRAINT {p}vacancies_author_fk FOREIGN KEY (author_id) REFERENCES {p}users (id),
  CONSTRAINT {p}vacancies_category_fk FOREIGN KEY (category_id) REFERENCES {p}job_categories (id),
  CONSTRAINT {p}vacancies_settlement_fk FOREIGN KEY (settlement_id) REFERENCES {p}settlements (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {p}applications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  vacancy_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  message TEXT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'new',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY applications_uq (vacancy_id, user_id),
  KEY applications_user_idx (user_id),
  CONSTRAINT {p}applications_vacancy_fk FOREIGN KEY (vacancy_id) REFERENCES {p}vacancies (id) ON DELETE CASCADE,
  CONSTRAINT {p}applications_user_fk FOREIGN KEY (user_id) REFERENCES {p}users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- повідомлення, статистика, ліміти ----------
CREATE TABLE {p}outbox (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  channel VARCHAR(8) NOT NULL,
  driver VARCHAR(20) NOT NULL,
  to_enc VARCHAR(512) NOT NULL,
  subject VARCHAR(200) NULL,
  body_enc TEXT NOT NULL,
  template VARCHAR(40) NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'queued',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  error VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  KEY outbox_status_idx (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {p}daily_visitors (
  day DATE NOT NULL,
  visitor_hash CHAR(32) NOT NULL,
  PRIMARY KEY (day, visitor_hash)
) ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_bin;

CREATE TABLE {p}rate_limits (
  rl_key CHAR(64) NOT NULL PRIMARY KEY,
  hits INT UNSIGNED NOT NULL,
  reset_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_bin;
