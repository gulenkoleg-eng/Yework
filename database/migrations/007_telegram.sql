-- YEWORK 0.4.6: Telegram-бот для персоналу — прив'язка акаунтів, одноразові коди, повідомлення модерації в Telegram.
CREATE TABLE IF NOT EXISTS {p}telegram_accounts (
  user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  tg_user_id BIGINT NOT NULL,
  tg_username VARCHAR(64) NULL,
  tg_name VARCHAR(128) NULL,
  linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY tg_accounts_tg_uq (tg_user_id),
  CONSTRAINT {p}tg_accounts_user_fk FOREIGN KEY (user_id) REFERENCES {p}users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {p}telegram_link_codes (
  code_hash CHAR(64) NOT NULL PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  KEY tg_codes_user_idx (user_id),
  CONSTRAINT {p}tg_codes_user_fk FOREIGN KEY (user_id) REFERENCES {p}users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {p}telegram_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  entity_type VARCHAR(16) NOT NULL COMMENT 'vacancy, resume',
  entity_id BIGINT UNSIGNED NOT NULL,
  chat_id BIGINT NOT NULL,
  message_id BIGINT NOT NULL,
  closed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY tg_msg_entity_idx (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
