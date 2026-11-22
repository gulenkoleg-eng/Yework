-- YEWORK 0.5.1: куди виїжджає майстер — кілька населених пунктів галочками (крім основного місця).
-- travel_all = 1 — майстер виїжджає в усі населені пункти сайту. Майстра знаходять у пошуку за кожним із цих міст.
ALTER TABLE {p}masters ADD COLUMN travel_all TINYINT(1) NOT NULL DEFAULT 0 AFTER areas;

CREATE TABLE IF NOT EXISTS {p}master_areas (
  master_id BIGINT UNSIGNED NOT NULL,
  settlement_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (master_id, settlement_id),
  KEY master_areas_settlement_idx (settlement_id),
  CONSTRAINT {p}master_areas_master_fk FOREIGN KEY (master_id) REFERENCES {p}masters (id) ON DELETE CASCADE,
  CONSTRAINT {p}master_areas_settlement_fk FOREIGN KEY (settlement_id) REFERENCES {p}settlements (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
