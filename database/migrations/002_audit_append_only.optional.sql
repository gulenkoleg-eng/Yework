-- Журнал дій не можна змінити чи видалити навіть через SQL.
-- Необов'язково: на деяких хостингах немає права TRIGGER — тоді захист діє лише на рівні програми.
CREATE TRIGGER {p}audit_no_update BEFORE UPDATE ON {p}audit_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_log is append-only';
CREATE TRIGGER {p}audit_no_delete BEFORE DELETE ON {p}audit_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_log is append-only';
