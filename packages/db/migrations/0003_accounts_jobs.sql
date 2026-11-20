CREATE SCHEMA "notify";
--> statement-breakpoint
CREATE SCHEMA "stats";
--> statement-breakpoint
CREATE SCHEMA "jobs";
--> statement-breakpoint
CREATE TABLE "stats"."daily_visitors" (
	"day" date NOT NULL,
	"visitor_hash" text NOT NULL,
	CONSTRAINT "daily_visitors_day_visitor_hash_pk" PRIMARY KEY("day","visitor_hash")
);
--> statement-breakpoint
CREATE TABLE "notify"."outbox" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"channel" text NOT NULL,
	"driver" text NOT NULL,
	"to_enc" text NOT NULL,
	"subject" text,
	"body_enc" text NOT NULL,
	"template" text NOT NULL,
	"status" text DEFAULT 'queued' NOT NULL,
	"attempts" integer DEFAULT 0 NOT NULL,
	"error" text,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"sent_at" timestamp with time zone
);
--> statement-breakpoint
CREATE TABLE "identity"."verification_codes" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"purpose" text NOT NULL,
	"channel" text NOT NULL,
	"target_hash" text NOT NULL,
	"user_id" uuid,
	"code_hash" text NOT NULL,
	"attempts" integer DEFAULT 0 NOT NULL,
	"expires_at" timestamp with time zone NOT NULL,
	"consumed_at" timestamp with time zone,
	"ip" text,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "jobs"."applications" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"vacancy_id" uuid NOT NULL,
	"user_id" uuid NOT NULL,
	"message" text DEFAULT '' NOT NULL,
	"status" text DEFAULT 'new' NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "jobs"."categories" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"slug" text NOT NULL,
	"name" text NOT NULL,
	"icon" text DEFAULT 'briefcase' NOT NULL,
	"sort" integer DEFAULT 100 NOT NULL,
	"is_active" boolean DEFAULT true NOT NULL
);
--> statement-breakpoint
CREATE TABLE "jobs"."employers" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"owner_id" uuid NOT NULL,
	"kind" text DEFAULT 'company' NOT NULL,
	"name" text NOT NULL,
	"edrpou" text,
	"about" text DEFAULT '' NOT NULL,
	"settlement_id" uuid,
	"website" text,
	"is_verified" boolean DEFAULT false NOT NULL,
	"status" text DEFAULT 'active' NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL,
	"deleted_at" timestamp with time zone,
	"version" integer DEFAULT 1 NOT NULL
);
--> statement-breakpoint
CREATE TABLE "jobs"."vacancies" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"employer_id" uuid NOT NULL,
	"author_id" uuid NOT NULL,
	"title" text NOT NULL,
	"category_id" uuid NOT NULL,
	"settlement_id" uuid NOT NULL,
	"address" text,
	"employment" text DEFAULT 'full' NOT NULL,
	"salary_from" integer,
	"salary_to" integer,
	"salary_period" text DEFAULT 'month' NOT NULL,
	"description" text NOT NULL,
	"requirements" text DEFAULT '' NOT NULL,
	"offers" text DEFAULT '' NOT NULL,
	"no_experience" boolean DEFAULT false NOT NULL,
	"contact_name" text,
	"contact_phone" text,
	"status" text DEFAULT 'draft' NOT NULL,
	"reject_reason" text,
	"moderated_by" uuid,
	"moderated_at" timestamp with time zone,
	"submitted_at" timestamp with time zone,
	"published_at" timestamp with time zone,
	"expires_at" timestamp with time zone,
	"views" integer DEFAULT 0 NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL,
	"deleted_at" timestamp with time zone,
	"version" integer DEFAULT 1 NOT NULL
);
--> statement-breakpoint
ALTER TABLE "identity"."verification_codes" ADD CONSTRAINT "verification_codes_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "identity"."users"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "jobs"."applications" ADD CONSTRAINT "applications_vacancy_id_vacancies_id_fk" FOREIGN KEY ("vacancy_id") REFERENCES "jobs"."vacancies"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "jobs"."applications" ADD CONSTRAINT "applications_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "identity"."users"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "jobs"."employers" ADD CONSTRAINT "employers_owner_id_users_id_fk" FOREIGN KEY ("owner_id") REFERENCES "identity"."users"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "jobs"."employers" ADD CONSTRAINT "employers_settlement_id_settlements_id_fk" FOREIGN KEY ("settlement_id") REFERENCES "reference"."settlements"("id") ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "jobs"."vacancies" ADD CONSTRAINT "vacancies_employer_id_employers_id_fk" FOREIGN KEY ("employer_id") REFERENCES "jobs"."employers"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "jobs"."vacancies" ADD CONSTRAINT "vacancies_author_id_users_id_fk" FOREIGN KEY ("author_id") REFERENCES "identity"."users"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "jobs"."vacancies" ADD CONSTRAINT "vacancies_category_id_categories_id_fk" FOREIGN KEY ("category_id") REFERENCES "jobs"."categories"("id") ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "jobs"."vacancies" ADD CONSTRAINT "vacancies_settlement_id_settlements_id_fk" FOREIGN KEY ("settlement_id") REFERENCES "reference"."settlements"("id") ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "jobs"."vacancies" ADD CONSTRAINT "vacancies_moderated_by_users_id_fk" FOREIGN KEY ("moderated_by") REFERENCES "identity"."users"("id") ON DELETE no action ON UPDATE no action;--> statement-breakpoint
CREATE INDEX "outbox_status_idx" ON "notify"."outbox" USING btree ("status","created_at");--> statement-breakpoint
CREATE INDEX "vc_target_idx" ON "identity"."verification_codes" USING btree ("target_hash","purpose");--> statement-breakpoint
CREATE UNIQUE INDEX "applications_uq" ON "jobs"."applications" USING btree ("vacancy_id","user_id");--> statement-breakpoint
CREATE UNIQUE INDEX "jobs_categories_slug_uq" ON "jobs"."categories" USING btree ("slug");--> statement-breakpoint
CREATE INDEX "employers_owner_idx" ON "jobs"."employers" USING btree ("owner_id");--> statement-breakpoint
CREATE INDEX "vacancies_public_idx" ON "jobs"."vacancies" USING btree ("status","published_at") WHERE "jobs"."vacancies"."deleted_at" is null;--> statement-breakpoint
CREATE INDEX "vacancies_settlement_idx" ON "jobs"."vacancies" USING btree ("settlement_id");--> statement-breakpoint
CREATE INDEX "vacancies_category_idx" ON "jobs"."vacancies" USING btree ("category_id");--> statement-breakpoint
CREATE INDEX "vacancies_employer_idx" ON "jobs"."vacancies" USING btree ("employer_id");