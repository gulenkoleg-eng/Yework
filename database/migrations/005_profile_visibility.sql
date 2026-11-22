-- YEWORK 0.4.4: хто бачить фото й телефон у резюме — нікому, лише роботодавцям або всім відвідувачам.
-- Наявні налаштування переносяться без змін: увімкнена галочка = «лише роботодавцям», вимкнена = «нікому».
ALTER TABLE {p}profiles ADD COLUMN photo_visibility VARCHAR(10) NOT NULL DEFAULT 'employers' COMMENT 'nobody, employers, all' AFTER show_contacts;
ALTER TABLE {p}profiles ADD COLUMN phone_visibility VARCHAR(10) NOT NULL DEFAULT 'employers' COMMENT 'nobody, employers, all; email — не ширше за роботодавців' AFTER photo_visibility;
UPDATE {p}profiles SET photo_visibility = IF(show_photo = 1, 'employers', 'nobody'), phone_visibility = IF(show_contacts = 1, 'employers', 'nobody');
