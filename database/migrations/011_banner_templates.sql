-- YEWORK 0.5.12: шаблони банерів. Роботодавець обирає шаблон і пише текст: позначку, до 3 переваг і текст кнопки.
ALTER TABLE {p}banners
  ADD COLUMN template VARCHAR(12) NOT NULL DEFAULT 'classic' COMMENT 'classic | sun | night | wave | urgent | photo' AFTER image,
  ADD COLUMN badge VARCHAR(24) NULL COMMENT 'Позначка: Терміново, Новий набір…' AFTER template,
  ADD COLUMN perks VARCHAR(255) NULL COMMENT 'JSON: до 3 коротких переваг' AFTER badge,
  ADD COLUMN cta VARCHAR(32) NULL COMMENT 'Текст кнопки; порожньо — «Дивитися N вакансій»' AFTER perks;
