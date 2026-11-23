-- YEWORK 0.5.3: адміністрація може виправляти оголошення, а власник отримує про це сповіщення в кабінеті.
-- user_notices — сповіщення людині в кабінеті (поки що: «Адміністрація сайту виправила вашу вакансію»).
CREATE TABLE IF NOT EXISTS {p}user_notices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  kind VARCHAR(32) NOT NULL,
  entity_type VARCHAR(16) NULL,
  entity_id BIGINT UNSIGNED NULL,
  title VARCHAR(200) NOT NULL,
  body TEXT NULL,
  link VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME NULL,
  KEY user_notices_user_idx (user_id, read_at),
  KEY user_notices_entity_idx (entity_type, entity_id),
  CONSTRAINT {p}user_notices_user_fk FOREIGN KEY (user_id) REFERENCES {p}users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Права, які головний адміністратор вимкнув у системній ролі. Синхронізація прав після оновлення їх не повертає.
CREATE TABLE IF NOT EXISTS {p}role_permission_removed (
  role_id BIGINT UNSIGNED NOT NULL,
  permission_code VARCHAR(80) NOT NULL,
  removed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (role_id, permission_code),
  CONSTRAINT {p}rp_removed_role_fk FOREIGN KEY (role_id) REFERENCES {p}roles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
