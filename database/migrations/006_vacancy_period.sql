-- YEWORK 0.4.5: термін показу вакансії (з … по …) та архів.
-- show_from / show_to — дати за київським часом, обидві включно.
-- Статус 'scheduled' — вакансія схвалена, але дата початку показу ще не настала.
-- archive_reason: hired (знайшли працівника), manual (знято вручну), expired (закінчився термін).
ALTER TABLE {p}vacancies ADD COLUMN show_from DATE NULL AFTER expires_at, ADD COLUMN show_to DATE NULL AFTER show_from, ADD COLUMN archived_at DATETIME NULL AFTER show_to, ADD COLUMN archive_reason VARCHAR(10) NULL AFTER archived_at;
ALTER TABLE {p}vacancies ADD KEY vacancies_period_idx (status, show_to);
-- Наявні вакансії: період береться з дати публікації і поточного «активна до» (+30 днів).
UPDATE {p}vacancies SET show_from = DATE(COALESCE(published_at, created_at)), show_to = DATE(COALESCE(expires_at, COALESCE(published_at, created_at) + INTERVAL 30 DAY));
-- Опубліковані вакансії, чий старий термін уже минув, не зникають одразу після оновлення: ще 14 днів показу.
UPDATE {p}vacancies SET show_to = CURDATE() + INTERVAL 14 DAY WHERE status = 'published' AND show_to < CURDATE();
UPDATE {p}vacancies SET archived_at = updated_at, archive_reason = 'manual' WHERE status = 'archived'
