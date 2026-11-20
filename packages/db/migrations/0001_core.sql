CREATE SCHEMA "identity";
--> statement-breakpoint
CREATE SCHEMA "access";
--> statement-breakpoint
CREATE SCHEMA "audit";
--> statement-breakpoint
CREATE SCHEMA "reference";
--> statement-breakpoint
CREATE SCHEMA "settings";
--> statement-breakpoint
CREATE SCHEMA "theming";
--> statement-breakpoint
CREATE TABLE "identity"."mfa_factors" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"user_id" uuid NOT NULL,
	"type" text DEFAULT 'totp' NOT NULL,
	"secret_enc" text NOT NULL,
	"confirmed_at" timestamp with time zone,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "identity"."sessions" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"user_id" uuid NOT NULL,
	"token_hash" text NOT NULL,
	"ip" text,
	"user_agent" text,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"last_seen_at" timestamp with time zone DEFAULT now() NOT NULL,
	"expires_at" timestamp with time zone NOT NULL,
	"revoked_at" timestamp with time zone
);
--> statement-breakpoint
CREATE TABLE "identity"."users" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"login" "citext" NOT NULL,
	"display_name" text NOT NULL,
	"password_hash" text NOT NULL,
	"phone_hash" text,
	"phone_enc" text,
	"phone_verified_at" timestamp with time zone,
	"email_hash" text,
	"email_enc" text,
	"email_verified_at" timestamp with time zone,
	"status" text DEFAULT 'active' NOT NULL,
	"is_owner" boolean DEFAULT false NOT NULL,
	"last_login_at" timestamp with time zone,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL,
	"deleted_at" timestamp with time zone,
	"version" integer DEFAULT 1 NOT NULL
);
--> statement-breakpoint
CREATE TABLE "access"."critical_grants" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"user_id" uuid NOT NULL,
	"permission_code" text NOT NULL,
	"granted_by" uuid,
	"reason" text NOT NULL,
	"granted_at" timestamp with time zone DEFAULT now() NOT NULL,
	"expires_at" timestamp with time zone,
	"revoked_at" timestamp with time zone
);
--> statement-breakpoint
CREATE TABLE "access"."permissions" (
	"code" text PRIMARY KEY NOT NULL,
	"module" text NOT NULL,
	"resource" text NOT NULL,
	"action" text NOT NULL,
	"is_critical" boolean DEFAULT false NOT NULL,
	"description" text NOT NULL
);
--> statement-breakpoint
CREATE TABLE "access"."role_assignment_scopes" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"user_role_id" uuid NOT NULL,
	"scope_type" text NOT NULL,
	"scope_id" text NOT NULL
);
--> statement-breakpoint
CREATE TABLE "access"."role_permissions" (
	"role_id" uuid NOT NULL,
	"permission_code" text NOT NULL,
	CONSTRAINT "role_permissions_role_id_permission_code_pk" PRIMARY KEY("role_id","permission_code")
);
--> statement-breakpoint
CREATE TABLE "access"."roles" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"slug" text NOT NULL,
	"name" text NOT NULL,
	"description" text DEFAULT '' NOT NULL,
	"is_system" boolean DEFAULT false NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"deleted_at" timestamp with time zone
);
--> statement-breakpoint
CREATE TABLE "access"."user_roles" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"user_id" uuid NOT NULL,
	"role_id" uuid NOT NULL,
	"granted_by" uuid,
	"granted_at" timestamp with time zone DEFAULT now() NOT NULL,
	"expires_at" timestamp with time zone
);
--> statement-breakpoint
CREATE TABLE "audit"."audit_log" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"at" timestamp with time zone DEFAULT now() NOT NULL,
	"actor_id" uuid,
	"action" text NOT NULL,
	"entity_type" text,
	"entity_id" text,
	"ip" text,
	"data" jsonb DEFAULT '{}'::jsonb NOT NULL
);
--> statement-breakpoint
CREATE TABLE "reference"."communities" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"region_id" uuid NOT NULL,
	"name" text NOT NULL,
	"katottg" text,
	"is_launch" boolean DEFAULT false NOT NULL
);
--> statement-breakpoint
CREATE TABLE "reference"."regions" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"parent_id" uuid,
	"kind" text NOT NULL,
	"name" text NOT NULL,
	"katottg" text
);
--> statement-breakpoint
CREATE TABLE "reference"."settlements" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"community_id" uuid NOT NULL,
	"name" text NOT NULL,
	"kind" text DEFAULT 'city' NOT NULL,
	"lat" text,
	"lon" text
);
--> statement-breakpoint
CREATE TABLE "settings"."system_settings" (
	"key" text PRIMARY KEY NOT NULL,
	"value" jsonb NOT NULL,
	"updated_by" uuid,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "theming"."theme_publications" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"theme_version_id" uuid NOT NULL,
	"published_by" uuid,
	"published_at" timestamp with time zone DEFAULT now() NOT NULL,
	"is_active" boolean NOT NULL
);
--> statement-breakpoint
CREATE TABLE "theming"."theme_versions" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"theme_id" uuid NOT NULL,
	"version" integer NOT NULL,
	"schema_version" integer DEFAULT 1 NOT NULL,
	"tokens" jsonb NOT NULL,
	"layouts" jsonb DEFAULT '{}'::jsonb NOT NULL,
	"checks" jsonb DEFAULT '{}'::jsonb NOT NULL,
	"comment" text,
	"created_by" uuid,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "theming"."themes" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"slug" text NOT NULL,
	"name" text NOT NULL,
	"description" text DEFAULT '' NOT NULL,
	"is_system" boolean DEFAULT false NOT NULL,
	"based_on_id" uuid,
	"default_mode" text DEFAULT 'light' NOT NULL,
	"draft" jsonb,
	"created_by" uuid,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"deleted_at" timestamp with time zone
);
--> statement-breakpoint
ALTER TABLE "identity"."mfa_factors" ADD CONSTRAINT "mfa_factors_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "identity"."users"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "identity"."sessions" ADD CONSTRAINT "sessions_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "identity"."users"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "access"."critical_grants" ADD CONSTRAINT "critical_grants_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "identity"."users"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "access"."critical_grants" ADD CONSTRAINT "critical_grants_permission_code_permissions_code_fk" FOREIGN KEY ("permission_code") REFERENCES "access"."permissions"("code") ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "access"."critical_grants" ADD CONSTRAINT "critical_grants_granted_by_users_id_fk" FOREIGN KEY ("granted_by") REFERENCES "identity"."users"("id") ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "access"."role_assignment_scopes" ADD CONSTRAINT "role_assignment_scopes_user_role_id_user_roles_id_fk" FOREIGN KEY ("user_role_id") REFERENCES "access"."user_roles"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "access"."role_permissions" ADD CONSTRAINT "role_permissions_role_id_roles_id_fk" FOREIGN KEY ("role_id") REFERENCES "access"."roles"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "access"."role_permissions" ADD CONSTRAINT "role_permissions_permission_code_permissions_code_fk" FOREIGN KEY ("permission_code") REFERENCES "access"."permissions"("code") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "access"."user_roles" ADD CONSTRAINT "user_roles_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "identity"."users"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "access"."user_roles" ADD CONSTRAINT "user_roles_role_id_roles_id_fk" FOREIGN KEY ("role_id") REFERENCES "access"."roles"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "access"."user_roles" ADD CONSTRAINT "user_roles_granted_by_users_id_fk" FOREIGN KEY ("granted_by") REFERENCES "identity"."users"("id") ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "reference"."communities" ADD CONSTRAINT "communities_region_id_regions_id_fk" FOREIGN KEY ("region_id") REFERENCES "reference"."regions"("id") ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "reference"."settlements" ADD CONSTRAINT "settlements_community_id_communities_id_fk" FOREIGN KEY ("community_id") REFERENCES "reference"."communities"("id") ON DELETE no action ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "theming"."theme_publications" ADD CONSTRAINT "theme_publications_theme_version_id_theme_versions_id_fk" FOREIGN KEY ("theme_version_id") REFERENCES "theming"."theme_versions"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "theming"."theme_versions" ADD CONSTRAINT "theme_versions_theme_id_themes_id_fk" FOREIGN KEY ("theme_id") REFERENCES "theming"."themes"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
CREATE UNIQUE INDEX "sessions_token_uq" ON "identity"."sessions" USING btree ("token_hash");--> statement-breakpoint
CREATE INDEX "sessions_user_idx" ON "identity"."sessions" USING btree ("user_id");--> statement-breakpoint
CREATE UNIQUE INDEX "users_login_uq" ON "identity"."users" USING btree ("login");--> statement-breakpoint
CREATE UNIQUE INDEX "users_phone_hash_uq" ON "identity"."users" USING btree ("phone_hash");--> statement-breakpoint
CREATE UNIQUE INDEX "users_email_hash_uq" ON "identity"."users" USING btree ("email_hash");--> statement-breakpoint
CREATE UNIQUE INDEX "users_single_owner_uq" ON "identity"."users" USING btree ("is_owner") WHERE "identity"."users"."is_owner";--> statement-breakpoint
CREATE INDEX "critical_grants_user_idx" ON "access"."critical_grants" USING btree ("user_id");--> statement-breakpoint
CREATE INDEX "ras_user_role_idx" ON "access"."role_assignment_scopes" USING btree ("user_role_id");--> statement-breakpoint
CREATE UNIQUE INDEX "roles_slug_uq" ON "access"."roles" USING btree ("slug");--> statement-breakpoint
CREATE UNIQUE INDEX "user_roles_uq" ON "access"."user_roles" USING btree ("user_id","role_id");--> statement-breakpoint
CREATE INDEX "audit_at_idx" ON "audit"."audit_log" USING btree ("at");--> statement-breakpoint
CREATE INDEX "audit_actor_idx" ON "audit"."audit_log" USING btree ("actor_id");--> statement-breakpoint
CREATE UNIQUE INDEX "one_active_theme" ON "theming"."theme_publications" USING btree ("is_active") WHERE "theming"."theme_publications"."is_active";--> statement-breakpoint
CREATE UNIQUE INDEX "theme_versions_uq" ON "theming"."theme_versions" USING btree ("theme_id","version");--> statement-breakpoint
CREATE UNIQUE INDEX "themes_slug_uq" ON "theming"."themes" USING btree ("slug");