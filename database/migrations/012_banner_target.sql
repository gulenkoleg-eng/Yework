-- YEWORK 0.5.15: куди веде кнопка банера. NULL — на всі вакансії роботодавця (так поводяться й старі банери).
ALTER TABLE {p}banners
  ADD COLUMN vacancy_id BIGINT UNSIGNED NULL COMMENT 'Вакансія, на яку веде кнопка; NULL — усі вакансії роботодавця' AFTER employer_id;
