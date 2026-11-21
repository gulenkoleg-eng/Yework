-- YEWORK 0.4.1: особистий профіль шукача, резюме, запрошення від роботодавців, резюме у відгуках.
-- Нічого з наявних даних не змінюється: лише нові таблиці й нові необов'язкові колонки.

-- Профіль людини: спільні дані для резюме, PDF і відгуків. Один рядок на користувача.
CREATE TABLE {p}profiles (
  user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  full_name VARCHAR(120) NULL,
  birth_year SMALLINT UNSIGNED NULL,
  settlement_id BIGINT UNSIGNED NULL,
  photo_file VARCHAR(80) NULL COMMENT 'Файл у storage/uploads/photos (поза доступом браузера)',
  photo_updated_at DATETIME NULL,
  show_photo TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Показувати фото роботодавцям і в PDF',
  show_contacts TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Показувати телефон і email у резюме',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT {p}profiles_user_fk FOREIGN KEY (user_id) REFERENCES {p}users (id) ON DELETE CASCADE,
  CONSTRAINT {p}profiles_settlement_fk FOREIGN KEY (settlement_id) REFERENCES {p}settlements (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Резюме. Поки одне на людину (унікальний user_id), структура дозволяє зняти обмеження пізніше.
CREATE TABLE {p}resumes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL COMMENT 'Бажана посада',
  category_id BIGINT UNSIGNED NULL,
  employment VARCHAR(12) NOT NULL DEFAULT 'full',
  salary_from INT UNSIGNED NULL,
  experience TEXT NULL COMMENT 'JSON: [{place, position, from, to, duties}]',
  education TEXT NULL COMMENT 'JSON: [{place, specialty, year}]',
  skills TEXT NULL,
  driving VARCHAR(20) NULL COMMENT 'Категорії посвідчення водія: A,B,C',
  about TEXT NULL,
  no_experience TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(12) NOT NULL DEFAULT 'draft' COMMENT 'draft, pending, published, rejected',
  is_hidden TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Тимчасово приховано власником',
  reject_reason VARCHAR(500) NULL,
  moderated_by BIGINT UNSIGNED NULL,
  moderated_at DATETIME NULL,
  submitted_at DATETIME NULL,
  published_at DATETIME NULL,
  views INT UNSIGNED NOT NULL DEFAULT 0,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY resumes_user_uq (user_id),
  KEY resumes_public_idx (status, is_hidden, published_at),
  KEY resumes_category_idx (category_id, status),
  CONSTRAINT {p}resumes_user_fk FOREIGN KEY (user_id) REFERENCES {p}users (id) ON DELETE CASCADE,
  CONSTRAINT {p}resumes_category_fk FOREIGN KEY (category_id) REFERENCES {p}job_categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Запрошення від роботодавця людині, яка приховала контакти. Одне на пару «резюме — роботодавець» на тиждень.
CREATE TABLE {p}resume_invites (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  resume_id BIGINT UNSIGNED NOT NULL,
  employer_id BIGINT UNSIGNED NOT NULL,
  vacancy_id BIGINT UNSIGNED NULL,
  sender_id BIGINT UNSIGNED NOT NULL,
  message TEXT NULL,
  contact VARCHAR(120) NULL COMMENT 'Як зв’язатися з роботодавцем (вказує він сам)',
  status VARCHAR(12) NOT NULL DEFAULT 'new' COMMENT 'new, seen',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY invites_resume_idx (resume_id, created_at),
  KEY invites_pair_idx (resume_id, employer_id, created_at),
  CONSTRAINT {p}invites_resume_fk FOREIGN KEY (resume_id) REFERENCES {p}resumes (id) ON DELETE CASCADE,
  CONSTRAINT {p}invites_employer_fk FOREIGN KEY (employer_id) REFERENCES {p}employers (id),
  CONSTRAINT {p}invites_vacancy_fk FOREIGN KEY (vacancy_id) REFERENCES {p}vacancies (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Відгук може містити резюме (тоді роботодавець отримує PDF на пошту).
ALTER TABLE {p}applications ADD COLUMN resume_id BIGINT UNSIGNED NULL AFTER message;
