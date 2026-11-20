-- YEWORK: розширення PostgreSQL, потрібні для схеми.
-- citext: логіни без урахування регістру; pgcrypto: gen_random_uuid() на старих версіях.
CREATE EXTENSION IF NOT EXISTS citext;
--> statement-breakpoint
CREATE EXTENSION IF NOT EXISTS pgcrypto;
